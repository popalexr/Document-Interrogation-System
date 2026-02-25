import json

from lib.openai import OpenAIClient
from lib.vector_stores import find_vector_store_by_name
from lib.filecite_sanitizer import FileciteSanitizer
from lib.payloads import EditPayload
from lib.system_prompts import EDIT_SYS_PROMPT_GENERATE_EDITING_PROMPT
from lib.system_prompts import EDIT_SYS_PROMPT_GENERATE_EDITING_CODE

def generate_editing_prompt(payload: EditPayload) -> dict:
    """
    Generate an editing prompt based on the user's instructions.
    """

    client = OpenAIClient().get_client()
    vector_store = find_vector_store_by_name("documents-" + payload.user_id)

    if vector_store is None:
        raise ValueError("Vector store for this user not found.")
    
    response = client.responses.create(
        model="gpt-5-nano",
        tools=[
            {
                "type": "file_search",
                "vector_store_ids": [vector_store.id],
                "filters": {
                    "type": "eq",
                    "key": "document_id",
                    "value": str(payload.document_id),
                },
            }
        ],
        instructions=EDIT_SYS_PROMPT_GENERATE_EDITING_PROMPT,
        input=[
            {
                "role": "user",
                "content": payload.prompt,
            }
        ],
    )

    sanitizer = FileciteSanitizer()

    final_response = sanitizer.sanitize(response.output_text)

    return {"prompt": final_response}

def generate_editing_code(payload: EditPayload) -> dict:
    """
    Generate editing code based on the user's instructions.
    """

    client = OpenAIClient().get_client()
    vector_store = find_vector_store_by_name("documents-" + payload.user_id)

    if vector_store is None:
        raise ValueError("Vector store for this user not found.")
    
    response = client.responses.create(
        model="gpt-5-nano",
        tools=[
            {
                "type": "file_search",
                "vector_store_ids": [vector_store.id],
                "filters": {
                    "type": "eq",
                    "key": "document_id",
                    "value": str(payload.document_id),
                },
            }
        ],
        instructions=EDIT_SYS_PROMPT_GENERATE_EDITING_CODE,
        input=[
            {
                "role": "user",
                "content": payload.prompt,
            }
        ],
    )

    # sanitizer = FileciteSanitizer()

    # final_response = sanitizer.sanitize(response.output_text)

    final_response = json.loads(response.output_text)

    return final_response