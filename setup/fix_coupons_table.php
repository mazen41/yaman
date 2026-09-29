<?php
/**
 * Fix Coupons Table - Add missing columns
 */

require_once '../config/database.php';

try {
    echo "<h2>Fixing Coupons Table...</h2>";
    
    // Check if created_by column exists
    $check = $db->query("SHOW COLUMNS FROM coupons LIKE 'created_by'");
    if ($check->rowCount() == 0) {
        echo "<p>Adding 'created_by' column...</p>";
        $db->exec("ALTER TABLE coupons ADD COLUMN created_by INT NULL AFTER is_active");
        $db->exec("ALTER TABLE coupons ADD FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL");
        echo "<p style='color: green;'>✓ Added 'created_by' column</p>";
    } else {
        echo "<p style='color: blue;'>✓ 'created_by' column already exists</p>";
    }
    
    // Check if coupon_name column exists
    $check = $db->query("SHOW COLUMNS FROM coupons LIKE 'coupon_name'");
    if ($check->rowCount() == 0) {
        echo "<p>Adding 'coupon_name' column...</p>";
        $db->exec("ALTER TABLE coupons ADD COLUMN coupon_name VARCHAR(100) NULL AFTER coupon_code");
        echo "<p style='color: green;'>✓ Added 'coupon_name' column</p>";
    } else {
        echo "<p style='color: blue;'>✓ 'coupon_name' column already exists</p>";
    }
    
    // Check if description column exists
    $check = $db->query("SHOW COLUMNS FROM coupons LIKE 'description'");
    if ($check->rowCount() == 0) {
        echo "<p>Adding 'description' column...</p>";
        $db->exec("ALTER TABLE coupons ADD COLUMN description TEXT NULL AFTER coupon_name");
        echo "<p style='color: green;'>✓ Added 'description' column</p>";
    } else {
        echo "<p style='color: blue;'>✓ 'description' column already exists</p>";
    }
    
    // Check if user_usage_limit column exists
    $check = $db->query("SHOW COLUMNS FROM coupons LIKE 'user_usage_limit'");
    if ($check->rowCount() == 0) {
        echo "<p>Adding 'user_usage_limit' column...</p>";
        $db->exec("ALTER TABLE coupons ADD COLUMN user_usage_limit INT DEFAULT 1 AFTER usage_limit");
        echo "<p style='color: green;'>✓ Added 'user_usage_limit' column</p>";
    } else {
        echo "<p style='color: blue;'>✓ 'user_usage_limit' column already exists</p>";
    }
    
    // Check if start_date column exists
    $check = $db->query("SHOW COLUMNS FROM coupons LIKE 'start_date'");
    if ($check->rowCount() == 0) {
        echo "<p>Adding 'start_date' column...</p>";
        $db->exec("ALTER TABLE coupons ADD COLUMN start_date DATE NULL AFTER user_usage_limit");
        echo "<p style='color: green;'>✓ Added 'start_date' column</p>";
    } else {
        echo "<p style='color: blue;'>✓ 'start_date' column already exists</p>";
    }
    
    // Check if end_date column exists
    $check = $db->query("SHOW COLUMNS FROM coupons LIKE 'end_date'");
    if ($check->rowCount() == 0) {
        echo "<p>Adding 'end_date' column...</p>";
        $db->exec("ALTER TABLE coupons ADD COLUMN end_date DATE NULL AFTER start_date");
        echo "<p style='color: green;'>✓ Added 'end_date' column</p>";
    } else {
        echo "<p style='color: blue;'>✓ 'end_date' column already exists</p>";
    }
    
    // Rename discount_type values if needed (fixed -> fixed_amount)
    echo "<p>Checking discount_type enum values...</p>";
    $db->exec("ALTER TABLE coupons MODIFY COLUMN discount_type ENUM('percentage', 'fixed_amount', 'fixed') NOT NULL");
    echo "<p style='color: green;'>✓ Updated discount_type enum values</p>";
    
    echo "<h3 style='color: green;'>✅ Coupons table fixed successfully!</h3>";
    echo "<p><a href='../modules/coupons/add.php'>Go to Add Coupon Page</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
