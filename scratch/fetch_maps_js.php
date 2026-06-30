<?php
$key = 'AIzaSyCcMERYHkvFS9E0oJ41qwDdJXbMWm3sm2A';
$url = "https://maps.googleapis.com/maps/api/js?key=" . urlencode($key) . "&callback=__gmaps_init_cb";

echo "Downloading Google Maps JS SDK from: $url ...\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Referer: https://sonxit.vn/account?tab=address"
]);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36");

$response = curl_exec($ch);
curl_close($ch);

// Output the first 1000 characters or any error comment
if (preg_match('/Console\s+Error/i', $response) || preg_match('/error/i', $response) || preg_match('/Billing/i', $response)) {
    echo "Found potential error text in response!\n";
}

// Print lines containing "Error" or "warning" or "Billing"
$lines = explode("\n", $response);
foreach ($lines as $line) {
    if (stripos($line, 'error') !== false || stripos($line, 'warning') !== false || stripos($line, 'billing') !== false) {
        echo "Line: " . trim($line) . "\n";
    }
}

// Save the response for complete inspection if needed
file_put_to_file:
file_put_contents(__DIR__ . '/maps_js_response.js', $response);
echo "Response saved to maps_js_response.js. Total size: " . strlen($response) . " bytes.\n";
