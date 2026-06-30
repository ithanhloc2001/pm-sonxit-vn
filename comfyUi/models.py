from pydantic import BaseModel
from typing import Optional
from enum import Enum

class JobStatus(str, Enum):
    pending = "pending"
    processing = "processing"
    completed = "completed"
    failed = "failed"

class GenerateRequest(BaseModel):
    prompt: str
    negative_prompt: str = ""
    width: int = 512
    height: int = 512
    steps: int = 20
    cfg_scale: float = 7.0
    seed: int = -1
    workflow_name: str = "txt2img"

# ← Thêm schema mới cho workflow rechange_image
class RechangeImageRequest(BaseModel):
    prompt: str = "Transform the model in image1 into the pose shown in image2 while maintaining character consistency"
    seed: int = -1
    steps: int = 10

class JobResponse(BaseModel):
    job_id: str
    status: JobStatus
    message: str = ""

class StatusResponse(BaseModel):
    job_id: str
    status: JobStatus
    progress: int = 0
    result_url: Optional[str] = None
    error: Optional[str] = None