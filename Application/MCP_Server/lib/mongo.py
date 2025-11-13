import os
from typing import Optional

from pymongo import MongoClient
from pymongo.errors import ConnectionFailure

from lib.singleton import singleton


@singleton
class MongoDBClient:
    def __init__(self, uri: str | None = None, db_name: str | None = None):
        """
        MongoDB client singleton.

        Reads `MONGODB_URI` and optional `MONGODB_DB` from environment if
        not explicitly provided.
        """

        self._uri = uri or os.getenv("MONGODB_URI")
        self._db_name = db_name or os.getenv("MONGODB_DB")

        if not self._uri:
            raise ValueError("MONGODB_URI is required to initialize MongoDBClient")

        self.client = MongoClient(self._uri)
        self._db = self.client[self._db_name] if self._db_name else None

    def get_client(self) -> MongoClient:
        return self.client

    def get_db(self, name: Optional[str] = None):
        """
        Return a database by name, or the default configured database.
        Raises if no default database is configured and `name` is not provided.
        """

        if name:
            return self.client[name]
        if self._db is None:
            raise ValueError(
                "No database selected. Provide a name or set MONGODB_DB."
            )
        return self._db

    def get_collection(self, name: str, db_name: Optional[str] = None):
        db = self.get_db(db_name) if db_name else self.get_db()
        return db[name]

    def ping(self) -> dict:
        """Ping the server; returns the server response if reachable."""
        try:
            return self.client.admin.command("ping")
        except ConnectionFailure as e:
            raise ConnectionFailure(f"MongoDB ping failed: {e}")

    def close(self) -> None:
        self.client.close()

