<?php require_once __DIR__ . '/../../../config.php'; ?>
<?php
if (empty($isAdmin)) {
    jOut(['ok' => false, 'msg' => 'Chức năng này chỉ dành cho quản trị viên.']);
}
?>
<?php
function jOutShippingRule($data, int $status = 200): void {
    while (ob_get_level()) {
        @ob_end_clean();
    }
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$parseNullableFloat = static function ($raw): ?float {
    $txt = trim((string)$raw);
    if ($txt === '') {
        return null;
    }
    if (!is_numeric($txt)) {
        return null;
    }
    return (float)$txt;
};

$parseNullableInt = static function ($raw): ?int {
    $txt = trim((string)$raw);
    if ($txt === '') {
        return null;
    }
    if (!is_numeric($txt)) {
        return null;
    }
    return (int)$txt;
};

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'list') {
    $rows = [];
    $res = $ithanhloc->query('SELECT * FROM ecommerce_shipping_rule ORDER BY is_active DESC, priority DESC, id DESC');
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $rows[] = $row;
        }
    }
    jOutShippingRule(['ok' => true, 'items' => $rows]);
}

if ($action === 'get') {
    $id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
    if ($id <= 0) {
        jOutShippingRule(['ok' => false, 'msg' => 'Thiếu ID'], 400);
    }
    $res = $ithanhloc->query('SELECT * FROM ecommerce_shipping_rule WHERE id = ' . $id . ' LIMIT 1');
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        jOutShippingRule(['ok' => true, 'item' => $row]);
    }
    jOutShippingRule(['ok' => false, 'msg' => 'Không tìm thấy rule'], 404);
}

