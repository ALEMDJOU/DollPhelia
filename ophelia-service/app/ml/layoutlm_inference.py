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
        # Tokenization. padding=True (plutot que "max_length") ne complete
        # que jusqu'a la longueur reelle de la sequence : le graphe ONNX
        # declare sequence_length comme axe dynamique (verifie via
        # onnxruntime.InferenceSession.get_inputs()), donc rien n'impose de
        # forcer 512 tokens a chaque appel. Un document court (DOCX/XLSX de
        # quelques lignes) passait par un forward pass de 512 tokens quel
        # que soit son contenu reel — le repli IA est deja le chemin le plus
        # lent du pipeline (CPU, pas de GPU), inutile de l'alourdir encore.
        encoding = processor(
            image,
            words,
            boxes=boxes,
            return_tensors="np",
            truncation=True,
            max_length=512,
            padding=True,
        )

        # Inférence — on n'appelle PAS model(**encoding) : le forward()
        # générique de optimum.ORTModelForTokenClassification ne connaît
        # que input_ids/attention_mask/token_type_ids et ignore silencieusement
        # bbox/pixel_values passés en **kwargs, alors que le graphe ONNX de
        # LayoutLMv3 les exige tous les deux (ValueError "Required inputs
        # ['bbox', 'pixel_values'] are missing from input feed"). On appelle
        # donc directement la session onnxruntime sous-jacente, avec un feed
        # construit à partir de ses propres entrées déclarées (nom + dtype)
        # pour rester correct quel que soit le jeu d'entrées du graphe.
        session = model.model
        feed = {}
        for onnx_input in session.get_inputs():
            value = encoding[onnx_input.name]
            if "int64" in onnx_input.type:
                value = value.astype(np.int64)
            elif "float" in onnx_input.type:
                value = value.astype(np.float32)
            feed[onnx_input.name] = value

        output_names = [o.name for o in session.get_outputs()]
        raw_outputs = session.run(output_names, feed)
        logits = raw_outputs[output_names.index("logits")]

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
    # Softmax sur les logits bruts : sans cela, `score` est un logit non
    # borné (ex: 6.23) au lieu d'une probabilité dans [0, 1], ce qui casse
    # le contrat de confidence_total utilisé partout ailleurs dans le
    # système (ExtractedFieldSchema, Définition 4.11 du mémoire).
    token_logits = logits[0]
    exp_logits = np.exp(token_logits - np.max(token_logits, axis=-1, keepdims=True))
    probabilities = exp_logits / np.sum(exp_logits, axis=-1, keepdims=True)

    predictions_idx = np.argmax(probabilities, axis=-1)
    scores = np.max(probabilities, axis=-1)

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
