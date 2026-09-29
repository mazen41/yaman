<?php
/**
 * Migration Script: Create basket_tracking table
 */
require_once 'config/database.php';

echo "<h2>Creating basket_tracking table</h2>";

try {
    // Check if table already exists
    $check = $db->query("SHOW TABLES LIKE 'basket_tracking'");
    
    if ($check->rowCount() > 0) {
        echo "<p style='color: orange;'>⚠️ Table 'basket_tracking' already exists.</p>";
    } else {
        // Create the table
        $sql = "CREATE TABLE basket_tracking (
            id INT AUTO_INCREMENT PRIMARY KEY,
            basket_id INT NOT NULL,
            tracking_number VARCHAR(255) NOT NULL,
            carrier VARCHAR(100) NULL,
            status ENUM('pending', 'in_transit', 'delivered', 'returned') DEFAULT 'pending',
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by INT NULL,
            INDEX idx_basket_id (basket_id),
            INDEX idx_tracking_number (tracking_number),
            INDEX idx_status (status),
            FOREIGN KEY (basket_id) REFERENCES purchase_baskets(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($sql);
        echo "<p style='color: green;'>✅ Successfully created 'basket_tracking' table.</p>";
    }
    
    // Show table structure
    echo "<h3>Table structure:</h3>";
    $columns = $db->query("SHOW COLUMNS FROM basket_tracking")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($columns);
    echo "</pre>";
    
    echo "<p style='color: green; font-weight: bold;'>✅ Migration completed successfully!</p>";
    echo "<p><a href='modules/purchases/tracking.php'>Go to Tracking Page</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
