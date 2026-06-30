<?php
require_once __DIR__ . '/../_admin_guard.php';
?>
<?php
// ===== ACTIONS =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim((string)($_POST['name'] ?? ''));
        $promo_type = trim((string)($_POST['promo_type'] ?? ''));
        $is_active = isset($_POST['is_active']) ? (int)($_POST['is_active'] ? 1 : 0) : 1;
        $start_at_raw = trim((string)($_POST['start_at'] ?? ''));
        $end_at_raw = trim((string)($_POST['end_at'] ?? ''));
        $start_at = ($start_at_raw === '') ? null : $start_at_raw;
        $end_at = ($end_at_raw === '') ? null : $end_at_raw;
        $priority = (int)($_POST['priority'] ?? 0);

        if ($name === '') jOut(['ok' => false, 'msg' => 'Vui lòng nhập tên chiến dịch']);
        if (!in_array($promo_type, ['gift','combo','bxgy'], true)) jOut(['ok' => false, 'msg' => 'Loại chiến dịch không hợp lệ']);

        $parseVariantMap = function(string $rawJson, array $allowedPids): array {
            $allowedSet = [];
            foreach ($allowedPids as $pid) {
                $pid = (int)$pid;
                if ($pid > 0) $allowedSet[$pid] = true;
            }
            $rawJson = trim($rawJson);
            if ($rawJson === '') return [];
            $obj = json_decode($rawJson, true);
            if (!is_array($obj)) return [];
            $out = [];
            foreach ($obj as $pidKey => $arr) {
                $pid = (int)$pidKey;
                if ($pid <= 0 || !isset($allowedSet[$pid])) continue;
                if (!is_array($arr)) continue;
                $uniq = [];
                foreach ($arr as $vid) {
                    $vid = (int)$vid;
                    if ($vid > 0) $uniq[$vid] = $vid;
                }
                if ($uniq) {
                    $out[$pid] = array_values($uniq);
                }
            }
            return $out;
        };

        $config = ['type' => $promo_type];

        if ($promo_type === 'gift') {
            $threshold_amount = (int)($_POST['threshold_amount'] ?? 0);
            $gift_ids_raw = (string)($_POST['gift_product_ids'] ?? '');
            $choice_limit = (int)($_POST['gift_choice_limit'] ?? 1);

            $ids = [];
            foreach (preg_split('/[^0-9]+/', $gift_ids_raw) as $part) {
                $v = (int)$part;
                if ($v > 0) $ids[$v] = $v;
            }
            $ids = array_values($ids);

            if ($threshold_amount <= 0) jOut(['ok' => false, 'msg' => 'Ngưỡng hoá đơn phải > 0']);
            if (!$ids) jOut(['ok' => false, 'msg' => 'Vui lòng nhập ít nhất 1 ID sản phẩm quà tặng']);
            if ($choice_limit <= 0) $choice_limit = 1;

            $config['threshold_amount'] = $threshold_amount;
            $config['gift_product_ids'] = $ids;
            $config['gift_choice_limit'] = $choice_limit;

            $gift_variant_raw = (string)($_POST['gift_variant_ids'] ?? '');
            $giftVariantMap = $parseVariantMap($gift_variant_raw, $ids);
            if ($giftVariantMap) {
                $config['gift_variant_ids'] = $giftVariantMap;
            }
        } elseif ($promo_type === 'combo') {
            $main_ids_raw = (string)($_POST['main_product_ids'] ?? '');
            $combo_lines = trim((string)($_POST['combo_items'] ?? ''));

            // Hỗ trợ cả kiểu cũ (1 sản phẩm chính duy nhất) và kiểu mới (nhiều sản phẩm chính)
            $mainIdsSet = [];
            foreach (preg_split('/[^0-9]+/', $main_ids_raw) as $part) {
                $v = (int)$part;
                if ($v > 0) $mainIdsSet[$v] = $v;
            }
            if (!$mainIdsSet) {
                // fallback cho request cũ nếu chỉ gửi main_product_id
                $legacyMainId = (int)($_POST['main_product_id'] ?? 0);
                if ($legacyMainId > 0) {
                    $mainIdsSet[$legacyMainId] = $legacyMainId;
                }
            }

            $mainProductIds = array_values($mainIdsSet);
            if (!$mainProductIds) jOut(['ok' => false, 'msg' => 'Vui lòng chọn sản phẩm chính']);

            $items = [];
            if ($combo_lines !== '') {
                $rows = preg_split('/[\r\n]+/', $combo_lines);
                foreach ($rows as $row) {
                    $row = trim($row);
                    if ($row === '') continue;
                    // format: product_id:promo_price
                    $parts = preg_split('/[:|,]+/', $row);
                    $rawPid = trim((string)($parts[0] ?? ''));
                    $price = (int)($parts[1] ?? 0);
                    if ($rawPid !== '' && $price >= 0) {
                        $items[] = ['product_id' => $rawPid, 'promo_price' => $price];
                    }
                }
            }
            if (!$items) jOut(['ok' => false, 'msg' => 'Vui lòng nhập danh sách sản phẩm tặng (mỗi dòng: product_id:giá_khuyến_mãi, có thể 0 = miễn phí)']);
            // Lưu lại cả trường cũ (main_product_id) để tương thích, ưu tiên ID đầu tiên
            $config['main_product_id'] = (int)$mainProductIds[0];
            $config['main_product_ids'] = $mainProductIds;
            $config['items'] = $items;
        } elseif ($promo_type === 'bxgy') {
            $main_ids_raw = (string)($_POST['bxgy_main_product_ids'] ?? '');
            $buy_qty = (int)($_POST['bxgy_buy_qty'] ?? 0);
            // legacy single gift field (older UI)
            $gift_product_id_legacy = (int)($_POST['bxgy_gift_product_id'] ?? 0);
            // new UI
            $gift_same = isset($_POST['bxgy_gift_same']) ? (int)(($_POST['bxgy_gift_same'] ? 1 : 0)) : 1;
            $gift_ids_raw = (string)($_POST['bxgy_gift_product_ids'] ?? '');
            $main_variant_raw = (string)($_POST['bxgy_main_variant_ids'] ?? '');
            $gift_variant_raw = (string)($_POST['bxgy_gift_variant_ids'] ?? '');
            $gift_qty = (int)($_POST['bxgy_gift_qty'] ?? 0);
            $max_gift_per_order = (int)($_POST['bxgy_max_gift_per_order'] ?? 0);
            // legacy single variant field (older UI)
            $gift_variant_id_legacy = (int)($_POST['bxgy_gift_variant_id'] ?? 0);

            $mainIdsSet = [];
            foreach (preg_split('/[^0-9]+/', $main_ids_raw) as $part) {
                $v = (int)$part;
                if ($v > 0) $mainIdsSet[$v] = $v;
            }
            $mainProductIds = array_values($mainIdsSet);

            if (!$mainProductIds) jOut(['ok' => false, 'msg' => 'Vui lòng chọn sản phẩm chính cho chiến dịch']);
            if ($buy_qty <= 0) jOut(['ok' => false, 'msg' => 'Số lượng mua phải > 0']);
            if ($gift_qty <= 0) jOut(['ok' => false, 'msg' => 'Số lượng tặng phải > 0']);

            $giftProductIds = [];
            if ($gift_same !== 1) {
                $giftSet = [];
                foreach (preg_split('/[^0-9]+/', $gift_ids_raw) as $part) {
                    $v = (int)$part;
                    if ($v > 0) $giftSet[$v] = $v;
                }
                if (!$giftSet && $gift_product_id_legacy > 0) {
                    $giftSet[$gift_product_id_legacy] = $gift_product_id_legacy;
                }
                $giftProductIds = array_values($giftSet);
                if (!$giftProductIds) jOut(['ok' => false, 'msg' => 'Vui lòng chọn ít nhất 1 sản phẩm tặng hoặc bật “Tặng cùng sản phẩm chính”']);
            }

            $mainVariantMap = $parseVariantMap($main_variant_raw, $mainProductIds);
            $giftVariantMap = ($gift_same !== 1) ? $parseVariantMap($gift_variant_raw, $giftProductIds) : [];

            $config['main_product_ids'] = $mainProductIds;
            $config['buy_qty'] = $buy_qty;
            // Nếu $gift_same = 1 => tặng cùng sản phẩm chính
            // Nếu $gift_same = 0 => cho phép chọn 1 hoặc nhiều sản phẩm làm quà
            $config['gift_product_ids'] = $giftProductIds;
            // legacy field: giữ để tương thích, ưu tiên id đầu tiên
            $config['gift_product_id'] = $giftProductIds ? (int)$giftProductIds[0] : 0;
            $config['gift_qty'] = $gift_qty;
            $config['max_gift_per_order'] = max(0, $max_gift_per_order);

            // Variant restrictions (optional)
            if ($mainVariantMap) {
                $config['main_variant_ids'] = $mainVariantMap;
            }
            if ($giftVariantMap) {
                $config['gift_variant_ids'] = $giftVariantMap;
            }

            // Legacy single fixed gift_variant_id: only when it is a single gift product + single variant.
            $legacyGiftVariantId = 0;
            if (count($giftProductIds) === 1) {
                $onlyGiftPid = (int)$giftProductIds[0];
                $arr = isset($giftVariantMap[$onlyGiftPid]) && is_array($giftVariantMap[$onlyGiftPid]) ? $giftVariantMap[$onlyGiftPid] : [];
                if (count($arr) === 1) {
                    $legacyGiftVariantId = (int)$arr[0];
                } elseif ($gift_variant_id_legacy > 0) {
                    $legacyGiftVariantId = $gift_variant_id_legacy;
                }
            }
            $config['gift_variant_id'] = max(0, $legacyGiftVariantId);
        }

        $config_json = json_encode($config, JSON_UNESCAPED_UNICODE);

        if ($id > 0) {
            $stmt = $ithanhloc->prepare('UPDATE ecommerce_product_promo SET name=?, promo_type=?, config_json=?, is_active=?, start_at=?, end_at=?, priority=? WHERE id=?');
            if (!$stmt) jOut(['ok' => false, 'msg' => $ithanhloc->error]);
            $stmt->bind_param('sssissii', $name, $promo_type, $config_json, $is_active, $start_at, $end_at, $priority, $id);
            $ok = $stmt->execute();
            $err = $stmt->error;
            $stmt->close();
            jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã lưu' : $err]);
        }

        $stmt = $ithanhloc->prepare('INSERT INTO ecommerce_product_promo (name, promo_type, config_json, is_active, start_at, end_at, priority) VALUES (?,?,?,?,?,?,?)');
        if (!$stmt) jOut(['ok' => false, 'msg' => $ithanhloc->error]);
        $stmt->bind_param('sssissi', $name, $promo_type, $config_json, $is_active, $start_at, $end_at, $priority);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã tạo chiến dịch' : $err]);
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $ok = $ithanhloc->query('DELETE FROM ecommerce_product_promo WHERE id=' . $id);
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã xóa' : $ithanhloc->error]);
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        $state = (int)($_POST['is_active'] ?? 1);
        $stmt = $ithanhloc->prepare('UPDATE ecommerce_product_promo SET is_active=? WHERE id=?');
        if (!$stmt) jOut(['ok' => false, 'msg' => $ithanhloc->error]);
        $stmt->bind_param('ii', $state, $id);
        $ok = $stmt->execute();
        $err = $stmt->error;
        $stmt->close();
        jOut(['ok' => (bool)$ok, 'msg' => $ok ? 'Đã cập nhật' : $err]);
    }

    if ($action === 'update_priorities') {
        $ids = $_POST['ids'] ?? [];
        if (is_array($ids) && !empty($ids)) {
            $ithanhloc->begin_transaction();
            try {
                $count = count($ids);
                $stmt = $ithanhloc->prepare('UPDATE ecommerce_product_promo SET priority=? WHERE id=?');
                foreach ($ids as $index => $id_val) {
                    $promo_id = (int)$id_val;
                    $priority_val = $index + 1;
                    $stmt->bind_param('ii', $priority_val, $promo_id);
                    $stmt->execute();
                }
                $stmt->close();
                $ithanhloc->commit();
                jOut(['ok' => true, 'msg' => 'Đã cập nhật thứ tự ưu tiên']);
            } catch (Throwable $e) {
                $ithanhloc->rollback();
                jOut(['ok' => false, 'msg' => $e->getMessage()]);
            }
        }
        jOut(['ok' => false, 'msg' => 'Dữ liệu không hợp lệ']);
    }
}

// ===== DATA =====
$Promos = [];
$q = $ithanhloc->query('SELECT * FROM ecommerce_product_promo ORDER BY CASE WHEN priority = 0 THEN 99999 ELSE priority END ASC, id DESC');
while ($r = $q->fetch_assoc()) $Promos[] = $r;

$templateCounts = [
    'all' => 0,
    'gift' => 0,
    'combo' => 0,
    'bxgy' => 0
];
foreach ($Promos as $p) {
    $templateCounts['all']++;
    $type = (string)($p['promo_type'] ?? '');
    if (isset($templateCounts[$type])) {
        $templateCounts[$type]++;
    }
}

$productOptions = [];
$categoryOptions = [];
$productVariantOptions = [];

// Chuẩn bị danh sách danh mục để filter trong picker
try {
    $categoryTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_category', 'list_category']) : 'ecommerce_category';
} catch (Throwable $e) {
    $categoryTable = 'ecommerce_category';
}
if ($categoryTable) {
    $qCat = $ithanhloc->query("SELECT id, name FROM `{$categoryTable}` ORDER BY name ASC, id ASC");
    if ($qCat) {
        while ($c = $qCat->fetch_assoc()) {
            $categoryOptions[] = [
                'id' => (int)($c['id'] ?? 0),
                'name' => (string)($c['name'] ?? ''),
            ];
        }
    }
}

// Chuẩn bị danh sách sản phẩm (kèm category_id + giá gốc tối thiểu để hiển thị trong picker)
try {
    $variantTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_product_variants']) : 'ecommerce_product_variants';
} catch (Throwable $e) {
    $variantTable = 'ecommerce_product_variants';
}

$priceMinExpr = $variantTable
    ? "COALESCE((SELECT MIN(price) FROM `{$variantTable}` v WHERE v.product_id = p.id), 0) AS price_min"
    : '0 AS price_min';

$skuExpr = $variantTable
    ? "COALESCE((SELECT sku_variant FROM `{$variantTable}` v WHERE v.product_id = p.id AND v.sku_variant <> '' ORDER BY v.id ASC LIMIT 1), '') AS sku"
    : "'' AS sku";

