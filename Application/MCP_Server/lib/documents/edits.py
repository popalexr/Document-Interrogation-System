from bson import ObjectId
from datetime import datetime

from lib.mongo import MongoDBClient

client = None

def init():
    global client
    client = MongoDBClient()

def store_document(
    original_document_id: str,
    user_id: str,
    document_name: str,
    r2_key: str,
    mime_type: str,
) -> str:
    """
    Store a new document record in the database and return its ID.
    """
    
    # Get the "edits" collection
    uploads_collection = client.get_collection("edits")
    
    # Create a new document record
    new_document = {
        "user_id": user_id,
        "original_document_id": ObjectId(original_document_id),
        "original_name": document_name,
        "mime_type": mime_type,
        "r2_key": r2_key,
        "created_at": datetime.utcnow(),
    }
    
    # Insert the new document into the collection
    result = uploads_collection.insert_one(new_document)
    
    # Return the ID of the newly created document
    return str(result.inserted_id)

def get_document(document_id: str) -> dict:
    """
    Retrieve a document record from the database by its ID.
    """
    
    # Get the "edits" collection
    uploads_collection = client.get_collection("edits")
    
    # Find the document by its ID
    document = uploads_collection.find_one({"_id": ObjectId(document_id)})
    
    if document is None:
        raise ValueError(f"Document with ID {document_id} not found.")
    
    # Convert ObjectId to string for the returned document
    document["_id"] = str(document["_id"])
    
    return document
