<?php
// Xóa sạch bộ đệm để đảm bảo đầu ra chỉ là JSON sạch
while (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json');

$type = $_GET['type'] ?? 'minute';
$task_name = "DIY_Project_Backup";

// Đọc đường dẫn mã nguồn từ config để xây dựng đường dẫn chạy file .bat tuyệt đối trên Windows
$code_source = 'C:\KUNLOC\diy\www';
$config_file = __DIR__ . DIRECTORY_SEPARATOR . 'backup-config.json';
if (file_exists($config_file)) {
    $config_data = json_decode(file_get_contents($config_file), true);
    if (!empty($config_data['code_source'])) {
        $code_source = $config_data['code_source'];
    }
}
$bat_dir = rtrim($code_source, '\\/') . '\admin-backup';
$windows_bat_path = $bat_dir . '\backup.bat';

// Task Scheduler chay task voi CWD = C:\Windows\System32, nen ta boc trong cmd /c cd /d <dir>
// va truyen tham so "auto" de bat KHONG mo Explorer khi chay nen.
$tr = 'cmd /c cd /d \\"' . $bat_dir . '\\" && \\"' . $windows_bat_path . '\\" auto';

$cmd = "";
$modifier = "";
$interval = "";
$time = "";

if ($type === 'minute' || $type === 'hour') {
    $interval = isset($_GET['interval']) ? (int)$_GET['interval'] : 60;
    $modifier = ($type === 'minute') ? "MINUTE" : "HOURLY";
    $cmd = "schtasks /create /sc $modifier /mo $interval /tn \"$task_name\" /tr \"$tr\" /rl HIGHEST /f 2>&1";
} elseif ($type === 'daily') {
    $time = $_GET['time'] ?? '02:00';
    $cmd = "schtasks /create /sc daily /st $time /tn \"$task_name\" /tr \"$tr\" /rl HIGHEST /f 2>&1";
}

// Kiểm tra hệ điều hành đang chạy PHP
$isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

if (!$isWindows) {
    // 1. Xác định URL của file run-backup.php trên server
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'];
    $cron_url = "$scheme://$host/admin-backup/run-backup.php";
    
    // 2. Xây dựng cấu trúc dòng Cron
    $cron_time = "* * * * *";
    if ($type === 'minute') {
        $interval = isset($_GET['interval']) ? (int)$_GET['interval'] : 60;
        $cron_time = "*/$interval * * * *";
    } elseif ($type === 'hour') {
        $interval = isset($_GET['interval']) ? (int)$_GET['interval'] : 1;
        $cron_time = "0 */$interval * * *";
    } elseif ($type === 'daily') {
        $time = $_GET['time'] ?? '02:00';
        list($hour, $minute) = explode(':', $time);
        $hour = (int)$hour;
        $minute = (int)$minute;
        $cron_time = "$minute $hour * * *";
    }
    
    $cron_line = "$cron_time curl -s \"$cron_url\" >/dev/null 2>&1";
    
    // Nếu chạy trong Docker (Linux), hướng dẫn người dùng cả 2 phương án (Local Windows vs Production Linux VPS)
    $msg = "Hệ thống Web (PHP) đang chạy bên trong Docker Container (môi trường Linux ảo), nên không thể tự động gọi lệnh đặt lịch của máy Windows bên ngoài.\n\n";
    $msg .= "👉 NẾU BẠN ĐANG CHẠY LOCAL TRÊN MÁY WINDOWS:\n";
    $msg .= "Vui lòng mở Command Prompt (Run as Administrator) trên Windows và chạy lệnh này để máy tự động backup:\n\n";
    $msg .= $cmd . "\n\n";
    $msg .= "--------------------------------------------------\n";
    $msg .= "👉 NẾU ĐÂY LÀ MÁY CHỦ LIVE LINUX (VPS/PRODUCTION):\n";
    $msg .= "Cấu hình Cron Job trên VPS bằng cách chạy `crontab -e` và dán dòng:\n\n";
    $msg .= $cron_line;

    echo json_encode([
        'status' => 'info',
        'message' => $msg,
        'windows_cmd' => $cmd,
        'cron_line' => $cron_line
    ]);
    exit;
}

// 1. Xóa Task cũ nếu đã tồn tại để cập nhật lịch mới
exec("schtasks /delete /tn \"$task_name\" /f 2>nul");

// 2. Thực thi lệnh Windows Task Scheduler
exec($cmd, $output, $return_var);

if ($return_var === 0) {
    echo json_encode([
        'status'  => 'success',
        'message' => "Lịch đã được thiết lập thành công trong Windows Task Scheduler.\n"
                   . "Tên task: \"$task_name\" — sẽ tự chạy backup.bat (auto) và lưu .loc vào "
                   . ($config_data['code_dest'] ?? 'D:\\XAMPP_Backups') . "."
    ]);
} else {
    $raw = trim(implode(" ", $output));
    // Access denied (5) hoac quyen khong du khi Apache khong chay bang Admin
    $is_perm = (stripos($raw, 'denied') !== false || stripos($raw, 'Access') !== false
              || stripos($raw, 'quyền') !== false || $return_var == 1);
    $hint = $is_perm
        ? "Apache/PHP đang chạy KHÔNG có quyền Administrator nên không tạo được Task. "
        . "Cách khắc phục: mở Command Prompt (Run as Administrator) và dán lệnh sau:\n\n$cmd"
        : "Chi tiết: $raw";
    echo json_encode([
        'status'      => 'error',
        'message'     => 'Không thể tạo lịch tự động. ' . $hint,
        'windows_cmd' => $cmd
    ]);
}
exit;