from __future__ import annotations

import dotenv
import os
import contextlib

from typing import Any

from fastapi import FastAPI
from lib.mcp_state import MCPState

from lib.payloads import *

dotenv.load_dotenv()

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

mcp_state = MCPState()

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
async def query(payload: QueryPayload) -> dict[str, Any]:
    """
    Query a document by its ID with a specific question.
    """

    return await mcp_state.call_tool("query", {"payload": payload.model_dump()})

if __name__ == "__main__":
    import uvicorn

    uvicorn.run(
        "mcp_client:app",
        host=os.getenv("HTTP_HOST", "localhost"),
        port=int(os.getenv("HTTP_PORT", 8888)),
        reload=True,
    )