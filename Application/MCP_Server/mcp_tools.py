from bson import ObjectId

from lib.payloads import *
from lib.openai import OpenAIClient
from lib.r2_storage import get_r2_stream
from lib.mongo import MongoDBClient

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
        Payload must contain 'document_id' and 'question' keys.
        """

        document_id = payload.document_id
        question = payload.question

        document_path = __get_document_path(document_id)

        stream = get_r2_stream(document_path)

        client = OpenAIClient().get_client()

        uploaded_file = client.files.create(
            file=(document_path, stream),
            purpose="assistants",
        )

        vector_store = client.vector_stores.create(
            name="project-docs",
        )

        client.vector_stores.files.create_and_poll(
            vector_store_id=vector_store.id,
            file_id=uploaded_file.id,
        )

        response = client.responses.create(
            model="gpt-4o",
            tools=[
                {
                    "type": "file_search",
                    "vector_store_ids": [vector_store.id],
                }
            ],
            instructions=(
                "Use the provided documents to answer the user's question as accurately as possible. "
                "If the information is not available in the documents, respond with 'I don't know.'"
            ),
            input=[
                {
                    "role": "user",
                    "content": [
                        {"type": "input_text", "text": question}
                    ],
                }
            ],
        )

        answer = response.output_text
        return {"answer": answer}

def __get_document_path(document_id: str) -> str:
    """
    Retrieve the R2 key of a document stored in R2 by its ID.
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
    
    if "r2_key" not in document_record:
        raise ValueError(f"Document {document_id} has no 'r2_key' field.")

    r2_key = document_record["r2_key"]
    return r2_key