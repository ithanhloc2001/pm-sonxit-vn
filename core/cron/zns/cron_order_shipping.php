<?php
/**
 * Cronjob: Tự động gửi thông báo ZNS cập nhật trạng thái đơn hàng (Chuyển tiếp qua cron.php chung)
 */
$zns_cron_config = [
    'refresh_token'   => false,
    'order_confirm'   => false,
    'order_shipping'  => true,
];
require_once __DIR__ . '/cron.php';
