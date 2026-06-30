<?php
require_once __DIR__ . '/../../../config.php';
header('Content-Type: application/json; charset=utf-8');

$action = trim((string)($_GET['action'] ?? ''));

// Danh sách cửa hàng đang hoạt động (cho modal "Cửa hàng gần bạn").
if ($action === 'list_stores') {
    $stores = [];
    $res = $ithanhloc->query("SELECT id, branch_name, region, address_detail, hotline, map_url, avatar_image, opening_hours_json
                              FROM site_store WHERE is_active = 1 ORDER BY (sort_order+0) ASC, id ASC");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $stores[] = [
                'id'             => (int)$row['id'],
                'branch_name'    => trim((string)($row['branch_name'] ?? '')),
                'region'         => trim((string)($row['region'] ?? '')),
                'address_detail' => trim((string)($row['address_detail'] ?? '')),
                'hotline'        => trim((string)($row['hotline'] ?? '')),
                'map_url'        => trim((string)($row['map_url'] ?? '')),
                'avatar_image'   => trim((string)($row['avatar_image'] ?? '')),
                'opening_hours'  => trim((string)($row['opening_hours_json'] ?? '')),
            ];
        }
    }
    echo json_encode(['ok' => true, 'stores' => $stores], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($action === 'get_details') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        echo json_encode(['ok' => false, 'msg' => 'ID không hợp lệ']);
        exit;
    }

    $res = $ithanhloc->query("SELECT * FROM site_store WHERE id = $id AND is_active = 1 LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) {
        // Sanitize sensitive data if any (though site_store is public info)
        echo json_encode([
            'ok' => true, 
            'data' => [
                'id' => (int)$row['id'],
                'branch_name' => $row['branch_name'],
                'address_detail' => $row['address_detail'],
                'hotline' => $row['hotline'],
                'avatar_image' => $row['avatar_image'],
                'gallery_images_json' => $row['gallery_images_json'],
                'opening_hours_json' => $row['opening_hours_json'],
                'map_url' => $row['map_url']
            ]
        ], JSON_UNESCAPED_UNICODE);
    } else {
        echo json_encode(['ok' => false, 'msg' => 'Không tìm thấy chi nhánh']);
    }
    exit;
}

echo json_encode(['ok' => false, 'msg' => 'Hành động không hợp lệ']);
