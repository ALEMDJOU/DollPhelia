"""
Tâches Celery pour le traitement asynchrone des documents.
Le worker est lancé séparément du serveur FastAPI.
"""

from __future__ import annotations

from celery import Celery
from celery.signals import worker_process_init

from app.config import settings
from app.models.schemas import TemplateSchema

celery_app = Celery(
    "ophelia",
    broker=settings.redis_url,
    backend=settings.redis_url,
)

celery_app.conf.update(
    task_serializer="json",
    result_serializer="json",
    accept_content=["json"],
    task_track_started=True,
    result_expires=3600,            # Résultats conservés 1 heure
    worker_max_tasks_per_child=50,  # Redémarrage du worker pour libérer la RAM
)


@worker_process_init.connect
def _load_model_on_worker_start(**kwargs):
    """
    Le worker Celery tourne dans un processus séparé du serveur FastAPI :
    le singleton du modèle LayoutLMv3 (app/ml/layoutlm_loader.py) n'y est
    donc jamais initialisé par le lifespan de FastAPI, et la stratégie de
    repli IA échoue silencieusement (0 champ extrait) pour toute tâche
    traitée en asynchrone. On charge le modèle une fois par processus
    worker, au démarrage, de la même façon que le fait main.py pour uvicorn.
    """
    from app.ml.layoutlm_loader import load_model
    load_model()


@celery_app.task(bind=True, name="ophelia.process_document")
def process_document_task(self, filepath: str, templates: list[dict], lang: str = "fra", strategy: str = "auto"):
    """
    Tâche asynchrone de traitement complet d'un document.
    Met à jour le statut à chaque étape pour permettre le polling côté PHP.
    """
    from app.services.orchestrator import process_document_sync

    # Progression : OCR en cours
    self.update_state(
        state="STARTED",
        meta={"progress": 10, "message": "Prétraitement et OCR en cours..."},
    )

    # Reconstruire les objets TemplateSchema depuis les dicts
    template_objects = [TemplateSchema(**t) for t in templates]

    # Progression : Appariement
    self.update_state(
        state="STARTED",
        meta={"progress": 40, "message": "Appariement et extraction en cours..."},
    )

    # Exécution synchrone du pipeline complet
    result = process_document_sync(
        filepath=filepath,
        templates=template_objects,
        lang=lang,
        strategy=strategy,
    )

    # Progression : Terminé
    self.update_state(
        state="STARTED",
        meta={"progress": 90, "message": "Finalisation..."},
    )

    return result.model_dump()
