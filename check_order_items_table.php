<?php
require_once 'config/database.php';

echo "<h2>Checking order_items table structure:</h2>";

try {
    $stmt = $db->query("SHOW COLUMNS FROM order_items");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    echo "<hr><h2>Sample data:</h2>";
    $stmt = $db->query("SELECT * FROM order_items LIMIT 3");
    $sample = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<pre>";
    print_r($sample);
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
