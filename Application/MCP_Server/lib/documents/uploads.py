from bson import ObjectId

from lib.mongo import MongoDBClient

client = None

def init():
    global client
    client = MongoDBClient()

def get_document(document_id: str) -> dict:
    """
    Retrieve the document record from the database by its ID.
    """
    # Get the "uploads" collection via your helper
    uploads_collection = client.get_collection("uploads")
    
    # Look up the document by _id
    try:
        oid = ObjectId(document_id)
    except Exception:
        raise ValueError(f"Invalid document ID: {document_id!r}")
    
    document_record = uploads_collection.find_one({"_id": oid})

    if not document_record:
        raise ValueError(f"Document with ID {document_id} not found.")
    
    return document_record