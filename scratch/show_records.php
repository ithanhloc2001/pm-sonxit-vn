<?php
require_once __DIR__ . '/../config.php';

echo "<h2>Some variants:</h2>";
$res = $ithanhloc->query("SELECT * FROM ecommerce_product_variants WHERE variant_name <> '' LIMIT 5");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        echo "<pre>";
        print_r($row);
        echo "</pre>";
    }
}

echo "<h2>Some orders products_json:</h2>";
$res2 = $ithanhloc->query("SELECT order_id, products_json FROM ecommerce_order WHERE products_json <> '' LIMIT 3");
if ($res2) {
    while ($row = $res2->fetch_assoc()) {
        echo "<h3>Order {$row['order_id']}:</h3>";
        echo "<pre>{$row['products_json']}</pre>";
    }
}
