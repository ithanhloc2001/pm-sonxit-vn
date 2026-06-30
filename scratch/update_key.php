<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$new_key = 'AIzaSyCcMERYHkvFS9E0oJ41qwDdJXbMWm3sm2A';
echo "Updating GOOGLE_MAPS_API_KEY to: " . $new_key . "\n";

$ok = app_upsert_bot_setting_value($ithanhloc, 'GOOGLE_MAPS_API_KEY', $new_key, true);
if ($ok) {
    echo "Successfully updated GOOGLE_MAPS_API_KEY in the database.\n";
} else {
    echo "Failed to update database.\n";
}
?>
