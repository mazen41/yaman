<?php
// Quick diagnostic
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Diagnostic Report</h2><pre>";

// Test 1: Database connection
echo "1. Testing database connection...\n";
try {
    require_once 'config/database.php';
    echo "   ✅ Database connection successful\n";
    echo "   Database: " . DB_NAME . "\n\n";
} catch (Exception $e) {
    echo "   ❌ Database connection failed: " . $e->getMessage() . "\n\n";
    die();
}

// Test 2: Check tables
echo "2. Checking required tables...\n";
$required_tables = ['roles', 'permissions', 'role_permissions', 'permission_cache', 'permission_audit_log'];
foreach ($required_tables as $table) {
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM `$table`");
        $count = $stmt->fetchColumn();
        echo "   ✅ $table exists ($count rows)\n";
    } catch (PDOException $e) {
        echo "   ❌ $table missing or error\n";
    }
}

echo "\n3. Testing file access...\n";
$test_files = [
    'modules/financial/employee-permissions.php',
    'modules/financial/employee-permissions-fixed.php',
    'includes/rbac_helpers.php'
];

foreach ($test_files as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file exists\n";
    } else {
        echo "   ❌ $file missing\n";
    }
}

echo "\n4. Session check...\n";
session_start();
if (isset($_SESSION['user_id'])) {
    echo "   ✅ User logged in: ID " . $_SESSION['user_id'] . "\n";
} else {
    echo "   ⚠️  No user logged in\n";
}

echo "\n</pre>";
echo "<p><a href='modules/financial/employee-permissions-fixed.php?user_id=13'>Test Fixed Page</a></p>";
?>
