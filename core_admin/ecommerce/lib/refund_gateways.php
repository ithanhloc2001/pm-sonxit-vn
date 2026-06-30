<?php
/**
 * Refund gateways — MoMo & ZaloPay.
 *
 * Bảng `ecommerce_refund_tx` ghi nhận mọi giao dịch hoàn tiền tự động:
 * - 1 đơn có thể có nhiều record (vd: lần 1 fail, lần 2 retry)
 * - Idempotency qua `refund_request_id` UNIQUE
 *
 * Mỗi hàm gateway trả về mảng chuẩn:
 *   ['ok' => bool, 'status' => 'success|pending|failed', 'msg' => string,
 *    'gateway_refund_id' => string, 'request_payload' => array, 'response_payload' => array]
 */

if (!function_exists('refund_ensure_table')) {
    function refund_ensure_table(mysqli $db): void {
        $db->query("CREATE TABLE IF NOT EXISTS ecommerce_refund_tx (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id VARCHAR(50) NOT NULL,
            gateway VARCHAR(20) NOT NULL,
            amount DECIMAL(15,2) NOT NULL,
            refund_request_id VARCHAR(100) NOT NULL,
            gateway_refund_id VARCHAR(100) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            request_payload LONGTEXT NULL,
            response_payload LONGTEXT NULL,
            initiated_by INT DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            completed_at DATETIME DEFAULT NULL,
            UNIQUE KEY uniq_refund_request (refund_request_id),
            KEY idx_order (order_id),
            KEY idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

if (!function_exists('refund_http_post_json')) {
    function refund_http_post_json(string $url, array $payload, int $timeout = 25): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false) return ['ok' => false, 'msg' => $err ?: 'Network error', 'http_code' => $httpCode];
        $json = json_decode((string)$raw, true);
        return ['ok' => is_array($json), 'http_code' => $httpCode, 'data' => $json, 'raw' => $raw];
    }
}

if (!function_exists('refund_http_post_form')) {
    function refund_http_post_form(string $url, array $payload, int $timeout = 25): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($payload),
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT => $timeout,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($raw === false) return ['ok' => false, 'msg' => $err ?: 'Network error', 'http_code' => $httpCode];
        $json = json_decode((string)$raw, true);
        return ['ok' => is_array($json), 'http_code' => $httpCode, 'data' => $json, 'raw' => $raw];
    }
}

/**
 * MoMo Refund — POST /v2/gateway/api/refund
 * Yêu cầu: transId gốc (lưu trong payment_meta_json.raw.transId)
 */
