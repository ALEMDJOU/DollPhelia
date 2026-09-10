"""
Tests du service d'export (export_service.py + utils/xml_builder.py).
"""

import json

from app.models.schemas import ExtractionResponse, ExtractedFieldSchema
from app.services.export_service import export_to_json, export_to_xml


def _resultat() -> ExtractionResponse:
    return ExtractionResponse(
        strategy="template_matching",
        template_id=1,
        template_label="Facture standard",
        matching_score=0.76,
        global_confidence=0.5,
        fields=[
            ExtractedFieldSchema(
                field_name="montant_ttc",
                field_label="Montant TTC",
                extracted_value="1250,00",
                confidence_total=0.5,
            ),
            ExtractedFieldSchema(
                field_name="iban",
                field_label="IBAN",
                extracted_value=None,  # champ non extrait -> doit être exclu de l'export
                confidence_total=0.0,
            ),
        ],
    )


def test_export_json_exclut_les_champs_non_extraits():
    content = export_to_json(_resultat(), document_ref="FACT-001")
    data = json.loads(content)

    export = data["ophelia_export"]
    assert export["document_ref"] == "FACT-001"
    assert len(export["fields"]) == 1
    assert export["fields"][0]["name"] == "montant_ttc"
    assert export["fields"][0]["value"] == "1250,00"


def test_export_xml_est_bien_forme_et_coherent_avec_json():
    from lxml import etree

    xml_content = export_to_xml(_resultat(), document_ref="FACT-001")
    root = etree.fromstring(xml_content.encode("utf-8"))

    assert root.tag == "ophelia_export"
    assert root.find("document_ref").text == "FACT-001"

    field_names = [f.find("name").text for f in root.find("fields")]
    assert field_names == ["montant_ttc"]
