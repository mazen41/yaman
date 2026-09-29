<?php
require_once 'config/database.php';

echo "<h2>Upgrading Purchase Card Transactions Table</h2>";
echo "<hr>";

try {
    // Get current columns
    $columns = $db->query("SHOW COLUMNS FROM purchase_card_transactions")->fetchAll(PDO::FETCH_ASSOC);
    $existing_columns = array_column($columns, 'Field');
    
    echo "<h3>Adding Missing Columns:</h3>";
    
    // Add balance_before if missing
    if (!in_array('balance_before', $existing_columns)) {
        $db->exec("ALTER TABLE purchase_card_transactions ADD COLUMN balance_before DECIMAL(15,2) NOT NULL DEFAULT 0 AFTER amount");
        echo "<p style='color:green;'>✅ Added 'balance_before' column</p>";
    } else {
        echo "<p>✓ 'balance_before' already exists</p>";
    }
    
    // Add reference_type if missing
    if (!in_array('reference_type', $existing_columns)) {
        $db->exec("ALTER TABLE purchase_card_transactions ADD COLUMN reference_type VARCHAR(50) NULL AFTER balance_after");
        echo "<p style='color:green;'>✅ Added 'reference_type' column</p>";
    } else {
        echo "<p>✓ 'reference_type' already exists</p>";
    }
    
    // Add reference_id if missing
    if (!in_array('reference_id', $existing_columns)) {
        $db->exec("ALTER TABLE purchase_card_transactions ADD COLUMN reference_id INT NULL AFTER reference_type");
        echo "<p style='color:green;'>✅ Added 'reference_id' column</p>";
    } else {
        echo "<p>✓ 'reference_id' already exists</p>";
    }
    
    // Add description if missing
    if (!in_array('description', $existing_columns)) {
        $db->exec("ALTER TABLE purchase_card_transactions ADD COLUMN description TEXT NULL AFTER reference_id");
        echo "<p style='color:green;'>✅ Added 'description' column</p>";
    } else {
        echo "<p>✓ 'description' already exists</p>";
    }
    
    echo "<hr>";
    echo "<h3>Updating Transaction Types:</h3>";
    
    // Update enum to include more transaction types
    $db->exec("ALTER TABLE purchase_card_transactions MODIFY COLUMN transaction_type ENUM('add_balance','deduct','transfer','purchase','refund','transfer_in','transfer_out') NOT NULL");
    echo "<p style='color:green;'>✅ Updated transaction_type enum to include: purchase, refund, transfer_in, transfer_out</p>";
    
    echo "<hr>";
    echo "<h3>Final Table Structure:</h3>";
    $final_columns = $db->query("SHOW COLUMNS FROM purchase_card_transactions")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($final_columns as $col) {
        echo "<tr>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    echo "<h3 style='color:green;'>✅ Upgrade Complete!</h3>";
    echo "<p>The purchase_card_transactions table now has all required columns:</p>";
    echo "<ul>";
    echo "<li>✅ purchase_card_id</li>";
    echo "<li>✅ transaction_type (with purchase, refund, etc.)</li>";
    echo "<li>✅ amount</li>";
    echo "<li>✅ balance_before</li>";
    echo "<li>✅ balance_after</li>";
    echo "<li>✅ reference_type (basket, order, etc.)</li>";
    echo "<li>✅ reference_id</li>";
    echo "<li>✅ description</li>";
    echo "<li>✅ notes</li>";
    echo "<li>✅ created_by</li>";
    echo "<li>✅ created_at</li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color:red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>
