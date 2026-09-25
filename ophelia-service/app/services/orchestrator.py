"""
Orchestrateur d'extraction — sélection adaptative de la stratégie.
Implémente le flux décrit dans le diagramme de séquence
« Choix de la stratégie de traitement » (section 4.3.10).

Pipeline :
    1. Prétraitement → 2. OCR → 3. Appariement → 4. Sélection de stratégie
    → 5. Extraction (template ou IA) → 6. Calcul de confiance
"""

from __future__ import annotations

from pathlib import Path

from app.config import settings
from app.models.schemas import (
    TemplateSchema,
    TextElementSchema,
    ExtractedFieldSchema,
    ExtractionResponse,
)
from app.ml.layoutlm_loader import is_model_loaded
from app.services.document_loader import load_elements
from app.services.matching_service import match_document_to_template
from app.services.extraction_service import extract_fields
from app.services.ai_extraction_service import extract_with_ai
from app.services.ner_service import detect_entities

# Types d'entités reconnus par heuristique (sans regex déterministe) —
# leur confiance est pondérée à la baisse dans le résultat NER de repli.
_HEURISTIC_ENTITY_TYPES = {"person", "organization"}

# Stratégies que l'utilisateur peut choisir explicitement au lancement du
# traitement (document_card.php côté Dolibarr), au lieu de la déléguer à
# la sélection automatique. Toute valeur hors de cet ensemble retombe sur
# "auto".
VALID_STRATEGIES = {"auto", "template_matching", "ai_layoutlm", "ner"}


def process_document_sync(
    filepath: str,
    templates: list[TemplateSchema],
    lang: str = "fra",
    strategy: str = "auto",
) -> ExtractionResponse:
    """
    Traitement complet synchrone d'un document.
    Utilisé directement par l'endpoint /process/sync et par le worker Celery.

    `strategy` :
        - "auto" (défaut) : sélection automatique — template le mieux
          apparié si un template franchit le seuil, sinon IA puis NER.
        - "template_matching" : force l'extraction par le template le
          mieux apparié, quel que soit son score (l'utilisateur a choisi
          explicitement cette stratégie, il assume le résultat même sous
          le seuil habituel).
        - "ai_layoutlm" : force l'extraction IA, sans tenter l'appariement
          de template au préalable.
        - "ner" : force la reconnaissance d'entités par règles.
    """
    if strategy not in VALID_STRATEGIES:
        strategy = "auto"

    path = Path(filepath)
    if not path.exists():
        raise FileNotFoundError(f"Fichier introuvable : {filepath}")

    # ── Étapes 1-2 : Chargement + OCR (images) ou texte natif ──
    # (PDF, DOCX, XLSX) — cf. document_loader.py. Le reste du pipeline
    # ne dépend que de la liste de TextElementSchema qui en ressort.
    all_elements = load_elements(path, lang=lang)

    if not all_elements:
        return ExtractionResponse(
            strategy="none",
            fields=[],
            global_confidence=0.0,
        )

    # ── Stratégie choisie explicitement par l'utilisateur ──────
    if strategy == "template_matching":
        return _extract_forced_template(all_elements, templates)

    if strategy == "ai_layoutlm":
        return extract_with_ai(elements=all_elements, image_path=filepath)

    if strategy == "ner":
        return _extract_with_ner(all_elements)

    # ── strategy == "auto" : sélection déléguée au système ─────

    # Étape 3 : Appariement
    if templates:
        detected_type = _detect_doc_type(all_elements, templates)
        matching_result = match_document_to_template(
            doc_type=detected_type,
            elements=all_elements,
            templates=templates,
        )
    else:
        matching_result = None

    # Étape 4 : Sélection de stratégie
    if matching_result and matching_result.matched:
        # Stratégie 1 : Extraction par template (Algorithme 2)
        matched_template = _find_template_by_id(
            templates, matching_result.best_template_id
        )
        result = extract_fields(
            elements=all_elements,
            template=matched_template,
            strategy="template_matching",
        )
        result.matching_score = matching_result.best_score

    else:
        # Stratégie de repli, par ordre de préférence :
        #   1. Extraction IA (LayoutLMv3), si le modèle est chargé dans
        #      CE processus (le worker Celery ne le charge pas par défaut,
        #      cf. tasks/celery_tasks.py -> worker_process_init)
        #   2. Extraction par NER (règles, toujours disponible, aucune
        #      dépendance à un modèle) : filet de sécurité qui garantit
        #      qu'un document non apparié ne retourne jamais 0 champ
        #      simplement parce que le modèle IA est indisponible.
        result = None
        if is_model_loaded():
            ai_result = extract_with_ai(elements=all_elements, image_path=filepath)
            if ai_result.fields:
                result = ai_result

        if result is None:
            result = _extract_with_ner(all_elements)

    return result


