from lib.openai import OpenAIClient

def find_vector_store_by_name(name: str):
    """
    Find a vector store by its name.
    """
    client = OpenAIClient().get_client()

    vector_stores = client.vector_stores.list()

    for vs in vector_stores.data:
        if vs.name == name:
            return vs

    return None

def get_file_from_vector_store(vector_store_id: str, file_id: str):
    """
    Retrieve a file from a vector store by its ID.
    """
    client = OpenAIClient().get_client()

    try:
        vector_store_file = client.vector_stores.files.retrieve(
            vector_store_id=vector_store_id,
            file_id=file_id,
        )

        return vector_store_file
    
    except Exception:
        return None

def search_vector_store(user_id: str, query: str, max_num_results: int = 10) -> dict:
    """
    Search the user's vector store semantically and return normalized file hits.
    """
    client = OpenAIClient().get_client()
    vector_store = find_vector_store_by_name("documents-" + user_id)

    if vector_store is None:
        return {
            "search_query": query,
            "results": [],
            "has_more": False,
            "next_page": None,
        }

    results = client.vector_stores.search(
        vector_store_id=vector_store.id,
        query=query,
        max_num_results=max(1, min(max_num_results, 50)),
        rewrite_query=True,
    )

    return {
        "search_query": getattr(results, "search_query", query),
        "results": [_normalize_search_result(result) for result in getattr(results, "data", [])],
        "has_more": bool(getattr(results, "has_more", False)),
        "next_page": getattr(results, "next_page", None),
    }

def _normalize_search_result(result) -> dict:
    attributes = getattr(result, "attributes", None) or {}

    return {
        "file_id": getattr(result, "file_id", ""),
        "filename": getattr(result, "filename", ""),
        "score": getattr(result, "score", None),
        "document_id": _attribute_value(attributes, "document_id"),
        "content": [
            {
                "type": getattr(content, "type", "text"),
                "text": getattr(content, "text", ""),
            }
            for content in (getattr(result, "content", None) or [])
        ],
    }

def _attribute_value(attributes, key: str):
    if isinstance(attributes, dict):
        return attributes.get(key)

    return getattr(attributes, key, None)
