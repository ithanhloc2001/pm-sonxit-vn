<?php
/**
 * Shared configuration for Zalo ZNS.
 * Loads settings from the `site_zns_conf` table into $zalo_config (tokens) and $templateId (template IDs).
 *
 * Template payload reference:
 *   ORDER_CONFIRM   : order_code, date, price, name, phone_number, status
 *   ORDER_SHIPPING  : order_code, order_status, user_name, created_at
 *   OTP             : otp
 *   REVIEW_SERVICE  : ma_don_hang, ten_khach_hang, ngay_giao_dich
 */

$zalo_config = [];
$templateId  = [];

// Ensure a DB connection ($ithanhloc) exists — include the root config if missing.
if (!isset($ithanhloc) || !($ithanhloc instanceof mysqli)) {
    $rootConfig = __DIR__ . '/../../config.php';
    if (file_exists($rootConfig)) {
        require_once $rootConfig;
    }
}

if (!isset($ithanhloc) || !($ithanhloc instanceof mysqli)) {
    return;
}

// Create the table if it does not exist.
$ithanhloc->query("CREATE TABLE IF NOT EXISTS `site_zns_conf` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `setting_key` VARCHAR(64) NOT NULL,
  `setting_value` TEXT NOT NULL,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_zns_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Fix legacy tables missing AUTO_INCREMENT (causes "Field 'id' doesn't have a default value" on INSERT).
$colRes = @$ithanhloc->query("SHOW COLUMNS FROM `site_zns_conf` LIKE 'id'");
if ($colRes instanceof mysqli_result) {
    $col = $colRes->fetch_assoc();
    $colRes->close();
    if ($col && stripos((string)($col['Extra'] ?? ''), 'auto_increment') === false) {
        @$ithanhloc->query("ALTER TABLE `site_zns_conf` MODIFY `id` INT NOT NULL AUTO_INCREMENT");
    }
}

// Load settings: tokens into $zalo_config, everything else as template IDs into $templateId.
$tokenKeys = ['app_id', 'secret_key', 'accessToken', 'refreshToken'];
$res = $ithanhloc->query("SELECT setting_key, setting_value FROM site_zns_conf");
if ($res instanceof mysqli_result) {
    while ($row = $res->fetch_assoc()) {
        $k = (string)($row['setting_key'] ?? '');
        if ($k === '') continue;
        $v = (string)($row['setting_value'] ?? '');
        if (in_array($k, $tokenKeys, true)) {
            $zalo_config[$k] = $v;
        } else {
            $templateId[$k] = $v;
        }
    }
    $res->close();
}