// Danh sách sản phẩm dùng cho picker
$qProducts = $ithanhloc->query("SELECT p.id, p.product_name, p.image_url, {$skuExpr}, p.category_id, {$priceMinExpr} FROM ecommerce_product p ORDER BY p.product_name ASC, p.id DESC");
if ($qProducts) {
    $p_list = [];
    $p_ids = [];
    while ($p = $qProducts->fetch_assoc()) {
        $pid = (int)$p['id'];
        $p_list[$pid] = [
            'id' => $pid,
            'name' => (string)($p['product_name'] ?? ''),
            'sku' => (string)($p['sku'] ?? ''),
            'category_id' => (int)($p['category_id'] ?? 0),
            'price' => (int)($p['price_min'] ?? 0),
            'image_url' => (string)($p['image_url'] ?? ''),
            'groups' => [],
            'no_group_variants' => []
        ];
        $p_ids[] = $pid;
    }

    if ($p_ids && $variantTable) {
        $p_ids_str = implode(',', $p_ids);
        
        // Load Variant Groups
        $qGroups = $ithanhloc->query("SELECT * FROM ecommerce_product_variant_groups WHERE product_id IN ($p_ids_str) ORDER BY sort_order ASC, id ASC");
        $group_map = [];
        if ($qGroups) {
            while ($g = $qGroups->fetch_assoc()) {
                $pid = (int)$g['product_id'];
                $gid = (int)$g['id'];
                $group_data = [
                    'id' => $gid,
                    'name' => (string)$g['name'],
                    'variants' => []
                ];
                $p_list[$pid]['groups'][] = &$group_data;
                $group_map[$gid] = &$group_data;
                unset($group_data);
            }
        }

        // Load Variants
        $qVariants = $ithanhloc->query("SELECT id, product_id, group_id, variant_name, sku_variant, shipping_weight_value, shipping_weight_unit, price FROM `{$variantTable}` WHERE product_id IN ($p_ids_str) ORDER BY price ASC, id ASC");
        if ($qVariants) {
            while ($v = $qVariants->fetch_assoc()) {
                $pid = (int)$v['product_id'];
                $gid = (int)$v['group_id'];
                $vid = (int)$v['id'];
                $wLabel = '';
                $v_data = [
                    'id' => $vid,
                    'name' => trim((string)$v['variant_name']),
                    'sku' => (string)$v['sku_variant'],
                    'price' => (int)($v['price'] ?? 0)
                ];
                
                // Add to productVariantOptions for backwards compatibility if needed
                if (!isset($productVariantOptions[$pid])) {
                    $productVariantOptions[$pid] = [];
                }
                $productVariantOptions[$pid][] = [
                    'id' => $vid,
                    'variant_name' => trim((string)($v['variant_name'] ?? '')),
                    'weight_label' => '',
                    'name' => trim((string)$v['variant_name']),
                    'sku' => (string)$v['sku_variant'],
                    'price' => (int)($v['price'] ?? 0),
                    'group_id' => $gid
                ];

                if ($gid > 0 && isset($group_map[$gid])) {
                    $group_map[$gid]['variants'][] = $v_data;
                } else {
                    $p_list[$pid]['no_group_variants'][] = $v_data;
                }
            }
        }
    }
    $productOptions = array_values($p_list);
}

function promoTypeLabel($t) {
    $t = (string)$t;
    if ($t === 'gift') return 'Quà tặng hoá đơn';
    if ($t === 'combo') return 'Mua thêm deal sốc';
    if ($t === 'bxgy') return 'Mua x tặng y';
    return $t;
}

function promo_product_names_short(array $ids, array $productNameById, int $max = 2): string {
    $out = [];
    $uniq = [];
    foreach ($ids as $v) {
        $v = (int)$v;
        if ($v > 0) $uniq[$v] = $v;
    }
    $ids = array_values($uniq);
    $total = count($ids);
    if ($total <= 0) return '';

    $show = array_slice($ids, 0, max(0, $max));
    foreach ($show as $pid) {
        $name = (string)($productNameById[$pid] ?? '');
        if ($name !== '') {
            $out[] = $name . ' (#' . $pid . ')';
        } else {
            $out[] = '#' . $pid;
        }
    }
    $label = implode(', ', $out);
    if ($total > count($show)) {
        $label .= ' +' . ($total - count($show)) . ' SP';
    }
    return $label;
}

function promoConfigHtml($row, array $productNameById): string {
    $type = (string)($row['promo_type'] ?? '');
    $cfg = json_decode((string)($row['config_json'] ?? ''), true) ?: [];
    // Overview only (keep it short; details are available in the View modal)
    $line1 = '';
    $line2 = '';

    if ($type === 'gift') {
        $threshold = (int)($cfg['threshold_amount'] ?? 0);
        $ids = is_array($cfg['gift_product_ids'] ?? null) ? $cfg['gift_product_ids'] : [];
        $limit = (int)($cfg['gift_choice_limit'] ?? 1);
        $uniq = [];
        foreach ($ids as $v) {
            $v = (int)$v;
            if ($v > 0) $uniq[$v] = $v;
        }
        $giftCount = count($uniq);

        $line1 = 'Ngưỡng: <span class="fw-semibold">' . h(number_format(max(0, $threshold), 0, ',', '.')) . 'đ</span>';
        $line2 = 'Quà: <span class="fw-semibold">' . h((string)$giftCount) . ' SP</span> · Chọn: <span class="fw-semibold">' . h((string)max(1, $limit)) . '</span>';
    } elseif ($type === 'combo') {
        $mainIdsSet = [];
        if (!empty($cfg['main_product_ids']) && is_array($cfg['main_product_ids'])) {
            foreach ($cfg['main_product_ids'] as $mid) {
                $mid = (int)$mid;
                if ($mid > 0) $mainIdsSet[$mid] = $mid;
            }
        }
        $legacyMain = (int)($cfg['main_product_id'] ?? 0);
        if ($legacyMain > 0) $mainIdsSet[$legacyMain] = $legacyMain;
        $mainCount = count($mainIdsSet);

        $items = is_array($cfg['items'] ?? null) ? $cfg['items'] : [];
        $itemCount = 0;
        $freeCount = 0;
        foreach ($items as $it) {
            if (!is_array($it)) continue;
            $pid = (int)($it['product_id'] ?? 0);
            $price = (int)($it['promo_price'] ?? 0);
            if ($pid > 0 && $price >= 0) {
                $itemCount++;
                if ($price === 0) $freeCount++;
            }
        }

        $line1 = 'SP chính: <span class="fw-semibold">' . h((string)$mainCount) . ' SP</span>';
        $line2 = 'Ưu đãi kèm: <span class="fw-semibold">' . h((string)$itemCount) . ' SP</span>' . ($freeCount > 0 ? ' <span class="text-muted">(' . h((string)$freeCount) . ' miễn phí)</span>' : '');
    } elseif ($type === 'bxgy') {
        $buy = (int)($cfg['buy_qty'] ?? 0);
        $gift = (int)($cfg['gift_qty'] ?? 0);
        $mainIds = is_array($cfg['main_product_ids'] ?? null) ? $cfg['main_product_ids'] : [];
        $giftIds = is_array($cfg['gift_product_ids'] ?? null) ? $cfg['gift_product_ids'] : [];
        $maxGiftPerOrder = (int)($cfg['max_gift_per_order'] ?? 0);

        $mainCount = count(array_values(array_filter(array_map('intval', $mainIds), fn($v) => $v > 0)));
        $giftUniq = [];
        foreach ($giftIds as $v) {
            $v = (int)$v;
            if ($v > 0) $giftUniq[$v] = $v;
        }
        $giftCount = count($giftUniq);
        $giftSame = $giftCount === 0;

        $line1 = 'Mua <span class="fw-semibold">' . h((string)max(0, $buy)) . '</span> tặng <span class="fw-semibold">' . h((string)max(0, $gift)) . '</span>';
        $line2 = 'Áp dụng: <span class="fw-semibold">' . h((string)$mainCount) . ' SP</span> · Quà: <span class="fw-semibold">' . h($giftSame ? 'cùng SP chính' : ((string)$giftCount . ' SP')) . '</span>';
        if ($maxGiftPerOrder > 0) {
            $line2 .= ' · Tối đa: <span class="fw-semibold">' . h((string)$maxGiftPerOrder) . '</span>';
        }
    } else {
        $line1 = h(promoSummary($row));
    }

    $html = '<div class="small text-muted">'
        . ($line1 !== '' ? '<div>' . $line1 . '</div>' : '')
        . ($line2 !== '' ? '<div>' . $line2 . '</div>' : '')
        . '</div>';
    return $html;
}

function promoTimeHtml($startAt, $endAt): string {
    $startAt = trim((string)($startAt ?? ''));
    $endAt = trim((string)($endAt ?? ''));
    $startText = ($startAt !== '') ? h($startAt) : '<span class="text-muted">Không giới hạn</span>';
    $endText = ($endAt !== '') ? h($endAt) : '<span class="text-muted">Không giới hạn</span>';

    $statusHtml = '';
    if ($endAt !== '') {
        $endTime = strtotime($endAt);
        $startTime = ($startAt !== '') ? strtotime($startAt) : 0;
        $now = time();

        if ($endTime < $now) {
            $statusHtml = '<div class="mt-1"><span class="badge bg-danger-subtle text-danger border border-danger-subtle py-1"><i class="bi bi-x-circle me-1"></i>Đã kết thúc</span></div>';
        } elseif ($startTime > $now) {
            $statusHtml = '<div class="mt-1"><span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle py-1"><i class="bi bi-clock-history me-1"></i>Chưa bắt đầu</span></div>';
        } else {
            // Đang diễn ra
            $diff = $endTime - $now;
            if ($diff < 86400) {
                $hours = floor($diff / 3600);
                $statusHtml = '<div class="mt-1"><span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle py-1"><i class="bi bi-exclamation-triangle me-1"></i>Sắp hết hạn (còn ' . $hours . 'h)</span></div>';
            } else {
                $days = floor($diff / 86400);
                $statusHtml = '<div class="mt-1"><span class="badge bg-success-subtle text-success border border-success-subtle py-1"><i class="bi bi-check-circle me-1"></i>Đang diễn ra (còn ' . $days . ' ngày)</span></div>';
            }
        }
    }

    return '<div class="small text-muted">'
        . '<div><span class="fw-semibold">Từ:</span> ' . $startText . '</div>'
        . '<div><span class="fw-semibold">Đến:</span> ' . $endText . '</div>'
        . $statusHtml
        . '</div>';
}

function promoSummary($row) {
    $type = (string)($row['promo_type'] ?? '');
    $cfg = json_decode((string)($row['config_json'] ?? ''), true) ?: [];
    if ($type === 'gift') {
        $threshold = (int)($cfg['threshold_amount'] ?? 0);
        $ids = $cfg['gift_product_ids'] ?? [];
        $limit = (int)($cfg['gift_choice_limit'] ?? 1);
        $parts = [];
        if ($threshold > 0) $parts[] = 'ĐH từ ' . number_format($threshold, 0, ',', '.') . 'đ';
        if ($ids) $parts[] = 'Quà: ' . count($ids) . ' SP';
        $parts[] = 'Chọn tối đa ' . $limit;
        return implode(' · ', $parts);
    }
    if ($type === 'combo') {
        $mainIdsSet = [];
        if (!empty($cfg['main_product_ids']) && is_array($cfg['main_product_ids'])) {
            foreach ($cfg['main_product_ids'] as $mid) {
                $mid = (int)$mid;
                if ($mid > 0) $mainIdsSet[$mid] = $mid;
            }
        }
        $legacyMain = (int)($cfg['main_product_id'] ?? 0);
        if ($legacyMain > 0) $mainIdsSet[$legacyMain] = $legacyMain;
        $mainList = array_values($mainIdsSet);
        $main = $mainList ? (int)$mainList[0] : 0;
        $items = is_array($cfg['items'] ?? null) ? $cfg['items'] : [];
        $parts = [];
        if ($main > 0) {
            $countMain = count($mainList);
            if ($countMain > 1) {
                $parts[] = 'SP chính #' . $main . ' + ' . ($countMain - 1) . ' SP';
            } else {
                $parts[] = 'SP chính #' . $main;
            }
        }
        if ($items) $parts[] = 'Kèm ' . count($items) . ' SP ưu đãi';
        return implode(' · ', $parts);
    }
    if ($type === 'bxgy') {
        $cfg = json_decode((string)($row['config_json'] ?? ''), true) ?: [];
        $buy = (int)($cfg['buy_qty'] ?? 0);
        $gift = (int)($cfg['gift_qty'] ?? 0);
        $mainIds = is_array($cfg['main_product_ids'] ?? null) ? $cfg['main_product_ids'] : [];
        $main = $mainIds ? (int)$mainIds[0] : 0;
        $parts = [];
        if ($buy > 0 && $gift > 0) {
            $parts[] = 'Mua ' . $buy . ' tặng ' . $gift;
        }
        if ($main > 0) {
            $extraMain = max(0, count($mainIds) - 1);
            $label = '#'.$main;
            if ($extraMain > 0) {
                $label .= ' +' . $extraMain . ' SP';
            }
            $parts[] = 'SP chính ' . $label;
        }
        return implode(' · ', $parts);
    }
    return '';
}
if (defined('AJAX_ONLY')) { return; }
?>


