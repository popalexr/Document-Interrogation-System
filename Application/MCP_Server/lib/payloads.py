from pydantic import BaseModel
from typing import Any, Dict, Optional, List

class QueryPayload(BaseModel):
    document_id: str
    user_id: str
    question: str
    extra: Optional[Dict[str, Any]] = None


class AIInterrogationPayload(BaseModel):
    documents_ids: List[str]
    user_id: str
    question: str
    extra: Optional[Dict[str, Any]] = None


class EditPayload(BaseModel):
    document_id: str
    user_id: str
    prompt: str
    extra: Optional[Dict[str, Any]] = None


class NameChatPayload(BaseModel):
    query: str
    extra: Optional[Dict[str, Any]] = None


class GenericPayload(BaseModel):
    action: str
    data: Optional[Dict[str, Any]] = None


class VectorizePayload(BaseModel):
    document_id: str
    user_id: str
    extra: Optional[Dict[str, Any]] = None

class VectorSearchPayload(BaseModel):
    user_id: str
    query: str
    max_num_results: int = 10
    extra: Optional[Dict[str, Any]] = None

class DeleteDocumentPayload(BaseModel):
    document_id: str
    user_id: str
    extra: Optional[Dict[str, Any]] = None
