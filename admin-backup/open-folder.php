<?php
// Xóa sạch đệm rác
while (ob_get_level()) ob_end_clean();
header('Content-Type: application/json');

$backup_dir = 'C:\KUNLOC\diy\backups'; // fallback mặc định mới

// Đọc đường dẫn từ config
$config_file = 'backup-config.json';
if (file_exists($config_file)) {
    $config_data = json_decode(file_get_contents($config_file), true);
    if (!empty($config_data['code_dest'])) {
        $backup_dir = $config_data['code_dest'];
    }
}

$isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

if ($isWindows) {
    if (is_dir($backup_dir)) {
        // Lệnh explorer.exe sẽ mở cửa sổ thư mục trên màn hình máy tính Windows
        shell_exec("explorer.exe " . escapeshellarg($backup_dir));
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Thư mục không tồn tại: ' . $backup_dir]);
    }
} else {
    // Trên Linux/Docker, trả về link thư mục web để mở tab mới
    echo json_encode([
        'status' => 'success',
        'redirect_url' => '/backups/'
    ]);
}
exit;