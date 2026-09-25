"""
Fonctions utilitaires géométriques.
Calculs sur les boîtes englobantes, recherche de clés, erreur spatiale.
"""

from __future__ import annotations

import math
from difflib import SequenceMatcher

from app.models.schemas import TextElementSchema, BBoxSchema, TemplateSchema


def center_of_bbox(bbox: BBoxSchema) -> tuple[float, float]:
    """Centre (cx, cy) d'une boîte englobante."""
    return (bbox.x1 + bbox.x2) / 2, (bbox.y1 + bbox.y2) / 2


def distance_between_points(x1: float, y1: float, x2: float, y2: float) -> float:
    """Distance euclidienne entre deux points."""
    return math.sqrt((x2 - x1) ** 2 + (y2 - y1) ** 2)


def find_key_in_elements(
    key_text: str,
    elements: list[TextElementSchema],
    threshold: float = 0.7,
) -> TextElementSchema | None:
    """
    Recherche le texte de la clé dans les éléments OCR.
    Utilise la similarité de séquence pour tolérer les erreurs d'OCR.

    Tesseract tokenise mot par mot : une clé de template à plusieurs mots
    (ex: "Montant TTC:") n'apparaît donc jamais comme un seul élément OCR.
    On reconstitue des candidats multi-mots à partir des éléments voisins
    sur une même ligne (cf. build_phrase_candidates) avant la recherche.

    Retourne l'élément le plus similaire au-dessus du seuil,
    ou None si aucune correspondance n'est trouvée.
    """
    key_lower = key_text.lower().strip()
    best_match = None
    best_score = 0.0

    candidates = list(elements) + build_phrase_candidates(elements)

    for element in candidates:
        elem_text = element.text.lower().strip()

        # Correspondance exacte
        if key_lower == elem_text:
            return element

        # Correspondance par inclusion
        if key_lower in elem_text or elem_text in key_lower:
            score = len(min(key_lower, elem_text, key=len)) / len(
                max(key_lower, elem_text, key=len)
            )
            if score > best_score:
                best_score = score
                best_match = element
            continue

        # Similarité floue (tolère les erreurs OCR)
        score = SequenceMatcher(None, key_lower, elem_text).ratio()
        if score > best_score:
            best_score = score
            best_match = element

    return best_match if best_score >= threshold else None


def _same_line(a: BBoxSchema, b: BBoxSchema) -> bool:
    """
    Indique si deux boîtes englobantes appartiennent à la même ligne de
    texte (chevauchement vertical significatif entre les deux boîtes).
    """
    overlap = min(a.y2, b.y2) - max(a.y1, b.y1)
    min_height = min(a.y2 - a.y1, b.y2 - b.y1)
    if min_height <= 0:
        return False
    return overlap / min_height >= 0.5


def _merge_elements(group: list[TextElementSchema]) -> TextElementSchema:
    """
    Fusionne un groupe d'éléments OCR adjacents en un candidat unique :
    boîte englobante, texte concaténé dans l'ordre de lecture,
    confiance moyenne.
    """
    text = " ".join(e.text for e in group)
    confidence = sum(e.confidence for e in group) / len(group)
    return TextElementSchema(
        text=text,
        bbox=BBoxSchema(
            x1=min(e.bbox.x1 for e in group),
            y1=min(e.bbox.y1 for e in group),
            x2=max(e.bbox.x2 for e in group),
            y2=max(e.bbox.y2 for e in group),
        ),
        confidence=confidence,
        page_num=group[0].page_num,
    )


def build_phrase_candidates(
    elements: list[TextElementSchema],
    max_words: int = 4,
    max_gap_ratio: float = 1.5,
) -> list[TextElementSchema]:
    """
    Reconstitue des candidats multi-mots en regroupant les éléments OCR
    adjacents sur une même ligne (écart horizontal raisonnable par
    rapport à la hauteur du texte). Ne fusionne jamais une clé avec sa
    valeur au-delà de ce que permet la proximité géométrique : c'est la
    recherche de la meilleure correspondance dans find_key_in_elements
    qui sélectionne le candidat pertinent parmi tous les groupements
    possibles.
    """
    sorted_elems = sorted(elements, key=lambda e: (e.page_num, e.bbox.y1, e.bbox.x1))
    n = len(sorted_elems)
    phrases: list[TextElementSchema] = []

    for i in range(n):
        group = [sorted_elems[i]]
        for span in range(1, max_words):
            j = i + span
            if j >= n:
                break
            prev, nxt = group[-1], sorted_elems[j]
            if nxt.page_num != prev.page_num or not _same_line(prev.bbox, nxt.bbox):
                break

            gap = nxt.bbox.x1 - prev.bbox.x2
            avg_height = ((prev.bbox.y2 - prev.bbox.y1) + (nxt.bbox.y2 - nxt.bbox.y1)) / 2
            if gap < 0 or (avg_height > 0 and gap > avg_height * max_gap_ratio):
                break

            group.append(nxt)
            phrases.append(_merge_elements(group))

    return phrases


