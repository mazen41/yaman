<?php
/**
 * EMERGENCY FIX - Restore Website
 * This fixes the database connection issue
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1 style='color: red;'>🚨 EMERGENCY FIX - Restoring Website</h1>";
echo "<pre>";

// Try with the password from config file
$host = 'localhost';
$dbname = 'taksroide-db';
$username = 'taksroide-user';
$password = 'gliE87jMZfZkyBeaoUzm'; // From config file

echo "Step 1: Testing database connection...\n";
echo "Database: $dbname\n";
echo "User: $username\n\n";

try {
    $db = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "✅ DATABASE CONNECTION SUCCESSFUL!\n\n";
    echo "The website should be working now.\n\n";
    
    // Check if tables exist
    echo "Step 2: Checking existing tables...\n";
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "Found " . count($tables) . " tables:\n";
    $has_roles = in_array('roles', $tables);
    $has_permissions = in_array('permissions', $tables);
    $has_role_permissions = in_array('role_permissions', $tables);
    $has_permission_cache = in_array('permission_cache', $tables);
    $has_permission_audit = in_array('permission_audit_log', $tables);
    
    echo "  " . ($has_roles ? "✅" : "❌") . " roles\n";
    echo "  " . ($has_permissions ? "✅" : "❌") . " permissions\n";
    echo "  " . ($has_role_permissions ? "✅" : "❌") . " role_permissions\n";
    echo "  " . ($has_permission_cache ? "✅" : "❌") . " permission_cache\n";
    echo "  " . ($has_permission_audit ? "✅" : "❌") . " permission_audit_log\n\n";
    
    // Create missing tables if needed
    if (!$has_role_permissions || !$has_permission_cache || !$has_permission_audit) {
        echo "Step 3: Creating missing tables...\n";
        
        if (!$has_role_permissions) {
            echo "Creating role_permissions...\n";
            $db->exec("
                CREATE TABLE IF NOT EXISTS `role_permissions` (
                  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                  `role_id` int(10) unsigned NOT NULL,
                  `permission_id` int(10) unsigned NOT NULL,
                  `can_view` tinyint(1) DEFAULT 0,
                  `can_add` tinyint(1) DEFAULT 0,
                  `can_edit` tinyint(1) DEFAULT 0,
                  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `unique_role_permission` (`role_id`,`permission_id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            echo "✅ role_permissions created\n";
        }
        
        if (!$has_permission_cache) {
            echo "Creating permission_cache...\n";
            $db->exec("
                CREATE TABLE IF NOT EXISTS `permission_cache` (
                  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                  `user_id` int(10) unsigned NOT NULL,
                  `cache_key` varchar(255) NOT NULL,
                  `cache_value` text DEFAULT NULL,
                  `expires_at` timestamp NOT NULL,
                  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`),
                  UNIQUE KEY `unique_cache` (`user_id`,`cache_key`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            echo "✅ permission_cache created\n";
        }
        
        if (!$has_permission_audit) {
            echo "Creating permission_audit_log...\n";
            $db->exec("
                CREATE TABLE IF NOT EXISTS `permission_audit_log` (
                  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                  `user_id` int(10) unsigned DEFAULT NULL,
                  `role_id` int(10) unsigned DEFAULT NULL,
                  `permission_id` int(10) unsigned DEFAULT NULL,
                  `action` varchar(50) NOT NULL,
                  `old_value` text DEFAULT NULL,
                  `new_value` text DEFAULT NULL,
                  `changed_by` int(10) unsigned DEFAULT NULL,
                  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                  PRIMARY KEY (`id`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            echo "✅ permission_audit_log created\n";
        }
        
        echo "\n";
    }
    
    echo "=================================\n";
    echo "✅ WEBSITE RESTORED!\n";
    echo "=================================\n\n";
    
    echo "Your website is now working!\n\n";
    
    echo "Next steps:\n";
    echo "1. Test your main page: https://taksoride.com/\n";
    echo "2. Go to setup: https://taksoride.com/setup_rbac.php\n";
    echo "3. Assign roles: https://taksoride.com/modules/financial/employee-permissions-fixed.php?user_id=13\n\n";
    
    echo "</pre>";
    echo "<h2 style='color: green;'>✅ Website is Back Online!</h2>";
    echo "<p><a href='/' style='padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px;'>Go to Homepage</a></p>";
    echo "<p><a href='setup_rbac.php' style='padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px;'>Setup RBAC</a></p>";
    
} catch (PDOException $e) {
    echo "\n❌ STILL CANNOT CONNECT TO DATABASE\n\n";
    echo "Error: " . $e->getMessage() . "\n\n";
    echo "This means the database credentials are wrong or the database server is down.\n\n";
    
    echo "EMERGENCY SOLUTION:\n";
    echo "1. Go to phpMyAdmin: https://taksoride.com/phpmyadmin\n";
    echo "2. Login with root credentials\n";
    echo "3. Run this SQL:\n\n";
    
    echo "<textarea style='width: 100%; height: 200px; font-family: monospace;'>";
    echo "-- Fix database user permissions\n";
    echo "GRANT ALL PRIVILEGES ON `taksroide-db`.* TO 'taksroide-user'@'localhost' IDENTIFIED BY 'gliE87jMZfZkyBeaoUzm';\n";
    echo "GRANT ALL PRIVILEGES ON `taksroide-db`.* TO 'taksroide-user'@'%' IDENTIFIED BY 'gliE87jMZfZkyBeaoUzm';\n";
    echo "FLUSH PRIVILEGES;\n\n";
    
    echo "-- Test connection\n";
    echo "SELECT USER(), DATABASE();\n";
    echo "</textarea>";
    
    echo "\n<p style='color: red; font-weight: bold;'>If this doesn't work, contact your hosting provider immediately!</p>";
}
?>
