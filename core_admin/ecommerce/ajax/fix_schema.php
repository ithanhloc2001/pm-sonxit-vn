<?php
require_once __DIR__ . '/../../../core_admin/_admin_guard.php';

$cols = [];
$res = $ithanhloc->query("SHOW COLUMNS FROM ecommerce_order");
while ($r = $res->fetch_assoc()) {
    $cols[] = $r['Field'];
}

if (!in_array('refunded_at', $cols)) {
    echo "Adding refunded_at column...\n";
    $ithanhloc->query("ALTER TABLE ecommerce_order ADD COLUMN refunded_at DATETIME NULL AFTER return_resolved_at");
}

if (!in_array('return_requested_at', $cols)) {
    echo "Adding return_requested_at column...\n";
    $ithanhloc->query("ALTER TABLE ecommerce_order ADD COLUMN return_requested_at DATETIME NULL AFTER delivered_at");
}

if (!in_array('canceled_reason', $cols)) {
    echo "Adding canceled_reason column...\n";
    $ithanhloc->query("ALTER TABLE ecommerce_order ADD COLUMN canceled_reason TEXT NULL AFTER canceled_at");
}

echo "Done.\n";
