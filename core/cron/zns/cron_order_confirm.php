<?php
/**
 * Cronjob: Tự động gửi thông báo ZNS xác nhận đơn hàng (Chuyển tiếp qua cron.php chung)
 */
$zns_cron_config = [
    'refresh_token'   => false,
    'order_confirm'   => true,
    'order_shipping'  => false,
];
require_once __DIR__ . '/cron.php';
