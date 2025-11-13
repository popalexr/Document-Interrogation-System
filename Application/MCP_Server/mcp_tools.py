from lib.payloads import *
from lib.openai import OpenAIClient

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

        document_path = f"README.md"

        vector_store = OpenAIClient().get_client().vector_stores.create(
            name="project-docs"
        )

        OpenAIClient().get_client().vector_stores.files.upload_and_poll(
            vector_store_id=vector_store.id,
            file=open(document_path, "rb")
        )

        response = OpenAIClient().get_client().responses.create(
            model="gpt-4o",
            tools=[
                {
                    "type": "file_search",
                    "vector_store_ids": [vector_store.id]
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
                        {
                            "type": "input_text",
                            "text": question
                        }
                    ]
                }
            ]
        )

        answer = response.output_text

        return {"document_id": document_id, "question": question, "answer": answer}