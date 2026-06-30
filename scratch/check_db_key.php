<?php
define('ALLOW_INC', true);
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../config.php';

$db_key = app_get_config_value_by_path('GOOGLE_MAPS_API_KEY');
echo "Current GOOGLE_MAPS_API_KEY in DB: '$db_key'\n";
