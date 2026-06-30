import aiohttp
import json
import uuid
import asyncio
import websockets
from pathlib import Path

COMFY_URL = "http://127.0.0.1:8188"
COMFY_WS  = "ws://127.0.0.1:8188"


async def upload_image(image_bytes: bytes, filename: str) -> str:
    """Upload ảnh lên ComfyUI và trả về filename đã lưu."""
    data = aiohttp.FormData()
    data.add_field(
        "image",
        image_bytes,
        filename=filename,
        content_type="image/png",
    )
    data.add_field("overwrite", "true")

    async with aiohttp.ClientSession() as session:
        async with session.post(f"{COMFY_URL}/upload/image", data=data) as resp:
            if resp.status != 200:
                text = await resp.text()
                raise RuntimeError(f"Upload ảnh thất bại ({resp.status}): {text}")
            result = await resp.json()
            return result["name"]


async def queue_prompt(workflow: dict, client_id: str) -> str:
    """Gửi workflow vào hàng đợi ComfyUI và trả về prompt_id."""
    payload = {"prompt": workflow, "client_id": client_id}
    async with aiohttp.ClientSession() as session:
        async with session.post(f"{COMFY_URL}/prompt", json=payload) as resp:
            if resp.status != 200:
                text = await resp.text()
                raise RuntimeError(f"Queue prompt thất bại ({resp.status}): {text}")
            data = await resp.json()
            if "error" in data:
                raise RuntimeError(f"ComfyUI workflow lỗi: {data['error']}")
            return data["prompt_id"]


async def wait_for_result(
    prompt_id: str,
    client_id: str,
    progress_cb=None,
    timeout: int = 300,
) -> list:
    """
    Kết nối WebSocket và chờ ComfyUI hoàn thành job.
    Timeout mặc định 300 giây (5 phút).
    """
    uri = f"{COMFY_WS}/ws?clientId={client_id}"

    async def _listen():
        async with websockets.connect(uri, ping_interval=20, ping_timeout=60) as ws:
            while True:
                raw = await ws.recv()
                if isinstance(raw, bytes):
                    continue
                msg  = json.loads(raw)
                mtype = msg.get("type")

                if mtype == "progress":
                    val  = msg["data"].get("value", 0)
                    maxi = msg["data"].get("max", 1) or 1
                    if progress_cb:
                        await progress_cb(int(val / maxi * 100))

                elif mtype == "executing":
                    d = msg.get("data", {})
                    if d.get("prompt_id") == prompt_id and d.get("node") is None:
                        # Job hoàn thành
                        return

    await asyncio.wait_for(_listen(), timeout=timeout)

    # Lấy danh sách filename kết quả
    async with aiohttp.ClientSession() as session:
        async with session.get(f"{COMFY_URL}/history/{prompt_id}") as resp:
            history = await resp.json()

    output_images = []
    for node_output in history.get(prompt_id, {}).get("outputs", {}).values():
        if "images" in node_output:
            for img in node_output["images"]:
                if img.get("type") == "output":
                    output_images.append(img["filename"])

    return output_images


async def get_image_bytes(
    filename: str,
    subfolder: str = "",
    folder_type: str = "output",
) -> bytes:
    """Tải bytes ảnh kết quả từ ComfyUI."""
    params = {"filename": filename, "subfolder": subfolder, "type": folder_type}
    async with aiohttp.ClientSession() as session:
        async with session.get(f"{COMFY_URL}/view", params=params) as resp:
            if resp.status != 200:
                raise RuntimeError(f"Không tải được ảnh '{filename}' ({resp.status})")
            return await resp.read()