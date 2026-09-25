"""
Service de reconnaissance d'entités nommées (NER).

Contrairement à l'extraction ancrée (Algorithme 2, extraction_service.py),
qui a besoin d'un template avec des clés connues, le NER scanne le document
sans a priori et détecte les entités typées qu'il reconnaît (dates, montants,
IBAN, emails, téléphones, pourcentages, SIRET, et — de façon heuristique —
personnes et organisations).

Deux usages :
  1. Endpoint autonome /api/v1/ner/extract, pour inspecter un document
     sans template.
  2. Stratégie de repli de l'orchestrateur (orchestrator.py) quand ni
     l'appariement de template ni le modèle LayoutLMv3 ne sont disponibles :
     c'est une extraction purement par règles, donc toujours disponible
     (pas de dépendance à un modèle chargé en mémoire), contrairement à
     l'extraction IA.

Les mêmes patterns servent aussi à confidence_service.py pour la
validation C_validation(f, v) — une seule source de vérité pour la
reconnaissance de type, au lieu de dupliquer les regex.
"""

from __future__ import annotations

import re

from app.models.schemas import TextElementSchema, EntitySchema
from app.utils.bbox_utils import build_phrase_candidates, bbox_overlap_ratio


# ── Patterns par type d'entité ─────────────────────────────────
# Utilisés à la fois par detect_entities() (scan libre) et par
# confidence_service.classify_value() (validation d'un champ connu).

TYPE_PATTERNS: dict[str, re.Pattern] = {
    "date": re.compile(
        r"^(?:\d{1,2}[/\-\.]\d{1,2}[/\-\.]\d{2,4}"          # 10/09/2026, 10-09-26
        r"|\d{4}-\d{1,2}-\d{1,2}"                            # 2026-09-10 (ISO)
        r"|(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Oct|Nov|Dec)[a-zA-Z]*\.?\s+\d{1,2},?\s+\d{4}"  # Sep 10, 2026
        r")$",
        re.IGNORECASE,
    ),
    "amount": re.compile(
        r"^(?:"
        r"[\$€£]\s?\d[\d.,\s]*"                              # $1,250.00 / €150
        r"|\d[\d.,\s]*\s?(?:€|\$|£|EUR|USD|GBP)"              # 150€ / 1250.00 EUR
        r"|\d[\d\s]*[,\.]\d{1,2}"                              # 150,00 / 150.00 (decimal, no currency needed)
        r")$",
        re.IGNORECASE,
    ),
    "iban": re.compile(
        r"^[A-Z]{2}\d{2}\s?[\dA-Z]{4}(\s?[\dA-Z]{4}){2,7}\s?[\dA-Z]{1,4}$",
        re.IGNORECASE,
    ),
    "email": re.compile(r"^[\w.+-]+@[\w-]+\.[\w.]+$"),
    # Au moins 8 chiffres réels (pas seulement 8 caractères) pour éviter
    # de confondre un numéro de facture/référence ("2026-001") avec un
    # téléphone — trouvé en test manuel sur un document réel.
    "phone": re.compile(r"^(?=(?:\D*\d){8,})[\+]?[\d\s\.\-\(\)]{7,20}$"),
    "siret": re.compile(r"^\d{3}\s?\d{3}\s?\d{3}\s?\d{5}$"),
    "percentage": re.compile(r"^\d{1,3}[,\.]?\d{0,2}\s?%$"),
}

# Suffixes juridiques usuels (FR + EN) signalant une organisation
_ORG_SUFFIXES = {
    "sarl", "sas", "sasu", "sa", "eurl", "sci", "scop",
    "ltd", "llc", "inc", "inc.", "corp", "corp.", "gmbh", "plc", "co", "co.",
}

