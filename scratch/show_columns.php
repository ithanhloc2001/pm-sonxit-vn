<?php
require_once __DIR__ . '/../config.php';

function print_columns($db, $table) {
    echo "Columns of $table:\n";
    $res = $db->query("DESCRIBE `$table`");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo "  {$row['Field']} - {$row['Type']}\n";
        }
    } else {
        echo "Table not found: " . $db->error . "\n";
    }
}

print_columns($ithanhloc, 'ghn_order_item');
