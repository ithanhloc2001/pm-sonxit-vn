<?php
/**
 * Cronjob: Tự động cập nhật trạng thái đơn hàng (Chuyển tiếp qua cron_orders.php chung)
 */
$cron_orders_config = [
    'n8n_orders'       => false,
    'order_status'     => true,
    'complete_orders'  => false,
];
require_once __DIR__ . '/cron_orders.php';
