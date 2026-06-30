<?php
require_once __DIR__ . '/../config.php';
$res = $ithanhloc->query("SELECT product_name, slug FROM ecommerce_product WHERE id = 116");
print_r($res->fetch_assoc());
