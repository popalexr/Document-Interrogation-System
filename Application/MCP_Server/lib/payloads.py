from pydantic import BaseModel
from typing import Any, Dict, Optional

class QueryPayload(BaseModel):
    document_id: Optional[str] = None
    question: Optional[str] = None
    extra: Optional[Dict[str, Any]] = None


class EditPayload(BaseModel):
    document_id: Optional[str] = None
    ops: Optional[list] = None
    extra: Optional[Dict[str, Any]] = None


class GenericPayload(BaseModel):
    action: str
    data: Optional[Dict[str, Any]] = None