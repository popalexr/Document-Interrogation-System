from __future__ import annotations

from typing import Generator

from lib.filecite_sanitizer import FileciteSanitizer
from lib.openai import OpenAIClient
from lib.payloads import AIInterrogationPayload
from lib.system_prompts import QUERY_SYS_PROMPT
from lib.vector_stores import find_vector_store_by_name


def stream_ai_interrogation(payload: AIInterrogationPayload) -> Generator[dict, None, None]:
    """
    Stream an AI Interrogation response across one or more documents.

    Yields dict payloads shaped as:
    - {"type": "chunk", "delta": "<text>"} for incremental text
    - {"type": "done", "answer": "<full answer>"} once finished
    """

    if not payload.documents_ids:
        raise ValueError("At least one document id is required.")

    client = OpenAIClient().get_client()
    vector_store = find_vector_store_by_name("documents-" + payload.user_id)

    if vector_store is None:
        raise ValueError("Vector store for this user not found.")

    chat_history = _get_chat_history(payload)

    stream = client.responses.stream(
        model="gpt-5-nano",
        tools=[
            {
                "type": "file_search",
                "vector_store_ids": [vector_store.id],
                "filters": _document_filters(payload.documents_ids),
            }
        ],
        instructions=QUERY_SYS_PROMPT,
        input=chat_history
        + [
            {
                "role": "user",
                "content": payload.question,
            }
        ],
    )

    sanitizer = FileciteSanitizer()
    accumulated = ""
    last_done_text = ""
    final_response = None

    with stream as response_stream:
        for event in response_stream:
            if event.type == "response.output_text.delta":
                delta = getattr(event, "delta", "") or ""
                if delta:
                    cleaned = sanitizer.sanitize(delta)
                    if cleaned:
                        accumulated += cleaned
                        yield {"type": "chunk", "delta": cleaned}

            elif event.type == "response.output_text.done":
                last_done_text = getattr(event, "text", "") or last_done_text

        final_response = response_stream.get_final_response()

    tail = sanitizer.flush()
    if tail:
        accumulated += tail
        yield {"type": "chunk", "delta": tail}

    if not accumulated:
        raw_text = last_done_text or _extract_text_from_response(final_response)
        accumulated = FileciteSanitizer.remove_all(raw_text)

    yield {"type": "done", "answer": accumulated}


def collect_ai_interrogation_answer(payload: AIInterrogationPayload) -> str:
    """
    Convenience helper that consumes the streaming generator and returns the final answer.
    """

    answer = ""

    for event in stream_ai_interrogation(payload):
        if event.get("type") == "chunk":
            answer += event.get("delta", "")
        elif event.get("type") == "done":
            answer = event.get("answer", answer)

    return answer.strip()


def _document_filters(documents_ids: list[str]) -> dict:
    """
    Build a file_search filter for one or more document ids.
    """

    unique_document_ids = list(dict.fromkeys(str(document_id) for document_id in documents_ids))

    if len(unique_document_ids) == 1:
        return {
            "type": "eq",
            "key": "document_id",
            "value": unique_document_ids[0],
        }

    return {
        "type": "or",
        "filters": [
            {
                "type": "eq",
                "key": "document_id",
                "value": document_id,
            }
            for document_id in unique_document_ids
        ],
    }


def _extract_text_from_response(response) -> str:
    """
    Extract concatenated output text from a parsed response object.
    """

    if response is None:
        return ""

    text_parts: list[str] = []

    for output in getattr(response, "output", []) or []:
        if getattr(output, "type", None) != "message":
            continue

        for content in getattr(output, "content", []) or []:
            if getattr(content, "type", None) == "output_text":
                text = getattr(content, "text", None)
                if text:
                    text_parts.append(text)

    return "".join(text_parts).strip()


def _get_chat_history(payload: AIInterrogationPayload) -> list[dict]:
    """
    Retrieve chat history for the given AI interrogation.
    """

    chat_history = []

    for message in (payload.extra or {}).get("history", []):
        role = message.get("role")
        content = message.get("content")
        if role and content:
            chat_history.append(
                {
                    "role": role,
                    "content": content,
                }
            )

    return chat_history
