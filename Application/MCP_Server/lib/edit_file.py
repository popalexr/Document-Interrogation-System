import json
import base64
import os

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

    document = uploads.get_document(payload.document_id)

    document_name = document["original_name"] if document else "unknown"
    document_mime_type = document["mime_type"] if document else "unknown"

    document_info = f"Document Name: {document_name}\nMIME Type: {document_mime_type}"
    
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
                        "file_data": f"data:{input_file['mime_type']};base64,{input_file['content']}",
                    },
                    {
                        "type": "input_text",
                        "text": f"{payload.prompt}\n\n{document_info}",
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
                        "file_data": f"data:{input_file['mime_type']};base64,{input_file['content']}",
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

def execute_code_in_docker(payload: dict) -> bytes:
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

    if isinstance(file_content, str):
        file_content = file_content.encode("utf-8")

    files = {
        "script.py": code,
        "requirements.txt": requirements,
    }
    files.update(_build_input_file_variants(file_name, file_content))

    current_code = code
    current_requirements = requirements
    current_packages = packages
    last_error: RuntimeError | None = None

    for attempt in range(2):
        files["script.py"] = current_code
        files["requirements.txt"] = current_requirements
        linux_script = (("apt-get update && apt-get install -y " + current_packages + " && ") if current_packages else "") + "pip install -r requirements.txt && python script.py"

        try:
            return run_container_with_files(
                image="python:3.11.15-slim-trixie",
                files=files,
                command=["sh", "-c", linux_script],
                output_path_in_container=output_file,
            )
        except RuntimeError as exc:
            if "Container exited with status code" not in str(exc):
                raise

            last_error = exc
            if attempt == 1:
                break

            repaired = _repair_editing_code(
                source_filename=file_name,
                output_file=output_file,
                code=current_code,
                requirements=current_requirements,
                packages=current_packages,
                runtime_error=_truncate_error(str(exc)),
            )
            current_code = repaired.get("code") or current_code
            current_requirements = repaired.get("requirements") or current_requirements
            current_packages = repaired.get("packages", current_packages)

    if last_error is not None:
        raise last_error

    raise RuntimeError("Unexpected editing runtime state.")

def _repair_editing_code(
    *,
    source_filename: str,
    output_file: str,
    code: str,
    requirements: str,
    packages: str,
    runtime_error: str,
) -> dict:
    """
    Request one corrective regeneration pass using the runtime logs.
    """

    client = OpenAIClient().get_client()
    repair_input = {
        "source_filename": source_filename,
        "output_file": output_file,
        "runtime_error": runtime_error,
        "code": code,
        "requirements": requirements,
        "packages": packages,
    }

    repair_instructions = """
You repair Python document-editing code that failed at runtime.
Return only valid JSON with exactly these keys:
- "code": string
- "requirements": string
- "packages": string
- "output_file": string

Rules:
1. Keep the same editing intent.
2. Open the input using source_filename exactly and save output_file exactly.
3. Do not intentionally raise errors for missing anchors; continue in best-effort mode.
4. Always produce output_file.
5. Keep dependencies minimal and pinned.
6. Do not include markdown or explanations.
"""

    response = client.responses.create(
        model="gpt-5.4",
        instructions=repair_instructions,
        input=[
            {
                "role": "user",
                "content": [
                    {
                        "type": "input_text",
                        "text": json.dumps(repair_input),
                    }
                ],
            }
        ],
    )

    parsed = json.loads(response.output_text)
    parsed["output_file"] = output_file
    return parsed

def _truncate_error(error_text: str, max_chars: int = 8000) -> str:
    if len(error_text) <= max_chars:
        return error_text

    return error_text[-max_chars:]

def _build_input_file_variants(file_name: str, file_content: bytes) -> dict[str, bytes]:
    """
    Mount the input document under multiple predictable names.
    This reduces crashes when generated code uses generic names like document.pdf.
    """

    source_name = os.path.basename((file_name or "").strip()) or "input.txt"
    _, extension = os.path.splitext(source_name)

    candidate_names = [source_name]
    if extension:
        candidate_names.extend([f"document{extension}", f"input{extension}", f"source{extension}"])
        lower_extension = extension.lower()
        if lower_extension != extension:
            candidate_names.extend([f"document{lower_extension}", f"input{lower_extension}", f"source{lower_extension}"])
    else:
        candidate_names.extend(["document", "input", "source"])

    variants: dict[str, bytes] = {}
    for candidate in candidate_names:
        if candidate in {"script.py", "requirements.txt"}:
            continue
        variants.setdefault(candidate, file_content)

    return variants

def _get_input_file(document_id: str) -> dict:
    """
    Retrieve the input file from R2 storage and return its content, filename, and MIME type.
    The file content is returned as base64 encoded string to be compatible with the OpenAI API input format.
    """

    document = uploads.get_document(document_id)
    stream = get_r2_stream(document["r2_key"])

    file_bytes = stream.read()
    stream.close()

    return {
        "content": base64.b64encode(file_bytes).decode("utf-8"),
        "filename": document["original_name"],
        "mime_type": document.get("mime_type") or "application/octet-stream",
    }