def bbox_overlap_ratio(a: BBoxSchema, b: BBoxSchema) -> float:
    """
    Ratio de recouvrement entre deux boîtes englobantes, relatif à l'aire
    de la plus petite : aire(a ∩ b) / min(aire(a), aire(b)).
    Renvoie 0.0 si les boîtes ne se recouvrent pas ou si l'une est vide.
    """
    ix1, iy1 = max(a.x1, b.x1), max(a.y1, b.y1)
    ix2, iy2 = min(a.x2, b.x2), min(a.y2, b.y2)
    if ix2 <= ix1 or iy2 <= iy1:
        return 0.0

    inter_area = (ix2 - ix1) * (iy2 - iy1)
    area_a = max(0, a.x2 - a.x1) * max(0, a.y2 - a.y1)
    area_b = max(0, b.x2 - b.x1) * max(0, b.y2 - b.y1)
    min_area = min(area_a, area_b)
    if min_area <= 0:
        return 0.0

    return inter_area / min_area


def elements_in_search_zone(
    elements: list[TextElementSchema],
    center_x: float,
    center_y: float,
    radius: float,
    exclude_text: str | None = None,
    exclude_bbox: BBoxSchema | None = None,
) -> list[TextElementSchema]:
    """
    Retourne les éléments dont le centre tombe dans la zone de recherche
    (cercle de rayon `radius` centré sur (center_x, center_y)).

    Exclut l'élément clé lui-même si exclude_text est fourni (correspondance
    exacte), et exclut aussi tout élément dont la boîte chevauche fortement
    exclude_bbox si fourni. Ce second critère est nécessaire car la clé
    retrouvée par find_key_in_elements() peut être un candidat multi-mots
    reconstitué (ex: "Total TTC" fusionné à partir des tokens OCR "Total"
    et "TTC") : sans lui, les tokens d'origine composant la clé restent
    dans `elements` et peuvent être sélectionnés à tort comme valeur du
    champ lorsque le vecteur spatial (delta_x, delta_y) est petit.
    """
    results = []
    exclude_lower = exclude_text.lower().strip() if exclude_text else None

    for elem in elements:
        # Exclure la clé elle-même (correspondance exacte de texte)
        if exclude_lower and elem.text.lower().strip() == exclude_lower:
            continue

        # Exclure les tokens qui composent la clé (chevauchement de boîte)
        if exclude_bbox is not None and bbox_overlap_ratio(exclude_bbox, elem.bbox) >= 0.5:
            continue

        cx, cy = center_of_bbox(elem.bbox)
        dist = distance_between_points(cx, cy, center_x, center_y)

        if dist <= radius:
            results.append(elem)

    return results


def compute_spatial_error(
    elements: list[TextElementSchema],
    template: TemplateSchema,
) -> float:
    """
    Erreur spatiale entre un document et un template.
    Définition 4.10 du mémoire :

        erreur_spatiale(D, Tj) = (1/|Φ|) Σ min_{e ∈ E} ||centre(e) - position_attendue(f)||

    Pour les champs dont la clé est trouvée, on calcule l'écart entre
    la position attendue et l'élément le plus proche de cette position.
    """
    if not template.fields:
        return float("inf")

    total_error = 0.0
    counted = 0

    for field_def in template.fields:
        key_elem = find_key_in_elements(field_def.key_text, elements)
        if key_elem is None:
            continue

        # Position attendue
        key_cx, key_cy = center_of_bbox(key_elem.bbox)
        expected_x = key_cx + field_def.delta_x
        expected_y = key_cy + field_def.delta_y

        # Distance minimale à un élément du document (hors tokens de la clé
        # elle-même — voir bbox_overlap_ratio dans elements_in_search_zone
        # pour pourquoi la seule exclusion par texte exact ne suffit pas)
        min_dist = float("inf")
        for elem in elements:
            if elem.text.lower().strip() == field_def.key_text.lower().strip():
                continue
            if bbox_overlap_ratio(key_elem.bbox, elem.bbox) >= 0.5:
                continue
            cx, cy = center_of_bbox(elem.bbox)
            dist = distance_between_points(cx, cy, expected_x, expected_y)
            min_dist = min(min_dist, dist)

        if min_dist < float("inf"):
            total_error += min_dist
            counted += 1

    return total_error / counted if counted > 0 else float("inf")
