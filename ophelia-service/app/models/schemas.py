"""
Schémas Pydantic — validation des requêtes et réponses de l'API.
"""

from __future__ import annotations

from pydantic import BaseModel, Field
from typing import Any


# ── Éléments OCR ───────────────────────────────────────────────

class BBoxSchema(BaseModel):
    x1: int
    y1: int
    x2: int
    y2: int


class TextElementSchema(BaseModel):
    text: str
    bbox: BBoxSchema
    confidence: float = Field(ge=0.0, le=1.0)
    page_num: int = 1


# ── Templates ──────────────────────────────────────────────────

class TemplateFieldSchema(BaseModel):
    field_name: str
    field_label: str
    field_type: str
    key_text: str
    delta_x: float = 0.0
    delta_y: float = 0.0
    theta: float = 0.0
    distance: float = 0.0
    relation_type: str = "right_of"
    extraction_method: str = "spatial"
    required: bool = True


class TemplateSchema(BaseModel):
    id: int
    label: str
    doc_type: str
    fields: list[TemplateFieldSchema] = []
    version: int = 1


# ── Requêtes ───────────────────────────────────────────────────

class OcrRequest(BaseModel):
    filepath: str
    lang: str = "fra"


class MatchingRequest(BaseModel):
    doc_type: str | None = None
    elements: list[TextElementSchema]
    templates: list[TemplateSchema]
    threshold: float | None = None  # Utilise la config par défaut si None


class ExtractionRequest(BaseModel):
    elements: list[TextElementSchema]
    template: TemplateSchema
    strategy: str = "template_matching"  # template_matching | ai_layoutlm


class ProcessRequest(BaseModel):
    filepath: str
    templates: list[TemplateSchema] = []
    lang: str = "fra"


# ── Réponses ───────────────────────────────────────────────────

class OcrResponse(BaseModel):
    filepath: str
    num_pages: int
    elements: list[TextElementSchema]


class MatchingResultDetail(BaseModel):
    template_id: int
    template_label: str
    score: float = Field(ge=0.0, le=1.0)
    s1_type: float
    s2_keys: float
    s3_spatial: float
    s4_ocr: float


class MatchingResponse(BaseModel):
    matched: bool
    best_template_id: int | None = None
    best_template_label: str | None = None
    best_score: float = 0.0
    details: list[MatchingResultDetail] = []


class ExtractedFieldSchema(BaseModel):
    field_name: str
    field_label: str
    extracted_value: str | None = None
    confidence_ocr: float = 0.0
    confidence_spatial: float = 0.0
    confidence_validation: float = 0.0
    confidence_total: float = 0.0
    bbox: BBoxSchema | None = None
    page_num: int = 1


class ExtractionResponse(BaseModel):
    strategy: str
    template_id: int | None = None
    template_label: str | None = None
    matching_score: float = 0.0
    fields: list[ExtractedFieldSchema] = []
    global_confidence: float = 0.0


class ProcessStartResponse(BaseModel):
    task_id: str


class ProcessStatusResponse(BaseModel):
    task_id: str
    status: str  # pending | processing | completed | failed
    progress: int = 0
    message: str = ""
    result: Any | None = None
