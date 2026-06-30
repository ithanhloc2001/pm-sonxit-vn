<?php
require_once __DIR__ . '/../config.php';
$res = $ithanhloc->query("DESCRIBE user_address");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        print_r($row);
    }
} else {
    echo "Query failed: " . $ithanhloc->error . "\n";
}
