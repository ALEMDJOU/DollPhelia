"""
Tests de l'orchestrateur (orchestrator.py).
Vérifie la détection automatique du type de document avant l'appariement.
"""

from app.models.schemas import BBoxSchema, TextElementSchema, TemplateSchema, TemplateFieldSchema
from app.services.orchestrator import _detect_doc_type


def _elem(text: str, x1: int, y1: int, x2: int, y2: int, conf: float = 0.9) -> TextElementSchema:
    return TextElementSchema(text=text, bbox=BBoxSchema(x1=x1, y1=y1, x2=x2, y2=y2), confidence=conf)


def _template(id_: int, doc_type: str, key_text: str) -> TemplateSchema:
    return TemplateSchema(
        id=id_,
        label=doc_type,
        doc_type=doc_type,
        fields=[
            TemplateFieldSchema(
                field_name="valeur",
                field_label="Valeur",
                field_type="text",
                key_text=key_text,
                delta_x=100,
                delta_y=0,
            )
        ],
    )


def test_detect_doc_type_choisit_le_template_dont_les_cles_correspondent():
    elements = [_elem("Montant TTC:", 0, 0, 100, 20)]
    templates = [
        _template(1, "facture", "Montant TTC:"),
        _template(2, "cv", "Expérience professionnelle"),
    ]

    assert _detect_doc_type(elements, templates) == "facture"


def test_detect_doc_type_sans_templates_retourne_none():
    assert _detect_doc_type([_elem("x", 0, 0, 10, 10)], []) is None


def test_detect_doc_type_sans_correspondance_retourne_none():
    elements = [_elem("texte totalement hors sujet", 0, 0, 10, 10)]
    templates = [_template(1, "facture", "Montant TTC:")]

    assert _detect_doc_type(elements, templates) is None
