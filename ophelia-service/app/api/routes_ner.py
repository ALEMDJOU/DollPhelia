"""
Endpoint de reconnaissance d'entités nommées (NER).
Scan libre d'un document, sans template : utile pour l'inspection
manuelle et comme filet de sécurité de l'orchestrateur.
"""

from fastapi import APIRouter, HTTPException

from app.models.schemas import NerRequest, NerResponse
from app.services.ner_service import detect_entities

router = APIRouter()


@router.post("/extract", response_model=NerResponse)
async def extract_entities(request: NerRequest):
    """
    Détecte les entités nommées (dates, montants, IBAN, emails,
    téléphones, pourcentages, SIRET, personnes, organisations) dans une
    liste d'éléments OCR déjà extraits (voir /api/v1/ocr/extract).
    """
    try:
        entities = detect_entities(request.elements)
        return NerResponse(entities=entities)
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur NER : {str(e)}")
