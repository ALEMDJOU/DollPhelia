"""
Prétraitement des images avant OCR.
Binarisation, redressement, débruitage.
"""

from __future__ import annotations

from pathlib import Path

import cv2
import numpy as np
from PIL import Image


def preprocess_image(filepath: Path) -> list[np.ndarray]:
    """
    Charge un fichier (image ou PDF) et retourne une liste d'images
    prétraitées (une par page), prêtes pour l'OCR.
    """
    suffix = filepath.suffix.lower()

    if suffix == ".pdf":
        images = _load_pdf_pages(filepath)
    elif suffix in (".png", ".jpg", ".jpeg", ".tiff", ".tif", ".bmp", ".webp"):
        img = cv2.imread(str(filepath))
        if img is None:
            raise ValueError(f"Impossible de charger l'image : {filepath}")
        images = [img]
    else:
        raise ValueError(f"Format non supporté : {suffix}")

    return [_enhance(img) for img in images]


def _load_pdf_pages(filepath: Path) -> list[np.ndarray]:
    """
    Convertit chaque page d'un PDF en image via Pillow.
    Pour les PDF multipages, utilise pdf2image si disponible,
    sinon traite comme une image unique.
    """
    try:
        from pdf2image import convert_from_path
        pil_images = convert_from_path(str(filepath), dpi=300)
        return [np.array(img.convert("RGB"))[:, :, ::-1] for img in pil_images]
    except ImportError:
        # Fallback : essayer d'ouvrir comme image (PDF mono-page)
        pil_img = Image.open(filepath).convert("RGB")
        return [np.array(pil_img)[:, :, ::-1]]


def _enhance(image: np.ndarray) -> np.ndarray:
    """
    Pipeline d'amélioration :
    1. Conversion en niveaux de gris
    2. Redressement (deskew)
    3. Débruitage
    4. Binarisation adaptative
    """
    # Niveaux de gris
    if len(image.shape) == 3:
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    else:
        gray = image.copy()

    # Redressement
    gray = _deskew(gray)

    # Débruitage
    denoised = cv2.fastNlMeansDenoising(gray, h=10)

    # Binarisation adaptative (Otsu)
    _, binary = cv2.threshold(denoised, 0, 255, cv2.THRESH_BINARY + cv2.THRESH_OTSU)

    return binary


def _deskew(image: np.ndarray) -> np.ndarray:
    """
    Corrige l'inclinaison de l'image par projection horizontale.
    """
    coords = np.column_stack(np.where(image < 128))
    if len(coords) < 100:
        return image

    angle = cv2.minAreaRect(coords)[-1]

    if angle < -45:
        angle = -(90 + angle)
    else:
        angle = -angle

    # Ne corriger que les petites inclinaisons (< 15°)
    if abs(angle) > 15 or abs(angle) < 0.1:
        return image

    h, w = image.shape[:2]
    center = (w // 2, h // 2)
    matrix = cv2.getRotationMatrix2D(center, angle, 1.0)
    rotated = cv2.warpAffine(
        image, matrix, (w, h),
        flags=cv2.INTER_CUBIC,
        borderMode=cv2.BORDER_REPLICATE,
    )
    return rotated
