"""
Service d'exportation des résultats d'extraction.
Génère les fichiers JSON et XML téléchargeables.
"""

from __future__ import annotations

import json
from datetime import datetime

from app.models.schemas import ExtractionResponse
from app.utils.xml_builder import build_export_xml


def export_to_json(
    result: ExtractionResponse,
    document_ref: str = "",
) -> str:
    """
    Génère le contenu JSON de l'export.
    """
    export_data = _build_export_dict(result, document_ref)
    return json.dumps(export_data, ensure_ascii=False, indent=2)


def export_to_xml(
    result: ExtractionResponse,
    document_ref: str = "",
) -> str:
    """
    Génère le contenu XML de l'export.
    """
    export_data = _build_export_dict(result, document_ref)
    return build_export_xml(export_data)


def _build_export_dict(
    result: ExtractionResponse,
    document_ref: str,
) -> dict:
    """
    Structure commune des données exportées.
    """
    return {
        "ophelia_export": {
            "version": "1.0",
            "generated_at": datetime.now().isoformat(),
            "document_ref": document_ref,
            "strategy": result.strategy,
            "template": {
                "id": result.template_id,
                "label": result.template_label,
            }
            if result.template_id
            else None,
            "matching_score": result.matching_score,
            "global_confidence": result.global_confidence,
            "fields": [
                {
                    "name": f.field_name,
                    "label": f.field_label,
                    "value": f.extracted_value,
                    "confidence": f.confidence_total,
                    "page": f.page_num,
                }
                for f in result.fields
                if f.extracted_value is not None
            ],
        }
    }
