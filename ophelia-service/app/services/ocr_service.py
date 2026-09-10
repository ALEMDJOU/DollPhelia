"""
Service OCR — extraction des éléments textuels localisés via Tesseract.
Produit les triplets (texte, bbox, confiance) de la Définition 4.3.
"""

from __future__ import annotations

import numpy as np
import pytesseract

from app.config import settings
from app.models.schemas import TextElementSchema, BBoxSchema

# Configuration du chemin Tesseract
pytesseract.pytesseract.tesseract_cmd = settings.tesseract_cmd


def extract_text_elements(
    image: np.ndarray,
    lang: str = "fra",
) -> list[TextElementSchema]:
    """
    Extrait les éléments textuels localisés d'une image prétraitée.

    Retourne une liste de TextElementSchema, chacun portant :
    - le texte reconnu
    - la boîte englobante (x1, y1, x2, y2)
    - le score de confiance OCR (normalisé dans [0, 1])
    """
    # Tesseract retourne un dictionnaire avec les détails par mot
    data = pytesseract.image_to_data(
        image,
        lang=lang,
        output_type=pytesseract.Output.DICT,
        config="--psm 6",  # Assume un bloc de texte uniforme
    )

    elements: list[TextElementSchema] = []
    n_items = len(data["text"])

    for i in range(n_items):
        text = data["text"][i].strip()
        conf = float(data["conf"][i])

        # Ignorer les résultats vides ou à confiance négative
        if not text or conf < 0:
            continue

        x = data["left"][i]
        y = data["top"][i]
        w = data["width"][i]
        h = data["height"][i]

        elements.append(
            TextElementSchema(
                text=text,
                bbox=BBoxSchema(x1=x, y1=y, x2=x + w, y2=y + h),
                confidence=conf / 100.0,  # Normalisation dans [0, 1]
            )
        )

    return elements


def extract_full_text(image: np.ndarray, lang: str = "fra") -> str:
    """Extraction du texte brut complet (sans positions)."""
    return pytesseract.image_to_string(image, lang=lang, config="--psm 6")
