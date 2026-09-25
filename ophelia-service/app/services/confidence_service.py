"""
Service de calcul de confiance.
Implémente la Définition 4.11 du mémoire (confiance totale).

    C_total(f, v) = C_OCR(v) · P_spatial(f, v) · C_validation(f, v)
"""

from __future__ import annotations

import math
from dataclasses import dataclass

from app.config import settings
from app.models.schemas import TextElementSchema, TemplateFieldSchema
from app.services.ner_service import classify_value
from app.utils.bbox_utils import center_of_bbox, distance_between_points


@dataclass
class ConfidenceResult:
    c_ocr: float
    c_spatial: float
    c_validation: float
    c_total: float


def compute_confidence(
    field_def: TemplateFieldSchema,
    value_element: TextElementSchema,
    expected_x: float,
    expected_y: float,
) -> ConfidenceResult:
    """
    Calcule la confiance totale C_total(f, v) = C_OCR · P_spatial · C_validation.

    - C_OCR       : confiance brute du moteur OCR
    - P_spatial    : exp(-erreur² / 2σ²), pénalité de distance
    - C_validation : validité syntaxique selon le type du champ
    """
    sigma = settings.spatial_tolerance

    # C_OCR(v) — directement la confiance Tesseract normalisée
    c_ocr = value_element.confidence

    # P_spatial(f, v) — modèle gaussien (Définition 4.12)
    val_cx, val_cy = center_of_bbox(value_element.bbox)
    error_dist = distance_between_points(val_cx, val_cy, expected_x, expected_y)
    c_spatial = math.exp(-(error_dist ** 2) / (2 * sigma ** 2))

    # C_validation(f, v) — validation par type (Définition 4.11)
    # Déléguée à ner_service pour ne pas dupliquer les patterns de
    # reconnaissance de type (source unique, partagée avec le NER).
    c_validation = classify_value(field_def.field_type, value_element.text)

    # Produit multiplicatif (Remarque 4.2 du mémoire)
    c_total = c_ocr * c_spatial * c_validation

    return ConfidenceResult(
        c_ocr=c_ocr,
        c_spatial=c_spatial,
        c_validation=c_validation,
        c_total=c_total,
    )
