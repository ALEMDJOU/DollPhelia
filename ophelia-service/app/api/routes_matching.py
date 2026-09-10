"""
Endpoints d'appariement document ↔ template.
Implémente l'Algorithme 1 du mémoire (section 4.1.6).
"""

from fastapi import APIRouter, HTTPException

from app.models.schemas import MatchingRequest, MatchingResponse
from app.services.matching_service import match_document_to_template

router = APIRouter()


@router.post("/match", response_model=MatchingResponse)
async def match_template(request: MatchingRequest):
    """
    Évalue le score F(D, Tj) pour chaque template candidat
    et retourne le meilleur (Définition 4.6).
    """
    if not request.elements:
        raise HTTPException(status_code=400, detail="Aucun élément textuel fourni.")
    if not request.templates:
        raise HTTPException(status_code=400, detail="Aucun template candidat fourni.")

    try:
        result = match_document_to_template(
            doc_type=request.doc_type,
            elements=request.elements,
            templates=request.templates,
            threshold=request.threshold,
        )
        return result

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur d'appariement : {str(e)}")
