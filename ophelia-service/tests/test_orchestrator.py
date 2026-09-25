"""
Tests de l'orchestrateur (orchestrator.py).
Vérifie la détection automatique du type de document avant l'appariement.
"""

from app.models.schemas import BBoxSchema, TextElementSchema, TemplateSchema, TemplateFieldSchema
from app.services.orchestrator import _detect_doc_type, _extract_forced_template


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


def test_extract_forced_template_sans_template_retourne_vide():
    result = _extract_forced_template([_elem("x", 0, 0, 10, 10)], [])

    assert result.strategy == "template_matching"
    assert result.fields == []
    assert result.global_confidence == 0.0


def test_extract_forced_template_prend_le_meilleur_meme_sous_le_seuil():
    # La stratégie "template_matching" choisie explicitement par
    # l'utilisateur doit extraire avec le template le mieux apparié même
    # si son score n'atteint pas le seuil habituel (theta_min) : c'est le
    # sens de "forcer" la stratégie plutôt que de la déléguer à "auto".
    elements = [
        _elem("Montant TTC:", 0, 0, 100, 20),
        _elem("1234,56", 140, 0, 160, 20),  # centre a (150,10) = cle(50,10) + delta(100,0)
    ]
    templates = [_template(1, "facture", "Montant TTC:")]

    result = _extract_forced_template(elements, templates)

    assert result.strategy == "template_matching"
    assert result.template_id == 1
    assert len(result.fields) == 1
    assert result.fields[0].extracted_value == "1234,56"
