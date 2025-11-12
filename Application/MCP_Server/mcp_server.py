from __future__ import annotations

import os
import dotenv
from typing import Any

from mcp.server.fastmcp import FastMCP

from lib.openai import OpenAIClient
import mcp_tools

dotenv.load_dotenv()

mcp = FastMCP(
    name=os.getenv("MCP_NAME", "Document Interrogation System")
)

openai_client = OpenAIClient(api_key=os.getenv("OPENAI_API_KEY", ""))

mcp_tools.initialize_mcp(mcp)

if __name__ == "__main__":
    mcp.run()