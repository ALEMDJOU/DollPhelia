"""
Tests du service NER (ner_service.py).
Couvre la classification de type (classify_value) et la détection
libre d'entités (detect_entities) sur des éléments OCR synthétiques.
"""

import pytest

from app.models.schemas import BBoxSchema, TextElementSchema
from app.services.ner_service import classify_value, detect_entities


def _elem(text: str, x1: int, y1: int, x2: int, y2: int, conf: float = 0.9) -> TextElementSchema:
    return TextElementSchema(text=text, bbox=BBoxSchema(x1=x1, y1=y1, x2=x2, y2=y2), confidence=conf)


@pytest.mark.parametrize(
    "field_type,value,attendu",
    [
        ("date", "12/03/2024", 1.0),
        ("date", "2024-03-12", 1.0),
        ("date", "pas une date", 0.0),
        ("amount", "1250,00", 1.0),
        ("amount", "2940€", 1.0),         # entier + devise, sans separateur decimal
        ("amount", "abc", 0.0),
        ("email", "test@exemple.fr", 1.0),
        ("email", "pas-un-email", 0.0),
        ("text", "n'importe quoi", 1.0),
        ("text", "", 0.0),
        ("type_inconnu", "valeur", 0.5),
    ],
)
def test_classify_value(field_type, value, attendu):
    assert classify_value(field_type, value) == attendu


def test_detect_entities_reconnait_date_et_montant():
    elements = [
        _elem("Total", 40, 60, 70, 70),
        _elem("TTC", 72, 60, 95, 70),
        _elem("154.90", 111, 63, 145, 74),
        _elem("EUR", 148, 63, 175, 74),
        _elem("Date", 20, 100, 50, 110),
        _elem("10/09/2026", 66, 103, 154, 113),
    ]

    entities = detect_entities(elements)
    types = {e.entity_type for e in entities}

    assert "date" in types
    assert "amount" in types
    date_entity = next(e for e in entities if e.entity_type == "date")
    assert date_entity.text == "10/09/2026"


def test_detect_entities_ne_confond_pas_reference_facture_et_telephone():
    # "2026-001" a 8 caracteres et un tiret comme un numero de telephone
    # court, mais seulement 7 chiffres reels : ne doit pas etre classe
    # comme "phone" (bug trouve en test manuel avant le durcissement du
    # pattern telephone dans TYPE_PATTERNS).
    elements = [_elem("2026-001", 110, 23, 180, 33)]
    entities = detect_entities(elements)
    assert all(e.entity_type != "phone" for e in entities)


def test_detect_entities_reconnait_un_vrai_telephone():
    elements = [_elem("+33 6 12 34 56 78", 10, 10, 120, 20)]
    entities = detect_entities(elements)
    assert any(e.entity_type == "phone" for e in entities)


def test_detect_entities_deduplique_les_chevauchements():
    # Le token seul "Total" et la phrase fusionnee "Total TTC" se
    # chevauchent fortement : si les deux matchaient un type, seule la
    # plus longue doit etre conservee. Ici aucun des deux n'est une
    # entite reconnue, donc la liste doit rester vide (pas de doublon
    # ni de faux positif).
    elements = [
        _elem("Total", 40, 60, 70, 70),
        _elem("TTC", 72, 60, 95, 70),
    ]
    entities = detect_entities(elements)
    assert entities == []
