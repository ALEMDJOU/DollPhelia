"""
Point d'entrée du microservice Ophélia.
Lance FastAPI et monte les routeurs.
"""

from contextlib import asynccontextmanager

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware

from app.config import settings
from app.api.routes_health import router as health_router
from app.api.routes_ocr import router as ocr_router
from app.api.routes_matching import router as matching_router
from app.api.routes_extraction import router as extraction_router
from app.api.routes_export import router as export_router


# ── Chargement du modèle au démarrage (une seule fois) ─────────
@asynccontextmanager
async def lifespan(application: FastAPI):
    """Charge les ressources lourdes au démarrage, les libère à l'arrêt."""
    from app.ml.layoutlm_loader import load_model
    load_model()
    yield
    # Nettoyage si nécessaire


app = FastAPI(
    title="Ophélia Service",
    description="Microservice d'extraction documentaire pour le module Ophélia (Dolibarr)",
    version="1.0.0",
    lifespan=lifespan,
)

# ── CORS (autorise les appels depuis Dolibarr) ─────────────────
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# ── Montage des routeurs ───────────────────────────────────────
app.include_router(health_router, prefix="/api/v1", tags=["health"])
app.include_router(ocr_router, prefix="/api/v1/ocr", tags=["ocr"])
app.include_router(matching_router, prefix="/api/v1/matching", tags=["matching"])
app.include_router(extraction_router, prefix="/api/v1/extraction", tags=["extraction"])
app.include_router(export_router, prefix="/api/v1/export", tags=["export"])
