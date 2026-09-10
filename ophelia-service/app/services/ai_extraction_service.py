"""
Service d'extraction par intelligence artificielle (LayoutLMv3).
Stratégie de repli lorsque l'appariement de templates échoue.
Cf. section 4.1.7 du mémoire — « Extraction sémantique de repli ».
"""

from __future__ import annotations

from app.models.schemas import (
    TextElementSchema,
    ExtractedFieldSchema,
    ExtractionResponse,
    BBoxSchema,
)
from app.ml.layoutlm_inference import run_layoutlm_inference


# ── Labels du modèle FUNSD (nielsr/layoutlmv3-finetuned-funsd) ──
#
#  Le modèle FUNSD classe chaque token en :
#    HEADER   — titre, en-tête de section
#    QUESTION — clé / label d'un champ (ex: "Montant TTC :")
#    ANSWER   — valeur associée à une clé (ex: "1 250,00 €")
#    O        — token hors entité
#
#  Ophélia exploite principalement les paires QUESTION → ANSWER :
#  la QUESTION correspond à la « clé » du formalisme (Définition 4.8)
#  et l'ANSWER correspond à la « valeur » extraite.
#
#  Si tu fine-tunes un modèle custom avec d'autres labels (DATE,
#  AMOUNT, IBAN...), ajoute-les ici.

LABEL_MAP = {
    # Labels FUNSD
    "B-HEADER": "header",
    "I-HEADER": "header",
    "B-QUESTION": "question",
    "I-QUESTION": "question",
    "B-ANSWER": "answer",
    "I-ANSWER": "answer",
    # Labels custom (si fine-tuning ultérieur)
    "B-DATE": "date",
    "I-DATE": "date",
    "B-AMOUNT": "amount",
    "I-AMOUNT": "amount",
    "B-COMPANY": "text",
    "I-COMPANY": "text",
    "B-ADDRESS": "text",
    "I-ADDRESS": "text",
    "B-INVOICE_NUM": "text",
    "I-INVOICE_NUM": "text",
    "B-IBAN": "iban",
    "I-IBAN": "iban",
}


def extract_with_ai(
    elements: list[TextElementSchema],
    image_path: str | None = None,
) -> ExtractionResponse:
    """
    Extraction par LayoutLMv3 — exploite conjointement le texte,
    la position et l'image pour identifier les entités.

    Retourne les champs extraits avec leurs confiances.
    """
    predictions = run_layoutlm_inference(elements, image_path)

    if predictions is None:
        return ExtractionResponse(
            strategy="ai_layoutlm",
            fields=[],
            global_confidence=0.0,
        )

    # Regrouper les tokens par entité (BIO → champs)
    raw_fields = _aggregate_predictions(predictions, elements)

    # Apparier les QUESTION → ANSWER (modèle FUNSD)
    extracted_fields = _pair_questions_answers(raw_fields)

    confidences = [f.confidence_total for f in extracted_fields if f.extracted_value]
    global_conf = sum(confidences) / len(confidences) if confidences else 0.0

    return ExtractionResponse(
        strategy="ai_layoutlm",
        fields=extracted_fields,
        global_confidence=round(global_conf, 4),
    )


def _aggregate_predictions(
    predictions: list[dict],
    elements: list[TextElementSchema],
) -> list[ExtractedFieldSchema]:
    """
    Agrège les prédictions token par token (schéma BIO) en champs complets.
    """
    fields: list[ExtractedFieldSchema] = []
    current_entity: dict | None = None

    for pred in predictions:
        label = pred.get("label", "O")
        score = pred.get("score", 0.0)
        token_idx = pred.get("index", 0)

        if label == "O":
            # Fin d'entité en cours
            if current_entity:
                fields.append(_build_field(current_entity, elements))
                current_entity = None
            continue

        if label.startswith("B-"):
            # Début d'une nouvelle entité
            if current_entity:
                fields.append(_build_field(current_entity, elements))

            current_entity = {
                "label": label[2:],
                "field_type": LABEL_MAP.get(label, "text"),
                "token_indices": [token_idx],
                "scores": [score],
            }

        elif label.startswith("I-") and current_entity:
            # Continuation de l'entité en cours
            current_entity["token_indices"].append(token_idx)
            current_entity["scores"].append(score)

    # Dernière entité
    if current_entity:
        fields.append(_build_field(current_entity, elements))

    return fields


