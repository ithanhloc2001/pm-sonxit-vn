<?php
// Tắt mọi cảnh báo lỗi hiển thị ra màn hình để tránh làm hỏng JSON
error_reporting(0);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Ho_Chi_Minh');

// Giải phóng bộ nhớ đệm
while (ob_get_level()) {
    ob_end_clean();
}
header('Content-Type: application/json');

$bat_path = __DIR__ . DIRECTORY_SEPARATOR . 'backup.bat';
$backup_dir = 'D:\XAMPP_Backups'; // fallback mặc định

// Đọc đường dẫn lưu file nén từ config
$config_file = __DIR__ . DIRECTORY_SEPARATOR . 'backup-config.json';
if (file_exists($config_file)) {
    $config_data = json_decode(file_get_contents($config_file), true);
    if (!empty($config_data['code_dest'])) {
        $backup_dir = $config_data['code_dest'];
    }
}

$res = [
    'status' => 'error',
    'message' => 'Unknown error',
    'output' => '',
    'backup_path' => $backup_dir,
    'latest_file' => ''
];

$isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');

if ($isWindows) {
    // -------------------------------------------------------------
    // CHẠY TRÊN WINDOWS HOST (Dùng file BAT tối ưu)
    // -------------------------------------------------------------
    if (!file_exists($bat_path)) {
        $res['message'] = 'Khong tim thay file .bat tai: ' . $bat_path;
        echo json_encode($res);
        exit;
    }

    // Chay .bat voi working directory = thu muc chua bat (de %~dp0 va duong dan tuong doi dung)
    // Dung cmd /c "cd /d <dir> && bat" de chac chan CWD dung du PHP duoc goi tu dau
    $bat_dir = __DIR__;
    $cmd = 'cmd /c "cd /d ' . escapeshellarg($bat_dir) . ' && '
         . escapeshellarg($bat_path) . ' auto" 2>&1';
    $output = shell_exec($cmd);
    $res['status'] = 'success';
    $res['output'] = $output;
} else {
    // -------------------------------------------------------------
    // CHẠY TRÊN LINUX (DOCKER / LIVE SERVER SONXIT.VN)
    // -------------------------------------------------------------
    $source_code = dirname(__DIR__); // Lấy thư mục gốc chứa source code
    
    // Nếu đích đến là đường dẫn Windows (ví dụ C:\ hoặc D:\), tự động đổi về thư mục local backups trong container
    $linux_backup_dir = $backup_dir;
    if (preg_match('/^[a-zA-Z]:/', $linux_backup_dir) || strpos($linux_backup_dir, '\\') !== false) {
        $linux_backup_dir = $source_code . '/backups';
    }
    
    if (!is_dir($linux_backup_dir)) {
        mkdir($linux_backup_dir, 0777, true);
    }
    
    $filename_time = date('H_i_s_\b\y_d_m_Y');
    $zip_prefix = isset($config_data['code_zip_prefix']) ? $config_data['code_zip_prefix'] : 'backup_diy_';
    $filename = $zip_prefix . $filename_time . '.tar.gz';
    $masked_filename = $zip_prefix . $filename_time . '.loc';
    
    $output_log = "==========================================\n";
    $output_log .= "THONG TIN BACKUP LINUX/DOCKER:\n";
    $output_log .= "- Source Code: $source_code\n";
    $output_log .= "- Backup Dest: $linux_backup_dir\n";
    $output_log .= "- Output File: $masked_filename\n";
    $output_log .= "==========================================\n";
    
    // 1. Xuất SQL bằng PHP thuần (không phụ thuộc mysqldump client)
    $enable_sql = isset($config_data['enable_sql']) ? $config_data['enable_sql'] : true;
    $sql_file_path = $source_code . '/admin-backup/db_backup.sql';
    
    if ($enable_sql) {
        $output_log .= "[1/3] Dang xuat database SQL bang PHP...\n";
        
        require_once $source_code . '/config.php';
        if (!isset($ithanhloc) || !($ithanhloc instanceof mysqli)) {
            $res['message'] = 'Khong the ket noi database tu config.php';
            echo json_encode($res);
            exit;
        }
        
        $dump_success = mysqli_dump_db_native($ithanhloc, $sql_file_path);
        if (!$dump_success) {
            $res['message'] = 'Gặp lỗi trong quá trình kết xuất database SQL';
            echo json_encode($res);
            exit;
        }
        $output_log .= "[OK] Xuat SQL thanh cong.\n";
    }
    
    // 2. Nén source code (Loại trừ uploads và chính folder backups)
    $enable_code = isset($config_data['enable_code']) ? $config_data['enable_code'] : true;
    if ($enable_code) {
        $output_log .= "[2/3] Dang nen source code va SQL bang tar...\n";
        
        $tar_dest = $linux_backup_dir . '/' . $filename;
        $cmd = "tar -czf " . escapeshellarg($tar_dest) . " -C " . escapeshellarg($source_code) . " --exclude='./uploads' --exclude='./backups' . 2>&1";
        $tar_out = shell_exec($cmd);
        if (!empty($tar_out)) {
            $output_log .= $tar_out . "\n";
        }
        
        // Xóa file SQL tạm sau khi nén xong
        if (file_exists($sql_file_path)) {
            unlink($sql_file_path);
        }
        
        // 3. Đổi đuôi file nén thành .loc
        $loc_dest = $linux_backup_dir . '/' . $masked_filename;
        if (file_exists($tar_dest)) {
            rename($tar_dest, $loc_dest);
            $output_log .= "[3/3] Dang doi duoi file: $masked_filename\n";
            $output_log .= "[OK] Backup hoan tat.\n";
            
            $res['status'] = 'success';
            $res['output'] = $output_log;
            $res['latest_file'] = $masked_filename;
            $res['backup_path'] = $linux_backup_dir;
        } else {
            $res['message'] = 'Lỗi nén file tar.gz tại: ' . $tar_dest . "\nLệnh: " . $cmd . "\nChi tiết lỗi: " . $tar_out;
            echo json_encode($res);
            exit;
        }
    }
}

