<?php
/**
 * DATABASE STRUCTURE BACKUP SCRIPT (MySQLi Version)
 * Tự động dùng kết nối từ config.php
 */

// Bắt đầu bộ đệm để tránh lỗi header nếu config.php lỡ có khoảng trắng thừa
ob_start();

// ==================================================================
// 1. CẤU HÌNH BẢO MẬT
// ==================================================================
$access_key = '123456'; // <--- Đổi pass này nếu muốn

if (!isset($_GET['key']) || $_GET['key'] !== $access_key) {
    die(json_encode(['error' => 'Access Denied', 'msg' => 'Missing or invalid key']));
}

// ==================================================================
// 2. KẾT NỐI (SỬ DỤNG $ithanhloc TỪ CONFIG CỦA BẠN)
// ==================================================================

// Đường dẫn tới file config (cùng thư mục)
$configFile = 'config.php';

if (!file_exists($configFile)) {
    die("Không tìm thấy file config.php");
}

// Gọi file config để lấy biến $ithanhloc
require_once($configFile);

// Kiểm tra xem biến $ithanhloc có tồn tại và kết nối thành công không
if (!isset($ithanhloc) || !($ithanhloc instanceof mysqli)) {
    die("Lỗi: Không tìm thấy biến \$ithanhloc hoặc kết nối không hợp lệ trong config.php");
}

// Lấy tên Database hiện tại để đặt tên file
$result = $ithanhloc->query("SELECT DATABASE()");
$row = $result->fetch_row();
$db_name = $row[0];

// ==================================================================
// 3. XỬ LÝ LẤY CẤU TRÚC (CREATE TABLE)
// ==================================================================

$sqlScript = "";
$sqlScript .= "-- Database Structure Backup: $db_name\n";
$sqlScript .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";
$sqlScript .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

// Lấy danh sách bảng
$tables = [];
$result = $ithanhloc->query("SHOW TABLES");
while ($row = $result->fetch_row()) {
    $tables[] = $row[0];
}

// Lặp qua từng bảng để lấy câu lệnh CREATE
foreach ($tables as $table) {
    $result = $ithanhloc->query("SHOW CREATE TABLE `$table`");
    $row = $result->fetch_row();
    
    // $row[1] chứa lệnh Create Table
    if (isset($row[1])) {
        $sqlScript .= "-- --------------------------------------------------------\n";
        $sqlScript .= "-- Structure for table `$table`\n";
        $sqlScript .= "-- --------------------------------------------------------\n";
        $sqlScript .= "DROP TABLE IF EXISTS `$table`;\n";
        $sqlScript .= $row[1] . ";\n\n";
    }
}

$sqlScript .= "SET FOREIGN_KEY_CHECKS=1;\n";

// ==================================================================
// 4. XUẤT FILE TẢI VỀ
// ==================================================================

// Xóa bộ đệm đầu ra để file sạch sẽ
ob_end_clean();

$filename = 'structure_' . $db_name . '_' . date('Ymd_His') . '.sql';

header('Content-Type: application/octet-stream');
header("Content-Transfer-Encoding: Binary");
header("Content-disposition: attachment; filename=\"" . $filename . "\"");
echo $sqlScript;
exit;
?>