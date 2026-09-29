<?php
/**
 * Fix Database Connection and Create Tables
 * This script uses direct credentials to fix the issue
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Fixing Database and Creating Tables</h1>";
echo "<pre>";

// Database credentials
$host = 'localhost';
$dbname = 'taksroide-db';
$username = 'taksroide-user';
$password = 'Tabarka2016@@GG00';

echo "Attempting to connect to database...\n";
echo "Database: $dbname\n";
echo "User: $username\n\n";

try {
    // Create PDO connection
    $db = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    echo "✅ Database connection successful!\n\n";
    
    // Create role_permissions table
    echo "Creating role_permissions table...\n";
    $db->exec("
        DROP TABLE IF EXISTS `role_permissions`;
        CREATE TABLE `role_permissions` (
          `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `role_id` int(10) unsigned NOT NULL,
          `permission_id` int(10) unsigned NOT NULL,
          `can_view` tinyint(1) DEFAULT 0,
          `can_add` tinyint(1) DEFAULT 0,
          `can_edit` tinyint(1) DEFAULT 0,
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `unique_role_permission` (`role_id`,`permission_id`),
          KEY `idx_role_view` (`role_id`,`can_view`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ role_permissions table created\n\n";
    
    // Create permission_cache table
    echo "Creating permission_cache table...\n";
    $db->exec("
        DROP TABLE IF EXISTS `permission_cache`;
        CREATE TABLE `permission_cache` (
          `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `user_id` int(10) unsigned NOT NULL,
          `cache_key` varchar(255) NOT NULL,
          `cache_value` text DEFAULT NULL,
          `expires_at` timestamp NOT NULL,
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `unique_cache` (`user_id`,`cache_key`),
          KEY `idx_expires` (`expires_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ permission_cache table created\n\n";
    
    // Create permission_audit_log table
    echo "Creating permission_audit_log table...\n";
    $db->exec("
        DROP TABLE IF EXISTS `permission_audit_log`;
        CREATE TABLE `permission_audit_log` (
          `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
          `user_id` int(10) unsigned DEFAULT NULL,
          `role_id` int(10) unsigned DEFAULT NULL,
          `permission_id` int(10) unsigned DEFAULT NULL,
          `action` varchar(50) NOT NULL,
          `old_value` text DEFAULT NULL,
          `new_value` text DEFAULT NULL,
          `changed_by` int(10) unsigned DEFAULT NULL,
          `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          KEY `idx_user` (`user_id`),
          KEY `idx_role` (`role_id`),
          KEY `idx_created` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ permission_audit_log table created\n\n";
    
    // Insert sample data
    echo "Inserting sample data...\n";
    $db->exec("
        INSERT INTO `role_permissions` (`role_id`, `permission_id`, `can_view`, `can_add`, `can_edit`) VALUES
        (2, 1, 1, 1, 1),
        (2, 2, 1, 1, 0),
        (2, 3, 1, 0, 1),
        (2, 4, 1, 1, 0),
        (3, 1, 1, 0, 0),
        (3, 2, 1, 0, 0)
    ");
    echo "✅ Sample data inserted\n\n";
    
    // Verify tables
    echo "Verifying tables...\n";
    $stmt = $db->query("SHOW TABLES LIKE '%permission%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($tables as $table) {
        echo "  ✅ $table\n";
    }
    
    // Count data
    echo "\nCounting data...\n";
    $stmt = $db->query("SELECT COUNT(*) FROM role_permissions");
    $count = $stmt->fetchColumn();
    echo "  role_permissions: $count rows\n";
    
    $stmt = $db->query("SELECT COUNT(*) FROM permission_cache");
    $count = $stmt->fetchColumn();
    echo "  permission_cache: $count rows\n";
    
    $stmt = $db->query("SELECT COUNT(*) FROM permission_audit_log");
    $count = $stmt->fetchColumn();
    echo "  permission_audit_log: $count rows\n";
    
    echo "\n";
    echo "=================================\n";
    echo "✅ SUCCESS! ALL TABLES CREATED!\n";
    echo "=================================\n\n";
    
    echo "Next steps:\n";
    echo "1. Go to: https://taksoride.com/setup_rbac.php\n";
    echo "2. Click 'Run Setup' button\n";
    echo "3. Go to: https://taksoride.com/modules/financial/employee-permissions-fixed.php?user_id=13\n";
    echo "4. Assign 'Manager' role to user 13\n";
    echo "5. Test login with user 13\n\n";
    
    echo "</pre>";
    echo "<h2>✅ Done!</h2>";
    echo "<p><a href='setup_rbac.php' style='padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px;'>Go to Setup Page</a></p>";
    echo "<p><a href='modules/financial/employee-permissions-fixed.php?user_id=13' style='padding: 10px 20px; background: #10b981; color: white; text-decoration: none; border-radius: 5px; display: inline-block; margin: 10px;'>Assign Roles</a></p>";
    
} catch (PDOException $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n\n";
    echo "Error details:\n";
    echo "  Code: " . $e->getCode() . "\n";
    echo "  File: " . $e->getFile() . "\n";
    echo "  Line: " . $e->getLine() . "\n\n";
    
    echo "Troubleshooting:\n";
    echo "1. Check if database 'taksroide-db' exists\n";
    echo "2. Check if user 'taksroide-user' has permissions\n";
    echo "3. Check if password is correct\n";
    echo "4. Try running this SQL in phpMyAdmin:\n\n";
    
    echo "<textarea style='width: 100%; height: 300px; font-family: monospace;'>";
    echo "-- Run this in phpMyAdmin (select database: taksroide-db first)\n\n";
    echo "DROP TABLE IF EXISTS `role_permissions`;\n";
    echo "CREATE TABLE `role_permissions` (\n";
    echo "  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n";
    echo "  `role_id` int(10) unsigned NOT NULL,\n";
    echo "  `permission_id` int(10) unsigned NOT NULL,\n";
    echo "  `can_view` tinyint(1) DEFAULT 0,\n";
    echo "  `can_add` tinyint(1) DEFAULT 0,\n";
    echo "  `can_edit` tinyint(1) DEFAULT 0,\n";
    echo "  PRIMARY KEY (`id`),\n";
    echo "  UNIQUE KEY (`role_id`,`permission_id`)\n";
    echo ") ENGINE=InnoDB;\n\n";
    
    echo "DROP TABLE IF EXISTS `permission_cache`;\n";
    echo "CREATE TABLE `permission_cache` (\n";
    echo "  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n";
    echo "  `user_id` int(10) unsigned NOT NULL,\n";
    echo "  `cache_key` varchar(255) NOT NULL,\n";
    echo "  `cache_value` text,\n";
    echo "  `expires_at` timestamp NOT NULL,\n";
    echo "  PRIMARY KEY (`id`),\n";
    echo "  UNIQUE KEY (`user_id`,`cache_key`)\n";
    echo ") ENGINE=InnoDB;\n\n";
    
    echo "DROP TABLE IF EXISTS `permission_audit_log`;\n";
    echo "CREATE TABLE `permission_audit_log` (\n";
    echo "  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,\n";
    echo "  `user_id` int(10) unsigned DEFAULT NULL,\n";
    echo "  `action` varchar(50) NOT NULL,\n";
    echo "  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,\n";
    echo "  PRIMARY KEY (`id`)\n";
    echo ") ENGINE=InnoDB;\n";
    echo "</textarea>";
}
?>
