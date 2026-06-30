<?php
require_once __DIR__ . '/../config.php';

$res = $ithanhloc->query("SELECT id, product_id, variant_name, sku_variant, image_url FROM ecommerce_product_variants WHERE product_id = 116");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Query failed";
}
