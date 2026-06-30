<?php
/**
 * Pha 0 — Chuẩn hoá URL media trong DB.
 *
 * Mục đích: tìm các giá trị đang lưu URL TUYỆT ĐỐI trỏ về domain cũ + /uploads/...
 * và đổi về path TƯƠNG ĐỐI 'uploads/...' để to_abs_url() có thể tự route sang
 * media domain. Quét TOÀN BỘ cột text/char của mọi bảng (schema-driven) nên
 * không bỏ sót cột nào.
 *
 * AN TOÀN:
 *   - Mặc định DRY-RUN: chỉ in ra những gì SẼ đổi, KHÔNG ghi DB.
 *   - Thêm --apply để thực sự cập nhật.
 *   - Luôn backup DB trước khi --apply.
 *
 * Dùng:
 *   php scripts/media_normalize_urls.php                 # dry-run, tự đoán domain
 *   php scripts/media_normalize_urls.php --domain=https://sonxit.vn
 *   php scripts/media_normalize_urls.php --domain=... --apply
 */

require __DIR__ . '/../config.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Chỉ chạy qua CLI.\n");
}
if (!($ithanhloc instanceof mysqli)) {
    exit("Không kết nối được DB.\n");
}

$args = $argv ?? [];
$apply = in_array('--apply', $args, true);
$domain = '';
foreach ($args as $a) {
    if (strpos($a, '--domain=') === 0) $domain = substr($a, strlen('--domain='));
}

$folder = trim((string)($uploadFolder ?? 'uploads'), '/');

// Danh sách domain cần chuẩn hoá. Nếu không truyền --domain, tự suy từ baseUrl
// + thêm các domain phổ biến từng dùng. Có thể bổ sung tại đây.
$domains = [];
if ($domain !== '') $domains[] = $domain;
$domains[] = rtrim((string)$baseUrl, '/');
$domains[] = 'https://sonxit.vn';
$domains[] = 'https://paintandmore.vn';
$domains = array_values(array_unique(array_filter(array_map(function ($d) {
    $d = trim((string)$d);
    return $d === '' ? '' : rtrim($d, '/');
}, $domains))));

echo "=== media_normalize_urls (" . ($apply ? "APPLY" : "DRY-RUN") . ") ===\n";
echo "Upload folder: {$folder}\n";
echo "Domains xử lý:\n  - " . implode("\n  - ", $domains) . "\n\n";

// Build regex: bắt 'scheme://domain/uploads/....' và lấy phần 'uploads/....'
$escFolder = preg_quote($folder, '#');
$domainAlt = implode('|', array_map(function ($d) {
    // bỏ scheme để match cả http/https/protocol-relative
    $noScheme = preg_replace('#^https?:#i', '', $d);
    return preg_quote($noScheme, '#');
}, $domains));
$pattern = '#(?:https?:)?(?:' . $domainAlt . ')/(' . $escFolder . '/[^"\'\s\\\\)]+)#i';

// Lấy danh sách bảng
$dbName = '';
$resDb = $ithanhloc->query('SELECT DATABASE()');
if ($resDb) { $dbName = (string)($resDb->fetch_row()[0] ?? ''); }
if ($dbName === '') exit("Không lấy được tên DB.\n");

$tables = [];
$resT = $ithanhloc->query("SHOW TABLES");
while ($resT && ($r = $resT->fetch_row())) $tables[] = $r[0];

$totalHits = 0;
$totalRows = 0;

foreach ($tables as $table) {
    // Lấy cột text/char + khóa chính
    $cols = [];
    $pk = '';
    $resC = $ithanhloc->query("SHOW COLUMNS FROM `{$table}`");
    while ($resC && ($c = $resC->fetch_assoc())) {
        $type = strtolower((string)$c['Type']);
        $name = (string)$c['Field'];
        if (($c['Key'] ?? '') === 'PRI' && $pk === '') $pk = $name;
        if (preg_match('/(char|text|json|blob)/', $type)) $cols[] = $name;
    }
    if ($pk === '' || !$cols) continue;

    $colList = '`' . implode('`,`', $cols) . '`';
    // Chỉ quét hàng có khả năng chứa 'uploads' để nhẹ
    $whereOr = [];
    foreach ($cols as $cc) $whereOr[] = "`{$cc}` LIKE '%{$folder}/%'";
    $sql = "SELECT `{$pk}`, {$colList} FROM `{$table}` WHERE " . implode(' OR ', $whereOr);
    $resR = @$ithanhloc->query($sql);
    if (!$resR) continue;

    while ($row = $resR->fetch_assoc()) {
        $id = $row[$pk];
        $updates = [];
        foreach ($cols as $cc) {
            $val = $row[$cc];
            if ($val === null || $val === '') continue;
            if (!preg_match($pattern, (string)$val)) continue;
            $newVal = preg_replace($pattern, '$1', (string)$val);
            if ($newVal !== $val) {
                $updates[$cc] = $newVal;
                $totalHits++;
                echo "[{$table}#{$id}] {$cc}\n    - {$val}\n    + {$newVal}\n";
            }
        }
        if ($updates) {
            $totalRows++;
            if ($apply) {
                $sets = [];
                $params = [];
                $types = '';
                foreach ($updates as $cc => $nv) { $sets[] = "`{$cc}`=?"; $params[] = $nv; $types .= 's'; }
                $params[] = $id; $types .= (is_int($id) ? 'i' : 's');
                $stmt = $ithanhloc->prepare("UPDATE `{$table}` SET " . implode(',', $sets) . " WHERE `{$pk}`=?");
                if ($stmt) {
                    $stmt->bind_param($types, ...$params);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
}

echo "\n=== Tổng kết ===\n";
echo "Số giá trị khớp: {$totalHits} (trên {$totalRows} dòng)\n";
echo $apply ? "ĐÃ cập nhật DB.\n" : "DRY-RUN: chưa đổi gì. Thêm --apply để áp dụng (NHỚ backup DB trước).\n";
