"""
Vecteur de relation spatiale S_kv.
Implémente les Définitions 4.4 et 4.5 du mémoire.
"""

from __future__ import annotations

import math
from dataclasses import dataclass
from enum import Enum


class RelationType(str, Enum):
    """Type de relation spatiale (Définition 4.5)."""
    RIGHT_OF = "right_of"
    LEFT_OF = "left_of"
    BELOW = "below"
    ABOVE = "above"
    OVERLAPPING = "overlapping"


@dataclass
class SpatialVector:
    """
    Vecteur S_kv = (Δx, Δy, θ, d, r_type)
    Définition 4.4 du mémoire.
    """
    delta_x: float
    delta_y: float
    theta: float
    distance: float
    relation_type: RelationType

    @classmethod
    def from_key_value(
        cls,
        key_cx: float, key_cy: float,
        val_cx: float, val_cy: float,
        epsilon: float = 5.0,
    ) -> SpatialVector:
        """
        Construit le vecteur spatial à partir des centres de la clé et de la valeur.

        Paramètres
        ----------
        key_cx, key_cy : centre de la boîte englobante de la clé
        val_cx, val_cy : centre de la boîte englobante de la valeur
        epsilon        : seuil de tolérance pour le type 'overlapping' (pixels)
        """
        dx = val_cx - key_cx
        dy = val_cy - key_cy

        # Angle θ (Définition 4.4)
        if dx != 0:
            theta = math.atan2(dy, dx)
        elif dy > 0:
            theta = math.pi / 2
        elif dy < 0:
            theta = -math.pi / 2
        else:
            theta = 0.0

        # Distance euclidienne
        d = math.sqrt(dx ** 2 + dy ** 2)

        # Type de relation (Définition 4.5)
        if abs(dx) <= epsilon and abs(dy) <= epsilon:
            r_type = RelationType.OVERLAPPING
        elif abs(dx) > abs(dy):
            r_type = RelationType.RIGHT_OF if dx > 0 else RelationType.LEFT_OF
        else:
            r_type = RelationType.BELOW if dy > 0 else RelationType.ABOVE

        return cls(
            delta_x=dx,
            delta_y=dy,
            theta=theta,
            distance=d,
            relation_type=r_type,
        )

    def to_dict(self) -> dict:
        return {
            "delta_x": self.delta_x,
            "delta_y": self.delta_y,
            "theta": self.theta,
            "distance": self.distance,
            "relation_type": self.relation_type.value,
        }
