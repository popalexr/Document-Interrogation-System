from bson import ObjectId

from lib.payloads import *
from lib.vector_stores import *
from lib.system_prompts import *
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
        Payload must contain 'document_id', 'user_id' and 'question' keys.
        """

        document_id = payload.document_id
        question = payload.question

        client = OpenAIClient().get_client()

        vector_store = find_vector_store_by_name("documents-" + payload.user_id)

        if vector_store is None:
            raise ValueError(f"Vector store for this user not found.")

        response = client.responses.create(
            model="gpt-5-nano",
            tools=[
                {
                    "type": "file_search",
                    "vector_store_ids": [vector_store.id],
                    "filters": {
                        "type": "eq",
                        "key": "document_id",
                        "value": str(document_id),
                    },
                }
            ],
            instructions=QUERY_SYS_PROMPT,
            input=[
                {
                    "role": "user",
                    "content": [
                        {"type": "input_text", "text": question}
                    ],
                }
            ],
        )

        answer_parts = []
        for out in response.output or []:
            for block in getattr(out, "content", []) or []:
                text = getattr(block, "text", None)
                if text:
                    answer_parts.append(text)

        answer = "".join(answer_parts).strip()
        return {"answer": answer}
    
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