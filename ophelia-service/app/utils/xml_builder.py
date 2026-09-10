"""
Construction de l'export XML.
"""

from __future__ import annotations

from lxml import etree


def build_export_xml(data: dict) -> str:
    """
    Convertit le dictionnaire d'export en document XML bien formé.
    """
    export = data.get("ophelia_export", {})

    root = etree.Element("ophelia_export")
    root.set("version", str(export.get("version", "1.0")))

    # Métadonnées
    _add_text_child(root, "generated_at", export.get("generated_at", ""))
    _add_text_child(root, "document_ref", export.get("document_ref", ""))
    _add_text_child(root, "strategy", export.get("strategy", ""))
    _add_text_child(root, "matching_score", str(export.get("matching_score", 0)))
    _add_text_child(root, "global_confidence", str(export.get("global_confidence", 0)))

    # Template
    template_data = export.get("template")
    if template_data:
        template_elem = etree.SubElement(root, "template")
        _add_text_child(template_elem, "id", str(template_data.get("id", "")))
        _add_text_child(template_elem, "label", str(template_data.get("label", "")))

    # Champs extraits
    fields_elem = etree.SubElement(root, "fields")
    for field_data in export.get("fields", []):
        field_elem = etree.SubElement(fields_elem, "field")
        _add_text_child(field_elem, "name", field_data.get("name", ""))
        _add_text_child(field_elem, "label", field_data.get("label", ""))
        _add_text_child(field_elem, "value", str(field_data.get("value", "")))
        _add_text_child(field_elem, "confidence", str(field_data.get("confidence", 0)))
        _add_text_child(field_elem, "page", str(field_data.get("page", 1)))

    return etree.tostring(
        root,
        pretty_print=True,
        xml_declaration=True,
        encoding="UTF-8",
    ).decode("utf-8")


def _add_text_child(parent: etree._Element, tag: str, text: str) -> etree._Element:
    """Ajoute un élément enfant avec du texte."""
    child = etree.SubElement(parent, tag)
    child.text = text
    return child
