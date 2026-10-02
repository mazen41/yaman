<?php
$db = new PDO('mysql:host=localhost;dbname=yaman;charset=utf8mb4', 'root', '');
$cols = $db->query('SHOW COLUMNS FROM customers')->fetchAll(PDO::FETCH_ASSOC);
echo "CUSTOMERS TABLE COLUMNS:\n";
foreach ($cols as $c) {
    echo "  {$c['Field']}  {$c['Type']}\n";
}
echo "\nALL TABLES:\n";
$tables = $db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
echo "  " . implode(', ', $tables) . "\n";

// Check existence of key tables
foreach (['customer_types', 'cities', 'customer_orders', 'order_damaged_items'] as $t) {
    $exists = $db->query("SHOW TABLES LIKE '$t'")->fetchColumn();
    echo "  table '$t': " . ($exists ? 'EXISTS' : 'MISSING') . "\n";
}
