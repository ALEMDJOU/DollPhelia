"""
Tâches Celery pour le traitement asynchrone des documents.
Le worker est lancé séparément du serveur FastAPI.
"""

from __future__ import annotations

from celery import Celery

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


@celery_app.task(bind=True, name="ophelia.process_document")
def process_document_task(self, filepath: str, templates: list[dict], lang: str = "fra"):
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
    )

    # Progression : Terminé
    self.update_state(
        state="STARTED",
        meta={"progress": 90, "message": "Finalisation..."},
    )

    return result.model_dump()
