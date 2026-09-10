"""
Représentation interne d'un template et de ses champs.
Implémente les Définitions 4.7 (Template) et 4.8 (Champ) du mémoire.
"""

from __future__ import annotations

from dataclasses import dataclass, field
from app.models.spatial import SpatialVector, RelationType


@dataclass
class TemplateField:
    """
    Champ f_i = (nom, type, S_kv, méthode, paramètres)
    Définition 4.8 du mémoire.
    """
    field_name: str
    field_label: str
    field_type: str              # text, date, amount, iban, email, phone, etc.
    key_text: str                # Texte de la clé (ancre textuelle)
    delta_x: float = 0.0
    delta_y: float = 0.0
    theta: float = 0.0
    distance: float = 0.0
    relation_type: str = "right_of"
    extraction_method: str = "spatial"
    required: bool = True

    @property
    def spatial_vector(self) -> SpatialVector:
        return SpatialVector(
            delta_x=self.delta_x,
            delta_y=self.delta_y,
            theta=self.theta,
            distance=self.distance,
            relation_type=RelationType(self.relation_type),
        )


@dataclass
class Template:
    """
    Template T = <τ, Φ, M, Ψ>
    Définition 4.7 du mémoire.
    τ = doc_type, Φ = fields, M implicite dans les champs, Ψ = métadonnées.
    """
    id: int
    label: str
    doc_type: str
    fields: list[TemplateField] = field(default_factory=list)
    version: int = 1
    active: bool = True

    @property
    def key_texts(self) -> list[str]:
        """Ensemble des textes de clés du template."""
        return [f.key_text for f in self.fields]
