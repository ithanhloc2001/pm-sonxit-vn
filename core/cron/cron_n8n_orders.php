<?php
/**
 * Cronjob: Đồng bộ đơn hàng mới qua Webhook n8n (Chuyển tiếp qua cron_orders.php chung)
 */
$cron_orders_config = [
    'n8n_orders'       => true,
    'order_status'     => false,
    'complete_orders'  => false,
];
require_once __DIR__ . '/cron_orders.php';
