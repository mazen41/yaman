<?php
require_once 'config/database.php';

$customer_id = 7; // Customer "علي"

echo "<h2>Debugging Customer Orders for ID: {$customer_id}</h2>";

// Check customer_orders table structure
echo "<h3>1. customer_orders table structure:</h3>";
$stmt = $db->query("SHOW COLUMNS FROM customer_orders");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($columns);
echo "</pre>";

// Check if customer has orders
echo "<h3>2. Orders for customer {$customer_id}:</h3>";
$stmt = $db->prepare("SELECT * FROM customer_orders WHERE customer_id = ? LIMIT 3");
$stmt->execute([$customer_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($orders);
echo "</pre>";

// Count total orders
echo "<h3>3. Total orders count:</h3>";
$stmt = $db->prepare("SELECT COUNT(*) FROM customer_orders WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$count = $stmt->fetchColumn();
echo "<p><strong>Total: {$count} orders</strong></p>";

// Check customer_payments table
echo "<h3>4. customer_payments table structure:</h3>";
try {
    $stmt = $db->query("SHOW COLUMNS FROM customer_payments");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}

// Check customer_invoices table
echo "<h3>5. customer_invoices table structure:</h3>";
try {
    $stmt = $db->query("SHOW COLUMNS FROM customer_invoices");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
