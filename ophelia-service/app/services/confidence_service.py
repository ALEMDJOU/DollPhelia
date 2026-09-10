"""
Service de calcul de confiance.
Implémente la Définition 4.11 du mémoire (confiance totale).

    C_total(f, v) = C_OCR(v) · P_spatial(f, v) · C_validation(f, v)
"""

from __future__ import annotations

import math
import re
from dataclasses import dataclass

from app.config import settings
from app.models.schemas import TextElementSchema, TemplateFieldSchema
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
    c_validation = _validate_by_type(field_def.field_type, value_element.text)

    # Produit multiplicatif (Remarque 4.2 du mémoire)
    c_total = c_ocr * c_spatial * c_validation

    return ConfidenceResult(
        c_ocr=c_ocr,
        c_spatial=c_spatial,
        c_validation=c_validation,
        c_total=c_total,
    )


# ── Fonctions de validation par type V_type(f) ────────────────


_VALIDATORS: dict[str, re.Pattern] = {
    "date": re.compile(
        r"^\d{1,2}[/\-\.]\d{1,2}[/\-\.]\d{2,4}$"
    ),
    "amount": re.compile(
        r"^\d[\d\s]*[,\.]\d{1,2}\s*€?$"
    ),
    "iban": re.compile(
        r"^[A-Z]{2}\d{2}\s?[\dA-Z]{4}(\s?[\dA-Z]{4}){2,7}\s?[\dA-Z]{1,4}$",
        re.IGNORECASE,
    ),
    "email": re.compile(
        r"^[\w.+-]+@[\w-]+\.[\w.]+$"
    ),
    "phone": re.compile(
        r"^[\+]?[\d\s\.\-\(\)]{7,20}$"
    ),
    "siret": re.compile(
        r"^\d{3}\s?\d{3}\s?\d{3}\s?\d{5}$"
    ),
    "percentage": re.compile(
        r"^\d{1,3}[,\.]\d{1,2}\s?%$"
    ),
}


def _validate_by_type(field_type: str, value: str) -> float:
    """
    Retourne 1.0 si la valeur est syntaxiquement valide pour le type attendu,
    0.5 pour les types non contrôlés (text), et 0.0 si la validation échoue.
    """
    field_type = field_type.lower()

    if field_type == "text":
        # Le type texte libre est toujours « valide »
        return 1.0 if value.strip() else 0.0

    pattern = _VALIDATORS.get(field_type)
    if pattern is None:
        return 0.5  # Type inconnu : neutre

    return 1.0 if pattern.match(value.strip()) else 0.0
