"""
Service d'extraction de champs.
Implémente l'Algorithme 2 du mémoire (extraction ancrée).
"""

from __future__ import annotations

import math

from app.config import settings
from app.models.schemas import (
    TextElementSchema,
    TemplateSchema,
    TemplateFieldSchema,
    ExtractedFieldSchema,
    ExtractionResponse,
    BBoxSchema,
)
from app.services.confidence_service import compute_confidence
from app.utils.bbox_utils import (
    find_key_in_elements,
    center_of_bbox,
    distance_between_points,
    elements_in_search_zone,
)


def extract_fields(
    elements: list[TextElementSchema],
    template: TemplateSchema,
    strategy: str = "template_matching",
) -> ExtractionResponse:
    """
    Extraction de tous les champs d'un template sur un ensemble d'éléments OCR.
    Complexité : O(|Φ| · |E| · log|E|) — Théorème 4.6.
    """
    extracted: list[ExtractedFieldSchema] = []

    for field_def in template.fields:
        result = _extract_single_field(field_def, elements)
        extracted.append(result)

    # Confiance globale = moyenne des confiances totales
    confidences = [f.confidence_total for f in extracted if f.extracted_value]
    global_conf = sum(confidences) / len(confidences) if confidences else 0.0

    return ExtractionResponse(
        strategy=strategy,
        template_id=template.id,
        template_label=template.label,
        matching_score=0.0,  # Rempli par l'orchestrateur
        fields=extracted,
        global_confidence=round(global_conf, 4),
    )


def _extract_single_field(
    field_def: TemplateFieldSchema,
    elements: list[TextElementSchema],
) -> ExtractedFieldSchema:
    """
    Algorithme 2 : Extraction d'un champ à partir de sa clé.

    1. Localiser la clé (ancre textuelle) dans le document
    2. Appliquer le vecteur spatial pour calculer la position attendue
    3. Chercher les éléments candidats dans la zone de tolérance
    4. Sélectionner le plus proche de la position attendue
    5. Calculer la confiance totale C_total(f, v)
    """
    sigma = settings.spatial_tolerance

    # Étape 1 : Localisation de l'ancre
    key_element = find_key_in_elements(field_def.key_text, elements)

    if key_element is None:
        # Sans ancre, pas d'extraction (ligne 3-4 de l'algo)
        return ExtractedFieldSchema(
            field_name=field_def.field_name,
            field_label=field_def.field_label,
            extracted_value=None,
            confidence_total=0.0,
        )

    # Étape 2 : Position attendue = position(clé) + vecteur spatial
    key_cx, key_cy = center_of_bbox(key_element.bbox)
    expected_x = key_cx + field_def.delta_x
    expected_y = key_cy + field_def.delta_y

    # Étape 3 : Éléments dans la zone de recherche (fenêtre de tolérance)
    candidates = elements_in_search_zone(
        elements=elements,
        center_x=expected_x,
        center_y=expected_y,
        radius=sigma * 3,  # 3σ couvre 99.7 % de la distribution
        exclude_text=field_def.key_text,
    )

    if not candidates:
        return ExtractedFieldSchema(
            field_name=field_def.field_name,
            field_label=field_def.field_label,
            extracted_value=None,
            confidence_total=0.0,
        )

    # Étape 4 : Sélection du candidat le plus proche
    best_candidate = None
    best_distance = float("inf")

    for candidate in candidates:
        c_cx, c_cy = center_of_bbox(candidate.bbox)
        dist = distance_between_points(c_cx, c_cy, expected_x, expected_y)
        if dist < best_distance:
            best_distance = dist
            best_candidate = candidate

    # Étape 5 : Calcul de la confiance totale
    conf = compute_confidence(
        field_def=field_def,
        value_element=best_candidate,
        expected_x=expected_x,
        expected_y=expected_y,
    )

    return ExtractedFieldSchema(
        field_name=field_def.field_name,
        field_label=field_def.field_label,
        extracted_value=best_candidate.text,
        confidence_ocr=round(conf.c_ocr, 4),
        confidence_spatial=round(conf.c_spatial, 4),
        confidence_validation=round(conf.c_validation, 4),
        confidence_total=round(conf.c_total, 4),
        bbox=best_candidate.bbox,
        page_num=best_candidate.page_num,
    )
