<?php
/**
 * Cronjob: Tự động hoàn thành đơn hàng (Chuyển tiếp qua cron_orders.php chung)
 */
$cron_orders_config = [
    'n8n_orders'       => false,
    'order_status'     => false,
    'complete_orders'  => true,
];
require_once __DIR__ . '/cron_orders.php';