def _extract_forced_template(
    elements: list[TextElementSchema],
    templates: list[TemplateSchema],
) -> ExtractionResponse:
    """
    Extraction par template forcée par l'utilisateur : prend le template
    le mieux apparié même s'il n'atteint pas le seuil habituel (theta_min)
    — l'utilisateur a choisi cette stratégie en connaissance de cause.
    """
    if not templates:
        return ExtractionResponse(strategy="template_matching", fields=[], global_confidence=0.0)

    detected_type = _detect_doc_type(elements, templates)
    matching_result = match_document_to_template(
        doc_type=detected_type,
        elements=elements,
        templates=templates,
        threshold=0.0,
    )
    if matching_result.best_template_id is None:
        return ExtractionResponse(strategy="template_matching", fields=[], global_confidence=0.0)

    matched_template = _find_template_by_id(templates, matching_result.best_template_id)
    result = extract_fields(elements=elements, template=matched_template, strategy="template_matching")
    result.matching_score = matching_result.best_score
    return result


def _extract_with_ner(elements: list[TextElementSchema]) -> ExtractionResponse:
    """
    Stratégie de repli par NER : détecte les entités typées du document
    sans template ni modèle IA, et les expose comme des champs génériques
    (field_name = "<type>_<n>", ex: "date_1", "amount_1").
    """
    entities = detect_entities(elements)

    fields: list[ExtractedFieldSchema] = []
    seen_counts: dict[str, int] = {}

    for entity in entities:
        seen_counts[entity.entity_type] = seen_counts.get(entity.entity_type, 0) + 1
        is_heuristic = entity.entity_type in _HEURISTIC_ENTITY_TYPES
        # Confiance validation : 1.0 pour un type reconnu par regex
        # déterministe, 0.5 pour une heuristique (personne/organisation).
        c_validation = 0.5 if is_heuristic else 1.0
        c_total = round(entity.confidence * c_validation, 4)

        fields.append(
            ExtractedFieldSchema(
                field_name=f"{entity.entity_type}_{seen_counts[entity.entity_type]}",
                field_label=entity.entity_type.replace("_", " ").title(),
                extracted_value=entity.text,
                confidence_ocr=entity.confidence,
                confidence_spatial=1.0,  # Pas d'ancre : sans objet en mode NER libre
                confidence_validation=c_validation,
                confidence_total=c_total,
                bbox=entity.bbox,
                page_num=entity.page_num,
            )
        )

    confidences = [f.confidence_total for f in fields]
    global_conf = sum(confidences) / len(confidences) if confidences else 0.0

    return ExtractionResponse(
        strategy="ner",
        fields=fields,
        global_confidence=round(global_conf, 4),
    )


def _detect_doc_type(
    elements: list,
    templates: list[TemplateSchema],
) -> str | None:
    """
    Devine le type de document avant l'appariement officiel.

    Réutilise F(D, Tj) (Définition 4.6) avec S1 neutre — c'est-à-dire
    sans présupposer de type — pour identifier le template dont le
    contenu (clés, position, confiance OCR) correspond le mieux au
    document. Son doc_type sert alors de type détecté pour l'étape
    d'appariement réelle, qui peut ainsi évaluer S1 correctement.

    N'accepte la détection que si au moins une clé du template gagnant
    a réellement été retrouvée (S2 > 0) : sans cela, S1 neutre et S4
    (confiance OCR, indépendante du contenu) suffiraient à désigner un
    template au hasard.
    """
    if not templates:
        return None

    probe = match_document_to_template(
        doc_type=None,
        elements=elements,
        templates=templates,
        threshold=0.0,
    )
    if probe.best_template_id is None:
        return None

    best_detail = next(
        (d for d in probe.details if d.template_id == probe.best_template_id), None
    )
    if best_detail is None or best_detail.s2_keys <= 0.0:
        return None

    return _find_template_by_id(templates, probe.best_template_id).doc_type


def _find_template_by_id(
    templates: list[TemplateSchema],
    template_id: int,
) -> TemplateSchema:
    """Retrouve un template par son identifiant dans la liste."""
    for t in templates:
        if t.id == template_id:
            return t
    raise ValueError(f"Template introuvable : id={template_id}")