# Mots capitalisés fréquents en tête de champ de formulaire, à ne pas
# confondre avec un prénom/nom de personne (liste volontairement courte :
# l'heuristique NOM DE PERSONNE reste imprécise par nature sans modèle
# statistique dédié — cf. limite documentée dans le message de réponse).
_NON_NAME_WORDS = {
    "total", "montant", "date", "adresse", "client", "facture", "numero",
    "invoice", "bill", "to", "from", "address", "number", "description",
    "amount", "due", "subtotal", "tax", "name", "ref", "reference",
    "total:", "sous", "tva", "email", "phone", "tel", "siret", "siren",
}


def classify_value(field_type: str, value: str) -> float:
    """
    Valide qu'une valeur correspond syntaxiquement au type attendu.
    Utilisé par confidence_service.py pour C_validation(f, v).

    Retourne 1.0 si valide, 0.5 pour un type non contrôlé (ex: 'text'
    ou un type inconnu), 0.0 si la validation échoue.
    """
    field_type = field_type.lower()

    if field_type == "text":
        return 1.0 if value.strip() else 0.0

    pattern = TYPE_PATTERNS.get(field_type)
    if pattern is None:
        return 0.5

    return 1.0 if pattern.match(value.strip()) else 0.0


def _classify_entity_text(text: str, word_count: int) -> str | None:
    """
    Tente de reconnaître le type d'entité d'un texte candidat (un token
    OCR ou une phrase reconstituée). Retourne None si rien ne correspond.
    """
    stripped = text.strip()
    if not stripped:
        return None

    for etype, pattern in TYPE_PATTERNS.items():
        if pattern.match(stripped):
            return etype

    # Heuristique ORGANISATION : suffixe juridique en fin de phrase
    last_word = re.sub(r"[.,]$", "", stripped.split()[-1]).lower()
    if word_count >= 2 and last_word in _ORG_SUFFIXES:
        return "organization"

    # Heuristique ORGANISATION : suite de mots en MAJUSCULES (2+ mots)
    words = stripped.split()
    if word_count >= 2 and all(len(w) >= 2 and w.isupper() for w in words):
        return "organization"

    # Heuristique PERSONNE : 2 mots consécutifs en Title Case, ni l'un ni
    # l'autre n'étant un mot d'étiquette de formulaire courant. Reste une
    # approximation par règles (pas de modèle statistique) — cf. limites.
    if word_count == 2:
        w1, w2 = words[0], words[1]
        titlecase = re.compile(r"^[A-ZÀ-Ý][a-zà-ÿ'\-]+$")
        if (
            titlecase.match(w1)
            and titlecase.match(w2)
            and w1.lower() not in _NON_NAME_WORDS
            and w2.lower() not in _NON_NAME_WORDS
        ):
            return "person"

    return None


def detect_entities(elements: list[TextElementSchema]) -> list[EntitySchema]:
    """
    Scanne librement une liste d'éléments OCR (sans template) et retourne
    toutes les entités reconnues, dédupliquées par recouvrement de boîte.
    """
    phrases = build_phrase_candidates(elements, max_words=4)
    candidates = list(elements) + phrases

    found: list[EntitySchema] = []
    for cand in candidates:
        word_count = len(cand.text.split())
        etype = _classify_entity_text(cand.text, word_count)
        if etype is None:
            continue

        found.append(
            EntitySchema(
                entity_type=etype,
                text=cand.text.strip(),
                bbox=cand.bbox,
                page_num=cand.page_num,
                confidence=round(cand.confidence, 4),
            )
        )

    return _dedupe_overlapping(found)


def _dedupe_overlapping(entities: list[EntitySchema]) -> list[EntitySchema]:
    """
    Supprime les détections redondantes : quand plusieurs candidats
    (ex: un token seul et la phrase qui le contient) se recouvrent
    fortement, ne garde que le plus long (texte le plus complet).
    """
    ordered = sorted(entities, key=lambda e: len(e.text), reverse=True)
    kept: list[EntitySchema] = []

    for entity in ordered:
        overlaps_kept = any(
            entity.page_num == other.page_num
            and bbox_overlap_ratio(other.bbox, entity.bbox) >= 0.5
            for other in kept
        )
        if not overlaps_kept:
            kept.append(entity)

    return kept
