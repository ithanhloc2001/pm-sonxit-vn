
<?php require_once __DIR__ . '/../../../config.php'; ?>
<?php require_once __DIR__ . '/../lib/ghn_admin.php'; ?>
<?php
if (empty($isAdmin)) {
    jOut(['ok' => false, 'msg' => 'Chức năng này chỉ dành cho quản trị viên.']);
}
?>
<?php
// Để tránh tình trạng script bị treo do chờ GHN API quá lâu, chúng ta sẽ giải phóng session lock ngay sau khi xác nhận quyền admin và trước khi thực hiện các tác vụ nặng hoặc gọi API bên ngoài.
app_release_session_lock();
function req_data(): array {
    $raw = file_get_contents('php://input');
    $json = json_decode((string)$raw, true);
    if (is_array($json)) return $json;
    return $_REQUEST;
}

function iVal($v, int $def = 0): int {
    if ($v === null || $v === '') return $def;
    return (int)$v;
}

function sVal($v, string $def = ''): string {
    if ($v === null) return $def;
    return trim((string)$v);
}

function jArr($raw): array {
    if (is_array($raw)) return $raw;
    $txt = trim((string)$raw);
    if ($txt === '') return [];
    $arr = json_decode($txt, true);
    return is_array($arr) ? $arr : [];
}



function vn_norm(string $text): string {
    $text = mb_strtolower($text, 'UTF-8');
    $map = array(
        'à'=>'a','á'=>'a','ạ'=>'a','ả'=>'a','ã'=>'a','â'=>'a','ầ'=>'a','ấ'=>'a','ậ'=>'a','ẩ'=>'a','ẫ'=>'a','ă'=>'a','ằ'=>'a','ắ'=>'a','ặ'=>'a','ẳ'=>'a','ẵ'=>'a',
        'è'=>'e','é'=>'e','ẹ'=>'e','ẻ'=>'e','ẽ'=>'e','ê'=>'e','ề'=>'e','ế'=>'e','ệ'=>'e','ể'=>'e','ễ'=>'e',
        'ì'=>'i','í'=>'i','ị'=>'i','ỉ'=>'i','ĩ'=>'i',
        'ò'=>'o','ó'=>'o','ọ'=>'o','ỏ'=>'o','õ'=>'o','ô'=>'o','ồ'=>'o','ố'=>'o','ộ'=>'o','ổ'=>'o','ỗ'=>'o','ơ'=>'o','ờ'=>'o','ớ'=>'o','ợ'=>'o','ở'=>'o','ỡ'=>'o',
        'ù'=>'u','ú'=>'u','ụ'=>'u','ủ'=>'u','ũ'=>'u','ư'=>'u','ừ'=>'u','ứ'=>'u','ự'=>'u','ử'=>'u','ữ'=>'u',
        'ỳ'=>'y','ý'=>'y','ỵ'=>'y','ỷ'=>'y','ỹ'=>'y','đ'=>'d',
    );
    $out = strtr($text, $map);
    $out = preg_replace('/[^a-z0-9\s]/', ' ', $out);
    $out = preg_replace('/\s+/', ' ', $out);
    return trim($out);
}

function strip_prefix(string $text): string {
    $text = vn_norm($text);
    $text = preg_replace('/^(tinh|thanh pho|tp|quan|huyen|thi xa|thi tran|phuong|xa)\s+/', '', $text);
    return trim($text);
}

$input = req_data();
$action = sVal($input['action'] ?? ($_REQUEST['action'] ?? ''));
$cfg = ghn_get_cfg($ithanhloc);
$orderCols = ghn_ecommerce_order_columns($ithanhloc);

function ghn_fetch_master_provinces(mysqli $ithanhloc, array $cfg): array {
    $cached = ghn_region_cache_get_provinces($ithanhloc);
    if ($cached) return $cached;
    if (empty($cfg['token'])) return [];

    $res = ghn_request($cfg, 'GET', '/v2/master-data/province', null, false);
    if (empty($res['ok']) && (int)($res['http'] ?? 0) === 404) {
        // Some GHN environments expose master-data endpoints without /v2 prefix.
        $res = ghn_request($cfg, 'GET', '/master-data/province', null, false);
    }
    $rows = is_array($res['data'] ?? null) ? ($res['data'] ?? []) : [];
    if ($rows) {
        ghn_region_cache_save_provinces($ithanhloc, $rows);
        return ghn_region_cache_get_provinces($ithanhloc);
    }
    return [];
}

function ghn_fetch_master_districts(mysqli $ithanhloc, array $cfg, int $provinceId): array {
    $cached = ghn_region_cache_get_districts($ithanhloc, $provinceId);
    if ($cached) return $cached;
    if (empty($cfg['token'])) return [];
    $res = ghn_request($cfg, 'GET', '/v2/master-data/district?province_id=' . urlencode((string)$provinceId), null, false);
    if (empty($res['ok']) && (int)($res['http'] ?? 0) === 404) {
        $res = ghn_request($cfg, 'GET', '/master-data/district?province_id=' . urlencode((string)$provinceId), null, false);
    }
    $rows = is_array($res['data'] ?? null) ? ($res['data'] ?? []) : [];
    if ($rows) {
        ghn_region_cache_save_districts($ithanhloc, $provinceId, $rows);
        return ghn_region_cache_get_districts($ithanhloc, $provinceId);
    }
    return [];
}

function ghn_fetch_master_wards(mysqli $ithanhloc, array $cfg, int $districtId): array {
    $cached = ghn_region_cache_get_wards($ithanhloc, $districtId);
    if ($cached) return $cached;
    if (empty($cfg['token'])) return [];
    $res = ghn_request($cfg, 'GET', '/v2/master-data/ward?district_id=' . urlencode((string)$districtId), null, false);
    if (empty($res['ok']) && (int)($res['http'] ?? 0) === 404) {
        $res = ghn_request($cfg, 'GET', '/master-data/ward?district_id=' . urlencode((string)$districtId), null, false);
    }
    $rows = is_array($res['data'] ?? null) ? ($res['data'] ?? []) : [];
    if ($rows) {
        ghn_region_cache_save_wards($ithanhloc, $districtId, $rows);
        return ghn_region_cache_get_wards($ithanhloc, $districtId);
    }
    return [];
}

// ===== Fetch FORCE (bỏ qua cache) — dùng cho nút Import để luôn lấy data mới nhất từ GHN. =====
// Trả về số bản ghi đã lưu, hoặc -1 nếu API GHN lỗi (để báo lỗi rõ ràng thay vì im lặng).
function ghn_force_provinces(mysqli $ithanhloc, array $cfg): int {
    $res = ghn_request($cfg, 'GET', '/v2/master-data/province', null, false);
    if (empty($res['ok']) && (int)($res['http'] ?? 0) === 404) {
        $res = ghn_request($cfg, 'GET', '/master-data/province', null, false);
    }
    if (empty($res['ok'])) return -1;
    $rows = is_array($res['data'] ?? null) ? $res['data'] : [];
    ghn_region_cache_save_provinces($ithanhloc, $rows);
    return count($rows);
}
function ghn_force_districts(mysqli $ithanhloc, array $cfg, int $provinceId): int {
    $res = ghn_request($cfg, 'GET', '/v2/master-data/district?province_id=' . urlencode((string)$provinceId), null, false);
    if (empty($res['ok']) && (int)($res['http'] ?? 0) === 404) {
        $res = ghn_request($cfg, 'GET', '/master-data/district?province_id=' . urlencode((string)$provinceId), null, false);
    }
    if (empty($res['ok'])) return -1;
    $rows = is_array($res['data'] ?? null) ? $res['data'] : [];
    ghn_region_cache_save_districts($ithanhloc, $provinceId, $rows);
    return count($rows);
}
function ghn_force_wards(mysqli $ithanhloc, array $cfg, int $districtId): int {
    $res = ghn_request($cfg, 'GET', '/v2/master-data/ward?district_id=' . urlencode((string)$districtId), null, false);
    if (empty($res['ok']) && (int)($res['http'] ?? 0) === 404) {
        $res = ghn_request($cfg, 'GET', '/master-data/ward?district_id=' . urlencode((string)$districtId), null, false);
    }
    if (empty($res['ok'])) return -1;
    $rows = is_array($res['data'] ?? null) ? $res['data'] : [];
    ghn_region_cache_save_wards($ithanhloc, $districtId, $rows);
    return count($rows);
}

function ghn_fetch_shop_detail_any(array $cfg, int $shopId): array {
    if ($shopId <= 0) return ['ok' => false, 'message' => 'Thiếu shop_id'];

    // Preferred: GET /v2/shop/detail with ShopId header
    $cfgHeader = $cfg;
    $cfgHeader['shop'] = ['shop_id' => $shopId];
    $res = ghn_request($cfgHeader, 'GET', '/v2/shop/detail', null, true);
    if (!empty($res['ok'])) {
        $res['strategy'] = 'GET /v2/shop/detail (ShopId header)';
        return $res;
    }

    // Fallback: legacy POST payload
    $res2 = ghn_request($cfg, 'POST', '/v2/shop/detail', ['shop_id' => $shopId], false);
    if (!empty($res2['ok'])) {
        $res2['strategy'] = 'POST /v2/shop/detail (payload shop_id)';
        return $res2;
    }

    // Last resort: derive from shop/all
    $resAll = ghn_request($cfg, 'POST', '/v2/shop/all', ['offset' => 0, 'limit' => 200], false);
    if (!empty($resAll['ok'])) {
        $data = $resAll['data'] ?? [];
        $shops = [];
        if (is_array($data)) {
            if (isset($data['shops']) && is_array($data['shops'])) $shops = $data['shops'];
            elseif (isset($data['data']) && is_array($data['data'])) $shops = $data['data'];
            elseif (isset($data[0])) $shops = $data;
        }

        foreach ($shops as $row) {
            if (!is_array($row)) continue;
            $id = (int)($row['_id'] ?? $row['shop_id'] ?? $row['ShopID'] ?? 0);
            if ($id === $shopId) {
                return [
                    'ok' => true,
                    'http' => 200,
                    'code' => 200,
                    'message' => 'Success (derived from /v2/shop/all)',
                    'data' => $row,
                    'raw' => ['derived_from' => '/v2/shop/all', 'shop' => $row],
                    'base_url' => (string)($resAll['base_url'] ?? ''),
                    'duration_ms' => (int)($resAll['duration_ms'] ?? 0),
                    'strategy' => 'derive from /v2/shop/all',
                ];
            }
        }
        return [
            'ok' => false,
            'http' => 404,
            'code' => 404,
            'message' => 'Không tìm thấy shop trong /v2/shop/all',
            'data' => null,
            'raw' => ['derived_from' => '/v2/shop/all', 'shop_id' => $shopId],
            'base_url' => (string)($resAll['base_url'] ?? ''),
            'duration_ms' => (int)($resAll['duration_ms'] ?? 0),
            'strategy' => 'derive from /v2/shop/all',
        ];
    }

    // If everything fails, return the more informative response.
    $res['fallbacks'] = [
        'post_detail' => $res2,
        'shop_all' => $resAll,
    ];
    return $res;
}



