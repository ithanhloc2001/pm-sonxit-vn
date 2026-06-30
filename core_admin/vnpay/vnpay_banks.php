<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$role = $_SESSION['role'] ?? null;
if (!isset($_SESSION['user_id']) || !in_array($role, ['user', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Forbidden']);
    exit;
}

require_once dirname(__DIR__, 2) . '/config.php';

$tmnCode = $ECOMMERCE_PAYMENT_METHODS['vnpay']['tmnCode'] ?? '';
if ($tmnCode === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => 'Missing VNPAY tmnCode in config.php']);
    exit;
}

$url = 'https://sandbox.vnpayment.vn/qrpayauth/api/merchant/get_bank_list';
$response = null;
$error = null;
$usedInsecureSsl = false;
$contentType = null;

if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query(['tmn_code' => $tmnCode]),
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'User-Agent: Mozilla/5.0',
        ],
    ]);
    $response = curl_exec($ch);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    if ($response === false) {
        $error = curl_error($ch);
        if (stripos($error, 'SSL') !== false) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            $response = curl_exec($ch);
            $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            if ($response !== false) {
                $usedInsecureSsl = true;
                $error = null;
            } else {
                $error = curl_error($ch);
            }
        }
    }
    curl_close($ch);
} else {
    $context = stream_context_create([
        'http' => [
            'method' => 'POST',
            'timeout' => 15,
            'header' => "Accept: application/json\r\nContent-Type: application/x-www-form-urlencoded\r\nUser-Agent: Mozilla/5.0\r\n",
            'content' => http_build_query(['tmn_code' => $tmnCode]),
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        $error = 'Unable to fetch VNPAY bank list.';
    }
}

if (!$response) {
    http_response_code(502);
    echo json_encode(['ok' => false, 'message' => 'VNPAY request failed', 'detail' => $error]);
    exit;
}

$contentType = $contentType ?: '';
if (stripos($contentType, 'text/html') !== false) {
    http_response_code(502);
    $excerpt = mb_substr($response, 0, 200, 'UTF-8');
    echo json_encode([
        'ok' => false,
        'message' => 'VNPAY returned HTML instead of JSON',
        'excerpt' => $excerpt,
    ]);
    exit;
}

$raw = trim($response);
$raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw);
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    $startObj = strpos($raw, '{');
    $endObj = strrpos($raw, '}');
    if ($startObj !== false && $endObj !== false && $endObj > $startObj) {
        $candidate = substr($raw, $startObj, $endObj - $startObj + 1);
        $payload = json_decode($candidate, true);
    }
}
if (!is_array($payload)) {
    http_response_code(502);
    $excerpt = mb_substr($raw, 0, 300, 'UTF-8');
    echo json_encode([
        'ok' => false,
        'message' => 'Invalid VNPAY response',
        'excerpt' => $excerpt,
    ]);
    exit;
}

$banks = $payload['data'] ?? $payload['banks'] ?? $payload['listBank'] ?? $payload;
if (!is_array($banks)) {
    $banks = [];
}

$normalized = [];
foreach ($banks as $item) {
    if (!is_array($item)) {
        continue;
    }
    $normalized[] = [
        'bank_code' => $item['bank_code'] ?? $item['code'] ?? $item['bankCode'] ?? '',
        'bank_name' => $item['bank_name'] ?? $item['name'] ?? $item['bankName'] ?? '',
        'short_name' => $item['short_name'] ?? $item['shortName'] ?? '',
        'logo' => $item['logo'] ?? $item['logo_url'] ?? '',
        'type' => $item['type'] ?? $item['bank_type'] ?? '',
        'raw' => $item,
    ];
}

echo json_encode([
    'ok' => true,
    'count' => count($normalized),
    'insecure_ssl' => $usedInsecureSsl,
    'data' => $normalized,
]);
