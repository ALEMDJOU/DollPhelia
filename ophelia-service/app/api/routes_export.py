"""
Endpoints d'exportation des résultats validés.
Génère les fichiers JSON et XML téléchargeables.
"""

from fastapi import APIRouter, HTTPException
from fastapi.responses import Response

from app.models.schemas import ExtractionResponse
from app.services.export_service import export_to_json, export_to_xml

router = APIRouter()


class ExportRequest(ExtractionResponse):
    """Requête d'export = résultat d'extraction + référence du document."""
    document_ref: str = ""


@router.post("/json")
async def export_json(request: ExportRequest):
    """
    Exporte un résultat d'extraction au format JSON.
    Retourne le fichier en téléchargement.
    """
    try:
        content = export_to_json(request, document_ref=request.document_ref)
        filename = f"ophelia_export_{request.document_ref or 'result'}.json"

        return Response(
            content=content,
            media_type="application/json",
            headers={"Content-Disposition": f'attachment; filename="{filename}"'},
        )

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur d'export JSON : {str(e)}")


@router.post("/xml")
async def export_xml(request: ExportRequest):
    """
    Exporte un résultat d'extraction au format XML.
    Retourne le fichier en téléchargement.
    """
    try:
        content = export_to_xml(request, document_ref=request.document_ref)
        filename = f"ophelia_export_{request.document_ref or 'result'}.xml"

        return Response(
            content=content,
            media_type="application/xml",
            headers={"Content-Disposition": f'attachment; filename="{filename}"'},
        )

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Erreur d'export XML : {str(e)}")


@router.post("/preview/json")
async def preview_json(request: ExportRequest):
    """
    Prévisualise l'export JSON sans déclencher le téléchargement.
    Utile côté Dolibarr pour afficher un aperçu avant export.
    """
    try:
        content = export_to_json(request, document_ref=request.document_ref)
        return {"format": "json", "content": content}

    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.post("/preview/xml")
async def preview_xml(request: ExportRequest):
    """
    Prévisualise l'export XML sans déclencher le téléchargement.
    """
    try:
        content = export_to_xml(request, document_ref=request.document_ref)
        return {"format": "xml", "content": content}

    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))
