"""
Tests du service d'appariement (matching_service.py).
Vérifie le score F(D, Tj) = w1.S1 + w2.S2 + w3.S3 + w4.S4 (Définition 4.6).
"""

from app.models.schemas import BBoxSchema, TextElementSchema, TemplateSchema, TemplateFieldSchema
from app.services.matching_service import match_document_to_template


def _elem(text: str, x1: int, y1: int, x2: int, y2: int, conf: float = 0.9) -> TextElementSchema:
    return TextElementSchema(text=text, bbox=BBoxSchema(x1=x1, y1=y1, x2=x2, y2=y2), confidence=conf)


def _template_facture() -> TemplateSchema:
    return TemplateSchema(
        id=1,
        label="Facture standard",
        doc_type="facture",
        fields=[
            TemplateFieldSchema(
                field_name="montant_ttc",
                field_label="Montant TTC",
                field_type="amount",
                key_text="Montant TTC:",
                delta_x=150,
                delta_y=0,
            ),
            TemplateFieldSchema(
                field_name="date",
                field_label="Date",
                field_type="date",
                key_text="Date:",
                delta_x=150,
                delta_y=0,
            ),
        ],
    )


def test_match_document_type_et_cles_correspondent():
    elements = [
        _elem("Montant TTC:", 50, 100, 150, 120),
        _elem("1250,00", 200, 100, 260, 120),
        _elem("Date:", 50, 60, 90, 80),
        _elem("12/03/2024", 200, 60, 280, 80),
    ]
    result = match_document_to_template(
        doc_type="facture", elements=elements, templates=[_template_facture()]
    )

    assert result.matched is True
    assert result.best_template_id == 1
    # S1 = 1.0 (type identique) et S2 = 1.0 (les deux clés sont trouvées)
    assert result.details[0].s1_type == 1.0
    assert result.details[0].s2_keys == 1.0


def test_match_document_type_different_donne_s1_nul():
    elements = [_elem("Montant TTC:", 50, 100, 150, 120)]
    result = match_document_to_template(
        doc_type="cv", elements=elements, templates=[_template_facture()]
    )
    assert result.details[0].s1_type == 0.0


def test_match_document_sans_type_est_neutre():
    elements = [_elem("Montant TTC:", 50, 100, 150, 120)]
    result = match_document_to_template(
        doc_type=None, elements=elements, templates=[_template_facture()]
    )
    assert result.details[0].s1_type == 0.5


def test_match_sous_le_seuil_ne_matche_pas():
    # Aucune clé présente, aucun type connu -> score bas
    elements = [_elem("texte hors sujet", 0, 0, 10, 10)]
    result = match_document_to_template(
        doc_type="facture",
        elements=elements,
        templates=[_template_facture()],
        threshold=0.9,
    )
    assert result.matched is False
    assert result.best_template_id is None


def test_match_sans_templates_retourne_non_matche():
    result = match_document_to_template(doc_type="facture", elements=[], templates=[])
    assert result.matched is False
    assert result.best_score == 0.0
    assert result.details == []
