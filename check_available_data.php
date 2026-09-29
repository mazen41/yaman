<?php
require_once 'config/database.php';

echo "<h2>Checking Available Data</h2>";
echo "<hr>";

try {
    // Check purchase_baskets table structure
    echo "<h3>Purchase Baskets Table Columns:</h3>";
    $columns = $db->query("SHOW COLUMNS FROM purchase_baskets")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    foreach ($columns as $col) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }
    echo "</pre>";
    
    // Check available baskets
    echo "<h3>Available Baskets (purchase_group_id IS NULL):</h3>";
    $baskets = $db->query("SELECT id, basket_code, basket_name, purchase_group_id FROM purchase_baskets LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Code</th><th>Name</th><th>Group ID</th></tr>";
    foreach ($baskets as $basket) {
        echo "<tr>";
        echo "<td>{$basket['id']}</td>";
        echo "<td>{$basket['basket_code']}</td>";
        echo "<td>" . ($basket['basket_name'] ?? 'N/A') . "</td>";
        echo "<td>" . ($basket['purchase_group_id'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Count available baskets
    $count = $db->query("SELECT COUNT(*) FROM purchase_baskets WHERE purchase_group_id IS NULL")->fetchColumn();
    echo "<p><strong>Total available baskets: {$count}</strong></p>";
    
    echo "<hr>";
    
    // Check customer_orders table structure
    echo "<h3>Customer Orders Table Columns:</h3>";
    $columns2 = $db->query("SHOW COLUMNS FROM customer_orders")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    foreach ($columns2 as $col) {
        echo $col['Field'] . " - " . $col['Type'] . "\n";
    }
    echo "</pre>";
    
    // Check available orders
    echo "<h3>Available Orders (purchase_group_id IS NULL):</h3>";
    $orders = $db->query("SELECT id, order_number, purchase_group_id FROM customer_orders LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Order Number</th><th>Group ID</th></tr>";
    foreach ($orders as $order) {
        echo "<tr>";
        echo "<td>{$order['id']}</td>";
        echo "<td>{$order['order_number']}</td>";
        echo "<td>" . ($order['purchase_group_id'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Count available orders
    $count2 = $db->query("SELECT COUNT(*) FROM customer_orders WHERE purchase_group_id IS NULL")->fetchColumn();
    echo "<p><strong>Total available orders: {$count2}</strong></p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>
