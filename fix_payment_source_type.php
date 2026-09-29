<?php
/**
 * Fix payment_source_type column size
 */

require_once 'config/database.php';

try {
    // Check current column definition
    $stmt = $db->query("SHOW COLUMNS FROM purchase_baskets LIKE 'payment_source_type'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>Current Column Definition:</h2>";
    echo "<pre>";
    print_r($column);
    echo "</pre>";
    
    // Fix the column - make it VARCHAR(20) to accommodate 'bank_account' and 'purchase_card'
    echo "<h2>Fixing column...</h2>";
    $db->exec("ALTER TABLE purchase_baskets MODIFY COLUMN payment_source_type VARCHAR(20) NULL");
    
    echo "<p style='color: green; font-weight: bold;'>✅ Column fixed successfully!</p>";
    
    // Verify the fix
    $stmt = $db->query("SHOW COLUMNS FROM purchase_baskets LIKE 'payment_source_type'");
    $column = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>New Column Definition:</h2>";
    echo "<pre>";
    print_r($column);
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