function ghn_round_fee_1000(float $fee): float {
    $fee = max(0.0, (float)$fee);
    return (float)(round($fee / 1000) * 1000);
}

// Use the same insurance policy as checkout: take from GHN config (often 0).
// Allow request override if caller explicitly supplies insurance_value.
function ghn_insurance_value(array $cfg, array $input): int {
    if (array_key_exists('insurance_value', $input)) {
        return max(0, (int)$input['insurance_value']);
    }
    return max(0, (int)($cfg['insurance_value'] ?? 0));
}

function products_from_json(string $raw): array {
    global $ithanhloc;

    $items = json_decode($raw, true);
    if (!is_array($items)) return [];

    $rows = [];
    $productIds = [];
    $variantIds = [];

    foreach ($items as $it) {
        if (!is_array($it)) continue;
        $pid = (int)($it['product_id'] ?? $it['id'] ?? 0);
        $vid = max(0, (int)($it['variant_id'] ?? $it['v_id'] ?? $it['vid'] ?? 0));
        $typeRaw = (string)($it['type'] ?? $it['item_type'] ?? $it['line_type'] ?? '');
        $type = strtolower(trim($typeRaw));
        $isGift = !empty($it['is_gift'] ?? $it['gift'] ?? $it['is_gift_item'] ?? $it['is_present'] ?? $it['is_free_gift'] ?? 0)
            || in_array($type, ['gift', 'free_gift', 'bonus', 'present', 'promotion_gift'], true);
        $weightLocked = !empty($it['weight_locked']);
        if ($pid > 0) {
            $productIds[] = $pid;
        }
        if ($vid > 0) {
            $variantIds[$vid] = $vid;
        }
        $rows[] = [
            'product_id' => $pid,
            'variant_id' => $vid,
            'weight_locked' => $weightLocked ? 1 : 0,
            'is_gift' => $isGift ? 1 : 0,
            'name' => (string)($it['name'] ?? ''),
            'sku' => (string)($it['sku'] ?? ''),
            'variant_name' => (string)($it['variant'] ?? $it['variant_name'] ?? ''),
            'quantity' => max(1, (int)($it['quantity'] ?? $it['qty'] ?? 1)),
            'price' => (float)($it['price'] ?? 0),
            'weight' => max(1, (int)($it['weight'] ?? 1200)),
            'length' => max(1, (int)($it['length'] ?? 20)),
            'width' => max(1, (int)($it['width'] ?? 20)),
            'height' => max(1, (int)($it['height'] ?? 20)),
            'image' => (string)($it['image'] ?? $it['image_url'] ?? ''),
            // Optional, enriched later from variant table if available
            'variant_image' => (string)($it['variant_image'] ?? $it['variant_image_url'] ?? $it['variant_img'] ?? ''),
        ];
    }

    // Thử enrich thêm SKU và ảnh từ bảng ecommerce_product nếu thiếu
    $productIds = array_values(array_unique(array_filter($productIds, static function ($v) {
        return (int)$v > 0;
    })));

    $skuMap = [];
    if ($productIds && $ithanhloc instanceof mysqli) {
        $safe = implode(',', array_map('intval', $productIds));
        try {
            $variantTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_product_variants']) : 'ecommerce_product_variants';
        } catch (Throwable $e) {
            $variantTable = 'ecommerce_product_variants';
        }

        $skuExpr = $variantTable
            ? "COALESCE((SELECT sku_variant FROM `{$variantTable}` v WHERE v.product_id = p.id AND v.sku_variant <> '' ORDER BY v.id ASC LIMIT 1), '') AS sku"
            : "'' AS sku";

        $res = $ithanhloc->query("SELECT p.id, p.image_url, p.product_name, {$skuExpr} FROM ecommerce_product p WHERE p.id IN ($safe)");
        if ($res instanceof mysqli_result) {
            while ($row = $res->fetch_assoc()) {
                $id = (int)($row['id'] ?? 0);
                if ($id <= 0) continue;
                $skuMap[$id] = [
                    'sku' => (string)($row['sku'] ?? ''),
                    'image' => (string)($row['image_url'] ?? ''),
                    'name' => (string)($row['product_name'] ?? ''),
                ];
            }
        }
    }

    // Enrich per-item shipping weight/dimensions from variant shipping fields
    $variantMap = [];
    if ($variantIds && $ithanhloc instanceof mysqli) {
        try {
            $variantTable = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_product_variants']) : 'ecommerce_product_variants';
        } catch (Throwable $e) {
            $variantTable = 'ecommerce_product_variants';
        }

        if ($variantTable !== '') {
            $cols = [];
            $cols = listColumns($ithanhloc, $variantTable);
            // Normalize columns into a lookup map
            $colMap = [];
            if (is_array($cols)) {
                foreach ($cols as $k => $v) {
                    if (is_string($k)) {
                        $colMap[$k] = true;
                    } elseif (is_string($v)) {
                        $colMap[$v] = true;
                    } elseif (is_array($v) && isset($v['Field'])) {
                        $colMap[(string)$v['Field']] = true;
                    }
                }
            }

            $select = ['id', 'product_id'];
            foreach (['sku_variant', 'variant_name', 'shipping_weight_value', 'shipping_weight_unit', 'shipping_length_cm', 'shipping_width_cm', 'shipping_height_cm'] as $c) {
                if (isset($colMap[$c])) $select[] = $c;
            }

            // Try to include variant image columns if the table has them
            $variantImageCols = [
                'image_url',
                'image',
                'variant_image',
                'image_variant',
                'image_variant_url',
                'thumbnail_url',
                'thumb_url',
                'thumbnail',
                'thumb',
                'img_url',
                'img',
                'picture_url',
                'picture',
                'photo',
                'avatar',
            ];
            foreach ($variantImageCols as $c) {
                if (isset($colMap[$c])) $select[] = $c;
            }

            $idList = array_values($variantIds);
            $ph = implode(',', array_fill(0, count($idList), '?'));
            $types = str_repeat('i', count($idList));
            $sql = 'SELECT ' . implode(',', $select) . " FROM `{$variantTable}` WHERE id IN ($ph)";
            $stmt = $ithanhloc->prepare($sql);
            if ($stmt) {
                bindParamsDynamic($stmt, $types, $idList);
                $stmt->execute();
                $resV = $stmt->get_result();
                while ($v = $resV->fetch_assoc()) {
                    $vid = (int)($v['id'] ?? 0);
                    if ($vid <= 0) continue;
                    $variantMap[$vid] = $v;
                }
                $stmt->close();
            }
        }
    }

    // Build secondary lookup: [product_id][variant_name_lower] => variant row
    // Dùng khi item chỉ lưu variant_name (không có variant_id) — phổ biến với đơn hàng cũ
    $variantNameMap = [];
    foreach ($variantMap as $vid => $v) {
        $vpid = (int)($v['product_id'] ?? 0);
        $vname = mb_strtolower(trim((string)($v['variant_name'] ?? '')));
        if ($vpid > 0 && $vname !== '') {
            $variantNameMap[$vpid][$vname] = $v;
        }
    }
    // Nếu có items thiếu variant_id nhưng có variant_name, tra thêm từ DB
    $missingVariantItems = [];
    foreach ($rows as $idx => $row) {
        $pid = (int)($row['product_id'] ?? 0);
        $vid = (int)($row['variant_id'] ?? 0);
        $vname = mb_strtolower(trim((string)($row['variant_name'] ?? '')));
        if ($pid > 0 && $vid === 0 && $vname !== '' && !isset($variantNameMap[$pid][$vname])) {
            $missingVariantItems[$pid][] = $vname;
        }
    }
    if ($missingVariantItems && $ithanhloc instanceof mysqli) {
        $missingPids = array_keys($missingVariantItems);
        try { $variantTable2 = function_exists('first_existing_table') ? first_existing_table($ithanhloc, ['ecommerce_product_variants']) : 'ecommerce_product_variants'; } catch (Throwable $e) { $variantTable2 = 'ecommerce_product_variants'; }
        if ($variantTable2 !== '') {
            if (empty($colMap)) {
                $cols = listColumns($ithanhloc, $variantTable2);
                $colMap = [];
                if (is_array($cols)) {
                    foreach ($cols as $k => $v) {
                        if (is_string($k)) {
                            $colMap[$k] = true;
                        } elseif (is_string($v)) {
                            $colMap[$v] = true;
                        } elseif (is_array($v) && isset($v['Field'])) {
                            $colMap[(string)$v['Field']] = true;
                        }
                    }
                }
            }
            $ph2 = implode(',', array_fill(0, count($missingPids), '?'));
            $cols2 = array_intersect(['id','product_id','variant_name','sku_variant','image_url','shipping_weight_value','shipping_weight_unit','shipping_length_cm','shipping_width_cm','shipping_height_cm'], array_keys($colMap ?? []));
            if (!$cols2) $cols2 = ['id','product_id','variant_name','sku_variant','image_url'];
            $sql2 = 'SELECT ' . implode(',', $cols2) . " FROM `{$variantTable2}` WHERE product_id IN ($ph2)";
            $stmt2 = $ithanhloc->prepare($sql2);
            if ($stmt2) {
                $types2 = str_repeat('i', count($missingPids));
                bindParamsDynamic($stmt2, $types2, $missingPids);
                $stmt2->execute();
                $res2 = $stmt2->get_result();
                while ($v2 = $res2->fetch_assoc()) {
                    $v2pid = (int)($v2['product_id'] ?? 0);
                    $v2name = mb_strtolower(trim((string)($v2['variant_name'] ?? '')));
                    if ($v2pid > 0 && $v2name !== '') {
                        $variantNameMap[$v2pid][$v2name] = $v2;
                    }
                }
                $stmt2->close();
            }
        }
    }

    if ($skuMap || $variantMap || $variantNameMap) {
        foreach ($rows as &$row) {
            $pid = (int)($row['product_id'] ?? 0);
            $vid = (int)($row['variant_id'] ?? 0);

            // Nếu variant_id = 0 nhưng có variant_name → tra theo tên
            if ($vid === 0 && $pid > 0) {
                $vnameLow = mb_strtolower(trim((string)($row['variant_name'] ?? '')));
                if ($vnameLow !== '' && isset($variantNameMap[$pid][$vnameLow])) {
                    $lookupV = $variantNameMap[$pid][$vnameLow];
                    $vid = (int)($lookupV['id'] ?? 0);
                    $row['variant_id'] = $vid;
                    if ($vid > 0 && !isset($variantMap[$vid])) {
                        $variantMap[$vid] = $lookupV;
                    }
                }
            }

            if ($pid > 0 && isset($skuMap[$pid])) {
                $info = $skuMap[$pid];
                // Chỉ fallback name/image từ product nếu chưa có — SKU sẽ được ghi đè bởi variant bên dưới
                if ($row['image'] === '' && $info['image'] !== '') {
                    $row['image'] = $info['image'];
                }
                if ($row['name'] === '' && $info['name'] !== '') {
                    $row['name'] = $info['name'];
                }
                // SKU: chỉ dùng product-level SKU nếu không có variant_id nào match
                if ($row['sku'] === '' && $info['sku'] !== '' && $vid === 0) {
                    $row['sku'] = $info['sku'];
                }
            }

            if ($vid > 0 && isset($variantMap[$vid])) {
                $v = $variantMap[$vid];

                if ($row['variant_name'] === '' && !empty($v['variant_name'])) {
                    $row['variant_name'] = (string)$v['variant_name'];
                }

                // Enrich variant image (prefer variant image, fallback handled in UI)
                if (empty($row['variant_image'])) {
                    foreach (['image_url', 'variant_image', 'image_variant_url', 'image_variant', 'image', 'thumbnail_url', 'thumb_url', 'thumbnail', 'thumb', 'img_url', 'img', 'picture_url', 'picture', 'photo', 'avatar'] as $c) {
                        $val = (string)($v[$c] ?? '');
                        if ($val !== '') {
                            $row['variant_image'] = $val;
                            break;
                        }
                    }
                }

                if (!empty($row['weight_locked'])) {
                    continue;
                }
                // Safety: ensure variant belongs to product
                if ((int)($v['product_id'] ?? 0) === $pid || $pid <= 0) {
                    $wVal = (float)($v['shipping_weight_value'] ?? 0);
                    $wUnit = (string)($v['shipping_weight_unit'] ?? 'kg');
                    $wGram = convertWeightToGram($wVal, $wUnit);
                    if ($wGram > 0) {
                        $row['weight'] = $wGram;
                    }
                    $l = (int)($v['shipping_length_cm'] ?? 0);
                    $wi = (int)($v['shipping_width_cm'] ?? 0);
                    $h = (int)($v['shipping_height_cm'] ?? 0);
                    if ($l > 0) $row['length'] = max(1, $l);
                    if ($wi > 0) $row['width'] = max(1, $wi);
                    if ($h > 0) $row['height'] = max(1, $h);

                    if ($row['sku'] === '' && !empty($v['sku_variant'])) {
                        $row['sku'] = (string)$v['sku_variant'];
                    }
                }
            }
        }
        unset($row);
    }

    return $rows;
}

