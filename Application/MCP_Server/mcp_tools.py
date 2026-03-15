from bson import ObjectId

from lib.payloads import *
from lib.vector_stores import *
from lib.openai import OpenAIClient
from lib.r2_storage import get_r2_stream
from lib.mongo import MongoDBClient
from lib.interrogation import collect_interrogation_answer
from lib.edit_file import generate_editing_prompt, generate_editing_code, execute_code_in_docker
from lib.title_generation import generate_chat_title

mcp = None

def initialize_mcp(mcp_instance):
    global mcp
    mcp = mcp_instance

    @mcp.tool()
    def ping(payload: dict | None = None) -> dict:
        """
        A simple tool that responds with 'pong' to test connectivity.
        """

        return {"status": "ok"}
    
    @mcp.tool()
    def query(payload: QueryPayload) -> dict:
        """
        Query a document by its ID with a specific question.
        Payload must contain 'document_id', 'user_id' and 'question' keys.
        """

        answer = collect_interrogation_answer(payload)
        return {"answer": answer}

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
    def execute_code(payload: dict) -> dict:
        """
        Execute the generated code in a Docker container and return the output.
        Payload keys: "code": str, "requirements": str, "output_file": str, "file_id": str
        """

        document = __get_document(payload["document_id"])
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

        return {"output": output}
    
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

        document = __get_document(payload.document_id)
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


def __get_document(document_id: str) -> dict:
    """
    Retrieve the document record from the database by its ID.
    """
    mongodb_client = MongoDBClient()
    
    # Get the "uploads" collection via your helper
    uploads_collection = mongodb_client.get_collection("uploads")
    
    # Look up the document by _id
    try:
        oid = ObjectId(document_id)
    except Exception:
        raise ValueError(f"Invalid document ID: {document_id!r}")
    
    document_record = uploads_collection.find_one({"_id": oid})

    if not document_record:
        raise ValueError(f"Document with ID {document_id} not found.")
    
    return document_record