$is_local_windows_docker = false;
if (!$isWindows && !empty($config_data['code_dest'])) {
    if (preg_match('/^[a-zA-Z]:/', $config_data['code_dest']) || strpos($config_data['code_dest'], '\\') !== false) {
        $is_local_windows_docker = true;
        $res['is_local_windows_docker'] = true;
        $res['windows_dest'] = $config_data['code_dest'];
    }
}

// Quét tìm file mới nhất theo thời gian sửa đổi (filemtime)
if (is_dir($res['backup_path'])) {
    $latest_time = 0;
    $latest_file = '';
    $files = scandir($res['backup_path']);
    foreach($files as $file) {
        if (strpos($file, '.loc') !== false) {
            $file_path = $res['backup_path'] . '/' . $file;
            $mtime = filemtime($file_path);
            if ($mtime > $latest_time) {
                $latest_time = $mtime;
                $latest_file = $file;
            }
        }
    }
    if (!empty($latest_file)) {
        $res['latest_file'] = $latest_file;
    }
}

echo json_encode($res);
exit;

// Hàm tự viết kết xuất Database sang file SQL (Hỗ trợ cấu trúc & dữ liệu)
function mysqli_dump_db_native($mysqli, $output_file) {
    $fp = fopen($output_file, 'w');
    if (!$fp) return false;

    fwrite($fp, "-- Database Dump (PHP Native Dumper)\n");
    fwrite($fp, "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n");
    fwrite($fp, "SET FOREIGN_KEY_CHECKS=0;\n\n");

    $tables = [];
    $result = $mysqli->query("SHOW TABLES");
    if (!$result) {
        fclose($fp);
        return false;
    }
    
    while ($row = $result->fetch_row()) {
        $tables[] = $row[0];
    }

    foreach ($tables as $table) {
        // Cấu trúc bảng
        $res = $mysqli->query("SHOW CREATE TABLE `$table`");
        if ($res) {
            $row = $res->fetch_row();
            fwrite($fp, "DROP TABLE IF EXISTS `$table`;\n");
            fwrite($fp, $row[1] . ";\n\n");
        }

        // Dữ liệu bảng
        $res = $mysqli->query("SELECT * FROM `$table`");
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $vals = [];
                foreach ($row as $key => $val) {
                    if ($val === null) {
                        $vals[] = 'NULL';
                    } else {
                        $vals[] = "'" . $mysqli->real_escape_string($val) . "'";
                    }
                }
                fwrite($fp, "INSERT INTO `$table` VALUES (" . implode(', ', $vals) . ");\n");
            }
        }
        fwrite($fp, "\n");
    }

    fwrite($fp, "SET FOREIGN_KEY_CHECKS=1;\n");
    fclose($fp);
    return true;
}