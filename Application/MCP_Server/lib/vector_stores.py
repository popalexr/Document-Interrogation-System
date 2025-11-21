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