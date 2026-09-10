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
    ExtractionResponse,
)
from app.services.preprocessing import preprocess_image
from app.services.ocr_service import extract_text_elements
from app.services.matching_service import match_document_to_template
from app.services.extraction_service import extract_fields
from app.services.ai_extraction_service import extract_with_ai


def process_document_sync(
    filepath: str,
    templates: list[TemplateSchema],
    lang: str = "fra",
) -> ExtractionResponse:
    """
    Traitement complet synchrone d'un document.
    Utilisé directement par l'endpoint /process/sync et par le worker Celery.
    """
    path = Path(filepath)
    if not path.exists():
        raise FileNotFoundError(f"Fichier introuvable : {filepath}")

    # ── Étape 1 : Prétraitement ────────────────────────────────
    processed_images = preprocess_image(path)

    # ── Étape 2 : OCR ─────────────────────────────────────────
    all_elements = []
    for page_num, image in enumerate(processed_images, start=1):
        elements = extract_text_elements(image, lang=lang)
        for elem in elements:
            elem.page_num = page_num
        all_elements.extend(elements)

    if not all_elements:
        return ExtractionResponse(
            strategy="none",
            fields=[],
            global_confidence=0.0,
        )

    # ── Étape 3 : Appariement ─────────────────────────────────
    if templates:
        detected_type = _detect_doc_type(all_elements, templates)
        matching_result = match_document_to_template(
            doc_type=detected_type,
            elements=all_elements,
            templates=templates,
        )
    else:
        matching_result = None

    # ── Étape 4 : Sélection de stratégie ──────────────────────
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
        # Stratégie de repli : Extraction par IA (LayoutLMv3)
        result = extract_with_ai(
            elements=all_elements,
            image_path=filepath,
        )

    return result


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