function order_items_summary(array $items): array {
    $totalWeight = 0;
    $maxLength = 1;
    $maxWidth = 1;
    $maxHeight = 1;
    $qty = 0;
    foreach ($items as $it) {
        $q = max(1, (int)($it['quantity'] ?? 1));
        $qty += $q;
        $w = max(1, (int)($it['weight'] ?? 1));
        $l = max(1, (int)($it['length'] ?? 1));
        $wi = max(1, (int)($it['width'] ?? 1));
        $h = max(1, (int)($it['height'] ?? 1));
        $totalWeight += $w * $q;
        $maxLength = max($maxLength, $l);
        $maxWidth = max($maxWidth, $wi);
        $maxHeight = max($maxHeight, $h);
    }
    return [
        'total_qty' => $qty,
        'actual_weight_gram' => $totalWeight,
        'chargeable_weight_gram' => $totalWeight,
        'length' => $maxLength,
        'width' => $maxWidth,
        'height' => $maxHeight,
    ];
}

function ghn_shop_normalize_row(array $row): array {
    $shopId = (int)($row['shop_id'] ?? $row['shop_id'] ?? $row['ShopID'] ?? $row['shopId'] ?? 0);
    $name = (string)($row['name'] ?? $row['shop_name'] ?? $row['ShopName'] ?? $row['shopName'] ?? '');
    $phone = (string)($row['phone'] ?? $row['phone_number'] ?? $row['Phone'] ?? '');
    $address = (string)($row['address'] ?? $row['address_full'] ?? $row['Address'] ?? '');
    $districtId = (int)($row['district_id'] ?? $row['DistrictID'] ?? $row['districtId'] ?? 0);
    $wardCode = (string)($row['ward_code'] ?? $row['WardCode'] ?? $row['wardCode'] ?? '');

    return [
        'shop_id' => $shopId,
        'name' => $name,
        'phone' => $phone,
        'address' => $address,
        'district_id' => $districtId,
        'ward_code' => $wardCode,
        'raw' => $row,
    ];
}

function addr_contains(string $addrNorm, string $nameNorm): bool {
    if ($addrNorm === '' || $nameNorm === '') return false;
    $needle = ' ' . $nameNorm . ' ';
    $hay = ' ' . $addrNorm . ' ';
    return strpos($hay, $needle) !== false;
}

function match_region(array $rows, string $address, string $keyName): ?array {
    $addrNorm = vn_norm($address);
    $best = null;
    $bestLen = 0;
    foreach ($rows as $r) {
        $name = (string)($r[$keyName] ?? '');
        $norm = strip_prefix($name);
        if ($norm === '') continue;
        if (addr_contains($addrNorm, $norm)) {
            $len = strlen($norm);
            if ($len > $bestLen) {
                $bestLen = $len;
                $best = $r;
            }
        }
    }
    return $best;
}


function resolve_address(mysqli $ithanhloc, array $cfg, string $address): array {
    $address = trim($address);
    if ($address === '') {
        return ['ok' => false, 'needs_manual' => true, 'msg' => 'Thiếu địa chỉ'];
    }

    $provinces = ghn_fetch_master_provinces($ithanhloc, $cfg);
    $p = match_region($provinces, $address, 'ProvinceName');
    if (!$p) {
        return ['ok' => false, 'needs_manual' => true, 'msg' => 'Không xác định được tỉnh/thành'];
    }

    $provinceId = (int)($p['ProvinceID'] ?? 0);
    $districts = ghn_fetch_master_districts($ithanhloc, $cfg, $provinceId);
    $d = match_region($districts, $address, 'DistrictName');
    if (!$d) {
        return ['ok' => false, 'needs_manual' => true, 'province_id' => $provinceId, 'province_name' => (string)($p['ProvinceName'] ?? ''), 'msg' => 'Không xác định được quận/huyện'];
    }

    $districtId = (int)($d['DistrictID'] ?? 0);
    $wards = ghn_fetch_master_wards($ithanhloc, $cfg, $districtId);
    $w = match_region($wards, $address, 'WardName');
    if (!$w) {
        return [
            'ok' => false,
            'needs_manual' => true,
            'province_id' => $provinceId,
            'province_name' => (string)($p['ProvinceName'] ?? ''),
            'district_id' => $districtId,
            'district_name' => (string)($d['DistrictName'] ?? ''),
            'msg' => 'Không xác định được phường/xã',
        ];
    }

    return [
        'ok' => true,
        'needs_manual' => false,
        'province_id' => $provinceId,
        'province_name' => (string)($p['ProvinceName'] ?? ''),
        'district_id' => $districtId,
        'district_name' => (string)($d['DistrictName'] ?? ''),
        'ward_code' => (string)($w['WardCode'] ?? ''),
        'ward_name' => (string)($w['WardName'] ?? ''),
        'msg' => 'Đã nhận diện địa chỉ',
    ];
}

// ... (Logic xử lý order action tiếp tục bên dưới)

if ($action === 'setting_get') {
    $config = [
        'env' => 'prod',
        'base_url' => 'https://online-gateway.ghn.vn/shiip/public-api',
        'enabled' => !empty($cfg['enabled']) ? 1 : 0,
        'has_token' => !empty($cfg['token']) ? 1 : 0,
        'token_masked' => ghn_mask_token((string)($cfg['token'] ?? '')),
    ];

    jOut([
        'ok' => true,
        'config' => $config,
        'active_shop' => (array)($cfg['shop'] ?? []),
    ]);
}

if ($action === 'setting_token_get') {
    // Admin-only: return plain token for copy-to-clipboard.
    // Keep this separate from setting_get to avoid exposing token by default.
    $token = (string)($cfg['token'] ?? '');
    jOut([
        'ok' => true,
        'has_token' => $token !== '' ? 1 : 0,
        'token' => $token,
        'token_masked' => ghn_mask_token($token),
    ]);
}

