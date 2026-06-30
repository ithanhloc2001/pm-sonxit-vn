<?php
$key = 'AIzaSyCcMERYHkvFS9E0oJ41qwDdJXbMWm3sm2A';
$url = "https://maps.googleapis.com/maps/api/geocode/json?address=hanoi&key=" . urlencode($key);

echo "Sending request to Google Geocoding API with Referer: https://sonxit.vn/ ...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Referer: https://sonxit.vn/'
]);
$response = curl_exec($ch);
if (curl_errno($ch)) {
    echo 'Curl error: ' . curl_error($ch) . "\n";
} else {
    echo "Response:\n";
    echo $response . "\n";
}
curl_close($ch);
?>
