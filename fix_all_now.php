<?php
/**
 * Complete Fix - Add role_id column and create missing tables
 * Standalone script with embedded credentials
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Complete System Fix</h1><pre>";

// Database credentials (hardcoded for reliability)
$host = 'localhost';
$dbname = 'taksroide-db';
$username = 'taksroide-user';
$password = 'gliE87jMZfZkyBeaoUzm';

try {
    // Connect to database
    echo "1. Connecting to database...\n";
    $db = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "   ✅ Connected successfully\n\n";
    
    // Check if role_id column exists
    echo "2. Checking users table structure...\n";
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'role_id'");
    $role_id_exists = $stmt->fetch();
    
    if ($role_id_exists) {
        echo "   ✅ role_id column already exists\n\n";
    } else {
        echo "   Adding role_id column...\n";
        $db->exec("ALTER TABLE users ADD COLUMN role_id INT UNSIGNED NULL AFTER email");
        $db->exec("ALTER TABLE users ADD INDEX idx_role_id (role_id)");
        echo "   ✅ role_id column added\n\n";
    }
    
    // Check and create missing tables
    echo "3. Checking RBAC tables...\n";
    
    // Check role_permissions
    $stmt = $db->query("SHOW TABLES LIKE 'role_permissions'");
    if (!$stmt->fetch()) {
        echo "   Creating role_permissions table...\n";
        $db->exec("
            CREATE TABLE role_permissions (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              role_id INT UNSIGNED NOT NULL,
              permission_id INT UNSIGNED NOT NULL,
              can_view TINYINT(1) DEFAULT 0,
              can_add TINYINT(1) DEFAULT 0,
              can_edit TINYINT(1) DEFAULT 0,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
              UNIQUE KEY unique_role_permission (role_id, permission_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "   ✅ role_permissions created\n";
    } else {
        echo "   ✅ role_permissions exists\n";
    }
    
    // Check permission_cache
    $stmt = $db->query("SHOW TABLES LIKE 'permission_cache'");
    if (!$stmt->fetch()) {
        echo "   Creating permission_cache table...\n";
        $db->exec("
            CREATE TABLE permission_cache (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              user_id INT UNSIGNED NOT NULL,
              cache_key VARCHAR(255) NOT NULL,
              cache_value TEXT,
              expires_at TIMESTAMP NOT NULL,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
              UNIQUE KEY unique_cache (user_id, cache_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "   ✅ permission_cache created\n";
    } else {
        echo "   ✅ permission_cache exists\n";
    }
    
    // Check permission_audit_log
    $stmt = $db->query("SHOW TABLES LIKE 'permission_audit_log'");
    if (!$stmt->fetch()) {
        echo "   Creating permission_audit_log table...\n";
        $db->exec("
            CREATE TABLE permission_audit_log (
              id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
              user_id INT UNSIGNED,
              role_id INT UNSIGNED,
              permission_id INT UNSIGNED,
              action VARCHAR(50) NOT NULL,
              old_value TEXT,
              new_value TEXT,
              changed_by INT UNSIGNED,
              created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        echo "   ✅ permission_audit_log created\n";
    } else {
        echo "   ✅ permission_audit_log exists\n";
    }
    
    echo "\n4. Verifying users table structure...\n";
    $stmt = $db->query("DESCRIBE users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "   Columns: " . implode(', ', $columns) . "\n";
    
    echo "\n";
    echo "═══════════════════════════════════════\n";
    echo "✅ ALL FIXES COMPLETED SUCCESSFULLY!\n";
    echo "═══════════════════════════════════════\n\n";
    
    echo "Next steps:\n";
    echo "1. Go to: https://taksoride.com/modules/financial/employee-permissions-fixed.php?user_id=13\n";
    echo "2. The page should now work!\n";
    echo "3. Select a role and assign it to user 13\n\n";
    
    echo "</pre>";
    echo "<p><a href='modules/financial/employee-permissions-fixed.php?user_id=13' style='padding: 15px 30px; background: #10b981; color: white; text-decoration: none; border-radius: 8px; display: inline-block; font-weight: bold;'>Go to Employee Permissions Page</a></p>";
    
} catch (PDOException $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n\n";
    echo "Error Code: " . $e->getCode() . "\n";
    echo "\nIf you see this error, the database credentials might be wrong.\n";
    echo "Check your config/database.php file.\n";
}
?>
