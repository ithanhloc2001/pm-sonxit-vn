<?php
// Cleanup script for VNPAY IPN logs
// Mục đích: giữ lại log tối thiểu 2 tháng, mặc định xóa log cũ hơn 6 tháng.
// NÊN chạy script này bằng cron qua CLI, ví dụ:
//   php core_admin/vnpay/cleanup_ipn_log.php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "Forbidden - CLI only" . PHP_EOL;
    exit(1);
}
require_once __DIR__ . '/../../config.php';

$table = 'vnpay_ipn_log';
$cols = listColumns($ithanhloc, $table);
if (!$cols) {
    echo "[WARN] Table {$table} not found or cannot list columns." . PHP_EOL;
    exit(0);
}

// Đảm bảo có cột created_at để làm mốc thời gian xóa log cũ
if (!hasCol($cols, 'created_at')) {
    echo "[INFO] Adding created_at column to {$table}..." . PHP_EOL;
    $alterSql = "ALTER TABLE `{$table}` ADD COLUMN `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP AFTER `raw_payload`";
    if (!$ithanhloc->query($alterSql)) {
        echo "[ERROR] Failed to add created_at column: " . $ithanhloc->error . PHP_EOL;
        // Không dừng toàn bộ script, nhưng cũng không cố xóa theo thời gian nếu không có cột.
    } else {
        echo "[OK] created_at column added." . PHP_EOL;
        // Re-cache columns after alter
        $cols = listColumns($ithanhloc, $table, true);
    }
}

if (!in_array('created_at', $cols, true)) {
    echo "[WARN] created_at column is not available; cannot apply time-based cleanup." . PHP_EOL;
    exit(0);
}

// Xóa log cũ hơn 6 tháng (vẫn thỏa điều kiện giữ tối thiểu 2 tháng)
$deleteSql = "DELETE FROM `{$table}` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 6 MONTH)";
$result = $ithanhloc->query($deleteSql);

if ($result === false) {
    echo "[ERROR] Failed to delete old logs: " . $ithanhloc->error . PHP_EOL;
    exit(1);
}

$affected = $ithanhloc->affected_rows;
echo "[OK] Deleted {$affected} old VNPAY IPN log rows (older than 6 months)." . PHP_EOL;

exit(0);