def _pair_questions_answers(
    raw_fields: list[ExtractedFieldSchema],
) -> list[ExtractedFieldSchema]:
    """
    Apparie les entités QUESTION et ANSWER produites par le modèle FUNSD.

    Logique : chaque QUESTION est suivie de l'ANSWER la plus proche
    spatialement (typiquement à droite ou en dessous). Le résultat
    est un champ dont le nom est le texte de la QUESTION et la valeur
    est le texte de l'ANSWER.

    Les entités qui ne sont ni QUESTION ni ANSWER (HEADER, types custom)
    passent telles quelles.
    """
    questions: list[ExtractedFieldSchema] = []
    answers: list[ExtractedFieldSchema] = []
    others: list[ExtractedFieldSchema] = []

    for field in raw_fields:
        label = field.field_name.lower()
        if label == "question":
            questions.append(field)
        elif label == "answer":
            answers.append(field)
        else:
            others.append(field)

    # Si pas de structure QUESTION/ANSWER, retourner tel quel
    if not questions or not answers:
        return raw_fields

    paired: list[ExtractedFieldSchema] = []
    used_answers: set[int] = set()

    for q in questions:
        if q.bbox is None or q.extracted_value is None:
            continue

        q_cx = (q.bbox.x1 + q.bbox.x2) / 2
        q_cy = (q.bbox.y1 + q.bbox.y2) / 2

        # Trouver l'ANSWER la plus proche non encore appariée
        best_idx = -1
        best_dist = float("inf")

        for i, a in enumerate(answers):
            if i in used_answers or a.bbox is None:
                continue

            a_cx = (a.bbox.x1 + a.bbox.x2) / 2
            a_cy = (a.bbox.y1 + a.bbox.y2) / 2

            dist = ((a_cx - q_cx) ** 2 + (a_cy - q_cy) ** 2) ** 0.5
            if dist < best_dist:
                best_dist = dist
                best_idx = i

        if best_idx >= 0:
            used_answers.add(best_idx)
            answer = answers[best_idx]

            # Construire le champ apparié : clé = QUESTION, valeur = ANSWER
            paired.append(
                ExtractedFieldSchema(
                    field_name=q.extracted_value.lower().replace(" ", "_").rstrip(":"),
                    field_label=q.extracted_value.rstrip(":").strip(),
                    extracted_value=answer.extracted_value,
                    confidence_ocr=answer.confidence_ocr,
                    confidence_spatial=answer.confidence_spatial,
                    confidence_validation=answer.confidence_validation,
                    confidence_total=answer.confidence_total,
                    bbox=answer.bbox,
                    page_num=answer.page_num,
                )
            )

    # Ajouter les ANSWER orphelines et les autres entités
    for i, a in enumerate(answers):
        if i not in used_answers:
            paired.append(a)

    paired.extend(others)
    return paired


def _build_field(
    entity: dict,
    elements: list[TextElementSchema],
) -> ExtractedFieldSchema:
    """
    Construit un ExtractedFieldSchema à partir d'une entité agrégée.
    """
    indices = entity["token_indices"]
    scores = entity["scores"]

    # Assembler le texte et la bbox englobante
    texts = []
    x1_min, y1_min = float("inf"), float("inf")
    x2_max, y2_max = 0, 0
    page = 1

    for idx in indices:
        if idx < len(elements):
            elem = elements[idx]
            texts.append(elem.text)
            x1_min = min(x1_min, elem.bbox.x1)
            y1_min = min(y1_min, elem.bbox.y1)
            x2_max = max(x2_max, elem.bbox.x2)
            y2_max = max(y2_max, elem.bbox.y2)
            page = elem.page_num

    avg_score = sum(scores) / len(scores) if scores else 0.0

    return ExtractedFieldSchema(
        field_name=entity["label"].lower(),
        field_label=entity["label"].replace("_", " ").title(),
        extracted_value=" ".join(texts) if texts else None,
        confidence_ocr=round(avg_score, 4),
        confidence_spatial=1.0,       # Pas de contrainte spatiale en mode IA
        confidence_validation=0.5,     # Validation non appliquée en mode IA
        confidence_total=round(avg_score * 0.5, 4),
        bbox=BBoxSchema(
            x1=int(x1_min) if x1_min != float("inf") else 0,
            y1=int(y1_min) if y1_min != float("inf") else 0,
            x2=int(x2_max),
            y2=int(y2_max),
        ),
        page_num=page,
    )
