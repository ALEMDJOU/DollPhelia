"""
Configuration centralisée du microservice Ophélia.
Les valeurs par défaut correspondent aux paramètres du mémoire (Tableau 4.1).
"""

from pydantic_settings import BaseSettings


class Settings(BaseSettings):
    # ── Tesseract OCR ──────────────────────────────────────────
    tesseract_cmd: str = r"C:\Program Files\Tesseract-OCR\tesseract.exe"
    tesseract_lang: str = "fra"

    # ── Modèle IA ──────────────────────────────────────────────
    model_path: str = "./models_cache/layoutlmv3"
    model_device: str = "cpu"

    # ── Seuils du modèle (Chapitre 4, section 4.1) ────────────
    matching_threshold: float = 0.7      # θ_min
    weight_type: float = 0.25            # w1 — concordance de type
    weight_keys: float = 0.25            # w2 — présence des clés
    weight_spatial: float = 0.25         # w3 — concordance spatiale
    weight_ocr: float = 0.25             # w4 — confiance OCR
    spatial_tolerance: float = 10.0      # σ_pos (pixels)
    adaptation_step: float = 0.1         # β

    # ── Redis / Celery ─────────────────────────────────────────
    redis_url: str = "redis://localhost:6379/0"

    # ── Serveur ────────────────────────────────────────────────
    api_host: str = "0.0.0.0"
    api_port: int = 8000

    # ── Dolibarr ───────────────────────────────────────────────
    dolibarr_documents_path: str = r"C:\wamp64\www\dolibarr\documents"

    @property
    def weights(self) -> list[float]:
        """Vecteur de pondération [w1, w2, w3, w4]."""
        return [self.weight_type, self.weight_keys, self.weight_spatial, self.weight_ocr]

    model_config = {
        "env_file": ".env",
        "env_file_encoding": "utf-8",
        "protected_namespaces": (),
    }


settings = Settings()
