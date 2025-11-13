import os
import boto3

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