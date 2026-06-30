<?php
$lines = file('c:\xampp\htdocs\main\account.php');
foreach ($lines as $idx => $line) {
    if (strpos($line, 'save_address') !== false) {
        echo "Line " . ($idx + 1) . ": " . trim($line) . "\n";
    }
}
