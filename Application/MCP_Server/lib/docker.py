import io
import os
import tarfile
from typing import Dict, Union, Optional

import docker

BytesLike = Union[bytes, str]

def run_container_with_files(
    *,
    image: str,
    workdir: str = "/work",
    files: Dict[str, BytesLike], # relpaths under workdir, ex. {"input.txt": "hello world", "script.sh": "..."}
    command: list[str],          # command to run in the container, ex. ["python", "script.py"]
    output_path_in_container: Optional[str] = None, # if specified, the file at this path in the container will be returned as bytes
):
    client = docker.from_env()

    # Pull image if missing locally
    try:
        client.images.get(image)
    except docker.errors.ImageNotFound:
        client.images.pull(image)
    
    container = None

    try:
        container = client.containers.create(
            image = image,
            command=command,
            working_dir=workdir,
        )

        # Copy files into the container
        tar_bytes = _tar_bytes_from_file(files)
        ok = container.put_archive(path=workdir, data=tar_bytes)

        if not ok:
            raise RuntimeError("Failed to copy files into container")
        
        # Run container and wait for completition
        container.start()
        result = container.wait()
        status_code = result.get("StatusCode", -1)

        logs = container.logs(stdout=True, stderr=True).decode("utf-8", errors="replace")

        if status_code != 0:
            raise RuntimeError(f"Container exited with status code {status_code}. Logs:\n{logs}")
        
        # If no output file requested, return logs
        if output_path_in_container is None:
            return logs
        
        # Resolve output path
        if output_path_in_container.startswith("/"):
            out_abs = output_path_in_container
        else:
            out_abs = f"{workdir.rstrip('/')}/{output_path_in_container.lstrip('/')}"
        
        # Retrieve output file from container
        stream, stat = container.get_archive(out_abs)
        tar_bytes = b"".join(stream)

        # Docker usually returns a tar containing just the basename when archiving a file
        wanted_name = os.path.basename(out_abs)

        data = _extract_single_file_from_tar_stream(tar_bytes, wanted_name)

        return data
    
    finally:
        if container is not None:
            try:
                container.remove(force=True)
            except Exception:
                pass

def _extract_single_file_from_tar_stream(tar_stream: bytes, wanted_relpath: str) -> bytes:
    """
    Given tar bytes (from get_archive), extract exactly wanted_relpath.
    """

    wanted_relpath = wanted_relpath.lstrip("/")
    buf = io.BytesIO(tar_stream)

    with tarfile.open(fileobj=buf, mode="r:*") as tf:
        member = tf.getmember(wanted_relpath)
        f = tf.extractfile(member)

        if f is None:
            raise FileNotFoundError(f"File {wanted_relpath!r} not found in tar archive")
        
        return f.read()

def _tar_bytes_from_file(files: Dict[str, BytesLike]) -> bytes:
    """
    Build an in-memory tar archive containing the given files.
    Keys are POSIX-style relative paths inside the tar.
    """
   
    buf = io.BytesIO()

    with tarfile.open(fileobj=buf, mode="w") as tf:
        for relpath, content in files.items():
            data = content.encode("utf-8") if isinstance(content, str) else content
            relpath = relpath.lstrip("/")

            ti = tarfile.TarInfo(name=relpath)
            ti.size = len(data)

            # Set mode bits to 644 (rw-r--r--)
            ti.mode = 0o644
            tf.addfile(tarinfo=ti, fileobj=io.BytesIO(data))
    
    return buf.getvalue()