"""
Endpoints d'extraction de données et de traitement complet.
"""

from fastapi import APIRouter, HTTPException

from app.models.schemas import (
    ExtractionRequest,
    ExtractionResponse,
    ProcessRequest,
    ProcessStartResponse,
    ProcessStatusResponse,
)
from app.services.extraction_service import extract_fields
from app.services.orchestrator import process_document_sync
from app.tasks.celery_tasks import process_document_task

router = APIRouter()


@router.post("/extract", response_model=ExtractionResponse)
async def extract_data(request: ExtractionRequest):
    """
    Extraction des champs à partir des éléments OCR et d'un template.
    Implémente l'Algorithme 2 du mémoire (extraction ancrée).
    """
    try:
        result = extract_fields(
            elements=request.elements,
            template=request.template,
            strategy=request.strategy,
        )
        return result

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur d'extraction : {str(e)}")


@router.post("/process/start", response_model=ProcessStartResponse)
async def start_processing(request: ProcessRequest):
    """
    Lance le traitement complet d'un document en tâche asynchrone.
    Retourne un task_id que PHP peut interroger via /process/status.
    """
    try:
        task = process_document_task.delay(
            filepath=request.filepath,
            templates=[t.model_dump() for t in request.templates],
            lang=request.lang,
        )
        return ProcessStartResponse(task_id=task.id)

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur de lancement : {str(e)}")


@router.get("/process/status/{task_id}", response_model=ProcessStatusResponse)
async def get_processing_status(task_id: str):
    """
    Interroge le statut d'une tâche Celery en cours.
    """
    from app.tasks.celery_tasks import celery_app

    task = celery_app.AsyncResult(task_id)

    if task.state == "PENDING":
        return ProcessStatusResponse(task_id=task_id, status="pending", progress=0)
    elif task.state == "STARTED":
        info = task.info or {}
        return ProcessStatusResponse(
            task_id=task_id,
            status="processing",
            progress=info.get("progress", 0),
            message=info.get("message", ""),
        )
    elif task.state == "SUCCESS":
        return ProcessStatusResponse(
            task_id=task_id,
            status="completed",
            progress=100,
            result=task.result,
        )
    elif task.state == "FAILURE":
        return ProcessStatusResponse(
            task_id=task_id,
            status="failed",
            progress=0,
            message=str(task.info),
        )
    else:
        return ProcessStatusResponse(task_id=task_id, status=task.state, progress=0)


@router.post("/process/sync", response_model=ExtractionResponse)
async def process_sync(request: ProcessRequest):
    """
    Traitement complet synchrone (utile pour le debug et les tests).
    """
    try:
        result = process_document_sync(
            filepath=request.filepath,
            templates=request.templates,
            lang=request.lang,
        )
        return result

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur de traitement : {str(e)}")