if ($action === 'api_test') {
    $cfgNow = ghn_get_cfg($ithanhloc);
    $shop = is_array($cfgNow['shop'] ?? null) ? (array)$cfgNow['shop'] : [];
    $shopId = (int)($shop['shop_id'] ?? 0);
    $token = trim((string)($cfgNow['token'] ?? ''));
    $enabled = !empty($cfgNow['enabled']);

    $tests = [];

    $tests[] = [
        'key' => 'config',
        'label' => 'Đọc cấu hình',
        'ok' => true,
        'data' => [
            'env' => 'prod',
            'base_url' => 'https://online-gateway.ghn.vn/shiip/public-api',
            'enabled' => $enabled ? 1 : 0,
            'has_token' => $token !== '' ? 1 : 0,
            'shop_id' => $shopId,
            'shop_name' => (string)($shop['name'] ?? $shop['shop_name'] ?? ''),
        ],
    ];

    if ($token === '') {
        $tests[] = [
            'key' => 'token',
            'label' => 'Token',
            'ok' => false,
            'message' => 'Chưa cấu hình token',
        ];
        jOut([
            'ok' => false,
            'ready' => false,
            'tests' => $tests,
            'msg' => 'Thiếu token GHN',
        ]);
    }

    // Test 1: master data provinces (Token only)
    $resProvince = ghn_request($cfgNow, 'GET', '/v2/master-data/province', null, false);
    if (empty($resProvince['ok']) && (int)($resProvince['http'] ?? 0) === 404) {
        $resProvince = ghn_request($cfgNow, 'GET', '/master-data/province', null, false);
        $resProvince['endpoint_used'] = '/master-data/province';
    } else {
        $resProvince['endpoint_used'] = '/v2/master-data/province';
    }
    $tests[] = [
        'key' => 'province',
        'label' => 'Master data: province',
        'endpoint' => '/v2/master-data/province',
        'result' => $resProvince,
        'ok' => !empty($resProvince['ok']),
    ];

    // Test 2: shop/all (Token only)
    $resShopAll = ghn_request($cfgNow, 'POST', '/v2/shop/all', ['offset' => 0, 'limit' => 5], false);
    $tests[] = [
        'key' => 'shop_all',
        'label' => 'Shop: all',
        'endpoint' => '/v2/shop/all',
        'payload' => ['offset' => 0, 'limit' => 5],
        'result' => $resShopAll,
        'ok' => !empty($resShopAll['ok']),
    ];

    // Optional: shop/detail for active shop
    if ($shopId > 0) {
        $resShopDetail = ghn_fetch_shop_detail_any($cfgNow, $shopId);
        $tests[] = [
            'key' => 'shop_detail',
            'label' => 'Shop: detail',
            'endpoint' => '/v2/shop/detail',
            'payload' => ['shop_id' => $shopId],
            'result' => $resShopDetail,
            'ok' => !empty($resShopDetail['ok']),
        ];
    } else {
        $tests[] = [
            'key' => 'shop_detail',
            'label' => 'Shop: detail',
            'ok' => false,
            'message' => 'Chưa chọn shop (ShopId)',
            'skipped' => true,
        ];
    }

    $allOk = true;
    foreach ($tests as $t) {
        if (!empty($t['skipped'])) continue;
        if (isset($t['ok']) && !$t['ok']) {
            $allOk = false;
            break;
        }
    }

    jOut([
        'ok' => $allOk,
        'ready' => $allOk,
        'tests' => $tests,
        'msg' => $allOk ? '200' : 'Gặp lỗi khi test API',
    ]);
}

if ($action === 'setting_save') {
    // Luôn dùng môi trường production với Base URL cố định
    $env = 'prod';
    $baseUrl = 'https://online-gateway.ghn.vn/shiip/public-api';
    $token = sVal($input['token'] ?? '');
    $enabled = iVal($input['enabled'] ?? 1, 1) ? '1' : '0';

    // Nếu token rỗng thì giữ token hiện tại
    $current = ghn_setting_get_all($ithanhloc);
    if ($token === '') {
        $token = (string)($current['token'] ?? '');
    }

    $configToSave = [
        'env' => $env,
        'base_url' => $baseUrl,
        'enabled' => $enabled,
        'token' => $token,
    ];

    $ok = ghn_setting_save_config($ithanhloc, $configToSave);

    $cfg = ghn_get_cfg($ithanhloc);
    $config = [
        'env' => 'prod',
        'base_url' => 'https://online-gateway.ghn.vn/shiip/public-api',
        'enabled' => !empty($cfg['enabled']) ? 1 : 0,
        'has_token' => !empty($cfg['token']) ? 1 : 0,
        'token_masked' => ghn_mask_token((string)($cfg['token'] ?? '')),
    ];

    jOut([
        'ok' => (bool)$ok,
        'msg' => $ok ? 'Đã lưu cấu hình GHN' : 'Lưu cấu hình GHN thất bại',
        'config' => $config,
        'active_shop' => (array)($cfg['shop'] ?? []),
    ]);
}

if ($action === 'shop_list') {
    $rows = ghn_shop_list($ithanhloc);
    jOut([
        'ok' => true,
        'rows' => $rows,
    ]);
}

if ($action === 'shop_set_active') {
    $shopId = iVal($input['shop_id'] ?? 0);
    if ($shopId <= 0) {
        jOut(['ok' => false, 'msg' => 'Thiếu shop_id']);
    }
    $ok = ghn_shop_set_active($ithanhloc, $shopId);
    jOut([
        'ok' => (bool)$ok,
        'msg' => $ok ? 'Đã chọn shop' : 'Không chọn được shop',
        'rows' => ghn_shop_list($ithanhloc),
    ]);
}

if ($action === 'shop_scan') {
    if (empty($cfg['token'])) {
        jOut(['ok' => false, 'msg' => 'Thiếu token GHN để quét shop']);
    }

    $limit = max(1, min(500, iVal($input['limit'] ?? 200, 200)));
    $res = ghn_request($cfg, 'POST', '/v2/shop/all', ['offset' => 0, 'limit' => $limit], false);
    if (empty($res['ok'])) {
        jOut(['ok' => false, 'msg' => $res['message'] ?? 'Không lấy được danh sách shop']);
    }

    $data = $res['data'] ?? [];
    $rows = [];
    if (is_array($data)) {
        if (isset($data['shops']) && is_array($data['shops'])) $rows = $data['shops'];
        elseif (isset($data['data']) && is_array($data['data'])) $rows = $data['data'];
        elseif (isset($data[0])) $rows = $data;
    }

    $activeShop = ghn_shop_get_active($ithanhloc);
    $activeId = (int)($activeShop['shop_id'] ?? 0);
    $firstId = 0;
    foreach ($rows as $row) {
        if (!is_array($row)) continue;
        $normalized = ghn_shop_normalize_row($row);
        if ($normalized['shop_id'] <= 0) continue;
        if ($firstId <= 0) $firstId = (int)$normalized['shop_id'];
        if ($activeId > 0 && $normalized['shop_id'] === $activeId) {
            $normalized['is_active'] = 1;
        }
        ghn_shop_upsert($ithanhloc, $normalized, false);
    }

    if ($activeId <= 0 && $firstId > 0) {
        ghn_shop_set_active($ithanhloc, $firstId);
    }

    jOut([
        'ok' => true,
        'rows' => ghn_shop_list($ithanhloc),
    ]);
}

if ($action === 'shop_refresh') {
    if (empty($cfg['token'])) {
        jOut(['ok' => false, 'msg' => 'Thiếu token GHN để tải mới shop']);
    }

    $shopId = iVal($input['shop_id'] ?? 0);
    if ($shopId <= 0) {
        $limit = max(1, min(500, iVal($input['limit'] ?? 200, 200)));
        $res = ghn_request($cfg, 'POST', '/v2/shop/all', ['offset' => 0, 'limit' => $limit], false);
        if (empty($res['ok'])) {
            jOut(['ok' => false, 'msg' => $res['message'] ?? 'Không lấy được danh sách shop']);
        }

        $data = $res['data'] ?? [];
        $rows = [];
        if (is_array($data)) {
            if (isset($data['shops']) && is_array($data['shops'])) $rows = $data['shops'];
            elseif (isset($data['data']) && is_array($data['data'])) $rows = $data['data'];
            elseif (isset($data[0])) $rows = $data;
        }

        $activeShop = ghn_shop_get_active($ithanhloc);
        $activeId = (int)($activeShop['shop_id'] ?? 0);
        $firstId = 0;
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $normalized = ghn_shop_normalize_row($row);
            if ($normalized['shop_id'] <= 0) continue;
            if ($firstId <= 0) $firstId = (int)$normalized['shop_id'];
            if ($activeId > 0 && $normalized['shop_id'] === $activeId) {
                $normalized['is_active'] = 1;
            }
            ghn_shop_upsert($ithanhloc, $normalized, false);
        }

        if ($activeId <= 0 && $firstId > 0) {
            ghn_shop_set_active($ithanhloc, $firstId);
        }

        jOut([
            'ok' => true,
            'rows' => ghn_shop_list($ithanhloc),
        ]);
    } else {
        $res = ghn_fetch_shop_detail_any($cfg, $shopId);
        if (empty($res['ok'])) {
            jOut(['ok' => false, 'msg' => $res['message'] ?? 'Không lấy được thông tin shop']);
        }
        $data = $res['data'] ?? [];
        $row = is_array($data) ? $data : [];
        $normalized = ghn_shop_normalize_row($row);
        if ($normalized['shop_id'] > 0) {
            $activeShop = ghn_shop_get_active($ithanhloc);
            $activeId = (int)($activeShop['shop_id'] ?? 0);
            if ($activeId > 0 && $normalized['shop_id'] === $activeId) {
                $normalized['is_active'] = 1;
            }
            ghn_shop_upsert($ithanhloc, $normalized, false);
        }
        jOut([
            'ok' => true,
            'rows' => ghn_shop_list($ithanhloc),
        ]);
    }
}

if ($action === 'region_provinces') {
    $rows = ghn_fetch_master_provinces($ithanhloc, $cfg);
    jOut(['ok' => true, 'rows' => $rows]);
}

if ($action === 'region_districts') {
    $provinceId = iVal($input['province_id'] ?? 0);
    $rows = $provinceId > 0 ? ghn_fetch_master_districts($ithanhloc, $cfg, $provinceId) : [];
    jOut(['ok' => true, 'rows' => $rows]);
}

if ($action === 'region_wards') {
    $districtId = iVal($input['district_id'] ?? 0);
    $rows = $districtId > 0 ? ghn_fetch_master_wards($ithanhloc, $cfg, $districtId) : [];
    jOut(['ok' => true, 'rows' => $rows]);
}

if ($action === 'region_lookup') {
    $districtId = iVal($input['district_id'] ?? 0);
    $wardCode = sVal($input['ward_code'] ?? '');
    $district = null;
    $ward = null;
    if ($districtId > 0) {
        $stmt = $ithanhloc->prepare("SELECT region_id AS DistrictID, name AS DistrictName FROM ghn_region WHERE level='district' AND region_id=? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('i', $districtId);
            $stmt->execute();
            $district = $stmt->get_result()->fetch_assoc() ?: null;
            $stmt->close();
        }
        if (!$district) {
            $provinces = ghn_fetch_master_provinces($ithanhloc, $cfg);
            foreach ($provinces as $p) {
                $pid = (int)($p['ProvinceID'] ?? 0);
                if ($pid <= 0) continue;
                $rows = ghn_fetch_master_districts($ithanhloc, $cfg, $pid);
                foreach ($rows as $d) {
                    if ((int)($d['DistrictID'] ?? 0) === $districtId) { $district = $d; break 2; }
                }
            }
        }
        if ($wardCode !== '') {
            $wards = ghn_fetch_master_wards($ithanhloc, $cfg, $districtId);
            foreach ($wards as $w) {
                if ((string)($w['WardCode'] ?? '') === $wardCode) { $ward = $w; break; }
            }
        }
    }
    jOut([
        'ok' => true,
        'district' => $district ? [
            'district_id' => (int)($district['DistrictID'] ?? 0),
            'district_name' => (string)($district['DistrictName'] ?? ''),
        ] : null,
        'ward' => $ward ? [
            'ward_code' => (string)($ward['WardCode'] ?? ''),
            'ward_name' => (string)($ward['WardName'] ?? ''),
        ] : null,
    ]);
}

