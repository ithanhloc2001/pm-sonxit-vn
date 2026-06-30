<?php
require_once __DIR__ . '/../config.php';
$res = $ithanhloc->query("SELECT * FROM support_ticket ORDER BY id DESC LIMIT 5");
while ($row = $res->fetch_assoc()) {
    print_r($row);
}
