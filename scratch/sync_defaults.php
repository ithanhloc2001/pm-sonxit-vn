<?php
require_once __DIR__ . '/../config.php';

$map = app_editable_config_map();
$expected = count($map);
echo "Expected config keys count: $expected\n";

$keys     = array_keys($map);
$safeKeys = array_map([$ithanhloc, 'real_escape_string'], $keys);
$in       = "'" . implode("','", $safeKeys) . "'";
$existing = 0;
if ($res = $ithanhloc->query("SELECT COUNT(*) AS c FROM site_setting WHERE setting_key IN ({$in})")) {
    $row = $res->fetch_assoc();
    $existing = (int)($row['c'] ?? 0);
}
echo "Existing keys count: $existing\n";

$settings = app_get_editable_config_values(false);
if (empty($settings)) {
    echo "No settings found in editable config values.\n";
    exit;
}

$sql  = "INSERT INTO site_setting (setting_key, setting_value, value_type, is_secret)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE value_type = VALUES(value_type), is_secret = VALUES(is_secret)";
$stmt = $ithanhloc->prepare($sql);
if (!$stmt) {
    echo "Failed to prepare stmt.\n";
    exit;
}

$seeded = 0;
foreach ($settings as $item) {
    $key = (string)($item['key'] ?? '');
    if ($key === '') continue;
    $raw = $item['raw_value'] ?? '';
    if (is_bool($raw))                   $value = $raw ? '1' : '0';
    elseif (is_array($raw) || is_object($raw)) $value = json_encode($raw, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    else                                  $value = (string)$raw;
    $type   = (string)($item['type'] ?? 'string');
    $secret = !empty($item['secret']) ? 1 : 0;
    $stmt->bind_param('sssi', $key, $value, $type, $secret);
    if ($stmt->execute()) {
        $seeded++;
    }
}
$stmt->close();

echo "Seeded/synchronized $seeded settings.\n";
