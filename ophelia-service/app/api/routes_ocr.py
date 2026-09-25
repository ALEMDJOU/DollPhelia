"""
Endpoints OCR — extraction de texte et d'éléments localisés.
"""

from fastapi import APIRouter, UploadFile, File, Form, HTTPException
from pathlib import Path
import tempfile
import shutil

from app.models.schemas import OcrRequest, OcrResponse
from app.services.document_loader import load_elements

router = APIRouter()


@router.post("/extract", response_model=OcrResponse)
async def extract_ocr(request: OcrRequest):
    """
    Extrait les éléments textuels localisés d'un document — image, PDF
    (texte natif ou OCR de repli pour les pages scannées), DOCX ou XLSX.
    Correspond à la production des triplets (texte, bbox, confiance)
    de la Définition 4.3 du mémoire (confiance = 1.0 pour le texte natif
    d'un PDF/DOCX/XLSX, qui n'a pas d'incertitude de reconnaissance).
    """
    filepath = Path(request.filepath)
    if not filepath.exists():
        raise HTTPException(status_code=404, detail=f"Fichier introuvable : {filepath}")

    try:
        all_elements = load_elements(filepath, lang=request.lang)
        num_pages = max((e.page_num for e in all_elements), default=1)

        return OcrResponse(
            filepath=str(filepath),
            num_pages=num_pages,
            elements=all_elements,
        )

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur OCR : {str(e)}")


@router.post("/extract-upload", response_model=OcrResponse)
async def extract_ocr_upload(
    file: UploadFile = File(...),
    lang: str = Form("fra"),
):
    """
    Variante avec upload direct du fichier (utile pour les tests).
    """
    suffix = Path(file.filename).suffix
    with tempfile.NamedTemporaryFile(delete=False, suffix=suffix) as tmp:
        shutil.copyfileobj(file.file, tmp)
        tmp_path = Path(tmp.name)

    try:
        all_elements = load_elements(tmp_path, lang=lang)
        num_pages = max((e.page_num for e in all_elements), default=1)

        return OcrResponse(
            filepath=str(tmp_path),
            num_pages=num_pages,
            elements=all_elements,
        )
    finally:
        tmp_path.unlink(missing_ok=True)
