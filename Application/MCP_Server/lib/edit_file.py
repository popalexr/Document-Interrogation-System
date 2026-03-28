import json
import os

from lib.openai import OpenAIClient
from lib.vector_stores import find_vector_store_by_name
from lib.filecite_sanitizer import FileciteSanitizer
from lib.payloads import EditPayload
from lib.system_prompts import EDIT_SYS_PROMPT_GENERATE_EDITING_PROMPT
from lib.system_prompts import EDIT_SYS_PROMPT_GENERATE_EDITING_CODE
from lib.docker import run_container_with_files

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

    # sanitizer = FileciteSanitizer()

    # final_response = sanitizer.sanitize(response.output_text)

    return {"prompt": response.output_text}

def generate_editing_code(payload: EditPayload) -> dict:
    """
    Generate editing code based on the user's instructions.
    """

    client = OpenAIClient().get_client()
    vector_store = find_vector_store_by_name("documents-" + payload.user_id)

    if vector_store is None:
        raise ValueError("Vector store for this user not found.")
    
    response = client.responses.create(
        model="gpt-5.4",
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

    json_response = json.loads(response.output_text)

    python_code = json_response.get("code", "")
    requirements = json_response.get("requirements", "")
    packages = json_response.get("packages", "")
    output_file = json_response.get("output_file", "")

    final_response = {
        "code": python_code,
        "requirements": requirements,
        "output_file": output_file,
        "packages": packages,
    }

    return final_response

def execute_code_in_docker(payload: dict) -> str:
    """
    Execute the generated code in a Docker container and return the output.
    Payload keys: "code": str, "requirements": str, "file": bytes, "filename": str, "output_file": str, "packages": str
    """

    code = payload.get("code", "")
    requirements = payload.get("requirements", "")
    file_content = payload.get("file", b"")
    file_name = payload.get("filename", "input.txt")
    output_file = payload.get("output_file", "")
    packages = payload.get("packages", "")

    files = {
        "script.py": code,
        "requirements.txt": requirements,
        file_name: file_content,
    }

    linux_script = (("apt-get update && apt-get install -y " + packages + " && ") if packages else "") + "pip install -r requirements.txt && python script.py" 

    output = run_container_with_files(
        image="python:3.11-slim",
        files=files,
        command=["sh", "-c", linux_script],
        output_path_in_container=output_file,
    )

    safe_name = os.path.basename(output_file)
    
    return output