<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-md-center align-items-start mb-4 flex-column flex-sm-row gap-3">
        <div class="d-flex align-items-start gap-3">
            <div class="header-icon rounded-3 d-flex align-items-center justify-content-center" style="width: 48px; height: 48px; min-width: 48px; background-color: rgba(12, 76, 41, 0.08) !important; color: var(--theme-primary, #0c4c29) !important; border: 1px solid rgba(12, 76, 41, 0.15);">
                <i class="bi bi-gift fs-4"></i>
            </div>
            <div>
                <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                    <h1 class="h3 mb-0 fw-bold" style="font-size: 1.45rem; color: #1e293b !important; letter-spacing: -0.01em;">Quản lý chiến dịch</h1>
                    <span class="badge bg-light text-secondary border border-secondary-subtle px-2 py-1 fw-semibold" id="promoMeta" style="font-size: 0.72rem;">Tổng: <?= is_array($Promos) ? count($Promos) : 0 ?> chiến dịch</span>
                </div>
                <!-- Description for Desktop / Tablet -->
                <p class="text-muted mb-0 small d-none d-md-block" style="font-size: 0.82rem; line-height: 1.45; max-width: 600px;">
                    Trang quản trị các chiến dịch khuyến mãi, quà tặng hóa đơn, mua kèm deal sốc, mua X tặng Y
                </p>
                <!-- Description for Mobile -->
                <p class="text-muted mb-0 small d-block d-md-none" style="font-size: 0.78rem; line-height: 1.4;">
                    Cấu hình quà tặng hóa đơn, mua kèm deal sốc, mua X tặng Y.
                </p>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-primary d-flex align-items-center justify-content-center gap-2 px-3 py-2 border-0 shadow-sm" id="btnNewPromo" style="font-size: 0.88rem; font-weight: 600; height: 40px;">
                <i class="bi bi-plus-lg fs-5"></i>
                <span class="d-none d-sm-inline">Tạo chiến dịch</span>
                <span class="d-inline d-sm-none">Tạo mới</span>
            </button>
        </div>
    </div>

    <!-- STANDALONE KPI SUMMARY CARDS -->
    <div class="mb-4 grid-4" id="summaryGrid">
        <div class="summary-card active" data-promo-tab="all">
            <div class="d-flex flex-column">
                <span>Tất cả</span>
                <strong class="mt-1"><?= (int)$templateCounts['all'] ?></strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-collection-fill fs-5"></i>
            </div>
        </div>
        <div class="summary-card" data-promo-tab="gift">
            <div class="d-flex flex-column">
                <span>Quà tặng hoá đơn</span>
                <strong class="mt-1"><?= (int)$templateCounts['gift'] ?></strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-gift-fill fs-5"></i>
            </div>
        </div>
        <div class="summary-card" data-promo-tab="combo">
            <div class="d-flex flex-column">
                <span>Mua kèm deal sốc</span>
                <strong class="mt-1"><?= (int)$templateCounts['combo'] ?></strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-lightning-charge-fill fs-5"></i>
            </div>
        </div>
        <div class="summary-card" data-promo-tab="bxgy">
            <div class="d-flex flex-column">
                <span>Mua X tặng Y</span>
                <strong class="mt-1"><?= (int)$templateCounts['bxgy'] ?></strong>
            </div>
            <div class="summary-icon">
                <i class="bi bi-tags-fill fs-5"></i>
            </div>
        </div>
    </div>

    <!-- STANDALONE SEARCH & FILTERS -->
    <div class="card border-0 shadow-sm mb-4 rounded-4" style="background: #fff; border: 1px solid var(--order-border, #e5e7eb) !important;">
        <div class="card-body p-3">
            <div class="row g-2 align-items-end">
                <div class="col-12 col-md-5">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: .68rem; letter-spacing: .03em;">Tìm kiếm chiến dịch</label>
                    <div class="position-relative">
                        <i class="bi bi-search position-absolute" style="left:12px; top:50%; transform:translateY(-50%); color:#94a3b8; font-size:.88rem; pointer-events:none;"></i>
                        <input type="text" id="promoSearchBox" class="form-control" placeholder="Tìm tên chiến dịch, loại, cấu hình..." style="padding-left:38px !important; border-radius:10px; height: 42px; border-color: #cbd5e1; font-size: 0.9rem;">
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: .68rem; letter-spacing: .03em;">Trạng thái</label>
                    <select id="promoFilterStatus" class="form-select" style="border-radius:10px; height: 42px; border-color: #cbd5e1; font-size: 0.9rem;">
                        <option value="all">Tất cả trạng thái</option>
                        <option value="1">Đang bật</option>
                        <option value="0">Đang tắt</option>
                    </select>
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label small fw-semibold text-muted text-uppercase mb-1" style="font-size: .68rem; letter-spacing: .03em;">Sắp xếp theo</label>
                    <select id="promoSortOrder" class="form-select" style="border-radius:10px; height: 42px; border-color: #cbd5e1; font-size: 0.9rem;">
                        <option value="priority_desc">Ưu tiên cao nhất (Mặc định)</option>
                        <option value="id_desc">Mới nhất</option>
                        <option value="id_asc">Cũ nhất</option>
                        <option value="name_asc">Tên chiến dịch (A-Z)</option>
                        <option value="name_desc">Tên chiến dịch (Z-A)</option>
                    </select>
                </div>
            </div>
        </div>
    </div>

    <!-- CAMPAIGN LIST DATA TABLE -->
    <div class="card border border-light-subtle shadow-sm rounded-3 overflow-hidden">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 table-striped" id="promoTable">
                <thead class="table-light">
                    <tr>
                        <th class="text-center ps-3" style="width:40px;"></th>
                        <th class="ps-3" style="width:200px;">Tên chiến dịch</th>
                        <th style="width:220px;">Sản phẩm áp dụng</th>
                        <th style="width:130px;">Thời gian áp dụng</th>
                        <th class="text-center" style="width:80px;">Bật</th>
                        <th class="text-end pe-4" style="width:120px;">Thao tác</th>
                        <th class="d-none">ID</th>
                        <th class="d-none">Ưu tiên</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                    $productNameById = [];
                    $productImageById = [];
                    foreach ($productOptions as $po) {
                        $pid = (int)($po['id'] ?? 0);
                        if ($pid > 0) {
                            $productNameById[$pid] = (string)($po['name'] ?? '');
                            $productImageById[$pid] = (string)($po['image_url'] ?? '');
                        }
                    }
                ?>
                <?php foreach ($Promos as $p): ?>
                    <?php
                        $pt = (string)($p['promo_type'] ?? '');
                        $badgeStyle = '';
                        if ($pt === 'gift') {
                            $badgeStyle = 'background-color: rgba(12, 76, 41, 0.06) !important; color: var(--theme-primary, #0c4c29) !important; border: 1px solid rgba(12, 76, 41, 0.15) !important;';
                        } else if ($pt === 'combo') {
                            $badgeStyle = 'background-color: #fff7ed !important; color: #ea580c !important; border: 1px solid #ffedd5 !important;';
                        } else if ($pt === 'bxgy') {
                            $badgeStyle = 'background-color: #eff6ff !important; color: #2563eb !important; border: 1px solid #dbeafe !important;';
                        }
                    ?>
                    <tr data-row='<?= h(json_encode($p, JSON_UNESCAPED_UNICODE)) ?>' data-promo-template="<?= h($pt) ?>">
                        <td class="text-center ps-3 drag-handle">
                            <i class="bi bi-grip-vertical fs-5"></i>
                        </td>
                        <!-- Tên chiến dịch kết hợp với loại chiến dịch -->
                        <td class="ps-3">
                            <div class="fw-bold text-dark-emphasis mb-1" style="font-size: 0.92rem; line-height: 1.35; max-width: 240px; white-space: normal; word-break: break-word;">
                                <?= h($p['name'] ?? '') ?>
                            </div>
                            <span class="badge text-uppercase" style="font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; letter-spacing: 0.02em; <?= $badgeStyle ?>">
                                <?= h(promoTypeLabel($pt)) ?>
                            </span>
                        </td>
                        <td>
                        <?php
                            $cfg = [];
                            try {
                                $cfg = json_decode($p['config_json'] ?? '{}', true) ?: [];
                            } catch (Throwable $e) {}

                            $promoType = $p['promo_type'] ?? '';
                            $appliedProductIds = [];
                            $isGlobalOrder = false;

                            if ($promoType === 'gift') {
                                $isGlobalOrder = true;
                            } else if ($promoType === 'combo') {
                                $appliedProductIds = $cfg['main_product_ids'] ?? [];
                                if (empty($appliedProductIds) && isset($cfg['main_product_id'])) {
                                    $appliedProductIds = [$cfg['main_product_id']];
                                }
                            } else if ($promoType === 'bxgy') {
                                $appliedProductIds = $cfg['main_product_ids'] ?? [];
                            }

                            if ($isGlobalOrder):
                        ?>
                            <div class="d-flex align-items-center gap-2">
                                <div class="product-avatar bg-primary-subtle text-primary rounded d-flex align-items-center justify-content-center" style="width:32px; height:32px; font-size:1rem; flex-shrink:0;">
                                    <i class="bi bi-cart3"></i>
                                </div>
                                <div class="min-width-0">
                                    <div class="fw-semibold text-truncate small" style="max-width: 170px;">Áp dụng toàn sàn</div>
                                    <div class="text-muted small" style="font-size: 0.75rem;">Cả đơn hàng</div>
                                </div>
                            </div>
                        <?php elseif (!empty($appliedProductIds)): 
                            $firstPid = (int)($appliedProductIds[0] ?? 0);
                            $firstName = $productNameById[$firstPid] ?? "Sản phẩm #{$firstPid}";
                            $firstImg = $productImageById[$firstPid] ?? '';
                            
                            $imgSrc = $firstImg !== '' ? h(to_abs_url((string)$firstImg, (string)$baseUrl)) : '';
                            
                            if ($imgSrc === '') {
                                $imgHtml = '<div class="product-avatar bg-light text-secondary rounded border d-flex align-items-center justify-content-center fw-bold" style="width:32px; height:32px; font-size:0.75rem; flex-shrink:0;">SP</div>';
                            } else {
                                $imgHtml = '<img src="' . $imgSrc . '" class="rounded border object-fit-cover" style="width:32px; height:32px; flex-shrink:0;" onerror="this.outerHTML=\'<div class=\\\'product-avatar bg-light text-secondary rounded border d-flex align-items-center justify-content-center fw-bold\\\' style=\\\'width:32px; height:32px; font-size:0.75rem; flex-shrink:0;\\\'>SP</div>\'">';
                            }
                            
                            $otherCount = count($appliedProductIds) - 1;
                        ?>
                            <div class="d-flex align-items-center gap-2">
                                <?= $imgHtml ?>
                                <div class="min-width-0">
                                    <div class="fw-semibold text-truncate small" style="max-width:170px; font-size: 0.85rem;" title="<?= h($firstName) ?>"><?= h($firstName) ?></div>
                                    <?php if ($otherCount > 0): ?>
                                        <div class="text-primary fw-semibold" style="font-size: 0.78rem;">+<?= $otherCount ?> sản phẩm khác</div>
                                    <?php else: ?>
                                        <div class="text-muted" style="font-size: 0.75rem;">ID: #<?= $firstPid ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <span class="text-muted small">—</span>
                        <?php endif; ?>
                        </td>
                        <td class="promo-time-cell" data-order="<?= h((string)($p['start_at'] ?? '')) ?>">
                            <?= promoTimeHtml($p['start_at'] ?? '', $p['end_at'] ?? '') ?>
                        </td>
                        <td class="text-center">
                            <div class="form-check form-switch d-inline-block">
                                <input class="form-check-input jsTogglePromo" type="checkbox" <?= ((int)($p['is_active'] ?? 0)===1)?'checked':'' ?> >
                            </div>
                        </td>
                        <td class="text-end pe-4">
                            <div class="voucher-actions">
                                <button type="button" class="btn btn-outline-secondary jsViewPromo" title="Xem chi tiết"><i class="bi bi-eye"></i></button>
                                <button type="button" class="btn btn-outline-primary jsEditPromo" title="Sửa"><i class="bi bi-pencil-square"></i></button>
                                <button type="button" class="btn btn-outline-danger jsDelPromo" title="Xóa"><i class="bi bi-trash"></i></button>
                            </div>
                        </td>
                        <td class="d-none"><?= (int)$p['id'] ?></td>
                        <td class="d-none" data-order="<?= (int)($p['priority'] ?? 0) <= 0 ? 99999 : (int)($p['priority'] ?? 0) ?>"><?= (int)($p['priority'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div id="promosEmpty" class="text-center py-5 text-muted" style="display:none;">
                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                Không có chiến dịch phù hợp bộ lọc.
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="promoViewModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <div>
                    <div class="fw-bold" id="promoViewTitle">Chi tiết chiến dịch</div>
                    <div class="text-muted small" id="promoViewMeta">—</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light">
                <div class="card border-0">
                    <div class="card-body" id="promoViewBody"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="promoModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="promoModalTitle">Tạo chiến dịch</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Step Navigation Progress Bar -->
                <div class="wizard-steps">
                    <div class="wizard-step-node active" id="nodeStep1" data-target-step="1">
                        <div class="wizard-step-circle">1</div>
                        <div class="wizard-step-info">
                            <div class="wizard-step-title">Chọn chiến dịch</div>
                            <div class="wizard-step-subtitle">Chọn mẫu khuyến mãi</div>
                        </div>
                    </div>
                    <div class="wizard-step-node" id="nodeStep2" data-target-step="2">
                        <div class="wizard-step-circle">2</div>
                        <div class="wizard-step-info">
                            <div class="wizard-step-title">Thiết lập chi tiết</div>
                            <div class="wizard-step-subtitle">Cấu hình toàn bộ</div>
                        </div>
                    </div>
                </div>

                <form id="promoForm">
                    <input type="hidden" name="id" value="0">

                    <!-- STEP 1: BASIC INFO -->
                    <div class="wizard-step" data-step="1" id="wizardStep1">
                        <div class="row g-2 mb-2">
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold">Tên chiến dịch</label>
                                <input name="name" class="form-control" placeholder="Nhập tên chiến dịch" required>
                            </div>
                            <div class="col-md-12">
                                <label class="form-label small fw-semibold d-block">Loại chiến dịch</label>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input jsPromoType" type="radio" name="promo_type" id="promoTypeGift" value="gift" checked>
                                    <label class="form-check-label" for="promoTypeGift">Quà tặng hoá đơn</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input jsPromoType" type="radio" name="promo_type" id="promoTypeCombo" value="combo">
                                    <label class="form-check-label" for="promoTypeCombo">Mua thêm deal sốc</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input jsPromoType" type="radio" name="promo_type" id="promoTypeBxgy" value="bxgy">
                                    <label class="form-check-label" for="promoTypeBxgy">Mua x tặng y</label>
                                </div>
                            </div>
                        </div>
                        <div class="row g-2 mb-2">
                            <div class="col-md-6 col-6">
                                <label class="form-label small fw-semibold">Bắt đầu</label>
                                <input name="start_at" type="datetime-local" class="form-control">
                            </div>
                            <div class="col-md-6 col-6">
                                <label class="form-label small fw-semibold">Kết thúc</label>
                                <input name="end_at" type="datetime-local" class="form-control">
                            </div>
                            <div class="col-md-6 col-6">
                                <label class="form-label small fw-semibold">Thứ tự ưu tiên (1-100: 1 cao nhất, 0 mặc định)</label>
                                <input name="priority" type="number" class="form-control" min="0" max="100" value="0">
                            </div>
                            <div class="col-6 d-flex align-items-center">
                                <div class="form-check form-switch mt-4">
                                    <input class="form-check-input" type="checkbox" role="switch" id="promoIsActive" checked>
                                    <label class="form-check-label" for="promoIsActive">Trạng thái bật</label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- STEP 2: DETAILS CONFIGURATION -->
                    <div class="wizard-step" data-step="2" id="wizardStep2" style="display:none;">
                        <!-- Promo Type 1: Quà tặng hoá đơn -->
                        <div id="promoGiftConfig">
                            <div class="row g-2 mb-2">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Tổng trị giá đơn hàng tối thiểu (Ngưỡng hoá đơn - đ)</label>
                                    <input name="threshold_amount" type="number" class="form-control" min="0" step="1" value="0">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Số lượng quà tặng được nhận</label>
                                    <input name="gift_choice_limit" type="number" class="form-control" min="1" step="1" value="1">
                                </div>
                                <div class="col-md-12 mt-3">
                                    <label class="form-label small fw-semibold">Chọn sản phẩm quà tặng</label>
                                    <input type="hidden" name="gift_product_ids" id="giftProductIds" value="">
                                    <input type="hidden" name="gift_variant_ids" id="giftVariantIds" value="">
                                    <div class="product-picker">
                                        <div class="product-picker-toolbar">
                                            <select class="form-select form-select-sm" id="giftCategoryFilter" style="max-width:220px;">
                                                <option value="0">Tất cả danh mục</option>
                                            </select>
                                            <input type="text" class="form-control form-control-sm" id="giftProductFilterInput" placeholder="Lọc theo tên/SKU" style="max-width:220px;">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnGiftPickAll">Tất cả</button>
                                            <button type="button" class="btn btn-light btn-sm" id="btnGiftPickClear">Bỏ</button>
                                            <span class="small text-muted align-self-center">Đã chọn: <b id="giftPickedCount">0</b> sản phẩm</span>
                                        </div>
                                        <div class="product-picker-list" id="giftProductPickerList"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Promo Type 2: Mua thêm deal sốc -->
                        <div id="promoComboConfig" style="display:none;">
                            <div class="row g-2 mb-2">
                                <div class="col-md-12">
                                    <label class="form-label small fw-semibold">Sản phẩm gốc (Sản phẩm chính)</label>
                                    <input type="hidden" name="main_product_ids" id="mainProductIds" value="">
                                    <div class="product-picker">
                                        <div class="product-picker-toolbar">
                                            <select class="form-select form-select-sm" id="mainProductCategoryFilter" style="max-width:220px;">
                                                <option value="0">Tất cả danh mục</option>
                                            </select>
                                            <input type="text" class="form-control form-control-sm" id="mainProductFilterInput" placeholder="Lọc theo tên/SKU" style="max-width:220px;">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnMainPickAll">Tất cả</button>
                                            <button type="button" class="btn btn-light btn-sm" id="btnMainPickClear">Bỏ</button>
                                            <span class="small text-muted align-self-center">Đã chọn: <b id="mainProductPickedCount">0</b> sản phẩm</span>
                                        </div>
                                        <div class="product-picker-list" id="mainProductPickerList"></div>
                                    </div>
                                </div>
                                <div class="col-md-12 mt-3">
                                    <label class="form-label small fw-semibold">Sản phẩm mua kèm (giá rẻ hơn)</label>
                                    <textarea name="combo_items" class="form-control" rows="4" style="display:none;"></textarea>
                                    <div class="product-picker">
                                        <div class="product-picker-toolbar">
                                            <select class="form-select form-select-sm" id="comboProductCategoryFilter" style="max-width:220px;">
                                                <option value="0">Tất cả danh mục</option>
                                            </select>
                                            <input type="text" class="form-control form-control-sm" id="comboProductFilterInput" placeholder="Lọc theo tên/SKU" style="max-width:220px;">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnComboPickAll">Tất cả</button>
                                            <button type="button" class="btn btn-light btn-sm" id="btnComboPickClear">Bỏ</button>
                                            <span class="small text-muted align-self-center">Đã chọn: <b id="comboPickedCount">0</b> sản phẩm</span>
                                        </div>
                                        <div class="product-picker-list" id="comboProductPickerList"></div>
                                    </div>
                                    <div class="form-text small mt-1">Chọn sản phẩm mua kèm và nhập giá khuyến mãi cho từng biến thể / sản phẩm.</div>
                                </div>
                            </div>
                        </div>

                        <!-- Promo Type 3: Mua X tặng Y -->
                        <div id="promoBxgyConfig" style="display:none;">
                            <div class="row g-2 mb-2">
                                <div class="col-md-12">
                                    <label class="form-label small fw-semibold">Sản phẩm gốc (Sản phẩm chính áp dụng Mua X tặng Y)</label>
                                    <input type="hidden" name="bxgy_main_product_ids" id="bxgyMainProductIds" value="">
                                    <input type="hidden" name="bxgy_main_variant_ids" id="bxgyMainVariantIds" value="">
                                    <div class="product-picker">
                                        <div class="product-picker-toolbar">
                                            <select class="form-select form-select-sm" id="bxgyMainCategoryFilter" style="max-width:220px;">
                                                <option value="0">Tất cả danh mục</option>
                                            </select>
                                            <input type="text" class="form-control form-control-sm" id="bxgyMainProductFilterInput" placeholder="Lọc theo tên/SKU" style="max-width:220px;">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btnBxgyMainPickAll">Tất cả</button>
                                            <button type="button" class="btn btn-light btn-sm" id="btnBxgyMainPickClear">Bỏ</button>
                                            <span class="small text-muted align-self-center">Đã chọn: <b id="bxgyMainPickedCount">0</b> sản phẩm</span>
                                        </div>
                                        <div class="product-picker-list" id="bxgyMainProductPickerList"></div>
                                    </div>
                                </div>
                                <div class="col-md-3 mt-3">
                                    <label class="form-label small fw-semibold">Số lượng mua (X)</label>
                                    <input name="bxgy_buy_qty" type="number" class="form-control" min="1" step="1" value="2">
                                </div>
                                <div class="col-md-3 mt-3">
                                    <label class="form-label small fw-semibold">Số lượng tặng (Y)</label>
                                    <input name="bxgy_gift_qty" type="number" class="form-control" min="1" step="1" value="1">
                                </div>
                                <div class="col-md-6 mt-3">
                                    <label class="form-label small fw-semibold">Tối đa quà / đơn hàng (0 = không giới hạn)</label>
                                    <input name="bxgy_max_gift_per_order" type="number" class="form-control" min="0" step="1" value="0">
                                </div>
                                <div class="col-md-12 mt-3">
                                    <label class="form-label small fw-semibold">Sản phẩm mua kèm (Quà tặng)</label>
                                    <div class="form-check form-switch mb-2">
                                        <input class="form-check-input" type="checkbox" id="bxgyGiftSameProduct" checked>
                                        <label class="form-check-label small fw-semibold" for="bxgyGiftSameProduct">Tặng cùng sản phẩm gốc (Chính)</label>
                                    </div>
                                    <input type="hidden" name="bxgy_gift_same" id="bxgyGiftSame" value="1">

                                    <div id="bxgyGiftPickerWrap" style="display:none;" class="mt-2">
                                        <input type="hidden" name="bxgy_gift_product_ids" id="bxgyGiftProductIds" value="">
                                        <input type="hidden" name="bxgy_gift_variant_ids" id="bxgyGiftVariantIds" value="">
                                        <div class="product-picker">
                                            <div class="product-picker-toolbar">
                                                <select class="form-select form-select-sm" id="bxgyGiftCategoryFilter" style="max-width:220px;">
                                                    <option value="0">Tất cả danh mục</option>
                                                </select>
                                                <input type="text" class="form-control form-control-sm" id="bxgyGiftProductFilterInput" placeholder="Lọc theo tên/SKU" style="max-width:220px;">
                                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnBxgyGiftPickAll">Tất cả</button>
                                                <button type="button" class="btn btn-light btn-sm" id="btnBxgyGiftPickClear">Bỏ</button>
                                                <span class="small text-muted align-self-center">Đã chọn: <b id="bxgyGiftPickedCount">0</b> sản phẩm</span>
                                            </div>
                                            <div class="product-picker-list" id="bxgyGiftProductPickerList"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer d-flex justify-content-between">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Đóng</button>
                <div>
                    <button type="button" class="btn btn-secondary me-1" id="btnWizardBack" style="display:none;"><i class="bi bi-chevron-left"></i> Quay lại</button>
                    <button type="button" class="btn btn-primary" id="btnWizardNext">Tiếp tục <i class="bi bi-chevron-right"></i></button>
                    <button type="button" class="btn btn-success" id="btnSavePromo" style="display:none;"><i class="bi bi-check-lg"></i> Hoàn tất & Lưu</button>
                </div>
            </div>
        </div>
    </div>
</div>
        </div>
    </div>
</div>

<script src="<?= h($baseUrl) ?>/assets/js/jquery.dataTables.min.js"></script>
<script src="<?= h($baseUrl) ?>/assets/js/dataTables.bootstrap5.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
(function(){
    let currentPromoTab = 'all';
    let uniqueIdCounter = 0;
    const API = '<?= $baseUrl ?>/core_admin/ecommerce/promotion.php';
    const PRODUCT_OPTIONS = <?= json_encode($productOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const PRODUCT_VARIANTS = <?= json_encode($productVariantOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const CATEGORY_OPTIONS = <?= json_encode($categoryOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const notify = (msg, type = 'info') => {
        if (window.toastr && toastr[type]) toastr[type](msg);
        else alert(msg);
    };

    const modalEl = document.getElementById('promoModal');
    const promoModal = new bootstrap.Modal(modalEl);

    const viewModalEl = document.getElementById('promoViewModal');
    const promoViewModal = viewModalEl ? new bootstrap.Modal(viewModalEl) : null;

    function escapeHtml(str){
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function uniqIntList(arr){
        const set = new Set();
        (arr || []).forEach(v => {
            const n = parseInt(v, 10);
            if (Number.isFinite(n) && n > 0) set.add(n);
        });
        return Array.from(set);
    }

    function toBadge(text){
        return `<span class="badge text-bg-light border">${escapeHtml(text || '')}</span>`;
    }

    function promoTypeLabelJs(t){
        t = String(t || '');
        if (t === 'gift') return 'Quà tặng hoá đơn';
        if (t === 'combo') return 'Mua thêm deal sốc';
        if (t === 'bxgy') return 'Mua x tặng y';
        return t;
    }

    function formatDateText(v){
        const s = String(v || '').trim();
        return s ? escapeHtml(s) : '<span class="text-muted">Không giới hạn</span>';
    }

    function formatMoneyText(v){
        const n = parseInt(String(v || '0').replace(/[^0-9]/g, ''), 10) || 0;
        return formatMoney(n) + 'đ';
    }

    function getVariantInfo(pid, vid){
        pid = parseInt(pid || 0, 10) || 0;
        vid = parseInt(vid || 0, 10) || 0;
        if (pid <= 0 || vid <= 0) return null;
        const list = PRODUCT_VARIANTS && PRODUCT_VARIANTS[String(pid)] ? PRODUCT_VARIANTS[String(pid)] : (PRODUCT_VARIANTS && PRODUCT_VARIANTS[pid] ? PRODUCT_VARIANTS[pid] : []);
        if (!Array.isArray(list)) return null;
        const v = list.find(x => Number(x.id || 0) === vid);
        return v || null;
    }

    function getProductInfo(pid){
        pid = parseInt(pid || 0, 10) || 0;
        if (pid <= 0) return null;
        return (PRODUCT_OPTIONS || []).find(x => Number(x.id || 0) === pid) || null;
    }

    function formatVariantLabel(v, showPrice = true){
        if (!v) return '';
        let label = String(v.name || 'Biến thể');
        if (v.sku) label += ` (SKU: ${v.sku})`;
        if (showPrice && v.price) label += ` - ${formatMoney(v.price)}đ`;
        return label;
    }

    function formatMoney(v){
        const n = parseInt(String(v || '0').replace(/[^0-9]/g, ''), 10) || 0;
        return n.toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    function toDtLocal(v){
        if (!v) return '';
        return String(v).replace(' ', 'T').slice(0, 16);
    }

    function fromDtLocal(v){
        return v ? String(v).replace('T', ' ') + ':00' : '';
    }

    function hasRealVariants(p) {
        const groups = Array.isArray(p.groups) ? p.groups : [];
        const noGroupVariants = Array.isArray(p.no_group_variants) ? p.no_group_variants : [];
        if (groups.length > 0) return true;
        if (noGroupVariants.length > 1) return true;
        if (noGroupVariants.length === 1) {
            const vName = String(noGroupVariants[0].name || '').trim();
            return vName !== '' && vName !== 'Mặc định';
        }
        return false;
    }

    // ==========================================
    // ADVANCED HIERARCHICAL TREE PICKER ENGINE
    // ==========================================
    function renderHierarchicalPicker({
        containerId,
        type,
        categoryFilterId,
        searchFilterId,
        hiddenProductIdsId,
        hiddenVariantIdsId
    }) {
        const listEl = document.getElementById(containerId);
        if (!listEl) return;

        // 1. Fetch current selection state from inputs
        const getSelectedProductIds = () => {
            const raw = String(document.getElementById(hiddenProductIdsId)?.value || '').trim();
            if (!raw) return [];
            return Array.from(new Set(raw.split(',').map(v => parseInt(v.trim(), 10)).filter(v => Number.isFinite(v) && v > 0)));
        };

        const setSelectedProductIds = (ids) => {
            const el = document.getElementById(hiddenProductIdsId);
            if (el) el.value = ids.join(',');
            const countEl = document.getElementById(containerId.replace('PickerList', 'PickedCount'));
            if (countEl) countEl.textContent = String(ids.length);
        };

        const getSelectedVariantMap = () => {
            if (!hiddenVariantIdsId) return {};
            return parseVariantMapFromHidden(hiddenVariantIdsId);
        };

        const setSelectedVariantMap = (map) => {
            if (!hiddenVariantIdsId) return;
            writeVariantMapToHidden(hiddenVariantIdsId, map);
        };

        const getComboItemsMap = () => {
            if (type !== 'combo_sub') return null;
            const raw = String(document.querySelector('textarea[name="combo_items"]')?.value || '').trim();
            const map = new Map();
            if (raw) {
                const rows = raw.split('\n');
                rows.forEach(r => {
                    const p = r.trim().split(':');
                    if (p.length >= 2) {
                        const key = p[0].trim();
                        const val = parseInt(p[1].trim(), 10) || 0;
                        map.set(key, val);
                    }
                });
            }
            return map;
        };

        const setComboItemsMap = (map) => {
            if (type !== 'combo_sub') return;
            const textarea = document.querySelector('textarea[name="combo_items"]');
            if (!textarea) return;
            const lines = [];
            map.forEach((val, key) => {
                lines.push(`${key}:${val}`);
            });
            textarea.value = lines.join('\n');
            const countEl = document.getElementById('comboPickedCount');
            if (countEl) countEl.textContent = String(map.size);
        };

        const selectedProductIds = new Set(getSelectedProductIds());
        const selectedVariantMap = getSelectedVariantMap();
        const comboItemsMap = getComboItemsMap();

        // 2. Filter products list
        const keyword = String(document.getElementById(searchFilterId)?.value || '').trim().toLowerCase();
        const catId = parseInt(String(document.getElementById(categoryFilterId)?.value || '0'), 10) || 0;

        const filteredProducts = (PRODUCT_OPTIONS || []).filter(p => {
            const pid = Number(p.id || 0);
            if (pid <= 0) return false;
            if (catId > 0 && Number(p.category_id || 0) !== catId) return false;
            if (!keyword) return true;
            const hay = `${p.name || ''} ${p.sku || ''} ${p.id || ''}`.toLowerCase();
            return hay.includes(keyword);
        });

        // Ưu tiên đưa sản phẩm ĐÃ CHỌN lên đầu danh sách (giữ thứ tự gốc trong mỗi nhóm)
        filteredProducts.sort((a, b) => {
            const sa = selectedProductIds.has(Number(a.id || 0)) ? 0 : 1;
            const sb = selectedProductIds.has(Number(b.id || 0)) ? 0 : 1;
            return sa - sb;
        });

        if (!filteredProducts.length) {
            listEl.innerHTML = '<div class="product-picker-empty">Không có sản phẩm phù hợp.</div>';
            return;
        }

        // 3. Render list of products as a collapsible tree
        listEl.innerHTML = filteredProducts.map(p => {
            const pid = Number(p.id || 0);
            const pChecked = selectedProductIds.has(pid) ? 'checked' : '';
            const pName = String(p.name || 'Sản phẩm');
            const pSku = String(p.sku || '');
            const pPrice = Number(p.price || 0);

            const groups = Array.isArray(p.groups) ? p.groups : [];
            const noGroupVariants = Array.isArray(p.no_group_variants) ? p.no_group_variants : [];
            const hasVariants = hasRealVariants(p);

            let variantsHtml = '';
            if (hasVariants) {
                const pVariantsSelected = new Set(selectedVariantMap[String(pid)] || []);

                const groupsHtml = groups.map(g => {
                    const gName = String(g.name || 'Nhóm');
                    const gVariants = Array.isArray(g.variants) ? g.variants : [];
                    
                    const variantsGrid = gVariants.map(v => {
                        const vid = Number(v.id || 0);
                        const vChecked = pVariantsSelected.has(vid) ? 'checked' : '';
                        const vName = String(v.name || 'Biến thể');
                        const vSku = String(v.sku || '');
                        const vPrice = Number(v.price || 0);

                        let priceInputHtml = '';
                        if (type === 'combo_sub') {
                            const comboKey = `${pid}_${vid}`;
                            const priceVal = comboItemsMap.has(comboKey) ? comboItemsMap.get(comboKey) : '';
                            priceInputHtml = `
                                <div class="input-group input-group-sm tree-variant-price-input" style="width:115px;">
                                    <input type="number" class="form-control jsComboPriceInput" data-key="${comboKey}" value="${priceVal}" placeholder="Giá" min="0">
                                    <span class="input-group-text p-1" style="font-size:0.7rem;">đ</span>
                                </div>
                            `;
                        }

                        return `
                            <div class="tree-variant-card">
                                <input type="checkbox" class="form-check-input jsTreeVariantCheck" data-pid="${pid}" data-vid="${vid}" ${vChecked}>
                                <div class="variant-info">
                                    <div class="variant-name" title="${escapeHtml(vName)}">${escapeHtml(vName)}</div>
                                    <div class="variant-meta">${vSku ? escapeHtml(vSku) + ' · ' : ''}${formatMoney(vPrice)}đ</div>
                                </div>
                                ${priceInputHtml}
                            </div>
                        `;
                    }).join('');

                    const gVids = gVariants.map(v => Number(v.id || 0)).filter(v => v > 0).join(',');
                    return `
                        <div class="tree-group-section">
                            <div class="tree-group-header d-flex align-items-center gap-2">
                                <i class="bi bi-tags text-primary"></i>
                                <span>Nhóm: ${escapeHtml(gName)}</span>
                                <span class="ms-auto d-flex gap-1">
                                    <button type="button" class="btn btn-outline-primary btn-sm py-0 px-2 jsTreeGroupAll" data-pid="${pid}" data-vids="${gVids}" style="font-size:0.7rem;">Chọn cả nhóm</button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-2 jsTreeGroupNone" data-pid="${pid}" data-vids="${gVids}" style="font-size:0.7rem;">Bỏ</button>
                                </span>
                            </div>
                            <div class="tree-variants-grid">
                                ${variantsGrid}
                            </div>
                        </div>
                    `;
                }).join('');

                let noGroupHtml = '';
                if (noGroupVariants.length > 0) {
                    const noGroupGrid = noGroupVariants.map(v => {
                        const vid = Number(v.id || 0);
                        const vChecked = pVariantsSelected.has(vid) ? 'checked' : '';
                        const vName = String(v.name || 'Biến thể');
                        const vSku = String(v.sku || '');
                        const vPrice = Number(v.price || 0);

                        let priceInputHtml = '';
                        if (type === 'combo_sub') {
                            const comboKey = `${pid}_${vid}`;
                            const priceVal = comboItemsMap.has(comboKey) ? comboItemsMap.get(comboKey) : '';
                            priceInputHtml = `
                                <div class="input-group input-group-sm tree-variant-price-input" style="width:115px;">
                                    <input type="number" class="form-control jsComboPriceInput" data-key="${comboKey}" value="${priceVal}" placeholder="Giá" min="0">
                                    <span class="input-group-text p-1" style="font-size:0.7rem;">đ</span>
                                </div>
                            `;
                        }

                        return `
                            <div class="tree-variant-card">
                                <input type="checkbox" class="form-check-input jsTreeVariantCheck" data-pid="${pid}" data-vid="${vid}" ${vChecked}>
                                <div class="variant-info">
                                    <div class="variant-name" title="${escapeHtml(vName)}">${escapeHtml(vName)}</div>
                                    <div class="variant-meta">${vSku ? escapeHtml(vSku) + ' · ' : ''}${formatMoney(vPrice)}đ</div>
                                </div>
                                ${priceInputHtml}
                            </div>
                        `;
                    }).join('');

                    noGroupHtml = `
                        <div class="tree-group-section">
                            <div class="tree-variants-grid">
                                ${noGroupGrid}
                            </div>
                        </div>
                    `;
                }

                // Tự mở rộng sản phẩm đã chọn để thấy/tích nhanh phân loại
                const expanded = selectedProductIds.has(pid);
                variantsHtml = `
                    <div class="tree-content" style="display:${expanded ? 'block' : 'none'};" id="${containerId}_treeContent_${pid}">
                        ${groupsHtml}
                        ${noGroupHtml}
                    </div>
                `;
            }

            const isExpanded = hasVariants && selectedProductIds.has(pid);
            const toggleIcon = hasVariants
                ? `<button type="button" class="tree-toggle-btn ${isExpanded ? '' : 'collapsed'} jsTreeToggle" data-pid="${pid}"><i class="bi bi-chevron-down"></i></button>`
                : '';

            let pPriceInputHtml = '';
            if (type === 'combo_sub' && !hasVariants) {
                const comboKey = `${pid}`;
                const priceVal = comboItemsMap.has(comboKey) ? comboItemsMap.get(comboKey) : '';
                pPriceInputHtml = `
                    <div class="input-group input-group-sm ms-auto" style="width:140px;">
                        <input type="number" class="form-control jsComboPriceInput" data-key="${comboKey}" value="${priceVal}" placeholder="Giá KM" min="0">
                        <span class="input-group-text">đ</span>
                    </div>
                `;
            }

            return `
                <div class="tree-node-product" id="${containerId}_treeNode_${pid}">
                    <div class="tree-header d-flex align-items-center gap-2 py-2 px-3">
                        ${toggleIcon}
                        <input type="checkbox" class="form-check-input jsTreeProductCheck" data-pid="${pid}" ${pChecked}>
                        <div class="product-info flex-grow-1 min-width-0">
                            <div class="product-name text-truncate fw-semibold">${escapeHtml(pName)} <span class="text-muted small">#${pid}</span></div>
                            <div class="product-meta small text-muted">${pSku ? 'SKU: ' + escapeHtml(pSku) + ' · ' : ''}Giá gốc: <span class="fw-semibold text-dark">${formatMoney(pPrice)}đ</span></div>
                        </div>
                        ${pPriceInputHtml}
                    </div>
                    ${variantsHtml}
                </div>
            `;
        }).join('');

        // 4. Attach event listeners
        listEl.querySelectorAll('.jsTreeToggle').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                const pid = btn.getAttribute('data-pid');
                const content = document.getElementById(`${containerId}_treeContent_${pid}`);
                if (content) {
                    const isCollapsed = content.style.display === 'none';
                    content.style.display = isCollapsed ? 'block' : 'none';
                    btn.classList.toggle('collapsed', !isCollapsed);
                }
            });
        });

        // Tích nhanh: chọn/bỏ TẤT CẢ phân loại trong 1 nhóm
        const applyGroupVariants = (pid, vids, makeChecked) => {
            if (!vids.length) return;
            const map = getSelectedVariantMap();
            const cur = new Set(map[String(pid)] || []);
            vids.forEach(vid => { makeChecked ? cur.add(vid) : cur.delete(vid); });
            // đồng bộ checkbox hiển thị
            const content = document.getElementById(`${containerId}_treeContent_${pid}`);
            if (content) {
                vids.forEach(vid => {
                    const vChk = content.querySelector(`.jsTreeVariantCheck[data-vid="${vid}"]`);
                    if (vChk) vChk.checked = makeChecked;
                });
            }
            if (cur.size > 0) map[String(pid)] = Array.from(cur); else delete map[String(pid)];
            setSelectedVariantMap(map);
            // đồng bộ cờ chọn sản phẩm cha
            const ids = new Set(getSelectedProductIds());
            const parentChk = listEl.querySelector(`.jsTreeProductCheck[data-pid="${pid}"]`);
            if (cur.size > 0) { ids.add(pid); if (parentChk) parentChk.checked = true; }
            else { ids.delete(pid); if (parentChk) parentChk.checked = false; }
            setSelectedProductIds(Array.from(ids));
        };
        const parseVids = (btn) => String(btn.getAttribute('data-vids') || '').split(',').map(s => parseInt(s, 10)).filter(v => v > 0);
        listEl.querySelectorAll('.jsTreeGroupAll').forEach(btn => {
            btn.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); applyGroupVariants(parseInt(btn.getAttribute('data-pid'), 10), parseVids(btn), true); });
        });
        listEl.querySelectorAll('.jsTreeGroupNone').forEach(btn => {
            btn.addEventListener('click', (e) => { e.preventDefault(); e.stopPropagation(); applyGroupVariants(parseInt(btn.getAttribute('data-pid'), 10), parseVids(btn), false); });
        });

        listEl.querySelectorAll('.jsTreeProductCheck').forEach(chk => {
            chk.addEventListener('change', (e) => {
                const pid = parseInt(chk.getAttribute('data-pid'), 10);
                const isChecked = chk.checked;

                const ids = new Set(getSelectedProductIds());
                if (isChecked) {
                    ids.add(pid);
                } else {
                    ids.delete(pid);
                }
                setSelectedProductIds(Array.from(ids));

                const content = document.getElementById(`${containerId}_treeContent_${pid}`);
                if (content) {
                    const variantChks = content.querySelectorAll('.jsTreeVariantCheck');
                    const map = getSelectedVariantMap();
                    const currentVids = new Set(map[String(pid)] || []);

                    variantChks.forEach(vChk => {
                        vChk.checked = isChecked;
                        const vid = parseInt(vChk.getAttribute('data-vid'), 10);
                        if (isChecked) {
                            currentVids.add(vid);
                        } else {
                            currentVids.delete(vid);
                        }
                    });

                    if (isChecked && variantChks.length > 0) {
                        map[String(pid)] = Array.from(currentVids);
                    } else {
                        delete map[String(pid)];
                    }
                    setSelectedVariantMap(map);

                    if (isChecked && content.style.display === 'none') {
                        content.style.display = 'block';
                        const toggle = listEl.querySelector(`.jsTreeToggle[data-pid="${pid}"]`);
                        if (toggle) toggle.classList.remove('collapsed');
                    }
                }

                if (type === 'combo_sub') {
                    const hasVars = !!content;
                    const itemsMap = getComboItemsMap();

                    if (!hasVars) {
                        const comboKey = String(pid);
                        if (isChecked) {
                            if (!itemsMap.has(comboKey)) {
                                itemsMap.set(comboKey, 0);
                                const inp = chk.closest('.tree-header').querySelector('.jsComboPriceInput');
                                if (inp) inp.value = '0';
                            }
                        } else {
                            itemsMap.delete(comboKey);
                        }
                    } else {
                        const variantChks = content.querySelectorAll('.jsTreeVariantCheck');
                        variantChks.forEach(vChk => {
                            const vid = vChk.getAttribute('data-vid');
                            const comboKey = `${pid}_${vid}`;
                            if (isChecked) {
                                if (!itemsMap.has(comboKey)) {
                                    itemsMap.set(comboKey, 0);
                                    const inp = vChk.closest('.tree-variant-card').querySelector('.jsComboPriceInput');
                                    if (inp) inp.value = '0';
                                }
                            } else {
                                itemsMap.delete(comboKey);
                            }
                        });
                    }
                    setComboItemsMap(itemsMap);
                }
            });
        });

        listEl.querySelectorAll('.jsTreeVariantCheck').forEach(chk => {
            chk.addEventListener('change', (e) => {
                const pid = parseInt(chk.getAttribute('data-pid'), 10);
                const vid = parseInt(chk.getAttribute('data-vid'), 10);
                const isChecked = chk.checked;

                const map = getSelectedVariantMap();
                const currentVids = new Set(map[String(pid)] || []);
                if (isChecked) {
                    currentVids.add(vid);
                } else {
                    currentVids.delete(vid);
                }

                if (currentVids.size > 0) {
                    map[String(pid)] = Array.from(currentVids);
                } else {
                    delete map[String(pid)];
                }
                setSelectedVariantMap(map);

                const parentChk = listEl.querySelector(`.jsTreeProductCheck[data-pid="${pid}"]`);
                const ids = new Set(getSelectedProductIds());
                
                if (currentVids.size > 0) {
                    if (parentChk) parentChk.checked = true;
                    ids.add(pid);
                } else {
                    if (parentChk) parentChk.checked = false;
                    ids.delete(pid);
                }
                setSelectedProductIds(Array.from(ids));

                if (type === 'combo_sub') {
                    const comboKey = `${pid}_${vid}`;
                    const itemsMap = getComboItemsMap();
                    if (isChecked) {
                        if (!itemsMap.has(comboKey)) {
                            itemsMap.set(comboKey, 0);
                            const inp = chk.closest('.tree-variant-card').querySelector('.jsComboPriceInput');
                            if (inp) inp.value = '0';
                        }
                    } else {
                        itemsMap.delete(comboKey);
                    }
                    setComboItemsMap(itemsMap);
                }
            });
        });

        listEl.querySelectorAll('.jsComboPriceInput').forEach(inp => {
            const handlePriceChange = () => {
                const key = inp.getAttribute('data-key');
                let price = parseInt(inp.value, 10);
                if (Number.isNaN(price) || price < 0) price = 0;
                
                const itemsMap = getComboItemsMap();
                itemsMap.set(key, price);
                setComboItemsMap(itemsMap);

                const isVariant = key.includes('_');
                if (isVariant) {
                    const parts = key.split('_');
                    const pid = parts[0];
                    const vid = parts[1];
                    const vChk = listEl.querySelector(`.jsTreeVariantCheck[data-pid="${pid}"][data-vid="${vid}"]`);
                    if (vChk && !vChk.checked) {
                        vChk.checked = true;
                        vChk.dispatchEvent(new Event('change'));
                    }
                } else {
                    const pid = key;
                    const pChk = listEl.querySelector(`.jsTreeProductCheck[data-pid="${pid}"]`);
                    if (pChk && !pChk.checked) {
                        pChk.checked = true;
                        pChk.dispatchEvent(new Event('change'));
                    }
                }
            };
            inp.addEventListener('input', handlePriceChange);
            inp.addEventListener('change', handlePriceChange);
        });
    }

    // ==========================================
    // WIZARD STEP NAVIGATION ENGINE
    // ==========================================
    let currentStep = 1;

    function setWizardStep(step) {
        currentStep = step;
        
        // Switch views
        document.getElementById('wizardStep1').style.display = (step === 1) ? '' : 'none';
        document.getElementById('wizardStep2').style.display = (step === 2) ? '' : 'none';

        // Update indicators
        document.getElementById('nodeStep1').classList.toggle('active', step === 1);
        document.getElementById('nodeStep1').classList.toggle('completed', step > 1);
        
        document.getElementById('nodeStep2').classList.toggle('active', step === 2);
        document.getElementById('nodeStep2').classList.toggle('completed', step > 2);

        // Footer buttons toggles
        document.getElementById('btnWizardBack').style.display = (step > 1) ? '' : 'none';
        document.getElementById('btnWizardNext').style.display = (step < 2) ? '' : 'none';
        document.getElementById('btnSavePromo').style.display = (step === 2) ? '' : 'none';
    }

    function validateStep1() {
        const name = String(document.querySelector('input[name="name"]')?.value || '').trim();
        if (!name) {
            notify('Vui lòng nhập tên chiến dịch', 'warning');
            return false;
        }
        return true;
    }

    // Attach step 1 next/back event listeners
    document.addEventListener('DOMContentLoaded', function(){
        // Collapsible list text toggler for campaign view modal
        $(document).on('show.bs.collapse', '#promoViewModal .collapse', function() {
            const btn = $(`#promoViewModal button[data-bs-target="#${this.id}"]`);
            btn.html(`<i class="bi bi-chevron-up small"></i> Thu gọn`);
        });
        $(document).on('hide.bs.collapse', '#promoViewModal .collapse', function() {
            const btn = $(`#promoViewModal button[data-bs-target="#${this.id}"]`);
            const count = btn.attr('data-count');
            if (this.id.indexOf('collapse_promo_var_') === 0) {
                btn.html(`<i class="bi bi-chevron-down small"></i> Xem thêm ${count} phân loại sản phẩm...`);
            } else {
                btn.html(`<i class="bi bi-chevron-down small"></i> Xem thêm ${count} sản phẩm...`);
            }
        });

        const nextBtn = document.getElementById('btnWizardNext');
        if (nextBtn) {
            nextBtn.addEventListener('click', function() {
                if (currentStep === 1) {
                    if (validateStep1()) {
                        setWizardStep(2);
                        
                        // Mount / render current active promotion pickers inside Step 2
                        const activeType = document.querySelector('input[name="promo_type"]:checked')?.value || 'gift';
                        if (activeType === 'gift') {
                            renderGiftProductPicker();
                        } else if (activeType === 'combo') {
                            renderMainProductPicker();
                            renderComboProductPicker();
                        } else if (activeType === 'bxgy') {
                            renderBxgyMainProductPicker();
                            renderBxgyGiftProductPicker();
                        }
                    }
                }
            });
        }

        const backBtn = document.getElementById('btnWizardBack');
        if (backBtn) {
            backBtn.addEventListener('click', function() {
                if (currentStep > 1) {
                    setWizardStep(currentStep - 1);
                }
            });
        }
    });

    function renderProductList(ids){
        const list = uniqIntList(ids);
        if (!list.length) return '<div class="text-muted">—</div>';
        
        const limit = 5;
        const visibleList = list.slice(0, limit);
        const hiddenList = list.slice(limit);
        
        const visibleItems = visibleList.map(pid => {
            const p = getProductInfo(pid);
            const name = p ? (p.name || '') : '';
            const sku = p ? (p.sku || '') : '';
            return `<li>${escapeHtml(name || ('#' + pid))} <span class="text-muted">(#${pid}${sku ? ' · ' + escapeHtml(sku) : ''})</span></li>`;
        }).join('');
        
        if (!hiddenList.length) {
            return `<ul class="mb-0 promo-product-list">${visibleItems}</ul>`;
        }
        
        const hiddenItems = hiddenList.map(pid => {
            const p = getProductInfo(pid);
            const name = p ? (p.name || '') : '';
            const sku = p ? (p.sku || '') : '';
            return `<li>${escapeHtml(name || ('#' + pid))} <span class="text-muted">(#${pid}${sku ? ' · ' + escapeHtml(sku) : ''})</span></li>`;
        }).join('');
        
        const collapseId = 'collapse_promo_prod_' + (++uniqueIdCounter);
        
        let html = '';
        html += `<div class="promo-product-list-wrapper">`;
        html += `<ul class="mb-0 promo-product-list">${visibleItems}</ul>`;
        html += `<div class="collapse mt-1" id="${collapseId}">`;
        html += `<ul class="mb-0 promo-product-list pt-1 border-top border-light-subtle">${hiddenItems}</ul>`;
        html += `</div>`;
        html += `<button class="btn btn-link btn-sm p-0 mt-1 fw-bold text-decoration-none d-inline-flex align-items-center gap-1 promo-list-toggle-btn" `;
        html += `type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" data-count="${hiddenList.length}" aria-expanded="false">`;
        html += `<i class="bi bi-chevron-down small"></i> Xem thêm ${hiddenList.length} sản phẩm...`;
        html += `</button>`;
        html += `</div>`;
        
        return html;
    }

    function renderVariantMap(map){
        if (!map || typeof map !== 'object' || Array.isArray(map)) return '<div class="text-muted">—</div>';
        const pids = Object.keys(map);
        if (!pids.length) return '<div class="text-muted">—</div>';
        
        const blocks = pids.map(pidStr => {
            const pid = parseInt(pidStr, 10) || 0;
            const arr = Array.isArray(map[pidStr]) ? map[pidStr] : [];
            const vids = uniqIntList(arr);
            if (!pid || !vids.length) return '';
            const p = getProductInfo(pid);
            const pName = p ? (p.name || '') : ('#' + pid);
            const labels = vids.map(vid => {
                const vInfo = getVariantInfo(pid, vid);
                if (vInfo) return escapeHtml(formatVariantLabel({ name: vInfo.name, sku: vInfo.sku, price: vInfo.price }));
                return '#' + vid;
            }).join(', ');
            return `<div class="mb-2"><div class="fw-semibold">${escapeHtml(pName)} <span class="text-muted">(#${pid})</span></div><div class="text-muted small">${labels || '—'}</div></div>`;
        }).filter(Boolean);
        
        if (!blocks.length) return '<div class="text-muted">—</div>';
        
        const limit = 3;
        const visibleBlocks = blocks.slice(0, limit).join('');
        const hiddenBlocks = blocks.slice(limit);
        
        if (!hiddenBlocks.length) {
            return visibleBlocks;
        }
        
        const collapseId = 'collapse_promo_var_' + (++uniqueIdCounter);
        let html = '';
        html += `<div class="promo-product-list-wrapper">`;
        html += visibleBlocks;
        html += `<div class="collapse mt-1" id="${collapseId}">`;
        html += `<div class="pt-2 border-top border-light-subtle">${hiddenBlocks.join('')}</div>`;
        html += `</div>`;
        html += `<button class="btn btn-link btn-sm p-0 mt-1 fw-bold text-decoration-none d-inline-flex align-items-center gap-1 promo-list-toggle-btn" `;
        html += `type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" data-count="${hiddenBlocks.length}" aria-expanded="false">`;
        html += `<i class="bi bi-chevron-down small"></i> Xem thêm ${hiddenBlocks.length} phân loại sản phẩm...`;
        html += `</button>`;
        html += `</div>`;
        
        return html;
    }

    function openViewPromo(row){
        if (!promoViewModal) return;
        row = row || {};
        const type = String(row.promo_type || '');
        const titleEl = document.getElementById('promoViewTitle');
        const metaEl = document.getElementById('promoViewMeta');
        const bodyEl = document.getElementById('promoViewBody');
        if (!bodyEl) return;

        const isActive = String(row.is_active || '0') === '1';
        const statusBadge = isActive ? '<span class="badge text-bg-success">Đang bật</span>' : '<span class="badge text-bg-secondary">Đang tắt</span>';
        const typeBadge = toBadge(promoTypeLabelJs(type));

        if (titleEl) titleEl.innerHTML = `${escapeHtml(row.name || 'Chi tiết chiến dịch')}`;
        if (metaEl) metaEl.innerHTML = `#${escapeHtml(row.id || '')} · ${typeBadge} · ${statusBadge}`;

        let cfg = {};
        try { cfg = JSON.parse(row.config_json || '{}') || {}; } catch(e) { cfg = {}; }

        const startAt = formatDateText(row.start_at);
        const endAt = formatDateText(row.end_at);
        const pr = parseInt(row.priority || 0, 10) || 0;

        let detailHtml = '';
        detailHtml += '<div class="row g-3">';
        detailHtml += '<div class="col-md-12">';
        detailHtml += '<div class="small text-muted fw-bold">THỜI GIAN</div>';
        detailHtml += `<div class="mt-1"><span class="fw-semibold">Từ:</span> ${startAt} - <span class="fw-semibold">Đến:</span> ${endAt}</div>`;
        if (pr) detailHtml += `<div class="mt-2 text-muted"><span class="fw-semibold">Ưu tiên:</span> ${escapeHtml(String(pr))}</div>`;
        detailHtml += '</div>';
        detailHtml += '<div class="col-md-12">';
        detailHtml += '<div class="small text-muted fw-bold">CẤU HÌNH</div>';

        if (type === 'gift') {
            const threshold = parseInt(cfg.threshold_amount || 0, 10) || 0;
            const giftIds = uniqIntList(cfg.gift_product_ids || []);
            const limit = parseInt(cfg.gift_choice_limit || 1, 10) || 1;
            detailHtml += `<div class="mt-1"><div><span class="fw-semibold">Ngưỡng:</span> ${escapeHtml(formatMoneyText(threshold))}</div>`;
            detailHtml += `<div class="mt-1"><span class="fw-semibold">Quà tặng (${giftIds.length} SP):</span>${renderProductList(giftIds)}</div>`;
            detailHtml += `<div class="mt-1"><span class="fw-semibold">Chọn tối đa:</span> ${escapeHtml(String(Math.max(1, limit)))}</div></div>`;
        } else if (type === 'combo') {
            const mainIds = uniqIntList(cfg.main_product_ids || (cfg.main_product_id ? [cfg.main_product_id] : []));
            const items = Array.isArray(cfg.items) ? cfg.items : [];
            const lines = items.map(it => {
                const pid = parseInt(it?.product_id || 0, 10) || 0;
                const price = parseInt(it?.promo_price || 0, 10);
                if (!pid || !Number.isFinite(price) || price < 0) return '';
                const p = getProductInfo(pid);
                const name = p ? (p.name || '') : ('#' + pid);
                return `<li>${escapeHtml(name)} <span class="text-muted">(#${pid})</span> — <span class="fw-semibold">${escapeHtml(formatMoneyText(price))}</span></li>`;
            }).filter(Boolean);
            
            detailHtml += `<div class="mt-1"><span class="fw-semibold">SP chính (${mainIds.length} SP):</span>${renderProductList(mainIds)}</div>`;
            detailHtml += `<div class="mt-2"><span class="fw-semibold">SP ưu đãi kèm (${items.length} SP):</span>`;
            
            if (!lines.length) {
                detailHtml += '<div class="text-muted">—</div>';
            } else {
                const limit = 5;
                const visibleLines = lines.slice(0, limit).join('');
                const hiddenLines = lines.slice(limit);
                
                if (!hiddenLines.length) {
                    detailHtml += `<ul class="mb-0 promo-product-list">${visibleLines}</ul>`;
                } else {
                    const collapseId = 'collapse_promo_prod_' + (++uniqueIdCounter);
                    detailHtml += `<div class="promo-product-list-wrapper">`;
                    detailHtml += `<ul class="mb-0 promo-product-list">${visibleLines}</ul>`;
                    detailHtml += `<div class="collapse mt-1" id="${collapseId}">`;
                    detailHtml += `<ul class="mb-0 promo-product-list pt-1 border-top border-light-subtle">${hiddenLines.join('')}</ul>`;
                    detailHtml += `</div>`;
                    detailHtml += `<button class="btn btn-link btn-sm p-0 mt-1 fw-bold text-decoration-none d-inline-flex align-items-center gap-1 promo-list-toggle-btn" `;
                    detailHtml += `type="button" data-bs-toggle="collapse" data-bs-target="#${collapseId}" data-count="${hiddenLines.length}" aria-expanded="false">`;
                    detailHtml += `<i class="bi bi-chevron-down small"></i> Xem thêm ${hiddenLines.length} sản phẩm...`;
                    detailHtml += `</button>`;
                    detailHtml += `</div>`;
                }
            }
            detailHtml += '</div>';
        } else if (type === 'bxgy') {
            const buyQty = parseInt(cfg.buy_qty || 0, 10) || 0;
            const giftQty = parseInt(cfg.gift_qty || 0, 10) || 0;
            const mainIds = uniqIntList(cfg.main_product_ids || []);
            const giftIds = uniqIntList(cfg.gift_product_ids || []);
            const maxPerOrder = parseInt(cfg.max_gift_per_order || 0, 10) || 0;
            const sameGift = giftIds.length === 0;

            detailHtml += `<div class="mt-1"><span class="fw-semibold">Điều kiện:</span> Mua ${escapeHtml(String(buyQty))} tặng ${escapeHtml(String(giftQty))}</div>`;
            if (maxPerOrder > 0) detailHtml += `<div class="mt-1"><span class="fw-semibold">Tối đa/đơn:</span> ${escapeHtml(String(maxPerOrder))}</div>`;
            detailHtml += `<div class="mt-2"><span class="fw-semibold">SP áp dụng (${mainIds.length} SP):</span>${renderProductList(mainIds)}</div>`;
            if (sameGift) {
                detailHtml += `<div class="mt-2"><span class="fw-semibold">Quà:</span> <span class="text-muted">cùng SP chính</span></div>`;
            } else {
                detailHtml += `<div class="mt-2"><span class="fw-semibold">SP quà (${giftIds.length} SP):</span>${renderProductList(giftIds)}</div>`;
            }

            const mainVar = (cfg && typeof cfg.main_variant_ids === 'object') ? cfg.main_variant_ids : null;
            const giftVar = (cfg && typeof cfg.gift_variant_ids === 'object') ? cfg.gift_variant_ids : null;
            if (mainVar && Object.keys(mainVar).length) {
                detailHtml += `<div class="mt-3"><div class="fw-semibold">Giới hạn phân loại (SP áp dụng)</div><div class="mt-1">${renderVariantMap(mainVar)}</div></div>`;
            }
            if (giftVar && Object.keys(giftVar).length) {
                detailHtml += `<div class="mt-3"><div class="fw-semibold">Giới hạn phân loại (SP quà)</div><div class="mt-1">${renderVariantMap(giftVar)}</div></div>`;
            }
        } else {
            detailHtml += `<div class="mt-1 text-muted">Không đọc được cấu hình.</div>`;
        }

        detailHtml += '</div>'; // col
        detailHtml += '</div>'; // row

        bodyEl.innerHTML = detailHtml;
        promoViewModal.show();
    }

    function renderCategorySelect(selectId){
        const el = document.getElementById(selectId);
        if (!el) return;
        let html = '<option value="0">Tất cả danh mục</option>';
        (CATEGORY_OPTIONS || []).forEach(c => {
            const id = Number(c.id || 0);
            const name = escapeHtml(c.name || 'Danh mục');
            if (id > 0) html += `<option value="${id}">${name}</option>`;
        });
        el.innerHTML = html;
    }

    function renderProductSelect(selectId){
        const el = document.getElementById(selectId);
        if (!el) return;
        let html = '<option value="0">Tặng cùng sản phẩm chính</option>';
        (PRODUCT_OPTIONS || []).forEach(p => {
            const id = Number(p.id || 0);
            if (id <= 0) return;
            const name = escapeHtml(p.name || 'Sản phẩm');
            const sku = escapeHtml(p.sku || '');
            html += `<option value="${id}">${name} #${id}${sku ? ' ('+sku+')' : ''}</option>`;
        });
        el.innerHTML = html;
    }

    function getBxgyGiftSelectedProductIds(){
        const raw = String(document.getElementById('bxgyGiftProductIds')?.value || '').trim();
        if (!raw) return [];
        const parts = raw.split(',').map(v => parseInt(v.trim(), 10)).filter(v => Number.isFinite(v) && v > 0);
        return Array.from(new Set(parts));
    }

    function setBxgyGiftSelectedProductIds(ids){
        const unique = Array.from(new Set((ids || []).map(v => parseInt(v, 10)).filter(v => Number.isFinite(v) && v > 0)));
        const hidden = document.getElementById('bxgyGiftProductIds');
        if (hidden) hidden.value = unique.join(',');
        const cnt = document.getElementById('bxgyGiftPickedCount');
        if (cnt) cnt.textContent = String(unique.length);
    }

    function parseVariantMapFromHidden(hiddenId){
        const raw = String(document.getElementById(hiddenId)?.value || '').trim();
        if (!raw) return {};
        let obj = {};
        try { obj = JSON.parse(raw) || {}; } catch (e) { obj = {}; }
        if (!obj || typeof obj !== 'object' || Array.isArray(obj)) return {};
        const out = {};
        Object.keys(obj).forEach(k => {
            const pid = parseInt(k || '0', 10) || 0;
            if (pid <= 0) return;
            const arr = Array.isArray(obj[k]) ? obj[k] : [];
            const uniq = [];
            arr.forEach(v => {
                const vid = parseInt(v || '0', 10) || 0;
                if (vid > 0 && !uniq.includes(vid)) uniq.push(vid);
            });
            out[String(pid)] = uniq;
        });
        return out;
    }

    // Lấy id các sản phẩm ĐANG HIỂN THỊ theo bộ lọc (danh mục + từ khoá) của 1 picker
    function filteredPidsBy(searchInputId, categorySelectId){
        const keyword = String(document.getElementById(searchInputId)?.value || '').trim().toLowerCase();
        const catId = parseInt(String(document.getElementById(categorySelectId)?.value || '0'), 10) || 0;
        return (PRODUCT_OPTIONS || []).filter(p => {
            const pid = Number(p.id || 0);
            if (pid <= 0) return false;
            if (catId > 0 && Number(p.category_id || 0) !== catId) return false;
            if (!keyword) return true;
            return `${p.name || ''} ${p.sku || ''} ${p.id || ''}`.toLowerCase().includes(keyword);
        }).map(p => Number(p.id || 0)).filter(v => v > 0);
    }

    function writeVariantMapToHidden(hiddenId, map){
        const el = document.getElementById(hiddenId);
        if (!el) return;
        const safe = {};
        if (map && typeof map === 'object') {
            Object.keys(map).forEach(k => {
                const pid = parseInt(k || '0', 10) || 0;
                if (pid <= 0) return;
                const arr = Array.isArray(map[k]) ? map[k] : [];
                const uniq = [];
                arr.forEach(v => {
                    const vid = parseInt(v || '0', 10) || 0;
                    if (vid > 0 && !uniq.includes(vid)) uniq.push(vid);
                });
                safe[String(pid)] = uniq;
            });
        }
        el.value = JSON.stringify(safe);
    }

    function setBxgyGiftSameProduct(isSame){
        const sw = document.getElementById('bxgyGiftSameProduct');
        const hidden = document.getElementById('bxgyGiftSame');
        const wrap = document.getElementById('bxgyGiftPickerWrap');
        if (sw) sw.checked = !!isSame;
        if (hidden) hidden.value = isSame ? '1' : '0';
        if (wrap) wrap.style.display = isSame ? 'none' : '';
        
        const giftProdEl = document.getElementById('bxgyGiftProductIds');
        if (isSame) {
            if (giftProdEl) giftProdEl.value = '';
            writeVariantMapToHidden('bxgyGiftVariantIds', {});
        }
        renderBxgyGiftProductPicker();
    }



    function setPromoType(v){
        const val = (v === 'gift' || v === 'combo' || v === 'bxgy') ? v : 'gift';
        document.querySelectorAll('input[name="promo_type"]').forEach(r => {
            r.checked = (r.value === val);
        });
        document.getElementById('promoGiftConfig').style.display = (val === 'gift') ? '' : 'none';
        document.getElementById('promoComboConfig').style.display = (val === 'combo') ? '' : 'none';
        document.getElementById('promoBxgyConfig').style.display = (val === 'bxgy') ? '' : 'none';
    }

    function openNewPromo(){
        setWizardStep(1);
        document.getElementById('promoModalTitle').innerText = 'Tạo chiến dịch';
        const form = document.getElementById('promoForm');
        form.reset();
        form.querySelector('[name="id"]').value = '0';
        document.getElementById('promoIsActive').checked = true;
        setPromoType('gift');
        
        // Reset inputs
        const giftProdEl = document.getElementById('giftProductIds');
        if (giftProdEl) giftProdEl.value = '';
        writeVariantMapToHidden('giftVariantIds', {});
        if (document.getElementById('giftProductFilterInput')) document.getElementById('giftProductFilterInput').value = '';
        
        const mainProdEl = document.getElementById('mainProductIds');
        if (mainProdEl) mainProdEl.value = '';
        if (document.getElementById('mainProductFilterInput')) document.getElementById('mainProductFilterInput').value = '';
        
        const comboProdEl = document.getElementById('comboProductIds');
        if (comboProdEl) comboProdEl.value = '';
        const textarea = document.querySelector('textarea[name="combo_items"]');
        if (textarea) textarea.value = '';
        if (document.getElementById('comboProductFilterInput')) document.getElementById('comboProductFilterInput').value = '';

        const bxgyMainProdEl = document.getElementById('bxgyMainProductIds');
        if (bxgyMainProdEl) bxgyMainProdEl.value = '';
        writeVariantMapToHidden('bxgyMainVariantIds', {});
        if (document.getElementById('bxgyMainProductFilterInput')) document.getElementById('bxgyMainProductFilterInput').value = '';
        
        setBxgyGiftSameProduct(true);
        const bxgyGiftProdEl = document.getElementById('bxgyGiftProductIds');
        if (bxgyGiftProdEl) bxgyGiftProdEl.value = '';
        writeVariantMapToHidden('bxgyGiftVariantIds', {});
        if (document.getElementById('bxgyGiftProductFilterInput')) document.getElementById('bxgyGiftProductFilterInput').value = '';
        
        promoModal.show();
    }

    function openEditPromo(row){
        setWizardStep(1);
        document.getElementById('promoModalTitle').innerText = 'Cấu hình chương trình';
        const form = document.getElementById('promoForm');
        form.reset();
        form.querySelector('[name="id"]').value = row.id || 0;
        form.querySelector('[name="name"]').value = row.name || '';
        const type = String(row.promo_type || 'gift');
        setPromoType(type);
        document.getElementById('promoIsActive').checked = String(row.is_active) === '1';
        form.querySelector('[name="start_at"]').value = toDtLocal(row.start_at || '');
        form.querySelector('[name="end_at"]').value = toDtLocal(row.end_at || '');
        form.querySelector('[name="priority"]').value = parseInt(row.priority || 0, 10) || 0;

        let cfg = {};
        try { cfg = JSON.parse(row.config_json || '{}') || {}; } catch(e) {}
        
        if (type === 'gift') {
            form.querySelector('[name="threshold_amount"]').value = cfg.threshold_amount || 0;
            form.querySelector('[name="gift_choice_limit"]').value = cfg.gift_choice_limit || 1;
            
            const giftProdEl = document.getElementById('giftProductIds');
            if (giftProdEl) giftProdEl.value = Array.isArray(cfg.gift_product_ids) ? cfg.gift_product_ids.join(',') : '';
            writeVariantMapToHidden('giftVariantIds', (cfg && typeof cfg.gift_variant_ids === 'object' && cfg.gift_variant_ids) ? cfg.gift_variant_ids : {});
            if (document.getElementById('giftProductFilterInput')) document.getElementById('giftProductFilterInput').value = '';
        } else if (type === 'combo') {
            const mainHidden = document.getElementById('mainProductIds');
            const mainIdsSet = new Set();
            if (Array.isArray(cfg.main_product_ids)) {
                cfg.main_product_ids.forEach(mid => {
                    mid = parseInt(mid || 0, 10);
                    if (mid > 0) mainIdsSet.add(mid);
                });
            }
            const legacyMain = parseInt(cfg.main_product_id || 0, 10);
            if (legacyMain > 0 && !mainIdsSet.size) mainIdsSet.add(legacyMain);
            if (mainHidden) mainHidden.value = Array.from(mainIdsSet).join(',');
            if (document.getElementById('mainProductFilterInput')) document.getElementById('mainProductFilterInput').value = '';

            const subHidden = document.getElementById('comboProductIds');
            const comboIdsSet = new Set();
            const textarea = document.querySelector('textarea[name="combo_items"]');
            if (textarea) {
                const lines = [];
                if (Array.isArray(cfg.items)) {
                    cfg.items.forEach(it => {
                        if (it.product_id) {
                            lines.push(`${it.product_id}:${it.promo_price || 0}`);
                            const rawPid = parseInt(String(it.product_id).split('_')[0], 10) || 0;
                            if (rawPid > 0) comboIdsSet.add(rawPid);
                        }
                    });
                }
                textarea.value = lines.join('\n');
            }
            if (subHidden) subHidden.value = Array.from(comboIdsSet).join(',');
            if (document.getElementById('comboProductFilterInput')) document.getElementById('comboProductFilterInput').value = '';
        } else if (type === 'bxgy') {
            const mainIds = Array.isArray(cfg.main_product_ids) ? cfg.main_product_ids : [];
            const mainHidden = document.getElementById('bxgyMainProductIds');
            if (mainHidden) mainHidden.value = mainIds.join(',');
            writeVariantMapToHidden('bxgyMainVariantIds', (cfg && typeof cfg.main_variant_ids === 'object' && cfg.main_variant_ids && !Array.isArray(cfg.main_variant_ids)) ? cfg.main_variant_ids : {});
            if (document.getElementById('bxgyMainProductFilterInput')) document.getElementById('bxgyMainProductFilterInput').value = '';

            form.querySelector('[name="bxgy_buy_qty"]').value = cfg.buy_qty || 0;
            form.querySelector('[name="bxgy_gift_qty"]').value = cfg.gift_qty || 0;
            form.querySelector('[name="bxgy_max_gift_per_order"]').value = cfg.max_gift_per_order || 0;

            const giftIdsRaw = Array.isArray(cfg.gift_product_ids) ? cfg.gift_product_ids : [];
            const legacyGiftId = parseInt(cfg.gift_product_id || 0, 10) || 0;
            const cleaned = (giftIdsRaw || []).map(v => parseInt(v || 0, 10)).filter(v => Number.isFinite(v) && v > 0);
            const unique = Array.from(new Set(cleaned));
            if (!unique.length && legacyGiftId > 0) unique.push(legacyGiftId);

            const isSame = unique.length === 0;
            setBxgyGiftSameProduct(isSame);
            const giftHidden = document.getElementById('bxgyGiftProductIds');
            if (giftHidden) giftHidden.value = unique.join(',');

            let giftVariantMap = (cfg && typeof cfg.gift_variant_ids === 'object' && cfg.gift_variant_ids && !Array.isArray(cfg.gift_variant_ids)) ? cfg.gift_variant_ids : {};
            const legacyGiftVariantId = parseInt(cfg.gift_variant_id || 0, 10) || 0;
            if ((!giftVariantMap || !Object.keys(giftVariantMap).length) && legacyGiftVariantId > 0 && unique.length === 1) {
                giftVariantMap = { [String(unique[0])]: [legacyGiftVariantId] };
            }
            writeVariantMapToHidden('bxgyGiftVariantIds', giftVariantMap);
            if (document.getElementById('bxgyGiftProductFilterInput')) document.getElementById('bxgyGiftProductFilterInput').value = '';
        }
        promoModal.show();
    }

    // Khởi tạo DataTables cho danh sách chiến dịch
    $(function(){
        if ($.fn.DataTable && $('#promoTable').length) {
            // Custom filters (type/status) based on row template and status
            $.fn.dataTable.ext.search.push(function(settings, data, dataIndex){
                try {
                    const tableId = settings && settings.nTable ? settings.nTable.getAttribute('id') : '';
                    if (tableId !== 'promoTable') return true;

                    const api = new $.fn.dataTable.Api(settings);
                    const node = api.row(dataIndex).node();
                    if (!node) return true;
                    const $tr = $(node);
                    const row = $tr.data('row') || {};

                    // 1. Lọc theo tab summary-card
                    if (currentPromoTab && currentPromoTab !== 'all') {
                        const tpl = String($tr.data('promo-template') || $tr.attr('data-promo-template') || '');
                        if (tpl !== currentPromoTab) return false;
                    }

                    // 2. Lọc theo input tìm kiếm
                    const searchVal = String($('#promoSearchBox').val() || '').trim().toLowerCase();
                    if (searchVal) {
                        const haystack = [
                            String(row.id || ''),
                            String(row.name || ''),
                            String(row.promo_type || '')
                        ].join(' ').toLowerCase();
                        if (!haystack.includes(searchVal)) return false;
                    }

                    // 3. Lọc theo trạng thái bật/tắt
                    const stVal = String($('#promoFilterStatus').val() || 'all');
                    if (stVal !== 'all') {
                        const isActive = (parseInt(row.is_active || 0, 10) || 0) === 1;
                        if (stVal === '1' && !isActive) return false;
                        if (stVal === '0' && isActive) return false;
                    }
                    return true;
                } catch (e) {
                    return true;
                }
            });

            const promoTable = $('#promoTable').DataTable({
                pageLength: 10,
                order: [[7, 'asc'], [6, 'desc']],
                columnDefs: [
                    { targets: [0, 5], orderable: false },
                    { targets: [6, 7], visible: false, searchable: false }
                ],
                dom: 't<"d-flex justify-content-between align-items-center p-2 flex-column flex-md-row"ip>',
                language: {
                    info: "Hiển thị _START_ - _END_ / _TOTAL_",
                    infoEmpty: "Không có dữ liệu",
                    infoFiltered: "(lọc từ _MAX_ mục)",
                    lengthMenu: "Hiển thị _MENU_ mục",
                    zeroRecords: "Không tìm thấy dữ liệu",
                    search: "Tìm kiếm:",
                    paginate: {
                        first: "Đầu",
                        last: "Cuối",
                        next: ">",
                        previous: "<"
                    },
                    processing: "Đang xử lý...",
                    loadingRecords: "Đang tải...",
                    emptyTable: "Không có dữ liệu trong bảng"
                }
            });

            // Toggle empty state
            promoTable.on('draw', function(){
                try {
                    const shown = promoTable.rows({ filter: 'applied' }).data().length;
                    $('#promosEmpty').toggle(shown === 0);
                    $('#promoMeta').html('Tổng: ' + shown + ' chiến dịch');
                } catch (e) {}
            });

            // Click summary cards to switch tab
            $(document).on('click', '.summary-card[data-promo-tab]', function(){
                const key = String($(this).data('promo-tab') || 'all');
                $('.summary-card').removeClass('active');
                $(this).addClass('active');
                currentPromoTab = key;
                promoTable.draw();
            });

            // Text search input with debounce
            let searchTimer = null;
            $('#promoSearchBox').on('input', function(){
                clearTimeout(searchTimer);
                searchTimer = setTimeout(() => {
                    promoTable.draw();
                }, 250);
            });

            // Status filter dropdown
            $('#promoFilterStatus').on('change', function(){
                promoTable.draw();
            });

            // Standard Sorting filter dropdown
            $('#promoSortOrder').on('change', function(){
                const val = $(this).val();
                if (val === 'priority_desc') {
                    promoTable.order([[7, 'asc'], [6, 'desc']]).draw();
                } else if (val === 'id_desc') {
                    promoTable.order([[6, 'desc']]).draw();
                } else if (val === 'id_asc') {
                    promoTable.order([[6, 'asc']]).draw();
                } else if (val === 'name_asc') {
                    promoTable.order([[1, 'asc']]).draw();
                } else if (val === 'name_desc') {
                    promoTable.order([[1, 'desc']]).draw();
                }
            });

            // Initialize SortableJS on table body for drag and drop sorting
            if (window.Sortable) {
                const el = document.querySelector('#promoTable tbody');
                Sortable.create(el, {
                    handle: '.drag-handle',
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    onEnd: function() {
                        saveNewOrder();
                    }
                });
            }

            function saveNewOrder() {
                const ids = [];
                $('#promoTable tbody tr').each(function(){
                    const rowData = $(this).data('row');
                    if (rowData && rowData.id) {
                        ids.push(parseInt(rowData.id, 10));
                    }
                });

                if (!ids.length) return;

                $.ajax({
                    url: location.href,
                    type: 'POST',
                    data: {
                        action: 'update_priorities',
                        ids: ids
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.ok) {
                            if (window.toastr) {
                                toastr.success('Đã cập nhật thứ tự ưu tiên thành công');
                            } else {
                                alert('Đã cập nhật thứ tự ưu tiên thành công');
                            }
                            setTimeout(() => {
                                location.reload();
                            }, 800);
                        } else {
                            if (window.toastr) toastr.error(res.msg || 'Không thể cập nhật thứ tự');
                            else alert(res.msg || 'Không thể cập nhật thứ tự');
                        }
                    },
                    error: function() {
                        if (window.toastr) toastr.error('Lỗi kết nối máy chủ');
                        else alert('Lỗi kết nối máy chủ');
                    }
                });
            }
        }

        // BXGY gift picker
        $(document).on('change', '#bxgyGiftSameProduct', function(){
            setBxgyGiftSameProduct(!!this.checked);
            if (!this.checked) renderBxgyGiftProductPicker();
        });

        // Event delegation để hoạt động tốt với DataTables
        $('#promoTable tbody').on('click', '.jsViewPromo', function(){
            const tr = $(this).closest('tr');
            const row = tr.data('row');
            if (!row) return;
            openViewPromo(row);
        });

        $('#promoTable tbody').on('click', '.jsEditPromo', function(){
            const tr = $(this).closest('tr');
            const row = tr.data('row');
            if (!row) return;
            openEditPromo(row);
        });

        $('#promoTable tbody').on('click', '.jsDelPromo', function(){
            const tr = $(this).closest('tr');
            const row = tr.data('row');
            if (!row) return;
            if (!confirm('Xóa chiến dịch "' + (row.name || '') + '"?')) return;
            $.post(API, { action: 'delete', id: row.id }, res => {
                if (res && res.ok) location.reload();
                else notify(res?.msg || 'Không xóa được', 'error');
            });
        });

        $('#promoTable tbody').on('change', '.jsTogglePromo', function(){
            const tr = $(this).closest('tr');
            const row = tr.data('row');
            if (!row) return;
            const is_active = this.checked ? 1 : 0;
            $.post(API, { action: 'toggle', id: row.id, is_active }, res => {
                if (!res || !res.ok) notify(res?.msg || 'Không cập nhật được', 'error');
            });
        });
    });

    document.querySelectorAll('input[name="promo_type"]').forEach(radio => {
        radio.addEventListener('change', function(){
            if (!this.checked) return;
            const v = this.value;
            setPromoType(v);
            if (v === 'gift') {
                renderGiftProductPicker();
            } else if (v === 'combo') {
                renderComboProductPicker();
            } else if (v === 'bxgy') {
                writeVariantMapToHidden('bxgyMainVariantIds', {});
                writeVariantMapToHidden('bxgyGiftVariantIds', {});
                renderBxgyMainProductPicker();
                setBxgyGiftSameProduct(true);
                renderBxgyGiftProductPicker();
            }
        });
    });

    document.getElementById('btnNewPromo').addEventListener('click', openNewPromo);

    document.getElementById('btnSavePromo').addEventListener('click', function(){
        const formEl = document.getElementById('promoForm');
        const fd = new FormData(formEl);
        const promoType = fd.get('promo_type') || 'gift';
        if (!fd.get('name')) {
            notify('Vui lòng nhập tên chiến dịch', 'warning');
            return;
        }
        if (promoType === 'gift') {
            const thr = parseInt(fd.get('threshold_amount') || '0', 10);
            const ids = String(fd.get('gift_product_ids') || '').trim();
            if (!thr || thr <= 0) {
                notify('Ngưỡng hoá đơn phải > 0', 'warning');
                return;
            }
            if (!ids) {
                notify('Vui lòng chọn sản phẩm quà tặng', 'warning');
                return;
            }
        } else if (promoType === 'combo') {
            const mainRaw = String(fd.get('main_product_ids') || '').trim();
            const items = String(fd.get('combo_items') || '').trim();
            if (!mainRaw) {
                notify('Vui lòng chọn sản phẩm chính', 'warning');
                return;
            }
            if (!items) {
                notify('Vui lòng chọn sản phẩm tặng và nhập giá (0 = miễn phí hoặc giá KM)', 'warning');
                return;
            }
        } else if (promoType === 'bxgy') {
            const mainRaw = String(fd.get('bxgy_main_product_ids') || '').trim();
            const buyQty = parseInt(fd.get('bxgy_buy_qty') || '0', 10);
            const giftQty = parseInt(fd.get('bxgy_gift_qty') || '0', 10);
            const giftSame = String(fd.get('bxgy_gift_same') || '1') === '1';
            const giftIds = String(fd.get('bxgy_gift_product_ids') || '').trim();
            if (!mainRaw) {
                notify('Vui lòng chọn sản phẩm chính', 'warning');
                return;
            }
            if (!buyQty || buyQty <= 0) {
                notify('Số lượng mua phải > 0', 'warning');
                return;
            }
            if (!giftQty || giftQty <= 0) {
                notify('Số lượng tặng phải > 0', 'warning');
                return;
            }
            if (!giftSame && !giftIds) {
                notify('Vui lòng chọn ít nhất 1 sản phẩm tặng (hoặc bật “Tặng cùng sản phẩm chính”)', 'warning');
                return;
            }
        }
        fd.set('action', 'save');
        fd.set('is_active', document.getElementById('promoIsActive').checked ? '1' : '0');
        fd.set('start_at', fromDtLocal(fd.get('start_at')));
        fd.set('end_at', fromDtLocal(fd.get('end_at')));

        $.ajax({
            url: API,
            method: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            success: (res) => {
                if (res && res.ok) location.reload();
                else notify(res?.msg || 'Không lưu được', 'error');
            },
            error: () => notify('Lỗi kết nối server', 'error')
        });
    });

    // ==========================================
    // INITIALIZATION OF PICKER EVENTS
    // ==========================================
    function renderGiftProductPicker() {
        renderHierarchicalPicker({
            containerId: 'giftProductPickerList',
            type: 'gift',
            categoryFilterId: 'giftCategoryFilter',
            searchFilterId: 'giftProductFilterInput',
            hiddenProductIdsId: 'giftProductIds',
            hiddenVariantIdsId: 'giftVariantIds'
        });
    }

    function renderMainProductPicker() {
        renderHierarchicalPicker({
            containerId: 'mainProductPickerList',
            type: 'combo_main',
            categoryFilterId: 'mainProductCategoryFilter',
            searchFilterId: 'mainProductFilterInput',
            hiddenProductIdsId: 'mainProductIds'
        });
    }

    function renderComboProductPicker() {
        renderHierarchicalPicker({
            containerId: 'comboProductPickerList',
            type: 'combo_sub',
            categoryFilterId: 'comboProductCategoryFilter',
            searchFilterId: 'comboProductFilterInput',
            hiddenProductIdsId: 'comboProductIds'
        });
    }

    function renderBxgyMainProductPicker() {
        renderHierarchicalPicker({
            containerId: 'bxgyMainProductPickerList',
            type: 'bxgy_main',
            categoryFilterId: 'bxgyMainCategoryFilter',
            searchFilterId: 'bxgyMainProductFilterInput',
            hiddenProductIdsId: 'bxgyMainProductIds',
            hiddenVariantIdsId: 'bxgyMainVariantIds'
        });
    }

    function renderBxgyGiftProductPicker() {
        renderHierarchicalPicker({
            containerId: 'bxgyGiftProductPickerList',
            type: 'bxgy_gift',
            categoryFilterId: 'bxgyGiftCategoryFilter',
            searchFilterId: 'bxgyGiftProductFilterInput',
            hiddenProductIdsId: 'bxgyGiftProductIds',
            hiddenVariantIdsId: 'bxgyGiftVariantIds'
        });
    }

    // Bind filters
    if (document.getElementById('giftProductFilterInput')) {
        renderCategorySelect('giftCategoryFilter');
        document.getElementById('giftProductFilterInput').addEventListener('input', renderGiftProductPicker);
        document.getElementById('giftCategoryFilter').addEventListener('change', renderGiftProductPicker);
        document.getElementById('btnGiftPickAll').addEventListener('click', function(){
            const el = document.getElementById('giftProductIds');
            const cur = new Set(String(el?.value || '').split(',').map(s => parseInt(s, 10)).filter(v => v > 0));
            filteredPidsBy('giftProductFilterInput', 'giftCategoryFilter').forEach(id => cur.add(id));
            if (el) el.value = Array.from(cur).join(',');
            renderGiftProductPicker();
        });
        document.getElementById('btnGiftPickClear').addEventListener('click', function(){
            const el = document.getElementById('giftProductIds');
            if (el) el.value = '';
            writeVariantMapToHidden('giftVariantIds', {});
            renderGiftProductPicker();
        });
    }

    if (document.getElementById('mainProductFilterInput')) {
        renderCategorySelect('mainProductCategoryFilter');
        document.getElementById('mainProductFilterInput').addEventListener('input', renderMainProductPicker);
        document.getElementById('mainProductCategoryFilter').addEventListener('change', renderMainProductPicker);
        document.getElementById('btnMainPickAll').addEventListener('click', function(){
            const el = document.getElementById('mainProductIds');
            const cur = new Set(String(el?.value || '').split(',').map(s => parseInt(s, 10)).filter(v => v > 0));
            filteredPidsBy('mainProductFilterInput', 'mainProductCategoryFilter').forEach(id => cur.add(id));
            if (el) el.value = Array.from(cur).join(',');
            renderMainProductPicker();
        });
        document.getElementById('btnMainPickClear').addEventListener('click', function(){
            const el = document.getElementById('mainProductIds');
            if (el) el.value = '';
            renderMainProductPicker();
        });
    }

    if (document.getElementById('comboProductFilterInput')) {
        renderCategorySelect('comboProductCategoryFilter');
        document.getElementById('comboProductFilterInput').addEventListener('input', renderComboProductPicker);
        document.getElementById('comboProductCategoryFilter').addEventListener('change', renderComboProductPicker);
        document.getElementById('btnComboPickAll').addEventListener('click', function(){
            const itemsMap = new Map();
            (PRODUCT_OPTIONS || []).forEach(p => {
                const pid = Number(p.id || 0);
                const groups = p.groups || [];
                const noGroup = p.no_group_variants || [];
                const hasVars = hasRealVariants(p);
                if (!hasVars) {
                    itemsMap.set(String(pid), 0);
                } else {
                    groups.forEach(g => {
                        (g.variants || []).forEach(v => {
                            itemsMap.set(`${pid}_${v.id}`, 0);
                        });
                    });
                    noGroup.forEach(v => {
                        itemsMap.set(`${pid}_${v.id}`, 0);
                    });
                }
            });
            const pids = Array.from(new Set((PRODUCT_OPTIONS || []).map(p => Number(p.id || 0)).filter(v => v > 0)));
            const el = document.getElementById('comboProductIds');
            if (el) el.value = pids.join(',');
            const textarea = document.querySelector('textarea[name="combo_items"]');
            if (textarea) {
                const lines = [];
                itemsMap.forEach((val, key) => lines.push(`${key}:${val}`));
                textarea.value = lines.join('\n');
            }
            renderComboProductPicker();
        });
        document.getElementById('btnComboPickClear').addEventListener('click', function(){
            const el = document.getElementById('comboProductIds');
            if (el) el.value = '';
            const textarea = document.querySelector('textarea[name="combo_items"]');
            if (textarea) textarea.value = '';
            renderComboProductPicker();
        });
    }

    if (document.getElementById('bxgyMainProductFilterInput')) {
        renderCategorySelect('bxgyMainCategoryFilter');
        document.getElementById('bxgyMainProductFilterInput').addEventListener('input', renderBxgyMainProductPicker);
        document.getElementById('bxgyMainCategoryFilter').addEventListener('change', renderBxgyMainProductPicker);
        document.getElementById('btnBxgyMainPickAll').addEventListener('click', function(){
            const el = document.getElementById('bxgyMainProductIds');
            const cur = new Set(String(el?.value || '').split(',').map(s => parseInt(s, 10)).filter(v => v > 0));
            filteredPidsBy('bxgyMainProductFilterInput', 'bxgyMainCategoryFilter').forEach(id => cur.add(id));
            if (el) el.value = Array.from(cur).join(',');
            renderBxgyMainProductPicker();
        });
        document.getElementById('btnBxgyMainPickClear').addEventListener('click', function(){
            const el = document.getElementById('bxgyMainProductIds');
            if (el) el.value = '';
            writeVariantMapToHidden('bxgyMainVariantIds', {});
            renderBxgyMainProductPicker();
        });
    }

    if (document.getElementById('bxgyGiftProductFilterInput')) {
        renderCategorySelect('bxgyGiftCategoryFilter');
        document.getElementById('bxgyGiftProductFilterInput').addEventListener('input', renderBxgyGiftProductPicker);
        document.getElementById('bxgyGiftCategoryFilter').addEventListener('change', renderBxgyGiftProductPicker);
        document.getElementById('btnBxgyGiftPickAll').addEventListener('click', function(){
            setBxgyGiftSameProduct(false);
            const el = document.getElementById('bxgyGiftProductIds');
            const cur = new Set(String(el?.value || '').split(',').map(s => parseInt(s, 10)).filter(v => v > 0));
            filteredPidsBy('bxgyGiftProductFilterInput', 'bxgyGiftCategoryFilter').forEach(id => cur.add(id));
            if (el) el.value = Array.from(cur).join(',');
            renderBxgyGiftProductPicker();
        });
        document.getElementById('btnBxgyGiftPickClear').addEventListener('click', function(){
            const el = document.getElementById('bxgyGiftProductIds');
            if (el) el.value = '';
            writeVariantMapToHidden('bxgyGiftVariantIds', {});
            renderBxgyGiftProductPicker();
        });
    }
})();
</script>
