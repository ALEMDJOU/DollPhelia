"""
Tests du service d'extraction (extraction_service.py).
Vérifie l'Algorithme 2 — extraction ancrée par vecteur spatial.
"""

from app.models.schemas import BBoxSchema, TextElementSchema, TemplateSchema, TemplateFieldSchema
from app.services.extraction_service import extract_fields


def _elem(text: str, x1: int, y1: int, x2: int, y2: int, conf: float = 0.9) -> TextElementSchema:
    return TextElementSchema(text=text, bbox=BBoxSchema(x1=x1, y1=y1, x2=x2, y2=y2), confidence=conf)


def _template_un_champ(delta_x=150, delta_y=0) -> TemplateSchema:
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
                delta_x=delta_x,
                delta_y=delta_y,
            )
        ],
    )


def test_extraction_reussie_quand_cle_et_valeur_proches():
    elements = [
        _elem("Montant TTC:", 50, 100, 150, 120),
        _elem("1250,00", 200, 100, 260, 120),
    ]
    result = extract_fields(elements, _template_un_champ())

    assert len(result.fields) == 1
    field = result.fields[0]
    assert field.extracted_value == "1250,00"
    assert field.confidence_total > 0.0


def test_extraction_sans_ancre_retourne_champ_vide():
    elements = [_elem("Un texte sans rapport", 0, 0, 10, 10)]
    result = extract_fields(elements, _template_un_champ())

    assert len(result.fields) == 1
    field = result.fields[0]
    assert field.extracted_value is None
    assert field.confidence_total == 0.0


def test_extraction_sans_candidat_dans_la_zone_retourne_champ_vide():
    # La clé est présente mais aucune valeur n'existe autour de la position attendue
    elements = [_elem("Montant TTC:", 50, 100, 150, 120)]
    result = extract_fields(elements, _template_un_champ())

    field = result.fields[0]
    assert field.extracted_value is None
    assert field.confidence_total == 0.0


def test_extraction_selectionne_le_candidat_le_plus_proche():
    elements = [
        _elem("Montant TTC:", 50, 100, 150, 120),
        _elem("999,00", 500, 500, 560, 520),   # loin de la position attendue
        _elem("1250,00", 200, 100, 260, 120),  # proche de la position attendue
    ]
    result = extract_fields(elements, _template_un_champ())

    assert result.fields[0].extracted_value == "1250,00"


def test_confiance_globale_ignore_les_champs_vides():
    # Un seul des deux champs a une ancre trouvée -> la moyenne ne compte que lui
    template = TemplateSchema(
        id=1,
        label="Facture",
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
                field_name="iban",
                field_label="IBAN",
                field_type="iban",
                key_text="IBAN introuvable:",
                delta_x=150,
                delta_y=0,
            ),
        ],
    )
    elements = [
        _elem("Montant TTC:", 50, 100, 150, 120),
        _elem("1250,00", 200, 100, 260, 120),
    ]
    result = extract_fields(elements, template)

    assert result.fields[1].extracted_value is None
    assert result.global_confidence == result.fields[0].confidence_total
