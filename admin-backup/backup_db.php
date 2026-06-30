<?php
// Include config để kết nối database
include 'config.php';

// Mảng để lưu cấu trúc database
$db_structure = array();

// Lấy danh sách tất cả các bảng
$result = $ithanhloc->query("SHOW TABLES");
if ($result) {
    while ($row = $result->fetch_array()) {
        $table_name = $row[0];
        
        // Lấy cấu trúc của từng bảng
        $describe_result = $ithanhloc->query("DESCRIBE `$table_name`");
        $columns = array();
        
        if ($describe_result) {
            while ($col = $describe_result->fetch_assoc()) {
                $columns[] = array(
                    'Field' => $col['Field'],
                    'Type' => $col['Type'],
                    'Null' => $col['Null'],
                    'Key' => $col['Key'],
                    'Default' => $col['Default'],
                    'Extra' => $col['Extra']
                );
            }
        }
        
        $db_structure[$table_name] = $columns;
    }
}

// Xuất ra file JSON
$json_output = json_encode($db_structure, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents('database-backup.json', $json_output);

echo "Cấu trúc database đã được xuất ra file database-backup.json";
?>