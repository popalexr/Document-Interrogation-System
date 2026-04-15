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

@app.post("/edit", tags=["Edit"])
async def edit(payload: EditPayload) -> dict[str, Any]:
    """
    Edit a document by its ID with new content.
    """

    async def event_source():
        try:
            edit_prompt = await mcp_state.call_tool("edit_prompt", {"payload": payload.model_dump()})
            yield f"data: {json.dumps({'type': 'edit_prompt', 'message': edit_prompt})}\n\n"
            edit_prompt_output_file = _extract_edit_prompt_output_file(edit_prompt)

            edit_payload = EditPayload(
                document_id=payload.document_id,
                user_id=payload.user_id,
                prompt=edit_prompt["prompt"],
            )

            edit_code = await mcp_state.call_tool("edit_code", {"payload": edit_payload.model_dump()})
            yield f"data: {json.dumps({'type': 'edit_code', 'status': 'ok', 'message': edit_code})}\n\n"

            edit_payload = {
                "code": edit_code["code"],
                "requirements": edit_code["requirements"],
                "document_id": payload.document_id,
                "output_file": edit_code["output_file"],
                "packages": edit_code["packages"],
                "prompt_output_file": edit_prompt_output_file,
                "user_id": payload.user_id,
            }

            try:
                execution_result = await mcp_state.call_tool("execute_code_and_save", {"payload": edit_payload})
                edited_document_id = execution_result.get("document_id") if isinstance(execution_result, dict) else None
                if edited_document_id is None:
                    message = execution_result.get("result") if isinstance(execution_result, dict) else None
                    raise RuntimeError(message or "Docker execution failed without returning a document_id.")

                edited_document_id = str(edited_document_id)

                yield f"data: {json.dumps({'type': 'execution_result', 'status': 'ok', 'document_id': edited_document_id})}\n\n"

                yield f"data: {json.dumps({'type': 'final_message', 'status': 'ok', 'message': 'Done!'})}\n\n"
            except Exception as ex:
                error_message = str(ex)
                yield f"data: {json.dumps({'type': 'execution_result', 'status': 'error', 'message': error_message})}\n\n"
                yield f"data: {json.dumps({'type': 'final_message', 'status': 'error', 'message': error_message})}\n\n"

        except Exception as exc:
            error_event = {"type": "error", "message": str(exc)}
            yield f"data: {json.dumps(error_event)}\n\n"

    headers = {
        "Cache-Control": "no-cache",
        "X-Accel-Buffering": "no",
    }

    return StreamingResponse(event_source(), media_type="text/event-stream", headers=headers)

@app.post("/generate_title", tags=["Generate Title"])
async def generate_title(payload: NameChatPayload) -> dict[str, Any]:
    """
    Generate a title for a chat based on the user's ID, the document ID and the query.
    """

    return await mcp_state.call_tool("name_chat", {"payload": payload.model_dump()})

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

def _extract_edit_prompt_output_file(edit_prompt: dict[str, Any]) -> str | None:
    """
    Extract the planned output filename from the edit_prompt tool response.
    """

    prompt = edit_prompt.get("prompt")

    if isinstance(prompt, dict):
        output_file = prompt.get("output_file")
        
        return output_file if isinstance(output_file, str) and output_file.strip() else None

    if not isinstance(prompt, str):
        return None

    try:
        parsed_prompt = json.loads(prompt)
    except json.JSONDecodeError:
        return None

    output_file = parsed_prompt.get("output_file")

    return output_file if isinstance(output_file, str) and output_file.strip() else None