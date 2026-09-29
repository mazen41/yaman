<?php
// Direct database connection and fix
$host = 'localhost';
$dbname = 'taksroide-db';
$username = 'taksroide-user';
$password = 'gliE87jMZfZkyBeaoUzm';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h2>Fixing payment_source_type column...</h2>";
    
    // Fix the column
    $db->exec("ALTER TABLE purchase_baskets MODIFY COLUMN payment_source_type VARCHAR(20) NULL");
    
    echo "<p style='color: green; font-size: 20px; font-weight: bold;'>✅ SUCCESS! Column fixed!</p>";
    echo "<p>The payment_source_type column can now hold 'bank_account' and 'purchase_card' values.</p>";
    
    // Verify
    $stmt = $db->query("SHOW COLUMNS FROM purchase_baskets LIKE 'payment_source_type'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h3>New Column Definition:</h3>";
    echo "<pre>";
    print_r($column);
    echo "</pre>";
    
    echo "<hr>";
    echo "<p><strong>You can now go back to:</strong></p>";
    echo "<p><a href='modules/purchases/basket_complete.php' style='color: blue; font-size: 18px;'>Create Purchase Basket</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red; font-weight: bold;'>Error: " . $e->getMessage() . "</p>";
}
?>
