<?php
$c = file_get_contents('c:\xampp\htdocs\main\account.php');
$queries = ['region-session.php', 'save_address', 'saveAddress', 'Không thể lưu', 'url:', '$.post', '$.ajax', 'fetch'];
foreach ($queries as $q) {
    $pos = strpos($c, $q);
    if ($pos !== false) {
        echo "Found '$q' at position $pos. Snippet: " . substr($c, max(0, $pos - 100), 200) . "\n\n";
    } else {
        echo "Not found: '$q'\n";
    }
}
