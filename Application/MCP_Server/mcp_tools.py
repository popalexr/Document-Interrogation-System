from lib.payloads import *

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

        # Placeholder logic for querying a document
        if document_id == "doc123" and question:
            answer = f"The answer to your question '{question}' is 42."
        else:
            answer = "Document not found or invalid question."

        return {"document_id": document_id, "question": question, "answer": answer}