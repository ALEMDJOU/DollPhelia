"""
Tests du service de confiance (confidence_service.py).
Vérifie la Définition 4.11 : C_total = C_OCR x P_spatial x C_validation.
"""

import math

import pytest

from app.config import settings
from app.models.schemas import BBoxSchema, TextElementSchema, TemplateFieldSchema
from app.services.confidence_service import compute_confidence, _validate_by_type


def _field(field_type: str) -> TemplateFieldSchema:
    return TemplateFieldSchema(
        field_name="f",
        field_label="F",
        field_type=field_type,
        key_text="cle",
    )


def _elem(text: str, conf: float, cx: float, cy: float) -> TextElementSchema:
    # bbox ponctuelle centree sur (cx, cy) pour simplifier le calcul de distance
    return TextElementSchema(
        text=text,
        bbox=BBoxSchema(x1=int(cx), y1=int(cy), x2=int(cx), y2=int(cy)),
        confidence=conf,
    )


def test_c_total_est_le_produit_des_trois_composantes():
    field_def = _field("text")
    element = _elem("valeur", conf=0.8, cx=100, cy=100)

    result = compute_confidence(field_def, element, expected_x=100, expected_y=100)

    assert result.c_ocr == 0.8
    assert result.c_spatial == pytest.approx(1.0)  # distance nulle -> exp(0) = 1
    assert result.c_validation == 1.0
    assert result.c_total == pytest.approx(0.8 * 1.0 * 1.0)


def test_c_spatial_decroit_avec_la_distance():
    field_def = _field("text")
    element = _elem("valeur", conf=1.0, cx=110, cy=100)  # 10 px d'écart = 1 sigma

    result = compute_confidence(field_def, element, expected_x=100, expected_y=100)

    sigma = settings.spatial_tolerance
    expected = math.exp(-(10 ** 2) / (2 * sigma ** 2))
    assert result.c_spatial == pytest.approx(expected)


@pytest.mark.parametrize(
    "field_type,value,attendu",
    [
        ("date", "12/03/2024", 1.0),
        ("date", "pas une date", 0.0),
        ("amount", "1250,00", 1.0),
        ("amount", "abc", 0.0),
        ("email", "test@exemple.fr", 1.0),
        ("email", "pas-un-email", 0.0),
        ("text", "n'importe quoi", 1.0),
        ("text", "", 0.0),
        ("type_inconnu", "valeur", 0.5),
    ],
)
def test_validate_by_type(field_type, value, attendu):
    assert _validate_by_type(field_type, value) == attendu
