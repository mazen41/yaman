<?php
/**
 * Create Missing Tables - Run this file once
 * URL: https://taksoride.com/create_tables_now.php
 */

session_start();

// Check if user is admin
if (!isset($_SESSION['user_id'])) {
    die("Please login first");
}

require_once 'config/database.php';

// Check if user is admin
$stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user || $user['is_admin'] != 1) {
    die("Access denied. Admin only.");
}

echo "<h1>Creating Missing Tables...</h1>";
echo "<pre>";

$errors = [];
$success = [];

// SQL for role_permissions
$sql1 = "
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
  UNIQUE KEY `unique_role_permission` (`role_id`,`permission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// SQL for permission_cache
$sql2 = "
DROP TABLE IF EXISTS `permission_cache`;
CREATE TABLE `permission_cache` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `cache_key` varchar(255) NOT NULL,
  `cache_value` text DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cache` (`user_id`,`cache_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// SQL for permission_audit_log
$sql3 = "
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
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";

// Create role_permissions
try {
    $db->exec($sql1);
    $success[] = "✅ role_permissions table created";
    echo "✅ role_permissions table created\n";
} catch (PDOException $e) {
    $errors[] = "❌ role_permissions: " . $e->getMessage();
    echo "❌ role_permissions: " . $e->getMessage() . "\n";
}

// Create permission_cache
try {
    $db->exec($sql2);
    $success[] = "✅ permission_cache table created";
    echo "✅ permission_cache table created\n";
} catch (PDOException $e) {
    $errors[] = "❌ permission_cache: " . $e->getMessage();
    echo "❌ permission_cache: " . $e->getMessage() . "\n";
}

// Create permission_audit_log
try {
    $db->exec($sql3);
    $success[] = "✅ permission_audit_log table created";
    echo "✅ permission_audit_log table created\n";
} catch (PDOException $e) {
    $errors[] = "❌ permission_audit_log: " . $e->getMessage();
    echo "❌ permission_audit_log: " . $e->getMessage() . "\n";
}

// Insert sample data
if (empty($errors)) {
    try {
        $db->exec("
            INSERT INTO `role_permissions` (`role_id`, `permission_id`, `can_view`, `can_add`, `can_edit`) VALUES
            (2, 1, 1, 1, 1),
            (2, 2, 1, 1, 0),
            (2, 3, 1, 0, 1),
            (2, 4, 1, 1, 0),
            (3, 1, 1, 0, 0),
            (3, 2, 1, 0, 0)
        ");
        $success[] = "✅ Sample data inserted";
        echo "✅ Sample data inserted\n";
    } catch (PDOException $e) {
        echo "⚠️ Sample data: " . $e->getMessage() . "\n";
    }
}

echo "\n\n";
echo "=================================\n";
echo "SUMMARY:\n";
echo "=================================\n";
echo "Success: " . count($success) . "\n";
echo "Errors: " . count($errors) . "\n";

if (empty($errors)) {
    echo "\n✅ ALL TABLES CREATED SUCCESSFULLY!\n\n";
    echo "Next steps:\n";
    echo "1. Go to: https://taksoride.com/setup_rbac.php\n";
    echo "2. Click 'Run Setup'\n";
    echo "3. Assign roles to users\n";
} else {
    echo "\n❌ SOME ERRORS OCCURRED:\n";
    foreach ($errors as $error) {
        echo "  - $error\n";
    }
}

// Verify tables exist
echo "\n\nVerifying tables...\n";
try {
    $stmt = $db->query("SHOW TABLES LIKE '%permission%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Found tables:\n";
    foreach ($tables as $table) {
        echo "  ✅ $table\n";
    }
} catch (PDOException $e) {
    echo "Error checking tables: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<h2>Done!</h2>";
echo "<p><a href='setup_rbac.php'>Go to Setup Page</a></p>";
echo "<p><a href='modules/financial/employee-permissions-fixed.php?user_id=13'>Go to Employee Permissions</a></p>";
?>
