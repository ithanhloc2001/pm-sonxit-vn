#!/usr/bin/env bash
# ============================================================
# deploy.sh — Deploy ComfyUI API lên server Linux (Ubuntu/Debian)
# Chạy: bash deploy.sh
# ============================================================
set -euo pipefail

API_DIR="/var/www/comfyui-api"
SERVICE_NAME="comfyui-api"
NGINX_CONF="/etc/nginx/sites-available/api.sonxit.vn"
DOMAIN="api.sonxit.vn"

echo "=== [1/6] Cài đặt dependencies ==="
apt-get update -qq
apt-get install -y python3 python3-pip python3-venv nginx certbot python3-certbot-nginx

echo "=== [2/6] Tạo thư mục & copy files ==="
mkdir -p "$API_DIR/workflows"

# Copy code từ thư mục hiện tại (chạy script trong thư mục comfyUi/)
cp main.py comfy_client.py models.py requirements.txt "$API_DIR/"
cp workflows/rechange_image.json "$API_DIR/workflows/"

# Copy index.html nếu có
[ -f index.html ] && cp index.html "$API_DIR/"

echo "=== [3/6] Tạo virtualenv & cài thư viện Python ==="
cd "$API_DIR"
python3 -m venv venv
venv/bin/pip install --upgrade pip -q
venv/bin/pip install -r requirements.txt -q

echo "=== [4/6] Cài systemd service ==="
cp /var/www/comfyui-api/../htdocs/comfyUi/comfyui-api.service \
   /etc/systemd/system/comfyui-api.service 2>/dev/null || \
cat > /etc/systemd/system/comfyui-api.service << 'EOF'
[Unit]
Description=Paint&More ComfyUI FastAPI Service
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/comfyui-api
ExecStart=/var/www/comfyui-api/venv/bin/uvicorn main:app --host 127.0.0.1 --port 8000 --workers 1 --loop asyncio
Restart=on-failure
RestartSec=5
LimitNOFILE=65536
TimeoutStopSec=30

[Install]
WantedBy=multi-user.target
EOF

chown -R www-data:www-data "$API_DIR"
systemctl daemon-reload
systemctl enable "$SERVICE_NAME"
systemctl restart "$SERVICE_NAME"
echo "✅ Service $SERVICE_NAME đã khởi động"
systemctl status "$SERVICE_NAME" --no-pager

echo "=== [5/6] Cấu hình Nginx ==="
cp nginx-api.sonxit.vn.conf "$NGINX_CONF"
ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/
nginx -t && systemctl reload nginx
echo "✅ Nginx đã reload"

echo "=== [6/6] Cấp SSL với Certbot ==="
certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos \
  --email admin@sonxit.vn --redirect || \
  echo "⚠️  Certbot thất bại — hãy chạy thủ công: certbot --nginx -d $DOMAIN"

echo ""
echo "=============================================="
echo "✅ Deploy hoàn tất!"
echo "API URL: https://$DOMAIN"
echo "Health:  https://$DOMAIN/health"
echo "Docs:    https://$DOMAIN/docs"
echo "=============================================="