if ($action === 'address_resolve') {
    $address = sVal($input['address'] ?? '');
    $resolve = resolve_address($ithanhloc, $cfg, $address);
    jOut(['ok' => (bool)($resolve['ok'] ?? false), 'resolve' => $resolve]);
}

if ($action === 'pickup_shifts') {
    if (empty($cfg['token'])) {
        jOut(['ok' => false, 'msg' => 'Thiếu token GHN']);
    }
    $res = ghn_request($cfg, 'GET', '/v2/shift', null, true);
    jOut([
        'ok' => !empty($res['ok']),
        'msg' => $res['message'] ?? '',
        'data' => $res['data'] ?? [],
    ]);
}

if ($action === 'dropoff_stations') {
    if (empty($cfg['token'])) {
        jOut(['ok' => false, 'msg' => 'Thiếu token GHN']);
    }
    $districtId = iVal($input['district_id'] ?? 0);
    $res = ghn_request($cfg, 'GET', '/v2/station?district_id=' . urlencode((string)$districtId), null, true);
    $rows = is_array($res['data'] ?? null) ? ($res['data'] ?? []) : [];
    jOut([
        'ok' => !empty($res['ok']),
        'msg' => $res['message'] ?? '',
        'rows' => $rows,
    ]);
}

if ($action === 'available_services') {
    if (!ghn_ready($cfg)) {
        jOut(['ok' => false, 'msg' => 'Chưa cấu hình API/Shop GHN']);
    }
    $payload = [
        'from_district' => iVal($input['from_district_id'] ?? 0),
        'to_district' => iVal($input['to_district_id'] ?? 0),
    ];
    $res = ghn_request($cfg, 'POST', '/v2/shipping-order/available-services', $payload, true);
    $rows = is_array($res['data'] ?? null) ? ($res['data'] ?? []) : [];
    jOut([
        'ok' => !empty($res['ok']),
        'msg' => $res['message'] ?? '',
        'rows' => $rows,
    ]);
}

if ($action === 'service_fee') {
    if (!ghn_ready($cfg)) {
        jOut(['ok' => false, 'msg' => 'Chưa cấu hình API/Shop GHN']);
    }
    $payload = [
        'from_district_id' => iVal($input['from_district_id'] ?? 0),
        'from_ward_code' => sVal($input['from_ward_code'] ?? ''),
        'to_district_id' => iVal($input['to_district_id'] ?? 0),
        'to_ward_code' => sVal($input['to_ward_code'] ?? ''),
        'weight' => iVal($input['weight'] ?? 0),
        'length' => iVal($input['length'] ?? 0),
        'width' => iVal($input['width'] ?? 0),
        'height' => iVal($input['height'] ?? 0),
        // IMPORTANT: keep consistent with checkout (do not auto-insure by goods_value unless configured)
        'insurance_value' => ghn_insurance_value($cfg, $input),
        'service_id' => iVal($input['service_id'] ?? 0),
        'service_type_id' => iVal($input['service_type_id'] ?? 0),
        'coupon' => sVal($input['coupon'] ?? ''),
    ];
    $res = ghn_request($cfg, 'POST', '/v2/shipping-order/fee', $payload, true);
    $feeData = is_array($res['data'] ?? null) ? $res['data'] : [];
    $lightFeeRaw = (float)($feeData['total'] ?? 0);
    $lightFee = ghn_round_fee_1000($lightFeeRaw);
    $weightGram = max(0, (int)($payload['weight'] ?? 0));
    // Ngưỡng hàng nặng: từ 20kg trở lên (20.000 gram)
    $heavyThresholdGram = 20000;
    $heavyApplies = $weightGram >= $heavyThresholdGram;

    jOut([
        'ok' => !empty($res['ok']),
        'msg' => $res['message'] ?? '',
        'light' => [
            'fee' => $lightFee,
            'fee_raw' => $lightFeeRaw,
            'weight_gram' => $weightGram,
        ],
        'heavy' => [
            'applies' => $heavyApplies,
            // Tạm thời dùng cùng mức phí với light; có thể tách công thức riêng nếu cần.
            'fee' => $heavyApplies ? $lightFee : 0.0,
            'threshold_gram' => $heavyThresholdGram,
        ],
        'raw' => $res['raw'] ?? null,
    ]);
}

