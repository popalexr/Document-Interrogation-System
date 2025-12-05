from __future__ import annotations

import contextlib
import json
import os

import dotenv
from fastapi import FastAPI
from fastapi.responses import StreamingResponse
from lib.interrogation import stream_interrogation
from lib.mcp_state import MCPState

from lib.payloads import *
from lib.openai import OpenAIClient
from lib.mongo import MongoDBClient

dotenv.load_dotenv()

mcp_state = MCPState()

@contextlib.asynccontextmanager
async def FastAPI_lifespan(app: FastAPI):
    # Startup: Start MCP client session
    await mcp_state.start()
    try:
        yield
    finally:
        # Shutdown: Stop MCP client session
        await mcp_state.stop()

app = FastAPI(title=os.getenv('HTTP_NAME'), version=os.getenv('HTTP_VERSION'), lifespan=FastAPI_lifespan)

OpenAIClient(api_key=os.getenv("OPENAI_API_KEY")) # Initialize OpenAI client singleton
MongoDBClient(os.getenv("MONGODB_URI"), os.getenv("MONGODB_DB")) # Initialize MongoDB client singleton

"""
REST API Endpoints
"""
@app.get("/health", tags=["Health"])
async def health() -> dict[str, Any]:
    """
    API health check endpoint.
    """

    return {"status": "ok"}

@app.get("/ping", tags=["Ping"])
async def ping() -> dict[str, Any]:
    """
    Simple ping endpoint to verify server responsiveness.
    """

    return await mcp_state.call_tool("ping", {})

@app.post("/query", tags=["Query"])
async def query(payload: QueryPayload):
    """
    Query a document by its ID with a specific question, streaming tokens as they arrive.
    """

    def event_source():
        try:
            for event in stream_interrogation(payload):
                yield f"data: {json.dumps(event)}\n\n"
        except Exception as exc:
            error_event = {"type": "error", "message": str(exc)}
            yield f"data: {json.dumps(error_event)}\n\n"

    headers = {
        "Cache-Control": "no-cache",
        "X-Accel-Buffering": "no",
    }

    return StreamingResponse(event_source(), media_type="text/event-stream", headers=headers)

@app.post("/vectorize", tags=["Vectorize"])
async def vectorize(payload: VectorizePayload) -> dict[str, Any]:
    """
    Vectorize a document by its ID.
    """

    return await mcp_state.call_tool("vectorize", {"payload": payload.model_dump()})

@app.delete("/delete_document", tags=["Delete Document"])
async def delete_document(payload: DeleteDocumentPayload) -> dict[str, Any]:
    """
    Delete a document by its ID.
    """

    return await mcp_state.call_tool("delete_document", {"payload": payload.model_dump()})

if __name__ == "__main__":
    import uvicorn

    uvicorn.run(
        "mcp_client:app",
        host=os.getenv("HTTP_HOST", "localhost"),
        port=int(os.getenv("HTTP_PORT", 8888)),
        reload=True,
    )
