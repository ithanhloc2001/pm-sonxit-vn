<?php
// Tắt mọi lỗi có thể làm hỏng giao diện nếu có file hệ thống tự chèn vào
error_reporting(0);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Hệ Thống Backup Master - Lộc Nguyễn</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background: #1a1a2e; color: #fff; padding: 30px; font-family: 'Segoe UI', sans-serif; }
        .card { background: #16213e; border: 1px solid #0f3460; border-radius: 15px; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
        .timer-display { font-size: 3.5rem; font-weight: bold; color: #e94560; font-family: 'Courier New', monospace; text-shadow: 0 0 15px rgba(233, 69, 96, 0.4); margin: 20px 0; }
        .terminal { background: #000; color: #00ff41; padding: 15px; border-radius: 8px; height: 250px; overflow-y: auto; font-family: 'Consolas', monospace; font-size: 12px; border: 1px solid #333; }
        .status-badge { font-size: 0.8rem; padding: 6px 15px; border-radius: 20px; transition: 0.3s; }
        .form-control, .form-select { background-color: #0f3460 !important; color: #fff !important; border: 1px solid #1a1a2e !important; }
        .nav-tabs .nav-link { color: #aaa; border: none; }
        .nav-tabs .nav-link.active { background: #e94560; color: #fff; border-radius: 5px; }
    </style>
</head>
<body>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-10">
            <div class="card shadow-lg">
                <div class="card-header bg-transparent border-bottom border-secondary d-flex justify-content-between align-items-center p-3">
                    <h4 class="mb-0 text-uppercase" style="letter-spacing: 2px;">🛡️ Backup Control Panel</h4>
                    <span id="syncStatus" class="status-badge bg-secondary">Chưa đồng bộ lịch</span>
                </div>
                
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-5 border-end border-secondary">
                            <h5 class="text-info mb-3">📅 Lập lịch chạy nền</h5>
                            <div class="mb-3">
                                <label class="small text-secondary">Kiểu lặp lịch:</label>
                                <select id="scheduleType" class="form-select">
                                    <option value="minute">Lặp theo Phút</option>
                                    <option value="hour">Lặp theo Giờ</option>
                                    <option value="daily">Giờ cố định hàng ngày</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label id="inputLabel" class="small text-secondary">Giá trị:</label>
                                <input type="number" id="intervalValue" class="form-control" value="60">
                                <input type="time" id="timeValue" class="form-control d-none" value="02:00">
                            </div>

                            <?php $isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'); ?>
                            <button id="saveConfigBtn" class="btn btn-primary w-100 fw-bold"><?php echo $isWindows ? 'LƯU & ĐẶT LỊCH WINDOWS' : 'LƯU & HƯỚNG DẪN CRON JOB'; ?></button>
                            
                            <div class="mt-4 p-3 bg-dark rounded border border-secondary">
                                <h6 class="text-warning small">⚡ Thao tác nhanh:</h6>
                                <div class="d-grid gap-2">
                                    <button id="runNow" class="btn btn-sm btn-outline-danger">Chạy Backup Ngay</button>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-7 text-center ps-4">
                            <p class="text-secondary small mb-0">Đếm ngược tới lần Backup tiếp theo</p>
                            <div class="timer-display" id="timerDisplay">00:00:00</div>
                            
                            <div id="fileInfo" class="text-start mb-2"></div>
                            <div class="terminal text-start" id="console">> Hệ thống sẵn sàng...</div>
                        </div>
                    </div>
                </div>
            </div>
            <p class="text-center mt-3 text-secondary small">© 2026 Lộc Nguyễn System - Chạy trên nền <?php echo $isWindows ? 'Windows / XAMPP' : 'Linux / Docker Container'; ?></p>
        </div>
    </div>
</div>

<script>
// Hàm sao chép nhanh vào Clipboard
function copyToClipboard(id) {
    const input = document.getElementById(id);
    input.select();
    input.setSelectionRange(0, 99999);
    navigator.clipboard.writeText(input.value).then(() => {
        alert("Đã sao chép lệnh thành công!");
    }).catch(err => {
        document.execCommand("copy");
        alert("Đã sao chép lệnh thành công!");
    });
}

let countdown;
let timeLeft;

// 1. Tải cấu hình đã lưu từ LocalStorage
const savedType = localStorage.getItem('backup_type') || 'minute';
const savedVal = localStorage.getItem('backup_val') || 60;

document.getElementById('scheduleType').value = savedType;
if (savedType === 'daily') {
    document.getElementById('timeValue').value = savedVal;
    document.getElementById('timeValue').classList.remove('d-none');
    document.getElementById('intervalValue').classList.add('d-none');
} else {
    document.getElementById('intervalValue').value = savedVal;
}

// 2. Hàm Format thời gian
function formatTime(seconds) {
    if (seconds < 0) return "00:00:00";
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    return [h, m, s].map(v => v < 10 ? "0" + v : v).join(":");
}

// 3. Xử lý UI thay đổi kiểu lịch
document.getElementById('scheduleType').addEventListener('change', function() {
    if (this.value === 'daily') {
        document.getElementById('intervalValue').classList.add('d-none');
        document.getElementById('timeValue').classList.remove('d-none');
    } else {
        document.getElementById('intervalValue').classList.remove('d-none');
        document.getElementById('timeValue').classList.add('d-none');
    }
});

// 4. Hàm thực thi Backup
function runBackupProcess() {
    const runBtn = document.getElementById('runNow');
    const consoleBox = document.getElementById('console');
    
    // Khóa nút và hiển thị spinner đang chạy
    const originalText = runBtn.innerHTML;
    runBtn.disabled = true;
    runBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Đang sao lưu...';
    
    // Hiển thị trạng thái đang xử lý trong khu vực fileInfo
    document.getElementById('fileInfo').innerHTML = `
        <div class="alert alert-warning py-3 text-center mb-3">
            <div class="spinner-border text-warning spinner-border-sm mb-2" role="status"></div>
            <div class="fw-bold small text-dark">Hệ thống đang tiến hành sao lưu toàn bộ Database & Source Code...</div>
            <div class="text-secondary small mt-1" style="font-size: 11px;">Vui lòng không tắt hoặc tải lại trang web này.</div>
        </div>`;

    consoleBox.innerHTML = `<span class="text-warning">[${new Date().toLocaleTimeString()}] Đang chạy lệnh backup...</span>\n` + consoleBox.innerHTML;

    fetch('run-backup.php')
        .then(res => res.text())
        .then(text => {
            // Mở khóa nút
            runBtn.disabled = false;
            runBtn.innerHTML = originalText;

            try {
                const data = JSON.parse(text);
                if(data.status === 'success') {
                    consoleBox.innerHTML = `<span class="text-success">[OK] Hoàn tất. File: ${data.latest_file}</span>\n` + consoleBox.innerHTML;
                    if(data.is_local_windows_docker) {
                        let destPath = data.windows_dest || 'D:\\XAMPP_Backups';
                        document.getElementById('fileInfo').innerHTML = `
                            <div class="alert alert-success py-3 small">
                                <h6 class="alert-heading fw-bold text-success mb-1">🎉 SAO LƯU THÀNH CÔNG!</h6>
                                <strong>Đã lưu trực tiếp vào ổ đĩa Windows:</strong><br>
                                <span class="badge bg-secondary p-2 my-1 text-wrap text-break w-100 text-start font-monospace">${destPath}\\${data.latest_file}</span>
                                <div class="text-secondary mt-1 small"><i>(File đã tự động đồng bộ sang máy Windows của bạn qua Docker Volume, bạn không cần phải tải về thủ công).</i></div>
                            </div>`;
                    } else {
                        let downloadUrl = `/backups/${data.latest_file}`;
                        document.getElementById('fileInfo').innerHTML = `
                            <div class="alert alert-success py-2 small">
                                <strong>Mới nhất:</strong> ${data.latest_file}<br>
                                <a href="${downloadUrl}" download class="btn btn-success btn-sm w-100 fw-bold mt-2 text-white">⬇️ TẢI FILE BACKUP VỀ MÁY</a>
                            </div>`;
                    }
                } else {
                    consoleBox.innerHTML = `<span class="text-danger">[LỖI] ${data.message}</span>\n` + consoleBox.innerHTML;
                    document.getElementById('fileInfo').innerHTML = `
                        <div class="alert alert-danger py-2 small">
                            <strong>Lỗi sao lưu:</strong> ${data.message}
                        </div>`;
                }
            } catch(e) { 
                consoleBox.innerHTML = `<span class="text-danger">[LỖI] Phản hồi: ${text}</span>\n` + consoleBox.innerHTML;
                document.getElementById('fileInfo').innerHTML = `
                    <div class="alert alert-danger py-2 small">
                        <strong>Lỗi phản hồi hệ thống:</strong> Xem chi tiết lỗi ở terminal bên dưới.
                    </div>`;
            }
            resetTimer(); // Reset đếm ngược
        })
        .catch(err => {
            // Mở khóa nút nếu có lỗi kết nối
            runBtn.disabled = false;
            runBtn.innerHTML = originalText;
            consoleBox.innerHTML = `<span class="text-danger">[LỖI] Kết nối mạng hoặc Server thất bại</span>\n` + consoleBox.innerHTML;
            document.getElementById('fileInfo').innerHTML = `
                <div class="alert alert-danger py-2 small">
                    <strong>Lỗi kết nối:</strong> Không thể gửi yêu cầu đến máy chủ.
                </div>`;
            resetTimer();
        });
}

// 5. Quản lý bộ đếm ngược
function resetTimer() {
    clearInterval(countdown);
    const type = document.getElementById('scheduleType').value;
    const val = (type === 'daily') ? 1440 : parseInt(document.getElementById('intervalValue').value); // daily coi như 24h
    
    if (type === 'daily') {
        // Tính giây từ hiện tại đến giờ đã đặt
        const now = new Date();
        const [targetH, targetM] = document.getElementById('timeValue').value.split(':');
        let target = new Date();
        target.setHours(targetH, targetM, 0);
        if (target <= now) target.setDate(target.getDate() + 1);
        timeLeft = Math.floor((target - now) / 1000);
    } else {
        timeLeft = (type === 'minute') ? val * 60 : val * 3600;
    }

    countdown = setInterval(() => {
        timeLeft--;
        document.getElementById('timerDisplay').innerText = formatTime(timeLeft);
        if (timeLeft <= 0) {
            clearInterval(countdown);
            runBackupProcess();
        }
    }, 1000);
}

// 6. Lưu cấu hình & Đồng bộ Task Scheduler
document.getElementById('saveConfigBtn').addEventListener('click', () => {
    const type = document.getElementById('scheduleType').value;
    const val = (type === 'daily') ? document.getElementById('timeValue').value : document.getElementById('intervalValue').value;
    
    localStorage.setItem('backup_type', type);
    localStorage.setItem('backup_val', val);

    let url = `setup-cron.php?type=${type}`;
    url += (type === 'daily') ? `&time=${val}` : `&interval=${val}`;

    document.getElementById('syncStatus').innerText = "Đang đồng bộ...";
    document.getElementById('syncStatus').className = "status-badge bg-warning text-dark";

    fetch(url)
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                document.getElementById('syncStatus').innerText = "Đã đồng bộ Windows";
                document.getElementById('syncStatus').className = "status-badge bg-success";
                resetTimer();
            } else if(data.status === 'info') {
                document.getElementById('syncStatus').innerText = "Cần tạo lịch thủ công";
                document.getElementById('syncStatus').className = "status-badge bg-info text-dark";
                
                // Hiển thị hướng dẫn trực quan trong Console để dễ dàng xem
                const consoleBox = document.getElementById('console');
                consoleBox.innerHTML = `<span class="text-info font-monospace">\n=== HƯỚNG DẪN CẤU HÌNH LỊCH TRÌNH ===\n${data.message}\n==================================\n</span>\n` + consoleBox.innerHTML;
                
                // Hiển thị khung hướng dẫn cùng nút COPY một chạm tiện lợi
                document.getElementById('fileInfo').innerHTML = `
                    <div class="card p-3 border-info mb-3 text-dark bg-light text-start" style="font-size: 12.5px;">
                        <h6 class="text-info fw-bold mb-2"><i class="bi bi-info-circle-fill me-1"></i> HƯỚNG DẪN ĐẶT LỊCH TỰ ĐỘNG</h6>
                        <p class="mb-2 text-muted">Hệ thống đang chạy trong Docker Linux, vui lòng tự cấu hình chạy tự động bằng 1 trong 2 lệnh tương ứng dưới đây:</p>
                        
                        <div class="mb-2">
                            <span class="badge bg-primary mb-1">1. Trên Windows Local (CMD Admin)</span>
                            <div class="input-group">
                                <input type="text" id="winCmdInput" class="form-control form-control-sm font-monospace border-secondary text-dark" readonly style="background:#e9ecef;">
                                <button class="btn btn-sm btn-dark font-monospace" onclick="copyToClipboard('winCmdInput')">Copy</button>
                            </div>
                        </div>
                        
                        <div>
                            <span class="badge bg-dark mb-1">2. Trên Linux VPS (Cron Job)</span>
                            <div class="input-group">
                                <input type="text" id="linuxCronInput" class="form-control form-control-sm font-monospace border-secondary text-dark" readonly style="background:#e9ecef;">
                                <button class="btn btn-sm btn-dark font-monospace" onclick="copyToClipboard('linuxCronInput')">Copy</button>
                            </div>
                        </div>
                    </div>`;
                
                // Gán giá trị an toàn qua thuộc tính value của DOM để tránh lỗi vỡ chuỗi do nháy kép "
                document.getElementById('winCmdInput').value = data.windows_cmd || '';
                document.getElementById('linuxCronInput').value = data.cron_line || '';
            } else {
                alert("Lỗi: " + data.message);
            }
        });
});

document.getElementById('runNow').addEventListener('click', runBackupProcess);



// Khởi chạy
resetTimer();
</script>
</body>
</html>