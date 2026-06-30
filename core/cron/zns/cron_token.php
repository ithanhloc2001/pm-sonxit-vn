<?php
/**
 * Cronjob: Tự động làm mới Zalo ZNS Access Token / Refresh Token (Chuyển tiếp qua cron.php chung)
 */
$zns_cron_config = [
    'refresh_token'   => true,
    'order_confirm'   => false,
    'order_shipping'  => false,
];
require_once __DIR__ . '/cron.php';
