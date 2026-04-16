import json

from lib.r2_storage import get_r2_stream
from lib.openai import OpenAIClient
from lib.payloads import EditPayload
from lib.system_prompts import EDIT_SYS_PROMPT_GENERATE_EDITING_PROMPT
from lib.system_prompts import EDIT_SYS_PROMPT_GENERATE_EDITING_CODE
from lib.docker import run_container_with_files
from lib.documents import uploads

def generate_editing_prompt(payload: EditPayload) -> dict:
    """
    Generate an editing prompt based on the user's instructions.
    """

    client = OpenAIClient().get_client()
    input_file = _get_input_file(payload.document_id)
    
    response = client.responses.create(
        model="gpt-5.4",
        instructions=EDIT_SYS_PROMPT_GENERATE_EDITING_PROMPT,
        input=[
            {
                "role": "user",
                "content": [
                    {
                        "type": "input_file",
                        "filename": input_file["filename"],
                        "file_data": f"data:{input_file['mime_type']};base64,{input_file['content'].decode('utf-8')}",
                    },
                    {
                        "type": "input_text",
                        "text": payload.prompt,
                    }
                ]
            }
        ],
    )

    return {"prompt": response.output_text}

def generate_editing_code(payload: EditPayload) -> dict:
    """
    Generate editing code based on the user's instructions.
    """

    client = OpenAIClient().get_client()
    input_file = _get_input_file(payload.document_id)

    response = client.responses.create(
        model="gpt-5.4",
        instructions=EDIT_SYS_PROMPT_GENERATE_EDITING_CODE,
        input=[
            {
                "role": "user",
                "content": [
                    {
                        "type": "input_file",
                        "filename": input_file["filename"],
                        "file_data": f"data:{input_file['mime_type']};base64,{input_file['content'].decode('utf-8')}",
                    },
                    {
                        "type": "input_text",
                        "text": payload.prompt,
                    }
                ]
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
        image="python:3.11.15-slim-trixie",
        files=files,
        command=["sh", "-c", linux_script],
        output_path_in_container=output_file,
    )
    
    return output

def _get_input_file(document_id: str) -> dict:
    document = uploads.get_document(document_id)
    stream = get_r2_stream(document["r2_key"])

    file_bytes = stream.read()
    stream.close()

    return {
        "content": file_bytes,
        "filename": document["original_name"],
        "mime_type": document["mime_type"],
    }