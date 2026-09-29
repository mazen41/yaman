<?php
/**
 * Migration Script: Add additional_link column to customer_orders table
 */
require_once 'config/database.php';

echo "<h2>Adding additional_link column to customer_orders table</h2>";

try {
    // Check if column already exists
    $check = $db->query("SHOW COLUMNS FROM customer_orders LIKE 'additional_link'");
    
    if ($check->rowCount() > 0) {
        echo "<p style='color: orange;'>⚠️ Column 'additional_link' already exists in customer_orders table.</p>";
    } else {
        // Add the column
        $db->exec("ALTER TABLE customer_orders ADD COLUMN additional_link VARCHAR(500) NULL AFTER order_link");
        echo "<p style='color: green;'>✅ Successfully added 'additional_link' column to customer_orders table.</p>";
    }
    
    // Show current structure
    echo "<h3>Current customer_orders table structure (relevant columns):</h3>";
    $columns = $db->query("SHOW COLUMNS FROM customer_orders WHERE Field IN ('order_link', 'additional_link')")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    echo "<p style='color: green; font-weight: bold;'>✅ Migration completed successfully!</p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
