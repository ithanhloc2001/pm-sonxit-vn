<?php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = [
    'action' => 'save_address',
    'address_id' => '',
    'region' => 'Miền Bắc',
    'branch_id' => '0',
    'street' => '73 Võ Nguyên Giáp',
    'province_id' => '202',
    'district_id' => '1443',
    'ward_code' => '20210',
    'ward' => 'Thảo Điền',
    'district' => 'Quận 2',
    'province' => 'Hồ Chí Minh',
    'contact_phone' => '0987654321',
    'recipient_name' => 'PaintMore Test User',
    'address_type' => 'home',
    'delivery_note' => 'Giao gio hanh chinh',
    'customer_lat' => '',
    'customer_lng' => ''
];

// Mock session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1;

ob_start();
require_once __DIR__ . '/../main/account/region-session.php';
$output = ob_get_clean();

echo "OUTPUT:\n";
echo $output . "\n";
echo "Database Error (if any): " . $ithanhloc->error . "\n";
