"""
Tests du chargeur de document multi-format (document_loader.py).
Génère de petits fichiers PDF/DOCX/XLSX réels (via les mêmes librairies
que celles utilisées pour les lire) et vérifie que load_elements() en
extrait le texte avec des positions cohérentes.
"""

from __future__ import annotations

import pytest

from app.services.document_loader import load_elements


def test_load_elements_format_non_supporte(tmp_path):
    bogus = tmp_path / "fichier.txt"
    bogus.write_text("peu importe")

    with pytest.raises(ValueError):
        load_elements(bogus, lang="eng")


def test_load_docx_paragraphes_et_tableau(tmp_path):
    docx = pytest.importorskip("docx")

    path = tmp_path / "facture.docx"
    document = docx.Document()
    document.add_paragraph("FACTURE N 2026-001")

    table = document.add_table(rows=1, cols=2)
    table.rows[0].cells[0].text = "Total TTC"
    table.rows[0].cells[1].text = "154.90 EUR"

    document.save(str(path))

    elements = load_elements(path, lang="eng")
    texts = [e.text for e in elements]

    assert "FACTURE N 2026-001" in texts
    assert "Total TTC" in texts
    assert "154.90 EUR" in texts

    # La cellule "valeur" doit être positionnée à droite de la cellule "clé"
    # (même ligne, x plus grand) pour que l'ancrage spatial fonctionne.
    key = next(e for e in elements if e.text == "Total TTC")
    value = next(e for e in elements if e.text == "154.90 EUR")
    assert value.bbox.x1 > key.bbox.x1
    assert value.bbox.y1 == key.bbox.y1

    # Texte natif : confiance maximale, pas d'incertitude OCR
    assert all(e.confidence == 1.0 for e in elements)


def test_load_xlsx_cellules_sur_une_grille(tmp_path):
    openpyxl = pytest.importorskip("openpyxl")

    path = tmp_path / "facture.xlsx"
    workbook = openpyxl.Workbook()
    sheet = workbook.active
    sheet["A1"] = "Total TTC"
    sheet["B1"] = "154.90 EUR"
    workbook.save(str(path))

    elements = load_elements(path, lang="eng")
    texts = {e.text for e in elements}

    assert texts == {"Total TTC", "154.90 EUR"}

    key = next(e for e in elements if e.text == "Total TTC")
    value = next(e for e in elements if e.text == "154.90 EUR")
    # B1 est a droite de A1 (colonne suivante), meme ligne
    assert value.bbox.x1 > key.bbox.x1
    assert value.bbox.y1 == key.bbox.y1
    assert value.page_num == 1


def test_load_pdf_texte_natif(tmp_path):
    pymupdf = pytest.importorskip("pymupdf")

    path = tmp_path / "facture.pdf"
    doc = pymupdf.open()
    page = doc.new_page()
    page.insert_text((72, 72), "Total TTC 154.90 EUR")
    doc.save(str(path))
    doc.close()

    elements = load_elements(path, lang="eng")
    texts = [e.text for e in elements]

    assert "Total" in texts
    assert "154.90" in texts
    assert all(e.confidence == 1.0 for e in elements)
    assert all(e.page_num == 1 for e in elements)
