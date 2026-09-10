"""
Tests du module utils/bbox_utils.py.
Couvre la géométrie de base et la recherche de clés (ancres textuelles).
"""

from app.models.schemas import BBoxSchema, TextElementSchema
from app.utils.bbox_utils import (
    center_of_bbox,
    distance_between_points,
    find_key_in_elements,
    elements_in_search_zone,
)


def _elem(text: str, x1: int, y1: int, x2: int, y2: int, conf: float = 0.9) -> TextElementSchema:
    return TextElementSchema(text=text, bbox=BBoxSchema(x1=x1, y1=y1, x2=x2, y2=y2), confidence=conf)


def test_center_of_bbox():
    bbox = BBoxSchema(x1=0, y1=0, x2=10, y2=20)
    assert center_of_bbox(bbox) == (5.0, 10.0)


def test_distance_between_points():
    assert distance_between_points(0, 0, 3, 4) == 5.0


def test_find_key_in_elements_exact_match():
    elements = [_elem("Montant TTC:", 0, 0, 10, 10), _elem("Date:", 20, 0, 30, 10)]
    found = find_key_in_elements("Montant TTC:", elements)
    assert found is not None
    assert found.text == "Montant TTC:"


def test_find_key_in_elements_fuzzy_match_tolere_erreur_ocr():
    # "Montant TTC" mal reconnu par l'OCR en "Montent TTC" doit rester trouvable
    elements = [_elem("Montent TTC:", 0, 0, 10, 10)]
    found = find_key_in_elements("Montant TTC:", elements)
    assert found is not None


def test_find_key_in_elements_aucune_correspondance():
    elements = [_elem("Totalement autre chose", 0, 0, 10, 10)]
    found = find_key_in_elements("IBAN:", elements)
    assert found is None


def test_elements_in_search_zone_exclut_la_cle():
    elements = [
        _elem("Date:", 0, 0, 10, 10),
        _elem("12/03/2024", 20, 0, 40, 10),
    ]
    candidates = elements_in_search_zone(
        elements, center_x=30, center_y=5, radius=50, exclude_text="Date:"
    )
    assert all(c.text != "Date:" for c in candidates)
    assert any(c.text == "12/03/2024" for c in candidates)


def test_elements_in_search_zone_respecte_le_rayon():
    elements = [_elem("loin", 1000, 1000, 1010, 1010)]
    candidates = elements_in_search_zone(elements, center_x=0, center_y=0, radius=10)
    assert candidates == []
