"""
Inférence LayoutLMv3 sur un document.
Prend les éléments OCR et produit des prédictions NER (token classification).
"""

from __future__ import annotations

import logging
from pathlib import Path

import numpy as np
from PIL import Image

from app.models.schemas import TextElementSchema
from app.ml.layoutlm_loader import get_model, get_processor, is_model_loaded

logger = logging.getLogger(__name__)


def run_layoutlm_inference(
    elements: list[TextElementSchema],
    image_path: str | None = None,
) -> list[dict] | None:
    """
    Exécute l'inférence LayoutLMv3 sur les éléments OCR d'un document.

    Paramètres
    ----------
    elements   : éléments textuels localisés produits par l'OCR
    image_path : chemin vers l'image du document (pour le canal visuel)

    Retourne
    --------
    Liste de prédictions {index, label, score} par token,
    ou None si le modèle n'est pas disponible.
    """
    if not is_model_loaded():
        logger.warning("Modèle LayoutLMv3 non chargé — extraction IA indisponible.")
        return None

    model = get_model()
    processor = get_processor()

    if not elements:
        return []

    # Préparer les entrées
    words = [e.text for e in elements]
    boxes = [
        [e.bbox.x1, e.bbox.y1, e.bbox.x2, e.bbox.y2]
        for e in elements
    ]

    # Normaliser les boîtes dans [0, 1000] (convention LayoutLMv3)
    boxes = _normalize_boxes(boxes, elements)

    # Charger l'image si disponible
    image = None
    if image_path:
        try:
            image = Image.open(image_path).convert("RGB")
        except Exception as e:
            logger.warning(f"Impossible de charger l'image : {e}")

    # Si pas d'image, créer une image blanche factice
    if image is None:
        image = Image.new("RGB", (224, 224), (255, 255, 255))

    try:
        # Tokenization
        encoding = processor(
            image,
            words,
            boxes=boxes,
            return_tensors="np",
            truncation=True,
            max_length=512,
            padding="max_length",
        )

        # Inférence
        outputs = model(**{k: v for k, v in encoding.items()})
        logits = outputs.logits

        # Décoder les prédictions
        predictions = _decode_predictions(logits, encoding, model)

        return predictions

    except Exception as e:
        logger.error(f"Erreur d'inférence LayoutLMv3 : {e}")
        return None


def _normalize_boxes(
    boxes: list[list[int]],
    elements: list[TextElementSchema],
) -> list[list[int]]:
    """
    Normalise les boîtes englobantes dans l'espace [0, 1000]
    attendu par LayoutLMv3.
    """
    if not boxes:
        return boxes

    all_coords = [c for box in boxes for c in box]
    max_coord = max(all_coords) if all_coords else 1

    if max_coord <= 1000:
        return boxes

    scale = 1000.0 / max_coord
    return [
        [int(x * scale) for x in box]
        for box in boxes
    ]


def _decode_predictions(
    logits: np.ndarray,
    encoding: dict,
    model,
) -> list[dict]:
    """
    Décode les logits du modèle en prédictions NER.
    """
    predictions_idx = np.argmax(logits[0], axis=-1)
    scores = np.max(logits[0], axis=-1)

    # Récupérer la correspondance token → mot original
    word_ids = encoding.word_ids(batch_index=0) if hasattr(encoding, "word_ids") else None

    # Labels du modèle
    id2label = model.config.id2label if hasattr(model.config, "id2label") else {}

    results = []
    seen_words = set()

    for token_idx, (pred_id, score) in enumerate(zip(predictions_idx, scores)):
        word_idx = word_ids[token_idx] if word_ids else token_idx

        if word_idx is None:
            continue
        if word_idx in seen_words:
            continue

        seen_words.add(word_idx)

        label = id2label.get(int(pred_id), "O")
        results.append({
            "index": int(word_idx),
            "label": label,
            "score": float(score),
        })

    return results
