<?php
require_once 'config/database.php';

echo "<h2>Checking Coupons Table</h2>";
echo "<hr>";

try {
    // Check if coupons table exists
    $tables = $db->query("SHOW TABLES LIKE 'coupons'")->fetchAll();
    
    if (empty($tables)) {
        echo "<p style='color:red;'><strong>❌ Coupons table does NOT exist!</strong></p>";
        echo "<p>Creating coupons table...</p>";
        
        // Create coupons table
        $db->exec("
            CREATE TABLE IF NOT EXISTS `coupons` (
              `id` int NOT NULL AUTO_INCREMENT,
              `coupon_code` varchar(50) NOT NULL,
              `discount_type` enum('percentage','fixed') NOT NULL DEFAULT 'percentage',
              `discount_value` decimal(10,2) NOT NULL,
              `min_order_amount` decimal(10,2) DEFAULT '0.00',
              `max_discount_amount` decimal(10,2) DEFAULT NULL,
              `valid_from` datetime DEFAULT NULL,
              `valid_until` datetime DEFAULT NULL,
              `usage_limit` int DEFAULT NULL,
              `used_count` int DEFAULT '0',
              `is_active` tinyint(1) DEFAULT '1',
              `description` text,
              `created_by` int DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              UNIQUE KEY `coupon_code` (`coupon_code`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "<p style='color:green;'><strong>✅ Coupons table created successfully!</strong></p>";
    } else {
        echo "<p style='color:green;'><strong>✅ Coupons table exists</strong></p>";
    }
    
    // Show table structure
    echo "<h3>Table Structure:</h3>";
    $columns = $db->query("SHOW COLUMNS FROM coupons")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check for 'code' column (the problematic one)
    $has_code = false;
    $has_coupon_code = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'code') $has_code = true;
        if ($col['Field'] === 'coupon_code') $has_coupon_code = true;
    }
    
    echo "<hr>";
    echo "<h3>Column Check:</h3>";
    echo "<p><strong>'code' column:</strong> " . ($has_code ? '✅ EXISTS' : '❌ MISSING') . "</p>";
    echo "<p><strong>'coupon_code' column:</strong> " . ($has_coupon_code ? '✅ EXISTS' : '❌ MISSING') . "</p>";
    
    if (!$has_code && $has_coupon_code) {
        echo "<p style='color:orange;'><strong>⚠️ Issue Found:</strong> Table has 'coupon_code' but code is looking for 'code'</p>";
        echo "<p>The create.php file needs to be updated to use 'coupon_code' instead of 'code'</p>";
    }
    
    // Show sample data
    echo "<hr>";
    echo "<h3>Sample Coupons:</h3>";
    $coupons = $db->query("SELECT * FROM coupons LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($coupons)) {
        echo "<p>No coupons found in database</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Code</th><th>Type</th><th>Value</th><th>Active</th></tr>";
        foreach ($coupons as $coupon) {
            $code_field = $has_coupon_code ? $coupon['coupon_code'] : ($has_code ? $coupon['code'] : 'N/A');
            echo "<tr>";
            echo "<td>{$coupon['id']}</td>";
            echo "<td><strong>{$code_field}</strong></td>";
            echo "<td>{$coupon['discount_type']}</td>";
            echo "<td>{$coupon['discount_value']}</td>";
            echo "<td>" . ($coupon['is_active'] ? '✅' : '❌') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>