if (!function_exists('momo_refund')) {
    function momo_refund(array $order, int $amount, string $requestId): array {
        $cfg = app_get_momo_config_by_env();
        $partnerCode = (string)$cfg['partnerCode'];
        $accessKey = (string)$cfg['accessKey'];
        $secretKey = (string)$cfg['secretKey'];
        if ($partnerCode === '' || $accessKey === '' || $secretKey === '') {
            return ['ok' => false, 'status' => 'failed', 'msg' => 'Thiếu cấu hình MoMo'];
        }

        // Lấy transId gốc từ payment_meta_json — thử nhiều path vì lưu ở các nơi khác nhau tuỳ flow:
        //  - meta.transId / meta.raw.transId: khi callback redirect xử lý
        //  - meta.last_raw.transId: khi sync_payment query MoMo
        $meta = json_decode((string)($order['payment_meta_json'] ?? ''), true);
        if (!is_array($meta)) $meta = [];
        $raw = is_array($meta['raw'] ?? null) ? $meta['raw'] : [];
        $lastRaw = is_array($meta['last_raw'] ?? null) ? $meta['last_raw'] : [];
        $origTransId = 0;
        foreach ([$meta['transId'] ?? null, $raw['transId'] ?? null, $lastRaw['transId'] ?? null,
                  $meta['trans_id'] ?? null, $raw['trans_id'] ?? null] as $v) {
            $candidate = (int)$v;
            if ($candidate > 0) { $origTransId = $candidate; break; }
        }
        // Fallback: lấy từ cột bank_tran_no (sync_payment lưu vào đây)
        if ($origTransId <= 0 && !empty($order['bank_tran_no'])) {
            $origTransId = (int)$order['bank_tran_no'];
        }
        if ($origTransId <= 0) {
            return ['ok' => false, 'status' => 'failed', 'msg' => 'Không tìm thấy transId MoMo gốc. Đơn cần được "Đồng bộ thanh toán" trước, hoặc dùng "Đã hoàn tiền tay".'];
        }

        $orderIdSys = (string)($order['order_id'] ?? '');
        $refundOrderId = 'RF' . $orderIdSys . '_' . time();
        $description = 'Hoan tien don ' . $orderIdSys;
        $lang = 'vi';

        // Chữ ký theo MoMo docs:
        // accessKey, amount, description, orderId, partnerCode, requestId, transId
        $rawHash = "accessKey={$accessKey}&amount={$amount}&description={$description}"
                 . "&orderId={$refundOrderId}&partnerCode={$partnerCode}"
                 . "&requestId={$requestId}&transId={$origTransId}";
        $signature = hash_hmac('sha256', $rawHash, $secretKey);

        $payload = [
            'partnerCode' => $partnerCode,
            'orderId' => $refundOrderId,
            'requestId' => $requestId,
            'amount' => $amount,
            'transId' => $origTransId,
            'lang' => $lang,
            'description' => $description,
            'signature' => $signature,
        ];

        // Endpoint refund — chung path /refund, base URL theo env (test/prod)
        $createUrl = (string)$cfg['createUrl'];
        $refundUrl = preg_replace('#/create$#', '/refund', $createUrl);
        if ($refundUrl === $createUrl) {
            // Fallback nếu createUrl không kết thúc bằng /create
            $base = ($cfg['env'] === 'test') ? 'https://test-payment.momo.vn' : 'https://payment.momo.vn';
            $refundUrl = $base . '/v2/gateway/api/refund';
        }

        $res = refund_http_post_json($refundUrl, $payload);
        $data = is_array($res['data'] ?? null) ? $res['data'] : [];
        $resultCode = (int)($data['resultCode'] ?? -1);
        $msg = (string)($data['message'] ?? 'Unknown');
        $gatewayRefundId = (string)($data['transId'] ?? '');

        $status = 'failed';
        $ok = false;
        if ($resultCode === 0) { $status = 'success'; $ok = true; }
        elseif (in_array($resultCode, [9000, 1000], true)) { $status = 'pending'; }

        return [
            'ok' => $ok,
            'status' => $status,
            'msg' => $msg . ' (code: ' . $resultCode . ')',
            'gateway_refund_id' => $gatewayRefundId,
            'request_payload' => $payload,
            'response_payload' => $data,
        ];
    }
}

/**
 * ZaloPay Refund — POST /v2/refund
 * Yêu cầu: zp_trans_id gốc (lưu trong payment_meta_json.zp_trans_id)
 */
