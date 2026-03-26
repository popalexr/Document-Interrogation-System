import os
import boto3
import uuid

def get_r2_stream(path: str):
    s3 = boto3.client(
        "s3",
        endpoint_url=os.getenv("R2_ENDPOINT"),
        aws_access_key_id=os.getenv("R2_ACCESS_KEY_ID"),
        aws_secret_access_key=os.getenv("R2_SECRET_ACCESS_KEY"),
        region_name="auto",
    )

    obj = s3.get_object(Bucket=os.getenv('R2_BUCKET'), Key=path)
    return obj["Body"]

def save_to_r2(file_content: bytes, document_name: str) -> str:
    """
    Save a file to R2 and return the new key.
    """
    
    s3 = boto3.client(
        "s3",
        endpoint_url=os.getenv("R2_ENDPOINT"),
        aws_access_key_id=os.getenv("R2_ACCESS_KEY_ID"),
        aws_secret_access_key=os.getenv("R2_SECRET_ACCESS_KEY"),
        region_name="auto",
    )

    new_document_id = str(uuid.uuid4())
    new_key = f"edited/{new_document_id}/{document_name}"

    s3.put_object(Bucket=os.getenv('R2_BUCKET'), Key=new_key, Body=file_content)

    return new_key