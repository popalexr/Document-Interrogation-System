# MCP Server

### Initialization

```bash
py -m venv .venv
.venv/bin/python -m pip install -r requirements.txt
```

### Running MCP client

```bash
.venv/bin/python mcp_client.py
```

### MongoDB

- Set environment variables in a `.env` file at the project root:
  - `MONGODB_URI` — connection string (e.g., `mongodb://localhost:27017` or Atlas URI)
  - `MONGODB_DB` — default database name (optional)

- Install dependencies (already listed in `requirements.txt`):

```bash
.venv/bin/python -m pip install -r requirements.txt
```

- Usage example inside the MCP server code:

```python
from lib.mongo import MongoDBClient

db = MongoDBClient().get_db()            # uses MONGODB_DB
coll = MongoDBClient().get_collection("documents")
MongoDBClient().ping()                   # health check
```
