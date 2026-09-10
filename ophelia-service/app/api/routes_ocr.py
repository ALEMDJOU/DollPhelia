"""
Endpoints OCR — extraction de texte et d'éléments localisés.
"""

from fastapi import APIRouter, UploadFile, File, Form, HTTPException
from pathlib import Path
import tempfile
import shutil

from app.models.schemas import OcrRequest, OcrResponse
from app.services.ocr_service import extract_text_elements
from app.services.preprocessing import preprocess_image

router = APIRouter()


@router.post("/extract", response_model=OcrResponse)
async def extract_ocr(request: OcrRequest):
    """
    Extrait les éléments textuels localisés d'un document.
    Correspond à la production des triplets (texte, bbox, confiance)
    de la Définition 4.3 du mémoire.
    """
    filepath = Path(request.filepath)
    if not filepath.exists():
        raise HTTPException(status_code=404, detail=f"Fichier introuvable : {filepath}")

    try:
        # Prétraitement de l'image
        processed_images = preprocess_image(filepath)

        # OCR sur chaque page
        all_elements = []
        for page_num, image in enumerate(processed_images, start=1):
            elements = extract_text_elements(image, lang=request.lang)
            for elem in elements:
                elem.page_num = page_num
            all_elements.extend(elements)

        return OcrResponse(
            filepath=str(filepath),
            num_pages=len(processed_images),
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
        processed_images = preprocess_image(tmp_path)

        all_elements = []
        for page_num, image in enumerate(processed_images, start=1):
            elements = extract_text_elements(image, lang=lang)
            for elem in elements:
                elem.page_num = page_num
            all_elements.extend(elements)

        return OcrResponse(
            filepath=str(tmp_path),
            num_pages=len(processed_images),
            elements=all_elements,
        )
    finally:
        tmp_path.unlink(missing_ok=True)
