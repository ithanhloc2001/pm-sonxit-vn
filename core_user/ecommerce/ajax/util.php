<?php require_once __DIR__ . '/../../../config.php'; ?>
<?php 
// Gợi ý địa chỉ bằng AI (ưu tiên Google Gemini nếu có API key)
function aiSuggestAddress(string $input): array {
    global $API_GEMINI_KEY;
    $query = trim($input);
    if ($query === '') {
        return ['ok' => false, 'msg' => 'Thiếu địa chỉ cần gợi ý'];
    }
    $apiKey = trim((string)($API_GEMINI_KEY ?? ''));
    // Fallback khi chưa cấu hình Gemini: trả về gợi ý đơn giản dùng chính input
    if ($apiKey === '') {
        return [
            'ok' => true,
            'ai_used' => false,
            'data' => [
                [
                    'full' => $query,
                    'street' => $query,
                    'ward' => '',
                    'district' => '',
                    'province' => '',
                ],
            ],
        ];
    }

    $prompt = "Bạn là trợ lý chuẩn hoá địa chỉ giao hàng tại Việt Nam.\n"
        . "Nhiệm vụ: nhận địa chỉ người dùng nhập (có thể thiếu thông tin) và chuẩn hoá lại.\n"
        . "Chỉ trả về DUY NHẤT JSON, không thêm giải thích.\n"
        . "Cấu trúc JSON mong muốn:\n"
        . "{\n  \"suggestions\": [\n    {\n      \"full\": \"địa chỉ đầy đủ, có số nhà + đường + phường/xã + quận/huyện + tỉnh/thành phố\",\n      \"street\": \"chỉ phần số nhà + tên đường\",\n      \"ward\": \"phường/xã\",\n      \"district\": \"quận/huyện\",\n      \"province\": \"tỉnh/thành phố\"\n    }\n  ]\n}\n";

    $body = [
        'contents' => [
            [
                'role' => 'user',
                'parts' => [
                    ['text' => $prompt . "\n\nĐịa chỉ người dùng nhập: " . $query],
                ],
            ],
        ],
    ];

    $ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=' . urlencode($apiKey));
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json; charset=utf-8',
        ],
        CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 18,
    ]);
    $raw = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    // Nếu lỗi mạng hoặc HTTP không thành công, fallback sang gợi ý đơn giản
    if ($err || !$raw || $httpCode < 200 || $httpCode >= 300) {
        return [
            'ok' => true,
            'ai_used' => false,
            'msg' => 'Gemini không phản hồi, dùng gợi ý thường',
            'data' => [
                [
                    'full' => $query,
                    'street' => $query,
                    'ward' => '',
                    'district' => '',
                    'province' => '',
                ],
            ],
        ];
    }

    $data = json_decode($raw, true);
    $text = '';
    if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
        $text = trim((string)$data['candidates'][0]['content']['parts'][0]['text']);
    }

    if ($text === '') {
        return [
            'ok' => true,
            'ai_used' => false,
            'data' => [
                [
                    'full' => $query,
                    'street' => $query,
                    'ward' => '',
                    'district' => '',
                    'province' => '',
                ],
            ],
        ];
    }

    // Cố gắng parse JSON từ nội dung trả về
    $parsed = json_decode($text, true);
    if (!is_array($parsed)) {
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $parsed = json_decode($m[0], true);
        }
    }

    $suggestions = [];
    if (is_array($parsed)) {
        if (isset($parsed['suggestions']) && is_array($parsed['suggestions'])) {
            $suggestions = $parsed['suggestions'];
        } elseif (isset($parsed[0]) && is_array($parsed[0])) {
            $suggestions = $parsed;
        }
    }

    $out = [];
    if (is_array($suggestions) && count($suggestions)) {
        foreach ($suggestions as $item) {
            if (!is_array($item)) continue;
            $full = trim((string)($item['full'] ?? $item['address'] ?? $query));
            if ($full === '') $full = $query;
            $out[] = [
                'full' => $full,
                'street' => trim((string)($item['street'] ?? '')),
                'ward' => trim((string)($item['ward'] ?? '')),
                'district' => trim((string)($item['district'] ?? '')),
                'province' => trim((string)($item['province'] ?? '')),
            ];
        }
    }

    if (empty($out)) {
        $out[] = [
            'full' => $query,
            'street' => $query,
            'ward' => '',
            'district' => '',
            'province' => '',
        ];
    }

    return [
        'ok' => true,
        'ai_used' => true,
        'data' => $out,
    ];
}






