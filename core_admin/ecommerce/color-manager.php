<?php
require_once __DIR__ . '/../_admin_guard.php';
function color_manager_json_response(array $payload): void {
    if (!headers_sent()) header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

function color_manager_sanitize_json_filename(string $f): string {
    return preg_match('/\A[a-zA-Z0-9][a-zA-Z0-9._-]*\.json\z/', $f = basename(trim($f))) ? $f : '';
}

function color_manager_normalize_group_id(string $id): string {
    return preg_match('/\A[a-z0-9][a-z0-9._-]*\z/', $id = strtolower(preg_replace('/\s+/', '-', trim($id)))) ? $id : '';
}

function color_manager_group_table_exists(mysqli $db): bool {
    $res = $db->query("SHOW TABLES LIKE 'site_color_groups'");
    return $res && $res->num_rows > 0;
}

function color_manager_group_table_create_sql(): string {
    return "CREATE TABLE IF NOT EXISTS `site_color_groups` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `group_id` VARCHAR(64) NOT NULL UNIQUE,
        `group_name` VARCHAR(255) NOT NULL,
        `is_active` TINYINT(1) NOT NULL DEFAULT 1,
        `sort_order` INT NOT NULL DEFAULT 0,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
}

function color_manager_normalize_hex(string $hex): string {
    $hex = strtoupper(trim($hex));
    return preg_match('/\A#?[0-9A-F]{6}\z/', $hex) ? ($hex[0] === '#' ? $hex : "#$hex") : '';
}

function color_manager_hex_to_rgb_string(string $hex): string {
    $h = ltrim(color_manager_normalize_hex($hex), '#');
    return $h ? hexdec(substr($h,0,2)) . ', ' . hexdec(substr($h,2,2)) . ', ' . hexdec(substr($h,4,2)) : '';
}

function color_manager_tone_from_rgb(string $rgb): string {
    if (!preg_match('/\A\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*\z/', $rgb, $m)) return '';
    $l = (0.2126 * (int)$m[1] + 0.7152 * (int)$m[2] + 0.0722 * (int)$m[3]) / 255;
    return $l >= 0.75 ? 'light' : ($l <= 0.35 ? 'dark' : 'mid');
}

function color_query(string $sql, string $types = '', ...$params) {
    global $ithanhloc;
    if ($types === '') return $ithanhloc->query($sql);
    if (!($stmt = $ithanhloc->prepare($sql))) return false;
    $stmt->bind_param($types, ...$params);
    $ok = $stmt->execute();
    $res = $ok ? $stmt->get_result() : false;
    $stmt->close();
    return $res ?: $ok;
}

$action = trim((string)($_REQUEST['action'] ?? ''));
if ($action !== '') {
    if (!$isAdmin) color_manager_json_response(['ok' => false, 'msg' => 'Chức năng này chỉ dành cho quản trị viên.']);

    if ($action === 'list') {
        $res = color_query("SELECT * FROM `site_color_options` ORDER BY `sort_order` ASC, `id` ASC");
        if (!$res) color_manager_json_response(['ok' => false, 'msg' => 'Không thể tải danh sách màu.']);
        color_manager_json_response(['ok' => true, 'rows' => $res->fetch_all(MYSQLI_ASSOC)]);
    }

    if ($action === 'save_color') {
        $id = (int)($_POST['id'] ?? 0);
        $code = trim((string)($_POST['code'] ?? ''));
        $name = trim((string)($_POST['name'] ?? ''));
        $hex = color_manager_normalize_hex((string)($_POST['hex'] ?? ''));
        $rgb = trim((string)($_POST['rgb'] ?? ''));
        $group_code = trim((string)($_POST['group_code'] ?? ''));
        $tone = trim((string)($_POST['tone'] ?? ''));
        $is_active = !empty($_POST['is_active']) ? 1 : 0;
        $sort_order = (int)($_POST['sort_order'] ?? 0);

        if ($code === '' || $name === '') color_manager_json_response(['ok' => false, 'msg' => 'Mã màu và tên màu là bắt buộc.']);

        $exists = color_query('SELECT id FROM site_color_options WHERE code = ? AND id <> ? LIMIT 1', 'si', $code, $id);
        if ($exists && $exists->num_rows > 0) color_manager_json_response(['ok' => false, 'msg' => 'Mã màu đã tồn tại.']);

        if ($sort_order <= 0) {
            $q = color_query('SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM site_color_options');
            $sort_order = $q ? (int)$q->fetch_assoc()['max_sort'] + 1 : 1;
        }

        if ($id > 0) {
            $success = color_query('UPDATE site_color_options SET code = ?, name = ?, hex = ?, rgb = ?, group_code = ?, tone = ?, is_active = ?, sort_order = ?, updated_at = NOW() WHERE id = ?', 'ssssssiii', $code, $name, $hex, $rgb, $group_code, $tone, $is_active, $sort_order, $id);
            if (!$success) color_manager_json_response(['ok' => false, 'msg' => 'Không thể cập nhật màu.']);
            $savedId = $id;
        } else {
            $success = color_query('INSERT INTO site_color_options (code, name, hex, rgb, group_code, tone, is_active, sort_order, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?, NOW(), NOW())', 'ssssssii', $code, $name, $hex, $rgb, $group_code, $tone, $is_active, $sort_order);
            if (!$success) color_manager_json_response(['ok' => false, 'msg' => 'Không thể thêm màu.']);
            global $ithanhloc;
            $savedId = (int)$ithanhloc->insert_id;
        }

        $rowRes = color_query('SELECT * FROM site_color_options WHERE id = ? LIMIT 1', 'i', $savedId);
        color_manager_json_response(['ok' => true, 'msg' => 'Đã lưu màu thành công.', 'row' => $rowRes ? $rowRes->fetch_assoc() : null, 'id' => $savedId, 'mode' => $id > 0 ? 'update' : 'insert']);
    }

    if ($action === 'delete_color') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) color_manager_json_response(['ok' => false, 'msg' => 'Thiếu ID màu cần xoá.']);
        $success = color_query('DELETE FROM site_color_options WHERE id = ?', 'i', $id);
        if (!$success) color_manager_json_response(['ok' => false, 'msg' => 'Không thể xoá màu.']);
        color_manager_json_response(['ok' => true, 'msg' => 'Đã xoá màu thành công.', 'id' => $id]);
    }

    if ($action === 'list_json_files') {
        $files = glob(realpath(__DIR__ . '/../../') . '/*.json') ?: [];
        $names = [];
        foreach ($files as $file) {
            if (($san = color_manager_sanitize_json_filename(basename($file))) !== '') $names[] = $san;
        }
        sort($names);
        color_manager_json_response(['ok' => true, 'files' => $names]);
    }

    if ($action === 'import_json_file') {
        $filename = color_manager_sanitize_json_filename((string)($_POST['filename'] ?? ''));
        $rootReal = realpath(__DIR__ . '/../../');
        if ($filename === '' || !$rootReal) color_manager_json_response(['ok' => false, 'msg' => 'Tên file không hợp lệ.']);

        $targetReal = realpath($rootReal . DIRECTORY_SEPARATOR . $filename);
        if (!$targetReal || !is_file($targetReal) || strpos($targetReal, rtrim($rootReal, '/\\') . DIRECTORY_SEPARATOR) !== 0) {
            color_manager_json_response(['ok' => false, 'msg' => 'File JSON không hợp lệ.']);
        }

        $size = filesize($targetReal);
        if ($size === false || $size <= 0 || $size > 20 * 1024 * 1024) color_manager_json_response(['ok' => false, 'msg' => 'File JSON rỗng hoặc quá lớn.']);

        $raw = file_get_contents($targetReal);
        if ($raw === false || trim($raw) === '') color_manager_json_response(['ok' => false, 'msg' => 'Không đọc được nội dung JSON.']);

        $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
        if (strpos($raw, "\x00") !== false && function_exists('mb_convert_encoding')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'UTF-16LE,UTF-16BE,UTF-8');
        }
        $raw = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $raw);

        $decoded = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) $decoded = json_decode((string)preg_replace('/,(\s*[}\]])/', '$1', $raw), true);
        if (!is_array($decoded) || json_last_error() !== JSON_ERROR_NONE) color_manager_json_response(['ok' => false, 'msg' => 'JSON không hợp lệ: ' . json_last_error_msg()]);

        $data = $decoded['data'] ?? $decoded;
        if (!is_array($data)) color_manager_json_response(['ok' => false, 'msg' => 'Không tìm thấy trường data trong JSON.']);

        $importGroup = trim((string)($_POST['group_code'] ?? '')) ?: pathinfo($filename, PATHINFO_FILENAME);
        $updateExisting = !empty($_POST['update_existing']) ? 1 : 0;

        try {
            $q = color_query('SELECT COALESCE(MAX(sort_order), 0) AS max_sort FROM site_color_options');
            $nextSort = ($q ? (int)$q->fetch_assoc()['max_sort'] : 0) + 1;

            $selectStmt = $ithanhloc->prepare('SELECT id FROM site_color_options WHERE code = ? LIMIT 1');
            $insertStmt = $ithanhloc->prepare('INSERT INTO site_color_options (code, name, hex, rgb, group_code, tone, is_active, sort_order, created_at, updated_at) VALUES (?,?,?,?,?,?,?,?, NOW(), NOW())');
            $updateStmt = $ithanhloc->prepare('UPDATE site_color_options SET name = ?, hex = ?, rgb = ?, group_code = ?, tone = ?, is_active = ?, sort_order = ?, updated_at = NOW() WHERE id = ?');

            if (!$selectStmt || !$insertStmt || !$updateStmt) color_manager_json_response(['ok' => false, 'msg' => 'Lỗi chuẩn bị SQL.']);

            $ithanhloc->begin_transaction();
            $inserted = $updated = $skipped = $invalid = 0;

            foreach ($data as $item) {
                if (!is_array($item)) { $invalid++; continue; }
                $rawName = trim((string)($item['paint_name'] ?? $item['name'] ?? ''));
                $paintId = trim((string)($item['paint_id'] ?? ''));
                $rawCode = trim((string)($item['code'] ?? ''));

                $code = $name = '';
                if ($rawName !== '' && preg_match('/\A([^\s]+)\s+(.+)\z/u', $rawName, $m)) {
                    $code = trim($m[1]); $name = trim($m[2]);
                } else if ($rawCode !== '' && $rawName !== '') {
                    $code = $rawCode; $name = $rawName;
                } else if ($paintId !== '' && $rawName !== '') {
                    $code = $paintId; $name = $rawName;
                }

                if ($code === '' || $name === '') { $invalid++; continue; }

                $hex = color_manager_normalize_hex((string)($item['hex_code'] ?? $item['hex'] ?? ''));
                $rgb = trim((string)($item['rgb'] ?? ''));
                if ($rgb === '' && $hex !== '') $rgb = color_manager_hex_to_rgb_string($hex);
                $tone = trim((string)($item['tone'] ?? ''));
                if ($tone === '' && $rgb !== '') $tone = color_manager_tone_from_rgb($rgb);

                $group = mb_substr($importGroup, 0, 64, 'UTF-8');
                $isActive = 1;
                $sortOrder = $nextSort++;

                $code = mb_substr($code, 0, 64, 'UTF-8');
                $name = mb_substr($name, 0, 255, 'UTF-8');
                $hex = mb_substr($hex, 0, 7, 'UTF-8');
                $rgb = mb_substr($rgb, 0, 32, 'UTF-8');
                $tone = mb_substr($tone, 0, 16, 'UTF-8');

                $existingId = 0;
                $selectStmt->bind_param('s', $code);
                $selectStmt->execute();
                $selectStmt->store_result();
                $selectStmt->bind_result($existingId);
                $selectStmt->fetch();
                $selectStmt->free_result();

                if ($existingId > 0) {
                    if (!$updateExisting) { $skipped++; continue; }
                    $updateStmt->bind_param('sssssiii', $name, $hex, $rgb, $group, $tone, $isActive, $sortOrder, $existingId);
                    $updateStmt->execute();
                    $updated++;
                } else {
                    $insertStmt->bind_param('ssssssii', $code, $name, $hex, $rgb, $group, $tone, $isActive, $sortOrder);
                    $insertStmt->execute();
                    $inserted++;
                }
            }
            $ithanhloc->commit();
            $selectStmt->close(); $insertStmt->close(); $updateStmt->close();

            color_manager_json_response(['ok' => true, 'msg' => 'Import thành công.', 'inserted' => $inserted, 'updated' => $updated, 'skipped' => $skipped, 'invalid' => $invalid]);
        } catch (Throwable $e) {
            if ($ithanhloc) $ithanhloc->rollback();
            color_manager_json_response(['ok' => false, 'msg' => 'Import thất bại: ' . $e->getMessage()]);
        }
    }

    if ($action === 'list_groups') {
        if (!color_manager_group_table_exists($ithanhloc)) {
            color_manager_json_response(['ok' => false, 'msg' => 'Bảng sql không tồn tại', 'sql' => color_manager_group_table_create_sql()]);
        }
        $res = color_query("SELECT g.*, COALESCE(c.cnt, 0) AS colors_count FROM `site_color_groups` g LEFT JOIN (SELECT `group_code`, COUNT(*) AS cnt FROM `site_color_options` GROUP BY `group_code`) c ON c.group_code COLLATE utf8mb4_unicode_ci = g.group_id COLLATE utf8mb4_unicode_ci ORDER BY g.sort_order ASC, g.id ASC");
        if (!$res) color_manager_json_response(['ok' => false, 'msg' => 'Không thể tải nhóm màu.']);
        color_manager_json_response(['ok' => true, 'rows' => $res->fetch_all(MYSQLI_ASSOC)]);
    }

    if ($action === 'save_group') {
        if (!color_manager_group_table_exists($ithanhloc)) {
            color_manager_json_response(['ok' => false, 'msg' => 'Bảng sql không tồn tại', 'sql' => color_manager_group_table_create_sql()]);
        }
        $id = (int)($_POST['id'] ?? 0);
        $groupId = color_manager_normalize_group_id((string)($_POST['group_id'] ?? ''));
        $groupName = trim((string)($_POST['group_name'] ?? ''));
        $isActive = !empty($_POST['is_active']) ? 1 : 0;
        $sortOrder = (int)($_POST['sort_order'] ?? 0);

        if ($groupId === '' || $groupName === '') color_manager_json_response(['ok' => false, 'msg' => 'ID nhóm và tên nhóm là bắt buộc.']);

        $groupName = mb_substr($groupName, 0, 255, 'UTF-8');
        $groupId = mb_substr($groupId, 0, 64, 'UTF-8');

        $exists = color_query("SELECT id FROM `site_color_groups` WHERE group_id = ? AND id <> ? LIMIT 1", "si", $groupId, $id);
        if ($exists && $exists->num_rows > 0) color_manager_json_response(['ok' => false, 'msg' => 'ID nhóm đã tồn tại.']);

        if ($id > 0) {
            $success = color_query("UPDATE `site_color_groups` SET group_id = ?, group_name = ?, is_active = ?, sort_order = ?, updated_at = NOW() WHERE id = ?", "ssiii", $groupId, $groupName, $isActive, $sortOrder, $id);
            if (!$success) color_manager_json_response(['ok' => false, 'msg' => 'Không thể cập nhật nhóm.']);
            $savedId = $id;
        } else {
            $success = color_query("INSERT INTO `site_color_groups` (group_id, group_name, is_active, sort_order, created_at, updated_at) VALUES (?,?,?,?, NOW(), NOW())", "ssii", $groupId, $groupName, $isActive, $sortOrder);
            if (!$success) color_manager_json_response(['ok' => false, 'msg' => 'Không thể thêm nhóm.']);
            global $ithanhloc;
            $savedId = (int)$ithanhloc->insert_id;
        }

        $rowRes = color_query("SELECT * FROM `site_color_groups` WHERE id = ? LIMIT 1", "i", $savedId);
        color_manager_json_response(['ok' => true, 'msg' => 'Đã lưu nhóm màu.', 'row' => $rowRes ? $rowRes->fetch_assoc() : null, 'id' => $savedId, 'mode' => $id > 0 ? 'update' : 'insert']);
    }

    if ($action === 'delete_group') {
        if (!color_manager_group_table_exists($ithanhloc)) color_manager_json_response(['ok' => false, 'msg' => 'Bảng sql không tồn tại', 'sql' => color_manager_group_table_create_sql()]);
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) color_manager_json_response(['ok' => false, 'msg' => 'Thiếu ID nhóm cần xoá.']);
        $success = color_query("DELETE FROM `site_color_groups` WHERE id = ?", "i", $id);
        if (!$success) color_manager_json_response(['ok' => false, 'msg' => 'Không thể xoá nhóm.']);
        color_manager_json_response(['ok' => true, 'msg' => 'Đã xoá nhóm màu.', 'id' => $id]);
    }

    if ($action === 'reorder_groups') {
        if (!color_manager_group_table_exists($ithanhloc)) color_manager_json_response(['ok' => false, 'msg' => 'Thiếu bảng.', 'sql' => color_manager_group_table_create_sql()]);
        $ids = $_POST['ids'] ?? [];
        if (is_string($ids)) $ids = array_filter(array_map('trim', explode(',', $ids)));
        $cleanIds = array_keys(array_flip(array_filter(array_map('intval', (array)$ids))));
        if (!$cleanIds) color_manager_json_response(['ok' => false, 'msg' => 'Danh sách nhóm rỗng.']);

        try {
            $ithanhloc->begin_transaction();
            $sort = 1;
            foreach ($cleanIds as $id) {
                color_query("UPDATE `site_color_groups` SET sort_order = ?, updated_at = NOW() WHERE id = ?", "ii", $sort++, $id);
            }
            $ithanhloc->commit();
            color_manager_json_response(['ok' => true, 'msg' => 'Đã lưu thứ tự nhóm.', 'count' => count($cleanIds)]);
        } catch (Throwable $e) {
            if ($ithanhloc) $ithanhloc->rollback();
            color_manager_json_response(['ok' => false, 'msg' => 'Không thể lưu thứ tự nhóm: ' . $e->getMessage()]);
        }
    }

    if ($action === 'bulk_set_group') {
        $ids = $_POST['ids'] ?? [];
        if (is_string($ids)) $ids = array_filter(array_map('trim', explode(',', $ids)));
        $cleanIds = array_keys(array_flip(array_filter(array_map('intval', (array)$ids))));
        if (!$cleanIds) color_manager_json_response(['ok' => false, 'msg' => 'Chưa chọn màu nào.']);
        if (count($cleanIds) > 2000) color_manager_json_response(['ok' => false, 'msg' => 'Bạn chọn quá nhiều màu.']);

        $groupId = color_manager_normalize_group_id((string)($_POST['group_id'] ?? ''));
        if ($groupId === '' && trim((string)($_POST['group_id'] ?? '')) !== '') color_manager_json_response(['ok' => false, 'msg' => 'ID nhóm không hợp lệ.']);

        if ($groupId !== '') {
            $found = color_query("SELECT id FROM `site_color_groups` WHERE group_id = ? LIMIT 1", "s", $groupId);
            if (!$found || $found->num_rows === 0) color_manager_json_response(['ok' => false, 'msg' => 'Nhóm màu không tồn tại.']);
        }

        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $success = color_query("UPDATE `site_color_options` SET `group_code` = ?, `updated_at` = NOW() WHERE `id` IN ({$placeholders})", 's' . str_repeat('i', count($cleanIds)), $groupId, ...$cleanIds);
        if (!$success) color_manager_json_response(['ok' => false, 'msg' => 'Không thể cập nhật nhóm cho màu.']);
        color_manager_json_response(['ok' => true, 'msg' => 'Đã cập nhật nhóm thành công.', 'updated' => count($cleanIds), 'group_id' => $groupId]);
    }

    color_manager_json_response(['ok' => false, 'msg' => 'Hành động không hợp lệ.']);
}
?>

