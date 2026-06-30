import json
import uuid
from pathlib import Path
from typing import Dict

from fastapi import FastAPI, HTTPException, BackgroundTasks, UploadFile, File, Form
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import Response, FileResponse
from fastapi.staticfiles import StaticFiles

from models import RechangeImageRequest, JobResponse, StatusResponse, JobStatus
from comfy_client import upload_image, queue_prompt, wait_for_result, get_image_bytes

# ── App ───────────────────────────────────────────────────────────────────────
app = FastAPI(
    title="Paint&More ComfyUI API",
    version="2.0.0",
    description="API sonxit.vn"
)

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

# ── State ─────────────────────────────────────────────────────────────────────
jobs: Dict[str, dict] = {}
WORKFLOW_DIR = Path(__file__).parent / "workflows"


def load_workflow(name: str) -> dict:
    path = WORKFLOW_DIR / f"{name}.json"
    if not path.exists():
        raise HTTPException(404, f"Workflow '{name}' không tìm thấy")
    return json.loads(path.read_text(encoding="utf-8"))


# ── Trang chủ ─────────────────────────────────────────────────────────────────
@app.get("/")
async def index():
    html_path = Path(__file__).parent / "index.html"
    if html_path.exists():
        return FileResponse(str(html_path), headers={
            "Cache-Control": "no-cache, no-store, must-revalidate",
            "Pragma": "no-cache",
            "Expires": "0",
        })
    return {"status": "ok", "message": "Paint&More ComfyUI API v2.0"}


# ── RECHANGE IMAGE ─────────────────────────────────────────────────────────────
async def _run_rechange_image(job_id: str, img1: bytes, img2: bytes, req: RechangeImageRequest):
    jobs[job_id]["status"] = JobStatus.processing
    try:
        name1 = await upload_image(img1, f"src_{job_id}.png")
        name2 = await upload_image(img2, f"pose_{job_id}.png")

        wf = load_workflow("rechange_image")
        wf["1"]["inputs"]["image"] = name1   # ảnh gốc (model/người)
        wf["2"]["inputs"]["image"] = name2   # ảnh pose tham khảo

        # Điền prompt vào node 19 (TextEncodeQwenImageEditPlusAdvance)
        wf["19"]["inputs"]["prompt"] = req.prompt

        # KSampler node 20
        wf["20"]["inputs"]["steps"] = req.steps
        wf["20"]["inputs"]["seed"] = (
            req.seed if req.seed != -1 else uuid.uuid4().int % (2 ** 32)
        )

        client_id = str(uuid.uuid4())
        prompt_id = await queue_prompt(wf, client_id)

        async def _cb(pct: int):
            jobs[job_id]["progress"] = pct

        filenames = await wait_for_result(prompt_id, client_id, _cb)

        if filenames:
            jobs[job_id]["image"]  = await get_image_bytes(filenames[0])
            jobs[job_id]["status"] = JobStatus.completed
        else:
            jobs[job_id]["status"] = JobStatus.failed
            jobs[job_id]["error"]  = "Không có ảnh đầu ra từ ComfyUI"

    except Exception as exc:
        jobs[job_id]["status"] = JobStatus.failed
        jobs[job_id]["error"]  = str(exc)


@app.post(
    "/rechange-image",
    response_model=JobResponse,
    status_code=202,
    summary="Đổi tư thế nhân vật (Pose Transfer)",
    tags=["Image Processing"],
)
async def rechange_image(
    bg: BackgroundTasks,
    image1: UploadFile = File(..., description="Ảnh gốc chứa nhân vật/model"),
    image2: UploadFile = File(..., description="Ảnh tham chiếu tư thế muốn áp dụng"),
    prompt: str = Form(
        "Transform the model in image1 into the pose shown in image2 "
        "while maintaining character consistency, lighting, shadow and sharpness, clothes detail"
    ),
    seed: int = Form(-1, description="-1 = random"),
    steps: int = Form(10, ge=1, le=50),
):
    """
    Nhận 2 ảnh qua multipart/form-data:
    - **image1**: ảnh gốc (nhân vật cần đổi tư thế)
    - **image2**: ảnh mẫu tư thế

    Trả về `job_id` để poll qua `/status/{job_id}`, lấy ảnh qua `/result/{job_id}`.
    """
    img1_bytes = await image1.read()
    img2_bytes = await image2.read()

    if len(img1_bytes) == 0 or len(img2_bytes) == 0:
        raise HTTPException(400, "File ảnh không được rỗng")
    if len(img1_bytes) > 20 * 1024 * 1024 or len(img2_bytes) > 20 * 1024 * 1024:
        raise HTTPException(413, "File ảnh không được vượt quá 20MB")

    job_id = str(uuid.uuid4())
    jobs[job_id] = {"status": JobStatus.pending, "progress": 0,
                    "image": None, "error": None}

    req = RechangeImageRequest(prompt=prompt, seed=seed, steps=steps)
    bg.add_task(_run_rechange_image, job_id, img1_bytes, img2_bytes, req)

    return JobResponse(job_id=job_id, status=JobStatus.pending,
                       message="Job đã được tạo, poll /status/{job_id} để kiểm tra tiến trình")


# ── SHARED ENDPOINTS ──────────────────────────────────────────────────────────
@app.get(
    "/status/{job_id}",
    response_model=StatusResponse,
    summary="Kiểm tra trạng thái job",
    tags=["Job Management"],
)
async def get_status(job_id: str):
    if job_id not in jobs:
        raise HTTPException(404, "Job không tồn tại")
    j = jobs[job_id]
    result_url = f"/result/{job_id}" if j["status"] == JobStatus.completed else None
    return StatusResponse(
        job_id=job_id,
        status=j["status"],
        progress=j["progress"],
        result_url=result_url,
        error=j["error"],
    )


@app.get(
    "/result/{job_id}",
    summary="Lấy ảnh kết quả (PNG)",
    tags=["Job Management"],
)
async def get_result(job_id: str):
    if job_id not in jobs:
        raise HTTPException(404, "Job không tồn tại")
    j = jobs[job_id]
    if j["status"] == JobStatus.failed:
        raise HTTPException(500, f"Job thất bại: {j['error']}")
    if j["status"] != JobStatus.completed:
        raise HTTPException(202, f"Job chưa hoàn thành (status: {j['status']})")
    return Response(
        content=j["image"],
        media_type="image/png",
        headers={"Content-Disposition": f'attachment; filename="result_{job_id}.png"'},
    )


@app.delete(
    "/job/{job_id}",
    summary="Xoá job khỏi bộ nhớ",
    tags=["Job Management"],
)
async def delete_job(job_id: str):
    if job_id not in jobs:
        raise HTTPException(404, "Job không tồn tại")
    del jobs[job_id]
    return {"ok": True, "message": f"Job {job_id} đã được xoá"}


@app.get("/health", summary="Health check", tags=["System"])
async def health():
    return {
        "status": "ok",
        "active_jobs": len(jobs),
        "jobs_by_status": {
            s.value: sum(1 for j in jobs.values() if j["status"] == s)
            for s in JobStatus
        },
    }


@app.get("/workflows", summary="Danh sách workflow có sẵn", tags=["System"])
async def list_workflows():
    files = [p.stem for p in WORKFLOW_DIR.glob("*.json")]
    return {"workflows": files}