if (!function_exists('zalopay_refund')) {
    function zalopay_refund(array $order, int $amount, string $mRefundId): array {
        $appId = (int)app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.app_id');
        $key1 = (string)app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.key1');
        $createUrl = (string)app_get_config_value_by_path('ECOMMERCE_PAYMENT_METHODS.zalopay.createUrl');
        if ($appId <= 0 || $key1 === '') {
            return ['ok' => false, 'status' => 'failed', 'msg' => 'Thiếu cấu hình ZaloPay'];
        }

        // Endpoint refund: dựa trên createUrl để suy ra sandbox/production
        $isSandbox = (stripos($createUrl, 'sb-openapi') !== false || stripos($createUrl, 'sandbox') !== false);
        $refundUrl = $isSandbox ? 'https://sb-openapi.zalopay.vn/v2/refund' : 'https://openapi.zalopay.vn/v2/refund';

        // Lấy zp_trans_id gốc từ payment_meta_json — thử nhiều path
        $meta = json_decode((string)($order['payment_meta_json'] ?? ''), true);
        if (!is_array($meta)) $meta = [];
        $rawMeta = is_array($meta['raw'] ?? null) ? $meta['raw'] : [];
        $lastRaw = is_array($meta['last_raw'] ?? null) ? $meta['last_raw'] : [];
        $zpTransId = '';
        foreach ([$meta['zp_trans_id'] ?? null, $rawMeta['zp_trans_id'] ?? null,
                  $lastRaw['zp_trans_id'] ?? null, $meta['zptransid'] ?? null,
                  $rawMeta['zptransid'] ?? null] as $v) {
            $t = trim((string)$v);
            if ($t !== '' && $t !== '0') { $zpTransId = $t; break; }
        }
        if ($zpTransId === '' && !empty($order['bank_tran_no'])) {
            $zpTransId = (string)$order['bank_tran_no'];
        }
        if ($zpTransId === '') {
            return ['ok' => false, 'status' => 'failed', 'msg' => 'Không tìm thấy zp_trans_id ZaloPay gốc. Đơn cần được callback ZaloPay xử lý đầy đủ trước, hoặc dùng "Đã hoàn tiền tay".'];
        }

        $timestamp = (int)round(microtime(true) * 1000);
        $description = 'Hoan tien don ' . (string)($order['order_id'] ?? '');

        // MAC: app_id|zp_trans_id|amount|description|timestamp
        $hashData = $appId . '|' . $zpTransId . '|' . $amount . '|' . $description . '|' . $timestamp;
        $mac = hash_hmac('sha256', $hashData, $key1);

        $payload = [
            'app_id' => $appId,
            'm_refund_id' => $mRefundId,
            'zp_trans_id' => $zpTransId,
            'amount' => $amount,
            'timestamp' => $timestamp,
            'description' => $description,
            'mac' => $mac,
        ];

        $res = refund_http_post_form($refundUrl, $payload);
        $data = is_array($res['data'] ?? null) ? $res['data'] : [];
        $returnCode = (int)($data['return_code'] ?? -1);
        $msg = (string)($data['return_message'] ?? 'Unknown');
        $gatewayRefundId = (string)($data['refund_id'] ?? '');

        $status = 'failed';
        $ok = false;
        if ($returnCode === 1) { $status = 'success'; $ok = true; }
        elseif ($returnCode === 3) { $status = 'pending'; }

        return [
            'ok' => $ok,
            'status' => $status,
            'msg' => $msg . ' (return_code: ' . $returnCode . ')',
            'gateway_refund_id' => $gatewayRefundId,
            'request_payload' => $payload,
            'response_payload' => $data,
        ];
    }
}

/**
 * Hàm tổng: gọi đúng gateway, ghi DB, cập nhật ecommerce_order khi success.
 * Tự chống double-refund qua MySQL named lock + check existing success record.
 */
