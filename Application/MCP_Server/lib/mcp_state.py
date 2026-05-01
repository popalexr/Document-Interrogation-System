import asyncio
import contextlib
import os
import sys
import json
from typing import Any, AsyncGenerator, Awaitable, Callable, Dict, Optional

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

    async def call_tool(
        self,
        name: str,
        arguments: Dict[str, Any] | None = None,
        progress_callback: Callable[[float, float | None, str | None], Awaitable[None]] | None = None,
    ) -> Any:
        if self.session is None:
            raise RuntimeError("MCP session is not started")

        arguments = arguments or {}

        # List tools
        tools_resp = await self.session.list_tools()
        tool_names = {t.name for t in tools_resp.tools}
        if name not in tool_names:
            # Proper FastAPI HTTPException signature
            raise HTTPException(status_code=404, detail=f"Tool '{name}' not found in MCP server")

        result = await self.session.call_tool(name, arguments, progress_callback=progress_callback)

        return self._decode_tool_result(name, result)

    async def stream_tool_events(
        self,
        name: str,
        arguments: Dict[str, Any] | None = None,
    ) -> AsyncGenerator[dict[str, Any], None]:
        queue: asyncio.Queue[dict[str, Any]] = asyncio.Queue()

        async def on_progress(progress: float, total: float | None, message: str | None) -> None:
            if not message:
                return

            try:
                event = json.loads(message)
            except json.JSONDecodeError:
                event = {"type": "progress", "progress": progress, "total": total, "message": message}

            if isinstance(event, dict):
                await queue.put(event)

        task = asyncio.create_task(self.call_tool(name, arguments, progress_callback=on_progress))

        try:
            while not task.done():
                try:
                    yield await asyncio.wait_for(queue.get(), timeout=0.1)
                except asyncio.TimeoutError:
                    continue

            while not queue.empty():
                yield queue.get_nowait()

            result = await task
            answer = self._extract_answer(result)

            if answer is not None:
                yield {"type": "done", "answer": answer}
            else:
                yield {"type": "done", "result": result}
        finally:
            if not task.done():
                task.cancel()
                with contextlib.suppress(asyncio.CancelledError):
                    await task

    def _decode_tool_result(self, name: str, result: Any) -> Any:
        structured_content = getattr(result, "structuredContent", None)
        is_error = bool(getattr(result, "isError", False))

        try:
            content = getattr(result, "content", None)
            texts = []
            if isinstance(content, list):
                for part in content:
                    text = getattr(part, "text", None)
                    if text is not None:
                        texts.append(text)

            text = "\n".join(texts).strip()

            if is_error:
                if text:
                    raise RuntimeError(text)

                if isinstance(structured_content, dict):
                    encoded = json.dumps(structured_content, ensure_ascii=False)
                    raise RuntimeError(encoded)

                raise RuntimeError(f"Tool '{name}' returned an error result")

            if isinstance(structured_content, dict):
                return structured_content

            if text:
                try:
                    return json.loads(text)
                except Exception:
                    return {"result": text}

            return {"result": repr(result)}
        except Exception:
            raise

    def _extract_answer(self, result: Any) -> str | None:
        if isinstance(result, dict):
            answer = result.get("answer")
            if isinstance(answer, str):
                return answer

            fallback = result.get("result")
            if isinstance(fallback, str):
                return fallback

        return None
