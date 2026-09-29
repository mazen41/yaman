<?php
require_once 'config/database.php';

$basket_id = 37;

echo "<h2>Basket #37 Details:</h2>";

// Check basket record
$stmt = $db->prepare("SELECT * FROM purchase_baskets WHERE id = ?");
$stmt->execute([$basket_id]);
$basket = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Basket Record:</h3>";
echo "<pre>";
print_r($basket);
echo "</pre>";

// Check basket items
$stmt = $db->prepare("SELECT * FROM basket_items WHERE basket_id = ?");
$stmt->execute([$basket_id]);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>Basket Items (Count: " . count($items) . "):</h3>";
echo "<pre>";
print_r($items);
echo "</pre>";

// Summary
echo "<hr>";
echo "<h3>Summary:</h3>";
echo "<p><strong>Basket Name:</strong> " . ($basket['basket_name'] ?? 'N/A') . "</p>";
echo "<p><strong>Basket Code:</strong> " . ($basket['basket_code'] ?? 'N/A') . "</p>";
echo "<p><strong>Status:</strong> " . ($basket['status'] ?? 'N/A') . "</p>";
echo "<p><strong>Total Items:</strong> " . ($basket['total_items'] ?? 0) . "</p>";
echo "<p><strong>Subtotal:</strong> " . ($basket['subtotal_amount'] ?? 0) . "</p>";
echo "<p><strong>Final Amount:</strong> " . ($basket['final_amount'] ?? 0) . "</p>";
echo "<p><strong>Items in basket_items table:</strong> " . count($items) . "</p>";

if (count($items) == 0) {
    echo "<p style='color: red; font-weight: bold;'>⚠️ This basket has NO items! That's why everything shows 0.</p>";
    echo "<p>To add items to this basket, you need to use the basket creation/edit page.</p>";
}
?>
