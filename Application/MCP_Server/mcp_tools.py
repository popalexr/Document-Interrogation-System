import mimetypes
import os
import json
from datetime import datetime

from mcp.server.fastmcp import Context

from lib.payloads import *
from lib.vector_stores import *
from lib.openai import OpenAIClient
from lib.r2_storage import get_r2_stream, save_to_r2
from lib.interrogation import stream_interrogation
from lib.all_docs_interrogation import stream_ai_interrogation
from lib.edit_file import generate_editing_prompt, generate_editing_code, execute_code_in_docker
from lib.title_generation import generate_chat_title

from lib.documents import uploads, edits

mcp = None

def initialize_mcp(mcp_instance):
    global mcp
    mcp = mcp_instance

    # Initialize document models
    uploads.init()
    edits.init()

    @mcp.tool()
    def ping(payload: dict | None = None) -> dict:
        """
        A simple tool that responds with 'pong' to test connectivity.
        """

        return {"status": "ok"}
    
    @mcp.tool()
    async def query(payload: QueryPayload, ctx: Context) -> dict:
        """
        Query a document by its ID with a specific question.
        Payload must contain 'document_id', 'user_id' and 'question' keys.
        """

        answer = ""
        progress = 0

        for event in stream_interrogation(payload):
            if event.get("type") == "chunk":
                progress += 1
                answer += event.get("delta", "")
                await ctx.report_progress(progress, message=json.dumps(event))
            elif event.get("type") == "done":
                answer = event.get("answer", answer)

        return {"answer": answer}

    @mcp.tool()
    async def ai_interrogation(payload: AIInterrogationPayload, ctx: Context) -> dict:
        """
        Query one or more documents with a specific question.
        Payload must contain 'documents_ids', 'user_id' and 'question' keys.
        """

        answer = ""
        citations = []
        progress = 0

        for event in stream_ai_interrogation(payload):
            if event.get("type") == "chunk":
                progress += 1
                answer += event.get("delta", "")
                await ctx.report_progress(progress, message=json.dumps(event))
            elif event.get("type") == "done":
                answer = event.get("answer", answer)
                citations = event.get("citations", citations)

        return {"answer": answer, "citations": citations}

    @mcp.tool()
    def edit_prompt(payload: EditPayload) -> dict:
        """
        Generate an editing prompt based on the user's instructions.
        Payload must contain 'document_id', 'user_id' and 'prompt' keys.
        """

        edit_prompt = generate_editing_prompt(payload)

        return edit_prompt
    
    @mcp.tool()
    def edit_code(payload: EditPayload) -> dict:
        """
        Generate editing code based on the user's instructions.
        Payload must contain 'document_id', 'user_id' and 'prompt' keys.
        """

        editing_code = generate_editing_code(payload)

        return editing_code
    
    @mcp.tool()
    def execute_code_and_save(payload: dict) -> dict:
        """
        Execute the generated code in a Docker container, save and return the document_id.
        Payload must contain 'code', 'requirements', 'document_id', 'output_file', 'packages', and 'user_id' keys.
        """

        document = uploads.get_document(payload["document_id"])
        stream = get_r2_stream(document["r2_key"])

        file_bytes = stream.read()
        stream.close()

        code_payload = {
            "code": payload["code"],
            "requirements": payload["requirements"],
            "file": file_bytes,
            "filename": document["original_name"],
            "output_file": payload["output_file"],
            "packages": payload["packages"],
        }

        output = execute_code_in_docker(code_payload)

        if output == file_bytes:
            raise RuntimeError("Edit produced no changes. The generated script could not apply the requested modifications.")

        stored_file_name = _resolve_saved_document_name(
            payload.get("prompt_output_file"),
            payload.get("output_file"),
            document["original_name"],
        )
        storage_file_name = "edited_" + datetime.utcnow().isoformat() + "_" + stored_file_name
        new_key = save_to_r2(output, storage_file_name)

        mime_type = _resolve_edited_mime_type(
            payload.get("output_file"),
            document.get("mime_type"),
        )

        document_id = edits.store_document(
            payload["document_id"],
            payload["user_id"],
            stored_file_name,
            new_key,
            mime_type,
        )

        return {"document_id": document_id}

    @mcp.tool()
    def name_chat(payload: NameChatPayload) -> dict:
        """
        Generate a name for a chat based on the user's ID, the document ID and the query.
        Payload must contain 'query' key.
        """
        title = generate_chat_title(payload)

        return {"title": title}

    @mcp.tool()
    def vectorize(payload: VectorizePayload) -> dict:
        """
        Vectorize a document by its ID for future querying.
        Payload must contain 'document_id' key.
        """

        document = uploads.get_document(payload.document_id)
        document_path = document["r2_key"]

        stream = get_r2_stream(document_path)

        client = OpenAIClient().get_client()

        uploaded_file = client.files.create(
            file=(document['original_name'], stream),
            purpose="assistants",
        )
        
        vector_store = find_vector_store_by_name("documents-" + payload.user_id)

        if vector_store is None:
            vector_store = client.vector_stores.create(
                name="documents-" + payload.user_id,
            )

        if get_file_from_vector_store(vector_store.id, uploaded_file.id) is not None:
            return {"status": "already_vectorized"}

        client.vector_stores.files.create_and_poll(
            vector_store_id=vector_store.id,
            file_id=uploaded_file.id,
            attributes={ "document_id": str(payload.document_id) }
        )

        return {"status": "vectorization_complete", "vector_store_id": vector_store.id, "vector_file_id": uploaded_file.id}
    
    @mcp.tool()
    def delete_document(payload: DeleteDocumentPayload) -> dict:
        """
        Delete a document by its ID from the vector store.
        """
        client = OpenAIClient().get_client()

        vector_store = find_vector_store_by_name("documents-" + payload.user_id)

        if vector_store is None:
            return {"status": "no_vector_store_found"}

        files = client.vector_stores.files.list(vector_store.id)

        for file in files.data:
            if file.metadata.get("document_id") == str(payload.document_id):
                client.vector_stores.files.delete(
                    vector_store_id=vector_store.id,
                    file_id=file.id
                )

        return {
            "status": "done",
        }

def _resolve_edited_mime_type(output_file: str | None, fallback_mime_type: str | None) -> str:
    """
    Resolve the edited file MIME type from the generated output filename.
    """

    guessed_mime_type, _ = mimetypes.guess_type(os.path.basename(output_file or ""))

    return guessed_mime_type or fallback_mime_type or "application/octet-stream"


def _resolve_saved_document_name(
    prompt_output_file: str | None,
    generated_output_file: str | None,
    fallback_name: str,
) -> str:
    """
    Resolve the filename persisted in the database for the edited document.
    """

    for candidate in (prompt_output_file, generated_output_file, fallback_name):
        base_name = os.path.basename((candidate or "").strip())
        if base_name:
            return base_name

    return fallback_name
