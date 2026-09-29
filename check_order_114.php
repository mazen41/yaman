<?php
require_once '../../config/database.php';

echo "<h2>Order 114 - Items Data</h2>";

try {
    // Get order details
    $stmt = $db->prepare("SELECT * FROM customer_orders WHERE id = 114");
    $stmt->execute();
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>Order Info:</h3>";
    echo "<pre>";
    print_r($order);
    echo "</pre>";
    
    // Get order items
    $stmt = $db->prepare("SELECT * FROM order_items WHERE order_id = 114");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Order Items:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Product Name</th><th>Quantity</th><th>Unit Price</th><th>Total Price</th></tr>";
    
    foreach ($items as $item) {
        echo "<tr>";
        echo "<td>" . $item['id'] . "</td>";
        echo "<td>" . htmlspecialchars($item['product_name']) . "</td>";
        echo "<td>" . $item['quantity'] . "</td>";
        echo "<td>" . $item['unit_price'] . "</td>";
        echo "<td>" . $item['total_price'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Calculate what it should be
    echo "<h3>Calculation:</h3>";
    $subtotal = 0;
    foreach ($items as $item) {
        $item_total = floatval($item['quantity']) * floatval($item['unit_price']);
        $subtotal += $item_total;
        echo "<p>{$item['product_name']}: {$item['quantity']} × {$item['unit_price']} = {$item_total}</p>";
    }
    echo "<p><strong>Subtotal: {$subtotal}</strong></p>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