<style>
    :root { --theme-primary: #0c4c29; --theme-primary-hover: #08361d; --order-primary: #2563eb; --order-border: #e2e8f0; --order-shadow: 0 10px 25px -5px rgba(15,23,42,0.05), 0 8px 10px -6px rgba(15,23,42,0.05); }
    .color-circle-swatch { width: 32px; height: 32px; border-radius: 50%; border: 2px solid #fff; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -2px rgba(0,0,0,0.1); transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1); }
    .color-circle-swatch:hover { transform: scale(1.15) rotate(5deg); }
    .tone-badge { padding: 0.35em 0.75em; font-size: 0.75rem; font-weight: 600; border-radius: 9999px; display: inline-flex; align-items: center; justify-content: center; text-transform: capitalize; }
    .tone-light { background-color: #fef9c3; color: #713f12; }
    .tone-mid { background-color: #dbeafe; color: #1e40af; }
    .tone-dark { background-color: #f1f5f9; color: #1e293b; }
    .tone-other { background-color: #f3f4f6; color: #4b5563; }
    .card { border-radius: 16px; border: 1px solid var(--order-border); box-shadow: var(--order-shadow) !important; }
    .btn { border-radius: 10px; font-weight: 500; padding: 0.5rem 1rem; transition: all 0.2s; }
    .btn-primary { background-color: var(--theme-primary); border-color: var(--theme-primary); }
    .btn-primary:hover, .btn-primary:focus { background-color: var(--theme-primary-hover); border-color: var(--theme-primary-hover); }
    .btn-outline-primary { color: var(--theme-primary); border-color: var(--theme-primary); }
    .btn-outline-primary:hover, .btn-outline-primary:focus, .btn-outline-primary:active { background-color: var(--theme-primary); border-color: var(--theme-primary); color: #fff; }
    .table th { font-weight: 600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.05em; color: #475569; background-color: #f8fafc; border-bottom: 2px solid var(--order-border); }
    .table td { color: #334155; border-bottom: 1px solid #f1f5f9; }
    .table-hover tbody tr:hover { background-color: #f8fafc; }
    .modal-content { border-radius: 20px; border: none; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
    .modal-header { border-bottom: 1px solid #f1f5f9; padding: 1.25rem 1.5rem; }
    .modal-footer { border-top: 1px solid #f1f5f9; padding: 1rem 1.5rem; }
    .form-control, .form-select { border-radius: 8px; border: 1px solid #cbd5e1; padding: 0.4rem 0.75rem; }
    .form-control:focus, .form-select:focus { border-color: var(--theme-primary); box-shadow: 0 0 0 3px rgba(12, 76, 41, 0.15); }
</style>

<div class="bg-white p-3 mb-3 rounded border shadow-sm">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div class="d-flex align-items-center gap-2">
            <a href="https://sonxit.vn" class="text-decoration-none text-dark"><i class="bi bi-arrow-left-circle-fill text-secondary fs-4"></i></a>
            <div class="fw-bold">Quay lại trang chủ</div>
        </div>
        <div class="d-flex gap-2 text-right align-items-center ms-auto flex-wrap">
            <button type="button" class="btn btn-outline-primary btn-sm" id="buttonColorGroup"><i class="bi bi-palette2"></i> Nhóm màu</button>
            <button type="button" class="btn btn-primary btn-sm" id="btnBulkAssignGroup" disabled><i class="bi bi-collection"></i> Gán nhóm (<span id="bulkSelectedCount">0</span>)</button>
            <button type="button" class="btn btn-outline-primary btn-sm" id="btnImportJson"><i class="bi bi-upload"></i> Import</button>
            <button type="button" class="btn btn-primary btn-sm" id="btnAddColor"><i class="bi bi-plus-circle"></i> Thêm màu</button>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <div class="fw-bold mb-2 fs-5 text-dark">Quản lý bảng màu</div>
        <div class="row g-2 align-items-center mt-2">
            <div class="col-12 col-md-4">
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-white border-end-0"><i class="bi bi-search text-muted"></i></span>
                    <input type="text" class="form-control border-start-0 ps-0" id="colorSearch" placeholder="Tìm theo mã, tên, HEX, RGB, nhóm...">
                </div>
            </div>
            <div class="col-6 col-md-4">
                <select id="colorToneFilter" class="form-select form-select-sm">
                    <option value="">Tất cả tone</option>
                    <option value="light">Tone sáng</option>
                    <option value="mid">Tone trung tính</option>
                    <option value="dark">Tone tối</option>
                    <option value="other">Khác</option>
                </select>
            </div>
            <div class="col-6 col-md-4">
                <select id="colorStatusFilter" class="form-select form-select-sm">
                    <option value="">Tất cả trạng thái</option>
                    <option value="1">Đang dùng</option>
                    <option value="0">Tạm tắt</option>
                </select>
            </div>
        </div>

        <div class="row g-2 align-items-center mt-2">
            <div class="col-12 col-md-4">
                <select id="colorGroupFilter" class="form-select form-select-sm">
                    <option value="">Tất cả bộ màu (nhóm)</option>
                </select>
            </div>
            <div class="col-6 col-md-4">
                <select id="colorGroupAssignedFilter" class="form-select form-select-sm">
                    <option value="">Tất cả (gán nhóm)</option>
                    <option value="assigned">Đã gán nhóm</option>
                    <option value="unassigned">Chưa gán nhóm</option>
                </select>
            </div>
            <div class="col-6 col-md-4">
                <select id="colorOrderBy" class="form-select form-select-sm">
                    <option value="sort_order_asc">Sắp xếp: Thứ tự ↑</option>
                    <option value="sort_order_desc">Sắp xếp: Thứ tự ↓</option>
                    <option value="code_asc">Sắp xếp: Mã màu A-Z</option>
                    <option value="code_desc">Sắp xếp: Mã màu Z-A</option>
                    <option value="name_asc">Sắp xếp: Tên A-Z</option>
                    <option value="name_desc">Sắp xếp: Tên Z-A</option>
                </select>
            </div>
        </div>
        
        <div class="text-muted small mt-3 mb-2" id="colorSummaryText">Đang tải dữ liệu bảng màu...</div>
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle" id="colorTable">
                <thead>
                    <tr>
                        <th style="width:44px;" class="text-center"><input class="form-check-input" type="checkbox" id="colorSelectAll" aria-label="Chọn tất cả"></th>
                        <th style="width:56px;">Màu</th>
                        <th style="width:110px;">Mã màu</th>
                        <th>Tên màu</th>
                        <th style="width:110px;">HEX</th>
                        <th style="width:120px;">RGB</th>
                        <th style="width:110px;">Nhóm</th>
                        <th style="width:90px;">Tone</th>
                        <th style="width:72px;">Thứ tự</th>
                        <th style="width:96px;">Trạng thái</th>
                        <th style="width:86px;" class="text-end">Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="11" class="text-center text-muted py-3">Đang tải dữ liệu...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="bulkAssignGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Gán nhóm cho màu đã chọn</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="text-muted small mb-3">Đã chọn: <b class="text-dark"><span id="bulkSelectedCountModal">0</span></b> màu</div>
                <div class="mb-3">
                    <label class="form-label form-label-sm mb-1">Chọn nhóm đã có</label>
                    <select class="form-select form-select-sm" id="bulkGroupSelect"></select>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="bulkClearGroup">
                    <label class="form-check-label small" for="bulkClearGroup">Bỏ nhóm (để trống group_code)</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnApplyBulkGroup">Áp dụng</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="colorGroupModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Nhóm màu</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small py-2 d-none" id="groupMissingTable"></div>
                <div class="row g-2 align-items-end mb-3">
                    <input type="hidden" id="groupRowId" value="0">
                    <div class="col-12 col-md-3">
                        <label class="form-label form-label-sm mb-1">ID nhóm</label>
                        <input type="text" class="form-control form-control-sm" id="groupId" maxlength="64" placeholder="vd: kellymoore">
                    </div>
                    <div class="col-12 col-md-5">
                        <label class="form-label form-label-sm mb-1">Tên nhóm</label>
                        <input type="text" class="form-control form-control-sm" id="groupName" maxlength="255" placeholder="vd: Kelly-Moore">
                    </div>
                    <div class="col-6 col-md-4">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="groupActive" checked>
                            <label class="form-check-label small" for="groupActive">Hoạt động</label>
                        </div>
                    </div>
                    <div class="col-12 text-end mt-2">
                        <div class="btn-group btn-group-sm" role="group">
                            <button type="button" class="btn btn-outline-secondary" id="btnGroupReset">Nhập mới</button>
                            <button type="button" class="btn btn-primary" id="btnGroupSave">Lưu nhóm</button>
                        </div>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle" id="groupTable">
                        <thead>
                            <tr>
                                <th style="width:44px;" class="text-center">#</th>
                                <th style="width:44px;" class="text-center"></th>
                                <th style="width:160px;">ID</th>
                                <th>Tên nhóm</th>
                                <th style="width:90px;">Màu</th>
                                <th style="width:110px;">Trạng thái</th>
                                <th style="width:90px;">Thứ tự</th>
                                <th style="width:140px;" class="text-end">Hành động</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="8" class="text-center text-muted py-3">Đang tải...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="colorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="colorModalTitle">Thêm màu mới</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="colorId" value="0">
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label form-label-sm mb-1">Mã màu <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="colorCode" maxlength="64" placeholder="KM-001">
                    </div>
                    <div class="col-6">
                        <label class="form-label form-label-sm mb-1">Nhóm</label>
                        <input type="text" class="form-control form-control-sm" id="colorGroupCode" maxlength="64" placeholder="classic / kma...">
                    </div>
                    <div class="col-12">
                        <label class="form-label form-label-sm mb-1">Tên màu <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="colorName" maxlength="255" placeholder="Tên hiển thị">
                    </div>
                    <div class="col-6">
                        <label class="form-label form-label-sm mb-1">Mã HEX</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text">#</span>
                            <input type="text" class="form-control" id="colorHex" maxlength="7" placeholder="RRGGBB">
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label form-label-sm mb-1">RGB</label>
                        <input type="text" class="form-control form-control-sm" id="colorRgb" maxlength="32" placeholder="255, 255, 255">
                    </div>
                    <div class="col-6">
                        <label class="form-label form-label-sm mb-1">Tone</label>
                        <select id="colorTone" class="form-select form-select-sm">
                            <option value="">Không xác định</option>
                            <option value="light">Sáng (light)</option>
                            <option value="mid">Trung tính (mid)</option>
                            <option value="dark">Tối (dark)</option>
                        </select>
                    </div>
                    <div class="col-3">
                        <label class="form-label form-label-sm mb-1">Thứ tự</label>
                        <input type="number" class="form-control form-control-sm" id="colorSortOrder" min="1" value="1">
                    </div>
                    <div class="col-3 d-flex align-items-end justify-content-center">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" id="colorIsActive" checked>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnColorReset"><i class="bi bi-arrow-counterclockwise"></i> Nhập mới</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnColorSave"><i class="bi bi-save"></i> Lưu màu</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="importJsonModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Import bảng màu từ JSON</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small mb-3">Đặt file JSON tại thư mục gốc dự án (vd: kellymoore.json).</div>
                <div class="row g-2">
                    <div class="col-12">
                        <label class="form-label form-label-sm mb-1">Chọn file JSON</label>
                        <select id="importJsonFile" class="form-select form-select-sm"></select>
                    </div>
                    <div class="col-12">
                        <label class="form-label form-label-sm mb-1">Nhóm (group_code)</label>
                        <input type="text" id="importGroupCode" class="form-control form-control-sm" maxlength="64" placeholder="kellymoore">
                    </div>
                    <div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="importUpdateExisting" checked>
                            <label class="form-check-label small" for="importUpdateExisting">Cập nhật nếu mã màu đã tồn tại (upsert)</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-dismiss="modal">Đóng</button>
                <button type="button" class="btn btn-primary btn-sm" id="btnRunImportJson"><i class="bi bi-cloud-arrow-up"></i> Import</button>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const API_URL = '<?= h($baseUrl . '/core_admin/ecommerce/color-manager.php') ?>';
    const esc = v => $('<div>').text(String(v || '')).html();
    const toneClass = t => 'tone-badge tone-' + (['light', 'mid', 'dark'].includes(t = String(t || '').toLowerCase()) ? t : 'other');
    let allColors = [], jsonFileList = [], colorDataTable = null, dtFilterHooked = false, selectedColorIds = new Set(), groupCache = [], groupDataTable = null, groupDragSrc = null;

    function notify(msg, type = 'info'){
        (window.toastr && toastr[type]) ? toastr[type](msg) : alert(msg);
    }

    function parseJsonLoose(payload){
        if (payload && typeof payload === 'object') return payload;
        const text = String(payload || '').trim();
        if (!text) return null;
        try { return JSON.parse(text); } catch (e) {
            const m = text.match(/\{[\s\S]*\}$/);
            if (m && m[0]) { try { return JSON.parse(m[0]); } catch (e2) {} }
        }
        return null;
    }

    function requestJson(opts, onSuccess){
        $.ajax(Object.assign({ dataType: 'text' }, opts || {})).done(raw => {
            const res = parseJsonLoose(raw);
            res ? onSuccess(res) : notify('Phản hồi server không hợp lệ', 'error');
        }).fail(xhr => {
            let msg = 'Lỗi kết nối server';
            if (xhr?.responseText) {
                const parsed = parseJsonLoose(xhr.responseText);
                if (parsed?.msg) msg = parsed.msg;
            }
            notify(msg, 'error');
        });
    }

    function toggleModal(id, show) {
        const el = document.getElementById(id);
        if (!el) return;
        if (window.bootstrap?.Modal) {
            const m = bootstrap.Modal.getOrCreateInstance(el);
            show ? m.show() : m.hide();
        } else {
            $(el).modal(show ? 'show' : 'hide');
        }
    }

    function ensureDataTablesReady(cb){
        let tries = 0;
        (function waitForDt(){
            if (window.jQuery?.fn?.DataTable) { cb(); return; }
            if (++tries > 120) { notify('Thiếu thư viện DataTables.', 'error'); return; }
            setTimeout(waitForDt, 100);
        })();
    }

    function updateSummary(){
        if (!colorDataTable) { $('#colorSummaryText').text(allColors.length + ' màu.'); return; }
        const info = colorDataTable.page.info();
        $('#colorSummaryText').text((info.recordsDisplay || 0) + ' / ' + (info.recordsTotal || 0) + ' màu.');
    }

    function refreshBulkUi(){
        const count = selectedColorIds.size;
        $('#bulkSelectedCount, #bulkSelectedCountModal').text(String(count));
        $('#btnBulkAssignGroup').prop('disabled', count === 0);
    }

    function clearSelection(){
        selectedColorIds.clear();
        $('#colorSelectAll').prop('checked', false).prop('indeterminate', false);
        refreshBulkUi();
    }

    function syncSelectAllState(){
        if (!colorDataTable) return;
        const rows = colorDataTable.rows({ page: 'current' }).data().toArray();
        if (!rows.length) { $('#colorSelectAll').prop('checked', false).prop('indeterminate', false); return; }
        let selectedOnPage = 0;
        rows.forEach(r => { if (Number(r?.id || 0) && selectedColorIds.has(Number(r.id))) selectedOnPage++; });
        if (selectedOnPage === 0) $('#colorSelectAll').prop('checked', false).prop('indeterminate', false);
        else if (selectedOnPage === rows.length) $('#colorSelectAll').prop('checked', true).prop('indeterminate', false);
        else $('#colorSelectAll').prop('checked', false).prop('indeterminate', true);
    }

    function installDtExternalFilters(){
        if (dtFilterHooked) return;
        dtFilterHooked = true;
        $.fn.dataTable.ext.search.push((settings, data, dataIndex) => {
            if (settings?.nTable?.id !== 'colorTable' || !colorDataTable) return true;
            const row = colorDataTable.row(dataIndex).data() || {};
            const toneFilter = String($('#colorToneFilter').val() || '');
            const statusFilter = String($('#colorStatusFilter').val() || '');
            const groupFilter = String($('#colorGroupFilter').val() || '');
            const groupAssignedFilter = String($('#colorGroupAssignedFilter').val() || '');
            const tone = String(row.tone || '').toLowerCase();

            if (toneFilter && (toneFilter === 'other' ? ['light', 'mid', 'dark'].includes(tone) : tone !== toneFilter)) return false;
            if (statusFilter !== '' && String(Number(row.is_active || 0)) !== statusFilter) return false;
            const groupCode = String(row.group_code || '').trim();
            if (groupAssignedFilter === 'assigned' && groupCode === '') return false;
            if (groupAssignedFilter === 'unassigned' && groupCode !== '') return false;
            if (groupFilter !== '' && groupCode !== groupFilter) return false;
            return true;
        });
    }

    function ensureColorDataTable(){
        const $table = $('#colorTable');
        if (!$table.length || colorDataTable) return;
        $table.find('tbody').empty();
        colorDataTable = $table.DataTable({
            data: allColors, pageLength: 10, lengthChange: false, order: [[8, 'asc'], [2, 'asc']],
            dom: "t<'d-flex justify-content-between align-items-center p-2 flex-column flex-md-row'<'dataTables_info'i><'dataTables_paginate paging_simple_numbers'p>>",
            language: {
                processing: 'Đang xử lý...', zeroRecords: 'Không tìm thấy màu phù hợp',
                info: 'Hiển thị _START_ - _END_ / _TOTAL_', infoEmpty: 'Không có màu', infoFiltered: '(lọc từ _MAX_ màu)',
                paginate: { first: 'Đầu', last: 'Cuối', next: '>', previous: '<' }
            },
            columns: [
                {
                    data: null, orderable: false, searchable: false, className: 'text-center',
                    render: (data, type, row) => {
                        const id = Number(row?.id || 0);
                        if (type !== 'display') return String(id);
                        return '<input class="form-check-input color-row-select" type="checkbox" data-id="' + id + '" ' + (selectedColorIds.has(id) ? 'checked' : '') + ' aria-label="Chọn màu">';
                    }
                },
                {
                    data: null, orderable: false, searchable: false,
                    render: (data, type, row) => {
                        const hex = String(row?.hex || '').trim() || '#ffffff';
                        return type === 'display' ? '<div class="color-circle-swatch" style="background:' + esc(hex) + '"></div>' : hex;
                    }
                },
                { data: 'code', render: (data, type) => type === 'display' ? '<span class="fw-semibold">' + esc(data) + '</span>' : data },
                {
                    data: 'name',
                    render: (data, type, row) => {
                        const name = String(data || ''), meta = row?.created_at || row?.updated_at ? String(row.created_at || row.updated_at) : '';
                        if (type !== 'display') return name + ' ' + meta;
                        return '<div class="d-flex flex-column"><span class="fw-medium text-dark">' + esc(name) + '</span>' + (meta ? '<span class="text-muted small" style="font-size:0.72rem;">' + esc(meta) + '</span>' : '') + '</div>';
                    }
                },
                { data: 'hex', render: (data, type) => esc(data) },
                { data: 'rgb', render: (data, type) => esc(data) },
                { data: 'group_code', render: (data, type) => esc(data) },
                {
                    data: 'tone',
                    render: (data, type) => {
                        const tone = String(data || '').trim();
                        return type === 'display' ? '<span class="' + toneClass(tone) + '">' + (tone ? esc(tone) : 'N/A') + '</span>' : tone;
                    }
                },
                { data: 'sort_order' },
                {
                    data: 'is_active', orderable: false,
                    render: (data, type) => {
                        if (type !== 'display') return String(Number(data));
                        return Number(data || 0) === 1
                            ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1">Đang dùng</span>'
                            : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1">Tạm tắt</span>';
                    }
                },
                {
                    data: null, orderable: false, searchable: false,
                    render: (data, type, row) => {
                        const id = Number(row?.id || 0);
                        return type === 'display' ? '<div class="text-end"><div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-secondary" data-edit-color="' + id + '" title="Sửa"><i class="bi bi-pencil"></i></button><button type="button" class="btn btn-outline-danger" data-delete-color="' + id + '" title="Xoá"><i class="bi bi-trash"></i></button></div></div>' : '';
                    }
                }
            ],
            createdRow: (row, data) => { if (Number(data?.id || 0)) $(row).attr('data-id', data.id); }
        });

        $table.on('draw.dt', () => { updateSummary(); syncSelectAllState(); refreshBulkUi(); });
    }

    function renderTable(){
        ensureDataTablesReady(() => {
            installDtExternalFilters(); ensureColorDataTable();
            if (!colorDataTable) { $('#colorSummaryText').text(allColors.length + ' màu.'); return; }
            colorDataTable.clear().rows.add(allColors).draw();
            applyFilters();
        });
    }

    function applyOrder(){
        if (!colorDataTable) return;
        const v = String($('#colorOrderBy').val() || 'sort_order_asc');
        const col = { sort_order: 8, code: 2, name: 3 };
        if (v === 'sort_order_desc') colorDataTable.order([[col.sort_order, 'desc'], [col.code, 'asc']]);
        else if (v === 'code_asc') colorDataTable.order([[col.code, 'asc']]);
        else if (v === 'code_desc') colorDataTable.order([[col.code, 'desc']]);
        else if (v === 'name_asc') colorDataTable.order([[col.name, 'asc']]);
        else if (v === 'name_desc') colorDataTable.order([[col.name, 'desc']]);
        else colorDataTable.order([[col.sort_order, 'asc'], [col.code, 'asc']]);
    }

    function applyFilters(){
        if (!colorDataTable) return;
        applyOrder();
        colorDataTable.search(String($('#colorSearch').val() || '').trim()).draw();
        updateSummary();
    }

    function renderGroupFilterOptions(groups){
        const $sel = $('#colorGroupFilter');
        if (!$sel.length) return;
        const current = String($sel.val() || '');
        $sel.empty().append('<option value="">Tất cả bộ màu (nhóm)</option>');
        if (Array.isArray(groups)) {
            groups.forEach(g => {
                const id = String(g.group_id || '').trim();
                if (id) $sel.append('<option value="' + esc(id) + '">' + esc(g.group_name || id) + ' (' + esc(id) + ')</option>');
            });
        }
        if (current) $sel.val(current);
    }

    function loadGroups(cb){
        $('#groupMissingTable').addClass('d-none').text('');
        requestJson({ url: API_URL, method: 'GET', data: { action: 'list_groups' } }, res => {
            if (!res || !res.ok) {
                if (res?.sql) {
                    $('#groupMissingTable').removeClass('d-none').html('<b>Thiếu bảng nhóm màu.</b> <button class="btn btn-primary btn-xs ms-2" id="btnCreateGroupTable">Tạo bảng ngay</button>');
                    $('#btnCreateGroupTable').off('click').on('click', () => createGroupTable(res.sql));
                } else {
                    notify(res?.msg || 'Không thể tải nhóm màu', 'error');
                }
                renderGroupRows([]); return;
            }
            groupCache = res.rows || [];
            renderGroupRows(groupCache);
            renderGroupFilterOptions(groupCache);
            if (cb) cb(groupCache);
        });
    }

    function loadColors(){
        $('#colorSummaryText').text('Đang tải dữ liệu bảng màu...');
        requestJson({ url: API_URL, method: 'GET', data: { action: 'list' } }, res => {
            if (!res?.ok) { notify(res?.msg || 'Không thể tải bảng màu', 'error'); return; }
            allColors = res.rows || [];
            clearSelection(); renderTable();
            if (!groupCache.length) loadGroups();
        });
    }

    function resetForm(){
        $('#colorId').val('0'); $('#colorCode, #colorName, #colorHex, #colorRgb, #colorGroupCode, #colorTone').val('');
        $('#colorIsActive').prop('checked', true);
        const nextSort = allColors.length ? allColors.reduce((max, c) => Math.max(max, Number(c.sort_order || 0)), 0) + 1 : 1;
        $('#colorSortOrder').val(nextSort);
        $('#colorModalTitle').text('Thêm màu mới');
    }

    function fillFormById(id){
        const row = allColors.find(c => Number(c.id || 0) === Number(id || 0));
        if (!row) { notify('Không tìm thấy màu.', 'warning'); return; }
        $('#colorId').val(row.id); $('#colorCode').val(String(row.code || '')); $('#colorName').val(String(row.name || ''));
        const hex = String(row.hex || '').toUpperCase();
        $('#colorHex').val(hex.startsWith('#') ? hex.substring(1) : hex);
        $('#colorRgb').val(String(row.rgb || '')); $('#colorGroupCode').val(String(row.group_code || ''));
        $('#colorTone').val(String(row.tone || '')); $('#colorSortOrder').val(Number(row.sort_order || 1));
        $('#colorIsActive').prop('checked', Number(row.is_active || 0) === 1);
        $('#colorModalTitle').text('Chỉnh sửa màu');
    }

    const filenameToGroup = f => String(f || '').replace(/\.json$/i, '');

    function openImportModal(){
        const $sel = $('#importJsonFile').empty();
        if (!jsonFileList.length) {
            $sel.append('<option value="">(Không có file .json ở thư mục gốc)</option>');
        } else {
            jsonFileList.forEach(f => $sel.append('<option value="' + esc(f) + '">' + esc(f) + '</option>'));
            $('#importGroupCode').val(filenameToGroup(jsonFileList[0]));
        }
        toggleModal('importJsonModal', true);
    }

    function runImport(){
        const filename = String($('#importJsonFile').val() || '').trim();
        if (!filename) { notify('Vui lòng chọn file JSON.', 'warning'); return; }
        if (!confirm('Import toàn bộ màu từ ' + filename + '?')) return;
        requestJson({
            url: API_URL, method: 'POST',
            data: {
                action: 'import_json_file', filename: filename,
                group_code: String($('#importGroupCode').val() || '').trim(),
                update_existing: $('#importUpdateExisting').is(':checked') ? 1 : 0
            }
        }, res => {
            if (!res?.ok) { notify(res?.msg || 'Import thất bại', 'error'); return; }
            notify((res.msg || 'Import thành công') + ' (Thêm: ' + (res.inserted||0) + ', Cập nhật: ' + (res.updated||0) + ', Bỏ qua: ' + (res.skipped||0) + ', Lỗi: ' + (res.invalid||0) + ')', 'success');
            toggleModal('importJsonModal', false); loadColors();
        });
    }

    function saveColor(){
        const code = String($('#colorCode').val() || '').trim(), name = String($('#colorName').val() || '').trim();
        if (!code || !name) { notify('Vui lòng nhập đầy đủ mã màu và tên màu.', 'warning'); return; }
        requestJson({
            url: API_URL, method: 'POST',
            data: {
                action: 'save_color', id: Number($('#colorId').val() || 0), code: code, name: name,
                hex: String($('#colorHex').val() || '').trim(), rgb: String($('#colorRgb').val() || '').trim(),
                group_code: String($('#colorGroupCode').val() || '').trim(), tone: String($('#colorTone').val() || '').trim(),
                sort_order: Number($('#colorSortOrder').val() || 0), is_active: $('#colorIsActive').is(':checked') ? 1 : 0
            }
        }, res => {
            if (!res?.ok) { notify(res?.msg || 'Không thể lưu màu', 'error'); return; }
            notify(res.msg || 'Đã lưu màu thành công.', 'success');
            toggleModal('colorModal', false); loadColors();
        });
    }

    function deleteColor(id){
        if (!id || !confirm('Bạn chắc chắn muốn xoá màu này?')) return;
        requestJson({ url: API_URL, method: 'POST', data: { action: 'delete_color', id: Number(id) } }, res => {
            if (!res?.ok) { notify(res?.msg || 'Không thể xoá màu', 'error'); return; }
            notify(res.msg || 'Đã xoá màu.', 'success'); loadColors();
        });
    }

    function resetGroupForm(){
        $('#groupRowId').val('0'); $('#groupId, #groupName').val(''); $('#groupActive').prop('checked', true);
    }

    function fillGroupForm(row){
        if (!row) return;
        $('#groupRowId').val(String(row.id || 0)); $('#groupId').val(String(row.group_id || ''));
        $('#groupName').val(String(row.group_name || '')); $('#groupActive').prop('checked', String(row.is_active) === '1');
    }

    function ensureGroupDataTable(){
        if (groupDataTable) return;
        groupDataTable = $('#groupTable').DataTable({ paging: false, info: false, searching: false, ordering: false, lengthChange: false, dom: 't', language: { zeroRecords: 'Không có nhóm màu' } });
    }

    function installGroupDnD(){
        const $tbody = $('#groupTable tbody');
        $tbody.find('tr').attr('draggable', 'true');
        $tbody.off('dragstart.groupDnD').on('dragstart.groupDnD', 'tr', function(e){
            groupDragSrc = this;
            try { e.originalEvent.dataTransfer.effectAllowed = 'move'; e.originalEvent.dataTransfer.setData('text/plain', $(this).attr('data-group-id') || ''); } catch (err) {}
        });
        $tbody.off('dragover.groupDnD').on('dragover.groupDnD', 'tr', function(e){ e.preventDefault(); try { e.originalEvent.dataTransfer.dropEffect = 'move'; } catch (err) {} });
        $tbody.off('drop.groupDnD').on('drop.groupDnD', 'tr', function(e){
            e.preventDefault();
            if (!groupDragSrc || groupDragSrc === this) return;
            const rect = this.getBoundingClientRect();
            if ((e.originalEvent.clientY - rect.top) < (rect.height / 2)) $(this).before($(groupDragSrc)); else $(this).after($(groupDragSrc));
            groupDragSrc = null; saveGroupOrderFromDom();
        });
    }

    function saveGroupOrderFromDom(){
        const ids = [];
        $('#groupTable tbody tr').each(function(){ const id = Number($(this).attr('data-group-id') || 0); if (id) ids.push(id); });
        if (!ids.length) return;
        requestJson({ url: API_URL, method: 'POST', data: { action: 'reorder_groups', ids: ids } }, res => {
            if (!res?.ok) { notify(res?.msg || 'Không thể lưu thứ tự nhóm', 'error'); return; }
            const map = new Map(); ids.forEach((id, idx) => map.set(id, idx + 1));
            groupCache = groupCache.map(g => { const id = Number(g.id || 0); if (id && map.has(id)) g.sort_order = map.get(id); return g; });
            $('#groupTable tbody tr').each(function(idx){ $(this).find('[data-group-sort-cell], [data-group-index-cell]').text(String(idx + 1)); });
        });
    }

    function renderGroupRows(rows){
        const $tbody = $('#groupTable tbody');
        if (!Array.isArray(rows) || !rows.length) {
            $tbody.html('<tr><td colspan="8" class="text-center text-muted py-3">Không có nhóm màu</td></tr>');
            if (groupDataTable) groupDataTable.clear().draw();
            return;
        }
        $tbody.html(rows.map((r, idx) => {
            const isActive = String(r.is_active) === '1';
            return '<tr data-group-id="' + esc(r.id) + '">' 
                + '<td class="text-center" data-group-index-cell>' + esc(idx + 1) + '</td>'
                + '<td class="text-center" style="cursor:grab" title="Kéo để sắp xếp"><i class="bi bi-grip-vertical text-muted"></i></td>'
                + '<td><code>' + esc(r.group_id) + '</code></td><td>' + esc(r.group_name) + '</td>'
                + '<td><span class="badge bg-light border text-dark">' + esc(r.colors_count || 0) + '</span></td>'
                + '<td>' + (isActive ? '<span class="badge bg-success-subtle text-success border border-success-subtle">Bật</span>' : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle">Tắt</span>') + '</td>'
                + '<td data-group-sort-cell>' + esc(r.sort_order || (idx + 1)) + '</td>'
                + '<td class="text-end"><div class="btn-group btn-group-sm"><button type="button" class="btn btn-outline-secondary" data-group-edit="' + esc(r.id) + '">Sửa</button><button type="button" class="btn btn-outline-danger" data-group-delete="' + esc(r.id) + '">Xoá</button></div></td></tr>';
        }).join(''));
        ensureGroupDataTable();
        if (groupDataTable) groupDataTable.clear().rows.add($('#groupTable tbody tr')).draw();
        installGroupDnD();
    }

    function createGroupTable(sql){
        if (!confirm('Tạo bảng site_color_groups trong database?')) return;
        requestJson({ url: API_URL, method: 'POST', data: { action: 'save_group', id: 0, group_id: 'temp', group_name: 'Temp' } }, res => {
            notify('Hãy tạo bảng trong phpMyAdmin với SQL đã cho.', 'info');
        });
    }

    function saveGroup(){
        const groupId = String($('#groupId').val() || '').trim(), groupName = String($('#groupName').val() || '').trim();
        if (!groupId || !groupName) { notify('Vui lòng nhập ID nhóm và tên nhóm.', 'warning'); return; }
        requestJson({
            url: API_URL, method: 'POST',
            data: { action: 'save_group', id: Number($('#groupRowId').val() || 0), group_id: groupId, group_name: groupName, is_active: $('#groupActive').is(':checked') ? 1 : 0 }
        }, res => {
            if (!res?.ok) { notify(res?.msg || 'Không thể lưu nhóm', 'error'); return; }
            notify(res.msg || 'Đã lưu nhóm màu.', 'success'); resetGroupForm(); loadGroups();
        });
    }

    function deleteGroup(id){
        if (!id || !confirm('Xoá nhóm màu này?')) return;
        requestJson({ url: API_URL, method: 'POST', data: { action: 'delete_group', id: Number(id) } }, res => {
            if (!res?.ok) { notify(res?.msg || 'Không thể xoá nhóm', 'error'); return; }
            notify(res.msg || 'Đã xoá nhóm.', 'success'); loadGroups();
        });
    }

    function openBulkAssignModal(){
        if (selectedColorIds.size === 0) return;
        $('#bulkClearGroup').prop('checked', false);
        $('#bulkSelectedCountModal').text(String(selectedColorIds.size));
        requestJson({ url: API_URL, method: 'GET', data: { action: 'list_groups' } }, res => {
            const $sel = $('#bulkGroupSelect').empty();
            if (!res?.ok) {
                $sel.append('<option value="">(Chưa có nhóm hoặc thiếu bảng nhóm)</option>');
            } else {
                const rows = res.rows || [];
                if (!rows.length) {
                    $sel.append('<option value="">(Chưa có nhóm)</option>');
                } else {
                    $sel.append('<option value="">-- Chọn nhóm --</option>');
                    rows.forEach(g => $sel.append('<option value="' + esc(g.group_id) + '">' + esc(g.group_name) + ' (' + esc(g.group_id) + ')</option>'));
                }
            }
            toggleModal('bulkAssignGroupModal', true);
        });
    }

    function applyBulkGroup(){
        const ids = Array.from(selectedColorIds);
        if (!ids.length) return;
        const clearGroup = $('#bulkClearGroup').is(':checked');
        let groupId = String($('#bulkGroupSelect').val() || '').trim();
        if (clearGroup) groupId = '';
        if (!clearGroup && !groupId) { notify('Vui lòng chọn nhóm cần gán (hoặc tick Bỏ nhóm).', 'warning'); return; }

        requestJson({ url: API_URL, method: 'POST', data: { action: 'bulk_set_group', ids: ids, group_id: groupId } }, res => {
            if (!res?.ok) { notify(res?.msg || 'Không thể gán nhóm', 'error'); return; }
            notify(res.msg || 'Đã gán nhóm.', 'success');
            toggleModal('bulkAssignGroupModal', false); loadColors();
        });
    }

    // Event Bindings
    $(document)
        .on('click', '#btnAddColor, #btnColorReset', e => { e.preventDefault(); resetForm(); if (e.currentTarget.id === 'btnAddColor') toggleModal('colorModal', true); })
        .on('click', '#buttonColorGroup', e => { e.preventDefault(); ensureDataTablesReady(() => { toggleModal('colorGroupModal', true); loadGroups(); }); })
        .on('click', '#btnGroupReset', e => { e.preventDefault(); resetGroupForm(); })
        .on('click', '#btnGroupSave', e => { e.preventDefault(); saveGroup(); })
        .on('click', '#btnImportJson', e => { e.preventDefault(); requestJson({ url: API_URL, method: 'GET', data: { action: 'list_json_files' } }, res => { jsonFileList = res.files || []; openImportModal(); }); })
        .on('change', '#importJsonFile', function(){ const f = String($(this).val() || '').trim(); if (f) $('#importGroupCode').val(filenameToGroup(f)); })
        .on('click', '#btnRunImportJson', e => { e.preventDefault(); runImport(); })
        .on('click', '#btnColorSave', e => { e.preventDefault(); saveColor(); })
        .on('input change', '#colorSearch, #colorToneFilter, #colorStatusFilter, #colorGroupFilter, #colorGroupAssignedFilter, #colorOrderBy', applyFilters)
        .on('click', '#btnBulkAssignGroup', e => { e.preventDefault(); ensureDataTablesReady(openBulkAssignModal); })
        .on('click', '#btnApplyBulkGroup', e => { e.preventDefault(); applyBulkGroup(); })
        .on('click', '[data-edit-color], [data-delete-color], [data-group-edit], [data-group-delete]', function(e){
            e.preventDefault();
            const $this = $(this);
            if ($this.is('[data-edit-color]')) { fillFormById($this.attr('data-edit-color')); toggleModal('colorModal', true); }
            if ($this.is('[data-delete-color]')) deleteColor($this.attr('data-delete-color'));
            if ($this.is('[data-group-edit]')) { const row = groupCache.find(x => Number(x.id || 0) === Number($this.attr('data-group-edit'))); if (row) fillGroupForm(row); }
            if ($this.is('[data-group-delete]')) deleteGroup($this.attr('data-group-delete'));
        })
        .on('change', '#colorTable .color-row-select', function(){
            const id = Number($(this).attr('data-id') || 0);
            if (!id) return;
            if ($(this).is(':checked')) selectedColorIds.add(id); else selectedColorIds.delete(id);
            syncSelectAllState(); refreshBulkUi();
        })
        .on('change', '#colorSelectAll', function(){
            if (!colorDataTable) return;
            const checked = $(this).is(':checked');
            colorDataTable.rows({ page: 'current' }).data().toArray().forEach(r => {
                const id = Number(r?.id || 0);
                if (id) { if (checked) selectedColorIds.add(id); else selectedColorIds.delete(id); }
            });
            colorDataTable.rows({ page: 'current' }).invalidate('data').draw(false);
            syncSelectAllState(); refreshBulkUi();
        });

    loadColors();
})();
</script>