if ($action === 'system_orders_list') {
    $status = sVal($input['status'] ?? 'open');
    $limit = max(1, min(500, iVal($input['limit'] ?? 200, 200)));
    $where = [];
    $params = [];
    $types = '';

    if (!empty($orderCols['status'])) {
        if ($status === 'open') {
            $where[] = "status IN ('pending','processing')";
        } elseif ($status !== '') {
            $where[] = 'status = ?';
            $params[] = $status;
            $types .= 's';
        }
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $sql = "SELECT * FROM ecommerce_order $whereSql ORDER BY created_at DESC LIMIT ?";
    $stmt = $ithanhloc->prepare($sql);
    if (!$stmt) {
        jOut(['ok' => false, 'msg' => 'Không tải được danh sách đơn']);
    }
    if ($types !== '') {
        $types .= 'i';
        $params[] = $limit;
        $stmt->bind_param($types, ...$params);
    } else {
        $stmt->bind_param('i', $limit);
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = [];
    while ($r = $res->fetch_assoc()) {
        $items = !empty($r['products_json'] ?? '') ? products_from_json((string)$r['products_json']) : [];
        $summary = order_items_summary($items);
        $rows[] = [
            'order_id' => (string)($r['order_id'] ?? ''),
            'user_name' => (string)($r['user_name'] ?? ''),
            'phone' => (string)($r['phone'] ?? ''),
            'address' => (string)($r['address'] ?? ''),
            'created_at' => (string)($r['created_at'] ?? ''),
            'total_qty' => $summary['total_qty'],
            'total_amount' => (float)($r['total_amount'] ?? 0),
            'payment_method' => (string)($r['payment_method'] ?? $r['payment_type'] ?? ''),
        ];
    }
    $stmt->close();

    jOut(['ok' => true, 'rows' => $rows]);
}

if ($action === 'system_order_detail') {
    $orderId = sVal($input['order_id'] ?? '');
    if ($orderId === '') {
        jOut(['ok' => false, 'msg' => 'Thiếu mã đơn']);
    }
    $stmt = $ithanhloc->prepare('SELECT * FROM ecommerce_order WHERE order_id=? LIMIT 1');
    if (!$stmt) {
        jOut(['ok' => false, 'msg' => 'Không tải được đơn']);
    }
    $stmt->bind_param('s', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order) {
        jOut(['ok' => false, 'msg' => 'Không tìm thấy đơn']);
    }

    $items = !empty($order['products_json'] ?? '') ? products_from_json((string)$order['products_json']) : [];
    $summary = order_items_summary($items);

    // Nếu đơn hàng có snapshot phí ship từ checkout, ưu tiên dùng trọng lượng/kích thước
    // trong snapshot để đồng bộ 100% với phía website.
    $snapshotRaw = (string)($order['shipping_snapshot_json'] ?? '');
    if ($snapshotRaw !== '') {
        $snap = json_decode($snapshotRaw, true);
        if (is_array($snap)) {
            $pkg = $snap['snapshot']['product_shipping']['package']
                ?? $snap['product_shipping']['package']
                ?? null;
            if (is_array($pkg)) {
                $w = (int)($pkg['weight'] ?? 0);
                $l = (int)($pkg['length'] ?? 0);
                $wi = (int)($pkg['width'] ?? 0);
                $h = (int)($pkg['height'] ?? 0);
                if ($w > 0) {
                    $summary['actual_weight_gram'] = $w;
                    $summary['chargeable_weight_gram'] = $w;
                }
                if ($l > 0) $summary['length'] = $l;
                if ($wi > 0) $summary['width'] = $wi;
                if ($h > 0) $summary['height'] = $h;
            }
        }
    }
    $resolve = resolve_address($ithanhloc, $cfg, (string)($order['address'] ?? ''));

    $orderPayload = [
        'order_id' => (string)($order['order_id'] ?? ''),
        'user_name' => (string)($order['user_name'] ?? ''),
        'phone' => (string)($order['phone'] ?? ''),
        'address' => (string)($order['address'] ?? ''),
        'cod_amount' => (strtolower($order['payment_method'] ?? '') === 'cod' && strtolower($order['payment_status'] ?? '') !== 'paid') ? (float)($order['total_amount'] ?? 0) : 0.0,
        'goods_value' => (float)($order['subtotal'] ?? $order['total_amount'] ?? 0),
        'cod_failed_amount' => (float)($order['cod_failed_amount'] ?? 0),
        'created_at' => (string)($order['created_at'] ?? ''),
        'payment_method' => (string)($order['payment_method'] ?? $order['payment_type'] ?? ''),
        'payment_gateway' => (string)($order['payment_gateway'] ?? ''),
    ];

    jOut([
        'ok' => true,
        'order' => $orderPayload,
        'items' => $items,
        'summary' => $summary,
        'resolve' => $resolve,
    ]);
}

function ghn_normalize_payment_method(string $raw, float $codAmount = 0.0): string {
    $s = strtolower(trim($raw));
    if ($s === '') {
        return $codAmount > 0 ? 'cod' : '';
    }
    $token = preg_replace('/[^a-z0-9]/', '', $s);
    return match (true) {
        $token === 'cod', $token === 'cash', $token === 'cashondelivery' => 'cod',
        str_contains($token, 'momo') => 'momo',
        str_contains($token, 'vnp') => 'vnpay',
        str_contains($token, 'zalo') => 'zalopay',
        default => $s,
    };
}

if ($action === 'ghn_orders_list') {
    $limit = max(1, min(200, iVal($input['limit'] ?? 50, 50)));
    // NOTE: Không assume ecommerce_order có các cột như order_code/system_order_id.
    // Join an toàn theo order_id để tránh lỗi 500 do sai schema.
    $sql = "SELECT
                g.*,
                COALESCE(
                    NULLIF(g.payment_method,''),
                    NULLIF(eo.payment_method,''),
                    NULLIF(eo.payment_gateway,''),
                    ''
                ) AS ecom_payment_method,
                COALESCE(NULLIF(eo.created_at,''), NULLIF(g.created_at,'')) AS created_at
            FROM ghn_order g
            LEFT JOIN ecommerce_order eo
                ON eo.order_id = g.system_order_id
            ORDER BY g.updated_at DESC, g.id DESC
            LIMIT ?";

    try {
        $stmt = $ithanhloc->prepare($sql);
        if (!$stmt) {
            jOut(['ok' => false, 'msg' => 'Không tải được danh sách đơn GHN']);
        }
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    } catch (mysqli_sql_exception $e) {
        jOut(['ok' => false, 'msg' => 'SQL lỗi khi tải danh sách đơn GHN']);
    }

    // Normalize payment method key for UI filtering/badges.
    foreach ($rows as &$r) {
        $rawMethod = (string)($r['ecom_payment_method'] ?? '');
        $codAmount = (float)($r['cod_amount'] ?? 0);
        $r['ecom_payment_method'] = ghn_normalize_payment_method($rawMethod, $codAmount);
        if (!isset($r['created_at']) || trim((string)$r['created_at']) === '') {
            $r['created_at'] = (string)($r['updated_at'] ?? '');
        }
    }
    unset($r);
    jOut(['ok' => true, 'rows' => $rows]);
}

if ($action === 'ghn_dashboard_summary') {
    $rows = $ithanhloc->query("SELECT status, COUNT(*) AS c FROM ghn_order GROUP BY status");
    $buckets = [];
    if ($rows instanceof mysqli_result) {
        while ($r = $rows->fetch_assoc()) {
            $key = (string)($r['status'] ?? '');
            if ($key !== '') $buckets[$key] = (int)($r['c'] ?? 0);
        }
    }
    $total = array_sum($buckets);
    $summary = [
        'total_orders' => $total,
        'pending_pickup' => ($buckets['ready_to_pick'] ?? 0) + ($buckets['picking'] ?? 0) + ($buckets['picked'] ?? 0),
        'in_transit' => ($buckets['delivering'] ?? 0) + ($buckets['transporting'] ?? 0),
        'delivered_success' => ($buckets['delivered'] ?? 0),
        'failed_return_cancel' => ($buckets['returned'] ?? 0) + ($buckets['cancel'] ?? 0) + ($buckets['canceled'] ?? 0) + ($buckets['cancelled'] ?? 0),
    ];
    $recent = $ithanhloc->query("SELECT created_at, order_code, status, status_text FROM ghn_order_log ORDER BY id DESC LIMIT 10");
    $activities = $recent instanceof mysqli_result ? $recent->fetch_all(MYSQLI_ASSOC) : [];

    jOut([
        'ok' => true,
        'summary' => $summary,
        'status_buckets' => $buckets,
        'trend_7d' => [],
        'activities' => $activities,
    ]);
}

if ($action === 'ghn_orders_sync') {
    if (!ghn_ready($cfg)) {
        jOut(['ok' => false, 'msg' => 'Chưa cấu hình API/Shop GHN']);
    }
    jOut(['ok' => true, 'updated' => 0, 'msg' => 'Đã đồng bộ (tạm thời dùng dữ liệu local)']);
}

if ($action === 'order_preview' || $action === 'order_create') {
    if (!ghn_ready($cfg)) {
        jOut(['ok' => false, 'msg' => 'Chưa cấu hình API/Shop GHN']);
    }
    $payload = is_array($input['payload'] ?? null) ? ($input['payload'] ?? []) : jArr($input['payload'] ?? '');
    $sender = (array)($payload['sender'] ?? []);
    $receiver = (array)($payload['receiver'] ?? []);
    $service = (array)($payload['service'] ?? []);
    $orderInfo = (array)($payload['order_info'] ?? []);
    $delivery = (array)($payload['delivery_note'] ?? []);
    $products = is_array($payload['products'] ?? null) ? ($payload['products'] ?? []) : [];

    if (empty($receiver['address'])) {
         jOut(['ok' => false, 'msg' => 'Thiếu thông tin người nhận (tên/sđt/địa chỉ)']);
    }

    $pickShiftRaw = $service['pick_shift'] ?? [2];
    $pickShiftVal = is_array($pickShiftRaw) ? $pickShiftRaw : [$pickShiftRaw];
    $pickShiftVal = array_values(array_filter(array_map('intval', $pickShiftVal), fn($v) => $v > 0));
    if (!$pickShiftVal) $pickShiftVal = [2];

    $ghnPayload = [
        'payment_type_id' => iVal($service['payment_type_id'] ?? 2),
        'note' => sVal($delivery['note'] ?? ''),
        'required_note' => sVal($delivery['required_note'] ?? 'KHONGCHOXEMHANG'),
        'return_phone' => sVal($sender['phone'] ?? ''),
        'return_address' => sVal($sender['address'] ?? ''),
        'return_district_id' => iVal($sender['from_district_id'] ?? 0),
        'return_ward_code' => sVal($sender['from_ward_code'] ?? ''),
        'to_name' => sVal($receiver['name'] ?? ''),
        'to_phone' => sVal($receiver['phone'] ?? ''),
        'to_address' => sVal($receiver['address'] ?? ''),
        'to_district_id' => iVal($receiver['to_district_id'] ?? 0),
        // Đảm bảo lấy đúng mã phường/xã từ payload.receiver.to_ward_code
        'to_ward_code' => sVal($receiver['to_ward_code'] ?? ''),
        'client_order_code' => sVal($orderInfo['client_order_code'] ?? ''),
        'cod_amount' => iVal($orderInfo['cod_amount'] ?? 0),
        // IMPORTANT: keep consistent with checkout (do not auto-insure by goods_value unless configured)
        'insurance_value' => ghn_insurance_value($cfg, $orderInfo),
        'weight' => iVal($orderInfo['weight'] ?? 0),
        'length' => iVal($orderInfo['length'] ?? 0),
        'width' => iVal($orderInfo['width'] ?? 0),
        'height' => iVal($orderInfo['height'] ?? 0),
        'service_id' => iVal($service['service_id'] ?? 0),
        'service_type_id' => iVal($service['service_type_id'] ?? 0),
        'pickup_type' => sVal($service['pickup_type'] ?? 'pickup'),
        'pick_shift' => $pickShiftVal,
        'station_id' => iVal($service['station_id'] ?? 0),
        'coupon' => sVal($service['coupon'] ?? ''),
        'content' => sVal($orderInfo['content'] ?? ''),
        'items' => [],
    ];

    foreach ($products as $p) {
        if (!is_array($p)) continue;
        $pName = sVal($p['name'] ?? '');
        $vName = sVal($p['variant_name'] ?? $p['variant'] ?? '');
        if ($vName !== '') {
            $pName .= ' (' . $vName . ')';
        }
        $ghnPayload['items'][] = [
            'name' => $pName,
            'code' => sVal($p['sku'] ?? ''),
            'quantity' => max(1, iVal($p['quantity'] ?? 1)),
            'price' => iVal($p['price'] ?? 0),
            'weight' => max(1, iVal($p['weight'] ?? 0)),
            'length' => max(1, iVal($p['length'] ?? 0)),
            'width' => max(1, iVal($p['width'] ?? 0)),
            'height' => max(1, iVal($p['height'] ?? 0)),
        ];
    }

    $endpoint = $action === 'order_preview' ? '/v2/shipping-order/preview' : '/v2/shipping-order/create';

    // Anti-duplicate: chỉ áp dụng cho create (preview thì không cần)
    $lockName = '';
    if ($action === 'order_create') {
        $systemOrderIdEarly = trim((string)($orderInfo['system_order_id'] ?? ''));
        if ($systemOrderIdEarly !== '') {
            // 1) Lấy MySQL named lock để chặn 2 request song song cùng system_order_id
            $lockName = 'ghn_create_' . substr(md5($systemOrderIdEarly), 0, 24);
            $lockStmt = $ithanhloc->prepare('SELECT GET_LOCK(?, 10)');
            if ($lockStmt) {
                $lockStmt->bind_param('s', $lockName);
                $lockStmt->execute();
                $lockStmt->bind_result($lockOk);
                $lockStmt->fetch();
                $lockStmt->close();
                if ((int)$lockOk !== 1) {
                    jOut(['ok' => false, 'msg' => 'Đơn này đang được xử lý ở phiên khác, vui lòng thử lại sau ít giây.']);
                }
            }
            // 2) Check tồn tại vận đơn còn hiệu lực (chưa cancel)
            $dupStmt = $ithanhloc->prepare("SELECT order_code, status FROM ghn_order WHERE system_order_id=? AND LOWER(COALESCE(status,'')) NOT IN ('cancel','canceled','cancelled') ORDER BY id DESC LIMIT 1");
            if ($dupStmt) {
                $dupStmt->bind_param('s', $systemOrderIdEarly);
                $dupStmt->execute();
                $dupRow = $dupStmt->get_result()->fetch_assoc();
                $dupStmt->close();
                if (is_array($dupRow) && !empty($dupRow['order_code'])) {
                    if ($lockName !== '') {
                        $rel = $ithanhloc->prepare('SELECT RELEASE_LOCK(?)');
                        if ($rel) { $rel->bind_param('s', $lockName); $rel->execute(); $rel->close(); }
                    }
                    jOut([
                        'ok' => false,
                        'msg' => 'Đơn hàng đã có vận đơn GHN: ' . $dupRow['order_code'] . ' (trạng thái: ' . ($dupRow['status'] ?: 'không xác định') . '). Hủy vận đơn cũ trước khi tạo mới.',
                        'existing_order_code' => $dupRow['order_code'],
                        'existing_status' => $dupRow['status'],
                    ]);
                }
            }
        }
    }

    $res = ghn_request($cfg, 'POST', $endpoint, $ghnPayload, true);
    if (empty($res['ok'])) {
        if ($lockName !== '') {
            $rel = $ithanhloc->prepare('SELECT RELEASE_LOCK(?)');
            if ($rel) { $rel->bind_param('s', $lockName); $rel->execute(); $rel->close(); }
        }
        jOut(['ok' => false, 'msg' => $res['message'] ?? 'GHN API lỗi', 'raw' => $res['raw'] ?? null]);
    }

    if ($action === 'order_create') {
        $orderCode = (string)($res['data']['order_code'] ?? '');

        $systemOrderId = (string)($orderInfo['system_order_id'] ?? '');
        $paymentMethodRaw = (string)($orderInfo['payment_method'] ?? '');
        if ($paymentMethodRaw === '' && $systemOrderId !== '') {
            $stmtPm = $ithanhloc->prepare('SELECT payment_method, payment_type, payment_gateway FROM ecommerce_order WHERE order_id=? LIMIT 1');
            if ($stmtPm) {
                $stmtPm->bind_param('s', $systemOrderId);
                $stmtPm->execute();
                $rowPm = $stmtPm->get_result()->fetch_assoc();
                $stmtPm->close();
                if (is_array($rowPm)) {
                    $paymentMethodRaw = (string)($rowPm['payment_method'] ?? $rowPm['payment_type'] ?? $rowPm['payment_gateway'] ?? '');
                }
            }
        }
        $paymentMethod = ghn_normalize_payment_method($paymentMethodRaw, (float)($orderInfo['cod_amount'] ?? 0));

        $orderId = ghn_order_insert($ithanhloc, [
            'system_order_id' => (string)($orderInfo['system_order_id'] ?? ''),
            'order_code' => $orderCode,
            'status' => (string)($res['data']['status'] ?? 'create'),
            'status_text' => (string)($res['data']['status'] ?? ''),
            'shipping_fee' => (float)($res['data']['total_fee'] ?? 0),
            'cod_amount' => (float)($orderInfo['cod_amount'] ?? 0),
            'goods_value' => (float)($orderInfo['goods_value'] ?? 0),
            'content' => (string)($orderInfo['content'] ?? ''),
            'weight' => (int)($orderInfo['weight'] ?? 0),
            'length' => (int)($orderInfo['length'] ?? 0),
            'width' => (int)($orderInfo['width'] ?? 0),
            'height' => (int)($orderInfo['height'] ?? 0),
            'from_name' => (string)($sender['name'] ?? ''),
            'from_phone' => (string)($sender['phone'] ?? ''),
            'from_address' => (string)($sender['address'] ?? ''),
            'from_district_id' => (int)($sender['from_district_id'] ?? 0),
            'from_ward_code' => (string)($sender['from_ward_code'] ?? ''),
            'to_name' => (string)($receiver['name'] ?? ''),
            'to_phone' => (string)($receiver['phone'] ?? ''),
            'to_address' => (string)($receiver['address'] ?? ''),
            'to_province_id' => (int)($receiver['to_province_id'] ?? 0),
            'to_district_id' => (int)($receiver['to_district_id'] ?? 0),
            'to_ward_code' => (string)($receiver['to_ward_code'] ?? ''),
            'pickup_type' => (string)($service['pickup_type'] ?? 'pickup'),
            'pick_shift' => $service['pick_shift'] ?? [],
            'station_id' => (int)($service['station_id'] ?? 0),
            'service_type_id' => (int)($service['service_type_id'] ?? 0),
            'payment_type_id' => (int)($service['payment_type_id'] ?? 0),
            'payment_method' => $paymentMethod,
            'coupon' => (string)($service['coupon'] ?? ''),
            'required_note' => (string)($delivery['required_note'] ?? ''),
            'note' => (string)($delivery['note'] ?? ''),
            'payload' => $ghnPayload,
            'response' => $res,
            'created_by' => (int)($_SESSION['user_id'] ?? 0),
        ], $products);

        if ($lockName !== '') {
            $rel = $ithanhloc->prepare('SELECT RELEASE_LOCK(?)');
            if ($rel) { $rel->bind_param('s', $lockName); $rel->execute(); $rel->close(); }
        }

        // CẢNH BÁO: nếu DB insert fail mà GHN đã tạo đơn, vận đơn vẫn tồn tại trên GHN
        // Cần admin vào GHN hủy thủ công hoặc gọi sync sau. Đánh dấu trong response để UI cảnh báo.
        jOut([
            'ok' => $orderId > 0,
            'msg' => $orderId > 0
                ? 'Đã tạo đơn GHN'
                : 'GHN đã tạo vận đơn (' . $orderCode . ') nhưng lưu DB thất bại. Vui lòng kiểm tra và hủy thủ công trên GHN nếu cần.',
            'order_id' => $orderId,
            'order_code' => $orderCode,
            'ghn_created_but_db_failed' => $orderId === 0 && $orderCode !== '',
            'raw' => $res['raw'] ?? null,
        ]);
    }

    jOut([
        'ok' => true,
        'preview' => $res,
    ]);
}

if ($action === 'region_sync_all') {
    @set_time_limit(0);
    @ini_set('max_execution_time', '0');

    if (empty($cfg['token'])) {
        jOut(['ok' => false, 'msg' => 'Thiếu token GHN để đồng bộ vùng miền']);
    }

    // Import nặng (≈63 tỉnh, ~700 quận, ~12.000 phường ⇒ hàng nghìn request GHN) nên
    // chạy theo từng BƯỚC (chunk) để không vượt timeout. Frontend gọi lặp tới khi done=true.
    //   step=provinces : sync 63 tỉnh, trả danh sách province_id → đưa vào hàng đợi quận.
    //   step=districts : sync quận của 1 tỉnh (mỗi lần 1 tỉnh), trả district_id → hàng đợi phường.
    //   step=wards     : sync phường theo BATCH district_id (vài quận mỗi lần).
    $step = sVal($input['step'] ?? 'provinces');

    if ($step === 'provinces') {
        $n = ghn_force_provinces($ithanhloc, $cfg);
        if ($n < 0) {
            jOut(['ok' => false, 'msg' => 'Không lấy được danh sách tỉnh/thành từ GHN']);
        }
        // Lấy danh sách province_id để frontend lần lượt sync quận.
        $ids = [];
        $r = $ithanhloc->query("SELECT region_id FROM ghn_region WHERE level='province' ORDER BY region_id ASC");
        while ($r && ($row = $r->fetch_assoc())) $ids[] = (int)$row['region_id'];
        jOut([
            'ok' => true,
            'done' => false,
            'next' => 'districts',
            'counts' => ['provinces' => $n],
            'queue' => $ids,            // các province_id cần sync quận
        ]);
    }

    if ($step === 'districts') {
        $provinceId = (int)($input['province_id'] ?? 0);
        if ($provinceId <= 0) {
            jOut(['ok' => false, 'msg' => 'Thiếu province_id']);
        }
        $n = ghn_force_districts($ithanhloc, $cfg, $provinceId);
        if ($n < 0) {
            jOut(['ok' => false, 'msg' => 'Không lấy được quận/huyện của tỉnh ' . $provinceId]);
        }
        // Trả district_id của tỉnh này để xếp vào hàng đợi phường.
        $ids = [];
        $stmt = $ithanhloc->prepare("SELECT region_id FROM ghn_region WHERE level='district' AND parent_id=? ORDER BY region_id ASC");
        $stmt->bind_param('i', $provinceId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) $ids[] = (int)$row['region_id'];
        $stmt->close();
        jOut([
            'ok' => true,
            'done' => false,
            'counts' => ['districts' => $n],
            'province_id' => $provinceId,
            'queue' => $ids,            // các district_id cần sync phường
        ]);
    }

    if ($step === 'wards') {
        // Nhận 1 batch district_id (frontend gửi mảng) để giảm số round-trip.
        $districtIds = $input['district_ids'] ?? [];
        if (is_string($districtIds)) $districtIds = json_decode($districtIds, true);
        if (!is_array($districtIds) || !$districtIds) {
            jOut(['ok' => false, 'msg' => 'Thiếu district_ids']);
        }
        $total = 0; $failed = [];
        foreach ($districtIds as $did) {
            $did = (int)$did;
            if ($did <= 0) continue;
            $n = ghn_force_wards($ithanhloc, $cfg, $did);
            if ($n < 0) { $failed[] = $did; continue; }
            $total += $n;
        }
        jOut([
            'ok' => true,
            'done' => false,
            'counts' => ['wards' => $total],
            'failed' => $failed,
        ]);
    }

    jOut(['ok' => false, 'msg' => 'Bước import không hợp lệ: ' . $step]);
}

if ($action === 'order_print_token') {
    if (!ghn_ready($cfg)) {
        jOut(['ok' => false, 'msg' => 'Chưa cấu hình API/Shop GHN']);
    }

    $orderCode = sVal($input['order_code'] ?? '');
    if ($orderCode === '') {
        jOut(['ok' => false, 'msg' => 'Thiếu mã đơn GHN']);
    }

    $payload = [
        'order_codes' => [$orderCode],
    ];

    $res = ghn_request($cfg, 'POST', '/v2/a5/gen-token', $payload, true);
    if (empty($res['ok'])) {
        jOut([
            'ok' => false,
            'msg' => $res['message'] ?? 'Không lấy được token in đơn từ GHN',
            'raw' => $res['raw'] ?? null,
        ]);
    }

    $token = (string)($res['data']['token'] ?? '');
    if ($token === '') {
        jOut([
            'ok' => false,
            'msg' => 'GHN không trả về token in đơn',
            'raw' => $res['raw'] ?? null,
        ]);
    }

    $env = strtolower((string)($cfg['env'] ?? 'test'));
    $host = $env === 'prod'
        ? 'https://online-gateway.ghn.vn'
        : 'https://dev-online-gateway.ghn.vn';

    $printUrls = [
        'a5' => $host . '/a5/public-api/printA5?token=' . urlencode($token),
        '80x80' => $host . '/a5/public-api/print80x80?token=' . urlencode($token),
        '52x70' => $host . '/a5/public-api/print52x70?token=' . urlencode($token),
    ];

    jOut([
        'ok' => true,
        'msg' => 'Đã tạo link in đơn GHN',
        'print_urls' => $printUrls,
        'token' => $token,
    ]);
}

if ($action === 'order_leadtime') {
    if (!ghn_ready($cfg)) {
        jOut(['ok' => false, 'msg' => 'Chưa cấu hình API/Shop GHN']);
    }
    
    // Sửa lỗi hiển thị trong câu báo lỗi địa chỉ
    if (empty($input['to_district_id']) || empty($input['to_ward_code'])) {
        jOut(['ok' => false, 'msg' => 'Thiếu quận/huyện/phường/xã để tính thời gian giao hàng (leadtime)']);
    }

    $res = ghn_request($cfg, 'POST', '/v2/shipping-order/leadtime', $input, true);
    jOut([
        'ok' => !empty($res['ok']),
        'msg' => !empty($res['ok']) ? 'Tính leadtime GHN thành công' : 'Không tính được leadtime GHN',
        'leadtime' => $res['data'] ?? null,
    ]);
}

if ($action === 'order_info') {
    if (!ghn_ready($cfg)) {
        jOut(['ok' => false, 'msg' => 'Chưa cấu hình API/Shop GHN']);
    }

    $orderCode = sVal($input['order_code'] ?? '');
    if ($orderCode === '') {
        jOut(['ok' => false, 'msg' => 'Thiếu mã đơn GHN']);
    }

    $payload = [
        'order_code' => $orderCode,
    ];

    $res = ghn_request($cfg, 'POST', '/v2/shipping-order/detail', $payload, true);
    if (empty($res['ok'])) {
        jOut([
            'ok' => false,
            'msg' => $res['message'] ?? 'Không lấy được chi tiết đơn GHN',
            'raw' => $res['raw'] ?? null,
        ]);
    }

    jOut([
        'ok' => true,
        'msg' => $res['message'] ?? 'Đã tải chi tiết đơn GHN',
        'data' => $res['data'] ?? null,
    ]);
}

if ($action === 'order_sync_status') {
    // Query GHN /detail để lấy status mới nhất rồi cập nhật ghn_order + ecommerce_order.
    // Hỗ trợ truyền order_code (mã GHN) HOẶC system_order_id (mã đơn hệ thống).
    if (!ghn_ready($cfg)) {
        jOut(['ok' => false, 'msg' => 'Chưa cấu hình API/Shop GHN']);
    }

    $orderCode = sVal($input['order_code'] ?? '');
    $systemOrderId = sVal($input['system_order_id'] ?? '');

    // Nếu chỉ có system_order_id → tra order_code từ ghn_order
    if ($orderCode === '' && $systemOrderId !== '') {
        $stmt = $ithanhloc->prepare("SELECT order_code FROM ghn_order WHERE system_order_id=? ORDER BY id DESC LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $systemOrderId);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $orderCode = trim((string)($row['order_code'] ?? ''));
        }
    }
    if ($orderCode === '') {
        jOut(['ok' => false, 'msg' => 'Đơn này chưa có vận đơn GHN để đồng bộ']);
    }

    $res = ghn_request($cfg, 'POST', '/v2/shipping-order/detail', ['order_code' => $orderCode], true);
    if (empty($res['ok'])) {
        jOut(['ok' => false, 'msg' => $res['message'] ?? 'Không lấy được chi tiết đơn GHN', 'raw' => $res['raw'] ?? null]);
    }

    $data = is_array($res['data'] ?? null) ? $res['data'] : [];
    $ghnStatus = sVal($data['status'] ?? '');
    if ($ghnStatus === '') {
        jOut(['ok' => false, 'msg' => 'GHN không trả về trạng thái']);
    }

    // Cập nhật bảng ghn_order + log
    if (function_exists('ghn_order_update_status')) {
        ghn_order_update_status($ithanhloc, $orderCode, $ghnStatus, $ghnStatus, $data);
    }
    // Đồng bộ ecommerce_order (đã có guard rails: không đè cancel_requested/return_requested)
    if ($systemOrderId === '') {
        $stmt = $ithanhloc->prepare("SELECT system_order_id FROM ghn_order WHERE order_code=? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param('s', $orderCode);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $systemOrderId = trim((string)($row['system_order_id'] ?? ''));
        }
    }
    if ($systemOrderId !== '' && function_exists('ghn_sync_ecommerce_order_status')) {
        ghn_sync_ecommerce_order_status($ithanhloc, $systemOrderId, $ghnStatus, $orderCode, $ghnStatus);
    }

    jOut([
        'ok' => true,
        'msg' => 'Đã đồng bộ trạng thái GHN: ' . $ghnStatus,
        'ghn_status' => $ghnStatus,
        'order_code' => $orderCode,
    ]);
}

if ($action === 'order_cancel' || $action === 'order_return' || $action === 'order_delivery_again') {
    if (!ghn_ready($cfg)) {
        jOut(['ok' => false, 'msg' => 'Chưa cấu hình API/Shop GHN']);
    }

    $orderCode = sVal($input['order_code'] ?? '');
    if ($orderCode === '') {
        jOut(['ok' => false, 'msg' => 'Thiếu mã đơn GHN']);
    }

    $payload = [
        'order_codes' => [$orderCode],
    ];

    $path = '';
    if ($action === 'order_cancel') {
        $path = '/v2/switch-status/cancel';
    } elseif ($action === 'order_return') {
        $path = '/v2/switch-status/return';
    } else { // order_delivery_again
        $path = '/v2/switch-status/storing';
    }

    $res = ghn_request($cfg, 'POST', $path, $payload, true);
    if (empty($res['ok'])) {
        jOut([
            'ok' => false,
            'msg' => $res['message'] ?? 'GHN API lỗi',
            'raw' => $res['raw'] ?? null,
        ]);
    }

    // Cập nhật trạng thái vào bảng ghn_order và (nếu có) ecommerce_order
    $newStatus = '';
    $statusText = '';
    if ($action === 'create') {
        $newStatus = 'create';
        $statusText = 'Đã tạo đơn';
    } elseif ($action === 'order_cancel') {
        $newStatus = 'cancel';
        $statusText = 'Đã yêu cầu huỷ';
    } elseif ($action === 'order_return') {
        $newStatus = 'return';
        $statusText = 'Đã yêu cầu hoàn';
    } elseif ($action === 'order_delivery_again') {
        $newStatus = 'storing';
        $statusText = 'Đã yêu cầu giao lại';
    }  else {
        // Với các action khác (nếu có), tạm thời không cập nhật trạng thái mới
        $newStatus = '';
    }

    if ($newStatus !== '') {
        // Ghi vào bảng ghn_order + log
        if (function_exists('ghn_order_update_status')) {
            ghn_order_update_status($ithanhloc, $orderCode, $newStatus, $statusText, $res['raw'] ?? []);
        }

        // Thử đồng bộ sang ecommerce_order nếu có system_order_id
        if (function_exists('ghn_sync_ecommerce_order_status')) {
            $systemOrderId = '';
            $stmt = $ithanhloc->prepare('SELECT system_order_id FROM ghn_order WHERE order_code=? LIMIT 1');
            if ($stmt) {
                $stmt->bind_param('s', $orderCode);
                $stmt->execute();
                $row = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                if (is_array($row)) {
                    $systemOrderId = trim((string)($row['system_order_id'] ?? ''));
                }
            }
            if ($systemOrderId !== '') {
                ghn_sync_ecommerce_order_status($ithanhloc, $systemOrderId, $newStatus, $orderCode, $statusText);
            }
        }
    }

    jOut([
        'ok' => true,
        'msg' => $res['message'] ?? 'Thao tác GHN thành công',
        'data' => $res['data'] ?? null,
    ]);
}

if ($action === 'order_update_cod') {
    if (!ghn_ready($cfg)) {
        jOut(['ok' => false, 'msg' => 'Chưa cấu hình API/Shop GHN']);
    }

    $orderCode = sVal($input['order_code'] ?? '');
    $codAmount = iVal($input['cod_amount'] ?? 0, 0);
    if ($orderCode === '') {
        jOut(['ok' => false, 'msg' => 'Thiếu mã đơn GHN']);
    }

    $payload = [
        'order_code' => $orderCode,
        'cod_amount' => $codAmount,
    ];

    $res = ghn_request($cfg, 'POST', '/v2/shipping-order/updateCOD', $payload, true);
    if (empty($res['ok'])) {
        jOut([
            'ok' => false,
            'msg' => $res['message'] ?? 'Cập nhật COD GHN thất bại',
            'raw' => $res['raw'] ?? null,
        ]);
    }

    jOut([
        'ok' => true,
        'msg' => $res['message'] ?? 'Đã cập nhật COD GHN',
    ]);
}

if ($action === 'order_update') {
    if (!ghn_ready($cfg)) {
        jOut(['ok' => false, 'msg' => 'Chưa cấu hình API/Shop GHN']);
    }

    $payload = is_array($input['payload'] ?? null) ? ($input['payload'] ?? []) : jArr($input['payload'] ?? '');
    $orderCode = sVal($payload['order_code'] ?? '');
    if ($orderCode === '') {
        jOut(['ok' => false, 'msg' => 'Thiếu mã đơn GHN trong payload']);
    }

    $res = ghn_request($cfg, 'POST', '/v2/shipping-order/update', $payload, true);
    if (empty($res['ok'])) {
        jOut([
            'ok' => false,
            'msg' => $res['message'] ?? 'Cập nhật đơn GHN thất bại',
            'raw' => $res['raw'] ?? null,
        ]);
    }

    jOut([
        'ok' => true,
        'msg' => $res['message'] ?? 'Đã cập nhật đơn GHN',
    ]);
}

if ($action === 'order_delete_local') {
    $orderCode = sVal($input['order_code'] ?? '');
    if ($orderCode === '') {
        jOut(['ok' => false, 'msg' => 'Thiếu mã đơn GHN']);
    }

    // Lấy id đơn GHN để xoá items
    $orderId = 0;
    $stmt = $ithanhloc->prepare('SELECT id FROM ghn_order WHERE order_code=? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $orderCode);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (is_array($row)) {
            $orderId = (int)($row['id'] ?? 0);
        }
    }

    if ($orderId <= 0) {
        jOut(['ok' => false, 'msg' => 'Không tìm thấy đơn GHN để xoá']);
    }

    // Xoá items
    $stmtDelItems = $ithanhloc->prepare('DELETE FROM ghn_order_item WHERE ghn_order_id=?');
    if ($stmtDelItems) {
        $stmtDelItems->bind_param('i', $orderId);
        $stmtDelItems->execute();
        $stmtDelItems->close();
    }

    // Xoá log
    $stmtDelLog = $ithanhloc->prepare('DELETE FROM ghn_order_log WHERE order_code=?');
    if ($stmtDelLog) {
        $stmtDelLog->bind_param('s', $orderCode);
        $stmtDelLog->execute();
        $stmtDelLog->close();
    }

    // Xoá bản ghi chính
    $stmtDelOrder = $ithanhloc->prepare('DELETE FROM ghn_order WHERE id=?');
    if ($stmtDelOrder) {
        $stmtDelOrder->bind_param('i', $orderId);
        $stmtDelOrder->execute();
        $stmtDelOrder->close();
    }

    jOut([
        'ok' => true,
        'msg' => 'Đã xoá đơn GHN khỏi hệ thống',
    ]);
}

if ($action === 'order_create') {
    // Sửa lỗi hiển thị thông tin thiếu
    if (empty($receiver['address'])) {
         jOut(['ok' => false, 'msg' => 'Thiếu thông tin người nhận (tên/sđt/địa chỉ)']);
    }
    // Logic tạo đơn tiếp tục...
}

jOut(['ok' => false, 'msg' => 'Hành động không hợp lệ']);