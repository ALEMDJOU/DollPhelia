"""
Utilitaires de manipulation d'images.
"""

from __future__ import annotations

from pathlib import Path

import cv2
import numpy as np
from PIL import Image


def resize_image(image: np.ndarray, max_dimension: int = 4096) -> np.ndarray:
    """
    Redimensionne l'image si elle dépasse la dimension maximale,
    en conservant le ratio d'aspect.
    """
    h, w = image.shape[:2]
    if max(h, w) <= max_dimension:
        return image

    scale = max_dimension / max(h, w)
    new_w = int(w * scale)
    new_h = int(h * scale)

    return cv2.resize(image, (new_w, new_h), interpolation=cv2.INTER_AREA)


def convert_to_pil(image: np.ndarray) -> Image.Image:
    """Convertit une image OpenCV (BGR) en image Pillow (RGB)."""
    if len(image.shape) == 2:
        # Niveaux de gris
        return Image.fromarray(image)
    return Image.fromarray(cv2.cvtColor(image, cv2.COLOR_BGR2RGB))


def load_image(filepath: str | Path) -> np.ndarray:
    """Charge une image depuis un chemin, retourne en format OpenCV."""
    img = cv2.imread(str(filepath))
    if img is None:
        raise ValueError(f"Impossible de charger : {filepath}")
    return img
