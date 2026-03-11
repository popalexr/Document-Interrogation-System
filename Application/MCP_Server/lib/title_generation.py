from __future__ import annotations

from lib.openai import OpenAIClient
from lib.payloads import NameChatPayload
from lib.system_prompts import TITLE_GENERATION_SYS_PROMPT

def generate_chat_title(payload: NameChatPayload) -> str:
    """
    Generate a name for a chat based on the query.
    """

    client = OpenAIClient().get_client()

    response = client.responses.create(
        model = "gpt-5-nano",
        instructions = TITLE_GENERATION_SYS_PROMPT,
        input = [
            {
                "role": "user",
                "content": payload.query,
            }
        ],
    )

    title = response.output_text.strip().replace("\n", " ")

    return title