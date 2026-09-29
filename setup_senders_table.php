<?php
/**
 * Setup Senders Table
 * Run this once to create the senders table and update shipments table
 */

require_once 'config/database.php';

echo "<h1>Setting up Senders Table...</h1>";

try {
    // Create senders table
    $sql = "CREATE TABLE IF NOT EXISTS `senders` (
      `id` INT AUTO_INCREMENT PRIMARY KEY,
      `name` VARCHAR(255) NOT NULL,
      `phone` VARCHAR(50) DEFAULT NULL,
      `email` VARCHAR(255) DEFAULT NULL,
      `address` TEXT DEFAULT NULL,
      `portal_token` VARCHAR(64) UNIQUE NOT NULL,
      `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
      `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX `idx_portal_token` (`portal_token`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "<p style='color: green;'>✓ Senders table created successfully!</p>";
    
    // Check if sender_id column exists in shipments
    $check = $db->query("SHOW COLUMNS FROM shipments LIKE 'sender_id'")->fetch();
    
    if (!$check) {
        // Add sender_id column to shipments table
        $sql = "ALTER TABLE `shipments` 
                ADD COLUMN `sender_id` INT DEFAULT NULL AFTER `order_id`,
                ADD INDEX `idx_sender_id` (`sender_id`)";
        
        $db->exec($sql);
        echo "<p style='color: green;'>✓ Added sender_id column to shipments table!</p>";
    } else {
        echo "<p style='color: blue;'>ℹ sender_id column already exists in shipments table</p>";
    }
    
    // Insert a default sender for testing
    $check_sender = $db->query("SELECT COUNT(*) FROM senders")->fetchColumn();
    if ($check_sender == 0) {
        $token = bin2hex(random_bytes(32));
        $db->prepare("INSERT INTO senders (name, phone, portal_token) VALUES (?, ?, ?)")
           ->execute(['مرسل افتراضي', '0500000000', $token]);
        echo "<p style='color: green;'>✓ Created default sender for testing</p>";
        echo "<p>Portal Link: <a href='https://taksoride.com/sender_portal/?token={$token}' target='_blank'>https://taksoride.com/sender_portal/?token={$token}</a></p>";
    }
    
    echo "<h2 style='color: green;'>✅ Setup Complete!</h2>";
    echo "<p><a href='modules/shipping/senders.php'>Go to Senders Management</a></p>";
    echo "<p><a href='modules/shipping/add.php'>Go to Add Shipment</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