if ($action === 'save') {
    $id = (int)($_POST['id'] ?? 0);
    $isActive = isset($_POST['is_active']) && (int)$_POST['is_active'] ? 1 : 0;
    // Phương thức chỉ cho phép tieu_chuan để tránh nhầm với GHN
    $methodKey = 'tieu_chuan';
    $region = trim((string)($_POST['region'] ?? ''));
    $districtId = $parseNullableInt($_POST['district_id'] ?? '');
    $minSubtotal = $parseNullableFloat($_POST['min_subtotal'] ?? '');
    $maxSubtotal = $parseNullableFloat($_POST['max_subtotal'] ?? '');
    $minWeightGram = $parseNullableInt($_POST['min_weight_gram'] ?? '');
    $maxWeightGram = $parseNullableInt($_POST['max_weight_gram'] ?? '');
    $fee = $parseNullableInt($_POST['fee'] ?? '');
    $priority = $parseNullableInt($_POST['priority'] ?? '');
    $note = trim((string)($_POST['note'] ?? ''));

    $errors = [];
    if ($fee === null || $fee < 0) {
        $errors[] = 'Vui lòng nhập phí vận chuyển hợp lệ (>= 0).';
    }
    if ($priority === null || $priority < 0) {
        $priority = 0;
    }
    if ($minSubtotal !== null && $maxSubtotal !== null && $minSubtotal > $maxSubtotal) {
        $errors[] = 'Đơn tối thiểu không được lớn hơn Đơn tối đa.';
    }
    if ($minWeightGram !== null && $maxWeightGram !== null && $minWeightGram > $maxWeightGram) {
        $errors[] = 'Khối lượng tối thiểu không được lớn hơn Khối lượng tối đa.';
    }

    if ($errors) {
        jOutShippingRule(['ok' => false, 'msg' => implode('\n', $errors)], 400);
    }

    $isActiveVal = $isActive ? 1 : 0;
    $methodSql = "'" . $ithanhloc->real_escape_string($methodKey) . "'";
    $regionSql = $region === '' ? 'NULL' : ("'" . $ithanhloc->real_escape_string($region) . "'");
    $districtSql = $districtId !== null && $districtId > 0 ? (string)(int)$districtId : 'NULL';
    $minSubtotalSql = $minSubtotal !== null ? (string)$minSubtotal : 'NULL';
    $maxSubtotalSql = $maxSubtotal !== null ? (string)$maxSubtotal : 'NULL';
    $minWeightSql = $minWeightGram !== null ? (string)(int)$minWeightGram : 'NULL';
    $maxWeightSql = $maxWeightGram !== null ? (string)(int)$maxWeightGram : 'NULL';
    $feeSql = (string)(int)max(0, (int)$fee);
    $prioritySql = (string)(int)max(0, (int)$priority);
    $noteSql = $note === '' ? 'NULL' : ("'" . $ithanhloc->real_escape_string($note) . "'");

    if ($id > 0) {
        $sql = "UPDATE ecommerce_shipping_rule SET "
            . "is_active = {$isActiveVal}, "
            . "method_key = {$methodSql}, "
            . "region = {$regionSql}, "
            . "district_id = {$districtSql}, "
            . "min_subtotal = {$minSubtotalSql}, "
            . "max_subtotal = {$maxSubtotalSql}, "
            . "min_weight_gram = {$minWeightSql}, "
            . "max_weight_gram = {$maxWeightSql}, "
            . "fee = {$feeSql}, "
            . "priority = {$prioritySql}, "
            . "note = {$noteSql} "
            . "WHERE id = " . $id;
        $ok = $ithanhloc->query($sql);
        if (!$ok) {
            jOutShippingRule(['ok' => false, 'msg' => 'Lỗi khi cập nhật: ' . $ithanhloc->error], 500);
        }
        jOutShippingRule(['ok' => true, 'msg' => 'Đã cập nhật rule.', 'id' => $id]);
    }

    $sql = "INSERT INTO ecommerce_shipping_rule (is_active, method_key, region, district_id, min_subtotal, max_subtotal, min_weight_gram, max_weight_gram, fee, priority, note) VALUES ("
        . "{$isActiveVal}, {$methodSql}, {$regionSql}, {$districtSql}, {$minSubtotalSql}, {$maxSubtotalSql}, {$minWeightSql}, {$maxWeightSql}, {$feeSql}, {$prioritySql}, {$noteSql})";
    $ok = $ithanhloc->query($sql);
    if (!$ok) {
        jOutShippingRule(['ok' => false, 'msg' => 'Lỗi khi thêm mới: ' . $ithanhloc->error], 500);
    }
    $newId = (int)$ithanhloc->insert_id;
    jOutShippingRule(['ok' => true, 'msg' => 'Đã thêm rule mới.', 'id' => $newId]);
}

if ($action === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    $state = isset($_POST['is_active']) && (int)$_POST['is_active'] ? 1 : 0;
    if ($id <= 0) {
        jOutShippingRule(['ok' => false, 'msg' => 'Thiếu ID'], 400);
    }
    $ok = $ithanhloc->query('UPDATE ecommerce_shipping_rule SET is_active = ' . $state . ' WHERE id = ' . $id);
    if (!$ok) {
        jOutShippingRule(['ok' => false, 'msg' => 'Không thể cập nhật trạng thái: ' . $ithanhloc->error], 500);
    }
    jOutShippingRule(['ok' => true, 'msg' => 'Đã cập nhật trạng thái.', 'id' => $id, 'is_active' => $state]);
}

if ($action === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        jOutShippingRule(['ok' => false, 'msg' => 'Thiếu ID'], 400);
    }
    $ok = $ithanhloc->query('DELETE FROM ecommerce_shipping_rule WHERE id = ' . $id);
    if (!$ok) {
        jOutShippingRule(['ok' => false, 'msg' => 'Không thể xoá rule: ' . $ithanhloc->error], 500);
    }
    jOutShippingRule(['ok' => true, 'msg' => 'Đã xoá rule.', 'id' => $id]);
}

jOutShippingRule(['ok' => false, 'msg' => 'Hành động không hợp lệ'], 400);