if (!function_exists('refund_apply_to_order')) {
    function refund_apply_to_order(mysqli $db, string $orderId, int $actorId = 0): array {
        refund_ensure_table($db);

        // Lock chống double-refund
        $lockName = 'refund_apply_' . substr(md5($orderId), 0, 24);
        $stLock = $db->prepare('SELECT GET_LOCK(?, 15)');
        $stLock->bind_param('s', $lockName);
        $stLock->execute();
        $stLock->bind_result($lockOk);
        $stLock->fetch();
        $stLock->close();
        if ((int)$lockOk !== 1) {
            return ['ok' => false, 'msg' => 'Refund khác đang xử lý cho đơn này, vui lòng đợi'];
        }

        $release = function() use ($db, $lockName) {
            $st = $db->prepare('SELECT RELEASE_LOCK(?)');
            if ($st) { $st->bind_param('s', $lockName); $st->execute(); $st->close(); }
        };

        // Load order
        $stmt = $db->prepare('SELECT order_id, user_id, status, payment_status, payment_gateway, payment_method, total_amount, payment_meta_json, bank_tran_no FROM ecommerce_order WHERE order_id=? LIMIT 1');
        $stmt->bind_param('s', $orderId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if (!$order) { $release(); return ['ok' => false, 'msg' => 'Không tìm thấy đơn']; }

        $ps = strtolower(trim((string)($order['payment_status'] ?? '')));
        if ($ps !== 'refund_pending') {
            $release();
            return ['ok' => false, 'msg' => 'Đơn không ở trạng thái refund_pending (hiện tại: ' . ($ps ?: 'unknown') . ')'];
        }

        $gateway = strtolower(trim((string)($order['payment_gateway'] ?? $order['payment_method'] ?? '')));
        if (!in_array($gateway, ['momo', 'zalopay'], true)) {
            $release();
            return ['ok' => false, 'msg' => 'Gateway "' . $gateway . '" chưa hỗ trợ refund tự động (chỉ MoMo & ZaloPay). Dùng "Đã hoàn tiền tay".'];
        }

        // Check existing success record (idempotency cấp 2)
        $stChk = $db->prepare("SELECT id, status FROM ecommerce_refund_tx WHERE order_id=? AND status='success' LIMIT 1");
        $stChk->bind_param('s', $orderId);
        $stChk->execute();
        $existed = $stChk->get_result()->fetch_assoc();
        $stChk->close();
        if ($existed) {
            $release();
            return ['ok' => false, 'msg' => 'Đơn đã có giao dịch refund success trước đó (id #' . $existed['id'] . ')'];
        }

        $amount = (int)round((float)($order['total_amount'] ?? 0));
        if ($amount <= 0) { $release(); return ['ok' => false, 'msg' => 'Số tiền refund không hợp lệ']; }

        $requestId = 'RF' . $orderId . '_' . time() . '_' . mt_rand(1000, 9999);

        // Insert pending record TRƯỚC khi gọi gateway
        $stIns = $db->prepare("INSERT INTO ecommerce_refund_tx (order_id, gateway, amount, refund_request_id, status, initiated_by, created_at) VALUES (?,?,?,?, 'pending', ?, NOW())");
        $stIns->bind_param('ssdsi', $orderId, $gateway, $amount, $requestId, $actorId);
        $stIns->execute();
        $refundId = (int)$db->insert_id;
        $stIns->close();

        // Gọi gateway tương ứng
        $result = ($gateway === 'momo')
            ? momo_refund($order, $amount, $requestId)
            : zalopay_refund($order, $amount, $requestId);

        // Update record với response
        $reqJson = json_encode($result['request_payload'] ?? [], JSON_UNESCAPED_UNICODE);
        $resJson = json_encode($result['response_payload'] ?? [], JSON_UNESCAPED_UNICODE);
        $newStatus = $result['status'] ?? 'failed';
        $gwId = (string)($result['gateway_refund_id'] ?? '');
        $stUp = $db->prepare("UPDATE ecommerce_refund_tx SET status=?, gateway_refund_id=?, request_payload=?, response_payload=?, completed_at=IF(?='success', NOW(), NULL) WHERE id=?");
        $stUp->bind_param('sssssi', $newStatus, $gwId, $reqJson, $resJson, $newStatus, $refundId);
        $stUp->execute();
        $stUp->close();

        // Nếu success → cập nhật ecommerce_order
        if ($newStatus === 'success') {
            $ocols = listColumns($db, 'ecommerce_order');
            $setOrd = "payment_status='refunded'";
            if (isset($ocols['refunded_at'])) $setOrd .= ", refunded_at=COALESCE(refunded_at, NOW())";
            if (isset($ocols['updated_at'])) $setOrd .= ", updated_at=NOW()";
            $stOrd = $db->prepare("UPDATE ecommerce_order SET $setOrd WHERE order_id=? LIMIT 1");
            $stOrd->bind_param('s', $orderId);
            $stOrd->execute();
            $stOrd->close();

            if (function_exists('ecommerce_order_log_insert')) {
                $note = sprintf('Auto-refund qua %s thành công. Refund ID: %s. Số tiền: %s đ',
                    strtoupper($gateway), $gwId, number_format($amount, 0, ',', '.'));
                ecommerce_order_log_insert($db, $orderId, 'admin', $actorId,
                    'refund_completed', (string)$order['status'], (string)$order['status'], $note);
            }

            $userId = (int)($order['user_id'] ?? 0);
            if ($userId > 0 && function_exists('app_user_notify_template')) {
                app_user_notify_template($db, $userId, 'order_refunded', [
                    'order_id' => $orderId,
                    'amount' => number_format($amount, 0, '.', ''),
                    'gateway' => strtoupper($gateway),
                    'time' => date('Y-m-d H:i:s'),
                    'link' => '/view-order?order_id=' . urlencode($orderId),
                    'event' => 'order_refunded',
                ]);
            }
        } else {
            // Log fail/pending để admin biết
            if (function_exists('ecommerce_order_log_insert')) {
                $note = sprintf('Auto-refund qua %s: %s — %s', strtoupper($gateway), $newStatus, $result['msg'] ?? '');
                ecommerce_order_log_insert($db, $orderId, 'admin', $actorId,
                    'refund_attempt_' . $newStatus, (string)$order['status'], (string)$order['status'], $note);
            }
        }

        $release();

        return [
            'ok' => $newStatus === 'success',
            'status' => $newStatus,
            'msg' => $result['msg'] ?? '',
            'refund_id' => $refundId,
            'gateway_refund_id' => $gwId,
        ];
    }
}
