import asyncio
import contextlib
import os
import sys
import json
from typing import Any, Dict, Optional

from mcp import ClientSession, StdioServerParameters
from mcp.client.stdio import stdio_client
from fastapi import HTTPException

class MCPState:
    def __init__(self) -> None:
        self.session: Optional[ClientSession] = None
        self._transport_cm = None
        self._session_cm = None
        self._lock = asyncio.Lock()

    async def start(self) -> None:
        if self.session is not None:
            return

        cmd = sys.executable or "python"
        server_script = os.path.join(os.getcwd(), "mcp_server.py")
        if not os.path.exists(server_script):
            raise RuntimeError("Cannot find mcp_server.py in CWD")

        server_params = StdioServerParameters(command=cmd, args=[server_script])
        self._transport_cm = stdio_client(server_params)
        read, write = await self._transport_cm.__aenter__()

        # Create session and initialize
        self._session_cm = ClientSession(read, write)
        self.session = await self._session_cm.__aenter__()
        await self.session.initialize()

    async def stop(self) -> None:
        async with self._lock:
            if self.session is None:
                return
            with contextlib.suppress(Exception):
                await self.session.close()
            self.session = None

            if self._session_cm is not None:
                with contextlib.suppress(Exception):
                    await self._session_cm.__aexit__(None, None, None)
                self._session_cm = None

            if self._transport_cm is not None:
                with contextlib.suppress(Exception):
                    await self._transport_cm.__aexit__(None, None, None)
                self._transport_cm = None

    async def call_tool(self, name: str, arguments: Dict[str, Any] | None = None) -> Any:
        if self.session is None:
            raise RuntimeError("MCP session is not started")

        arguments = arguments or {}

        # List tools
        tools_resp = await self.session.list_tools()
        tool_names = {t.name for t in tools_resp.tools}
        if name not in tool_names:
            # Proper FastAPI HTTPException signature
            raise HTTPException(status_code=404, detail=f"Tool '{name}' not found in MCP server")

        result = await self.session.call_tool(name, arguments)

        # Extract common text content shapes
        try:
            if isinstance(result, dict):
                return result

            content = getattr(result, "content", None)
            if isinstance(content, list) and content:
                texts = []
                for part in content:
                    text = getattr(part, "text", None)
                    if text is not None:
                        texts.append(text)
                if texts:
                    text = "\n".join(texts).strip()

                    try:
                        return json.loads(text)
                    except:
                        return {"result": text}

            return {"result": repr(result)}
        except Exception:
            return {"result": str(result)}
