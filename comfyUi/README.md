# ComfyUI FastAPI Wrapper

Wrap ComfyUI thành REST API đầy đủ.

## Cài đặt

```bash
pip install -r requirements.txt
```

## Chạy

```bash
# 1. Khởi động ComfyUI (port 8188)
python -m comfy --listen 0.0.0.0 --port 8188

# 2. Khởi động FastAPI (port 8000)
uvicorn main:app --host 0.0.0.0 --port 8000 --reload
```

## Các endpoint

| Method | Endpoint | Mô tả |
|--------|----------|-------|
| POST | `/generate` | Tạo job sinh ảnh |
| GET | `/status/{job_id}` | Kiểm tra trạng thái |
| GET | `/result/{job_id}` | Tải ảnh kết quả (PNG) |
| GET | `/workflows` | Liệt kê workflow có sẵn |
| GET | `/health` | Health check |
| GET | `/docs` | Swagger UI tự động |

## Ví dụ sử dụng

```bash
# Tạo job
curl -X POST http://localhost:8000/generate \
  -H "Content-Type: application/json" \
  -d '{"prompt": "a cat on the moon, cinematic", "steps": 25}'

# Kiểm tra trạng thái
curl http://localhost:8000/status/<job_id>

# Tải ảnh
curl http://localhost:8000/result/<job_id> --output result.png
```

## Thêm workflow

Đặt file `.json` vào thư mục `workflows/` và truyền `workflow_name` khi gọi `/generate`.
