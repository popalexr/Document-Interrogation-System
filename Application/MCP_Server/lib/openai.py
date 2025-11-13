from openai import OpenAI

from lib.singleton import singleton

@singleton
class OpenAIClient:
    def __init__(self, api_key: str | None = None):
        if api_key is None:
            return

        self.client = OpenAI(api_key=api_key)
    
    def get_client(self):
        return self.client