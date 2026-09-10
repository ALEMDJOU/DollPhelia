"""
Service d'appariement document ↔ template.
Implémente l'Algorithme 1 (section 4.1.6) et la Définition 4.6 du mémoire.
"""

from __future__ import annotations

import math

from app.config import settings
from app.models.schemas import (
    TextElementSchema,
    TemplateSchema,
    MatchingResponse,
    MatchingResultDetail,
)
from app.utils.bbox_utils import find_key_in_elements, compute_spatial_error


def match_document_to_template(
    doc_type: str | None,
    elements: list[TextElementSchema],
    templates: list[TemplateSchema],
    threshold: float | None = None,
) -> MatchingResponse:
    """
    Algorithme 1 : Appariement d'un document à un template.

    Pour chaque template Tj, calcule :
        F(D, Tj) = Σ wi · Si(D, Tj)    avec Σ wi = 1

    Retourne le template dont le score est maximal,
    ou matched=False si aucun n'atteint le seuil θ_min.
    """
    if threshold is None:
        threshold = settings.matching_threshold

    weights = settings.weights
    best_score = 0.0
    best_template_id = None
    best_template_label = None
    details: list[MatchingResultDetail] = []

    for template in templates:
        # S1 — Concordance de type (Indicatrice)
        s1 = _score_type(doc_type, template.doc_type)

        # S2 — Présence des clés (ratio)
        s2 = _score_key_presence(elements, template)

        # S3 — Concordance spatiale (gaussienne)
        s3 = _score_spatial(elements, template)

        # S4 — Confiance OCR moyenne
        s4 = _score_ocr_confidence(elements)

        # F(D, Tj) — combinaison convexe
        score = (
            weights[0] * s1
            + weights[1] * s2
            + weights[2] * s3
            + weights[3] * s4
        )

        detail = MatchingResultDetail(
            template_id=template.id,
            template_label=template.label,
            score=round(score, 4),
            s1_type=round(s1, 4),
            s2_keys=round(s2, 4),
            s3_spatial=round(s3, 4),
            s4_ocr=round(s4, 4),
        )
        details.append(detail)

        if score > best_score:
            best_score = score
            best_template_id = template.id
            best_template_label = template.label

    matched = best_score >= threshold

    return MatchingResponse(
        matched=matched,
        best_template_id=best_template_id if matched else None,
        best_template_label=best_template_label if matched else None,
        best_score=round(best_score, 4),
        details=details,
    )


# ── Composantes du score F(D, Tj) ─────────────────────────────


def _score_type(doc_type: str | None, template_type: str) -> float:
    """
    S1 : indicatrice de concordance de type.
    I[τ(D) = τ(Tj)]
    """
    if doc_type is None:
        return 0.5  # Type non encore détecté → neutre
    return 1.0 if doc_type.lower() == template_type.lower() else 0.0


def _score_key_presence(
    elements: list[TextElementSchema],
    template: TemplateSchema,
) -> float:
    """
    S2 : proportion des clés du template retrouvées dans le document.
    |{k ∈ clés(Tj) : trouvé(k, D)}| / |clés(Tj)|
    """
    if not template.fields:
        return 0.0

    found = 0
    for field in template.fields:
        match = find_key_in_elements(field.key_text, elements)
        if match is not None:
            found += 1

    return found / len(template.fields)


def _score_spatial(
    elements: list[TextElementSchema],
    template: TemplateSchema,
) -> float:
    """
    S3 : concordance spatiale.
    exp(-erreur_spatiale² / 2σ²)
    Définition 4.10 du mémoire.
    """
    sigma = settings.spatial_tolerance
    error = compute_spatial_error(elements, template)

    return math.exp(-(error ** 2) / (2 * sigma ** 2))


def _score_ocr_confidence(elements: list[TextElementSchema]) -> float:
    """
    S4 : confiance OCR moyenne sur tous les éléments du document.
    (1/|E|) Σ confiance(e)
    """
    if not elements:
        return 0.0
    return sum(e.confidence for e in elements) / len(elements)
