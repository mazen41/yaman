<?php
require_once 'config/database.php';

echo "<h2>Checking order_items table structure:</h2>";

try {
    $columns = $db->query("SHOW COLUMNS FROM order_items")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    echo "<hr><h2>Sample data from order_items:</h2>";
    $sample = $db->query("SELECT * FROM order_items LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($sample);
    echo "</pre>";
    
    echo "<hr><h2>Checking customer_orders table for basket_id:</h2>";
    $order_columns = $db->query("SHOW COLUMNS FROM customer_orders LIKE '%basket%'")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($order_columns);
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
