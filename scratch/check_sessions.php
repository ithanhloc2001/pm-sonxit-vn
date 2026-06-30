<?php
$dir = 'd:/xampp/tmp';
if (!is_dir($dir)) {
    $dir = 'c:/xampp/tmp';
}
if (!is_dir($dir)) {
    die("XAMPP tmp directory not found.\n");
}

$files = glob($dir . '/sess_*');
usort($files, function($a, $b) {
    return filemtime($b) - filemtime($a);
});

foreach (array_slice($files, 0, 3) as $f) {
    echo "File: " . basename($f) . " (Modified: " . date('Y-m-d H:i:s', filemtime($f)) . ")\n";
    $content = file_get_contents($f);
    $data = unserialize_session($content);
    if ($data && isset($data['shop_cart'])) {
        foreach ($data['shop_cart'] as $k => $it) {
            echo "Index: $k | Item Key: " . ($it['key'] ?? 'NULL') . "\n";
            echo "  Product ID: " . ($it['product_id'] ?? '') . ", Variant ID: " . ($it['variant_id'] ?? '') . ", Is Gift: " . ($it['is_gift'] ?? '0') . "\n";
            echo "  Display Variant Name: " . ($it['variant'] ?? 'NULL') . "\n";
            echo "  Thumb: " . ($it['thumb'] ?? 'NULL') . "\n";
        }
    }
    echo "\n";
}

function unserialize_session($session_data) {
    $return_data = array();
    $offset = 0;
    while ($offset < strlen($session_data)) {
        if (!strstr(substr($session_data, $offset), "|")) {
            return null;
        }
        $pos = strpos($session_data, "|", $offset);
        $num = $pos - $offset;
        $varname = substr($session_data, $offset, $num);
        $offset += $num + 1;
        $data = unserialize(substr($session_data, $offset));
        $return_data[$varname] = $data;
        $offset += strlen(serialize($data));
    }
    return $return_data;
}
