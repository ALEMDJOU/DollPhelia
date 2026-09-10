"""
Endpoint de vérification de l'état du service.
"""

from fastapi import APIRouter

from app.ml.layoutlm_loader import is_model_loaded

router = APIRouter()


@router.get("/health")
async def health_check():
    return {
        "status": "ok",
        "model_loaded": is_model_loaded(),
        "service": "ophelia-service",
        "version": "1.0.0",
    }
