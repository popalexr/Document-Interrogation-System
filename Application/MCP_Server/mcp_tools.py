mcp = None

def initialize_mcp(mcp_instance):
    global mcp
    mcp = mcp_instance

    @mcp.tool()
    def ping(payload: dict | None = None) -> dict:
        """
        A simple tool that responds with 'pong' to test connectivity.
        """

        return {"status": "ok"}