<?php
$lines = file('c:\xampp\htdocs\main\account.php');
foreach ($lines as $idx => $line) {
    if (stripos($line, 'support') !== false || stripos($line, 'ticket') !== false || stripos($line, 'yêu cầu') !== false || stripos($line, 'Đã đóng') !== false) {
        echo "Line " . ($idx + 1) . ": " . trim($line) . "\n";
    }
}
