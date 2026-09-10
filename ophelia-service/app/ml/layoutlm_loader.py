"""
Chargement du modèle LayoutLMv3 au format ONNX.
Le modèle est chargé une seule fois au démarrage (lifespan de FastAPI)
pour économiser la mémoire et le temps de chargement.
"""

from __future__ import annotations

import logging
from pathlib import Path

from app.config import settings

logger = logging.getLogger(__name__)

# Singleton : modèle et tokenizer chargés une seule fois
_model = None
_processor = None
_loaded = False


def load_model() -> None:
    """
    Charge le modèle LayoutLMv3 ONNX et son processeur.
    Appelé une seule fois au démarrage via le lifespan de FastAPI.
    """
    global _model, _processor, _loaded

    model_path = Path(settings.model_path)

    if not model_path.exists():
        logger.warning(
            f"Répertoire du modèle introuvable : {model_path}. "
            "L'extraction IA ne sera pas disponible. "
            "Exécutez scripts/convert_to_onnx.py pour préparer le modèle."
        )
        _loaded = False
        return

    try:
        from optimum.onnxruntime import ORTModelForTokenClassification
        from transformers import AutoProcessor

        logger.info(f"Chargement du modèle ONNX depuis {model_path}...")

        _model = ORTModelForTokenClassification.from_pretrained(str(model_path))
        _processor = AutoProcessor.from_pretrained(
            str(model_path), apply_ocr=False
        )

        _loaded = True
        logger.info("Modèle LayoutLMv3 ONNX chargé avec succès.")

    except Exception as e:
        logger.error(f"Erreur de chargement du modèle : {e}")
        _loaded = False


def get_model():
    """Retourne le modèle ONNX chargé."""
    return _model


def get_processor():
    """Retourne le processeur (tokenizer + feature extractor)."""
    return _processor


def is_model_loaded() -> bool:
    """Indique si le modèle est opérationnel."""
    return _loaded
