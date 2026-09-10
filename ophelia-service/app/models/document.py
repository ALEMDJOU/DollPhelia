"""
Représentation interne d'un document et de ses éléments.
Implémente la Définition 4.3 du mémoire (structure du document).
"""

from __future__ import annotations

from dataclasses import dataclass, field


@dataclass
class BoundingBox:
    """Rectangle englobant (x1, y1, x2, y2)."""
    x1: int
    y1: int
    x2: int
    y2: int

    @property
    def center_x(self) -> float:
        return (self.x1 + self.x2) / 2

    @property
    def center_y(self) -> float:
        return (self.y1 + self.y2) / 2

    @property
    def width(self) -> int:
        return self.x2 - self.x1

    @property
    def height(self) -> int:
        return self.y2 - self.y1

    @property
    def area(self) -> int:
        return self.width * self.height


@dataclass
class TextElement:
    """
    Élément textuel localisé : e_j = (texte, bbox, confiance)
    Triplet de la Définition 4.3.
    """
    text: str
    bbox: BoundingBox
    confidence: float
    page_num: int = 1

    @property
    def center_x(self) -> float:
        return self.bbox.center_x

    @property
    def center_y(self) -> float:
        return self.bbox.center_y


@dataclass
class Page:
    """Page P_i = {e_1, e_2, ..., e_m}."""
    page_num: int
    elements: list[TextElement] = field(default_factory=list)
    width: int = 0
    height: int = 0


@dataclass
class Document:
    """
    Document D = {P_1, P_2, ..., P_k}.
    Définition 4.3 du mémoire.
    """
    filepath: str
    pages: list[Page] = field(default_factory=list)
    doc_type: str | None = None

    @property
    def num_pages(self) -> int:
        return len(self.pages)

    @property
    def all_elements(self) -> list[TextElement]:
        """Tous les éléments textuels, toutes pages confondues."""
        elements = []
        for page in self.pages:
            elements.extend(page.elements)
        return elements
