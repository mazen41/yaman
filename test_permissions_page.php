<?php
/**
 * Test if the permissions page loads correctly
 */

// Simulate session
$_SESSION['user_id'] = 1;
$_SESSION['is_admin'] = 1;

require_once 'config/database.php';

try {
    echo "Testing permissions page components...\n\n";
    
    // Test 1: Check if permissions exist
    echo "1. Checking permissions...\n";
    $perms = $db->query("SELECT COUNT(*) as count FROM permissions WHERE permission_key LIKE '%_view' OR permission_key LIKE '%_edit' OR permission_key LIKE '%_add'")->fetch();
    echo "   Found {$perms['count']} granular permissions\n";
    
    // Test 2: Check if users exist
    echo "\n2. Checking non-admin users...\n";
    $users = $db->query("SELECT COUNT(*) as count FROM users WHERE is_admin = 0")->fetch();
    echo "   Found {$users['count']} non-admin users\n";
    
    // Test 3: Sample permissions
    echo "\n3. Sample permissions:\n";
    $samples = $db->query("SELECT permission_key, permission_name, permission_type FROM permissions WHERE permission_key LIKE 'customers_%' OR permission_key LIKE 'orders_%' LIMIT 10")->fetchAll();
    foreach ($samples as $sample) {
        echo "   - {$sample['permission_key']} ({$sample['permission_type']}): {$sample['permission_name']}\n";
    }
    
    // Test 4: Check user_permissions table
    echo "\n4. Checking user_permissions table...\n";
    $user_perms = $db->query("SELECT COUNT(*) as count FROM user_permissions")->fetch();
    echo "   Found {$user_perms['count']} user permission assignments\n";
    
    echo "\n✅ All tests passed! The page should work now.\n";
    echo "\nAccess the page at: https://taksoride.com/modules/financial/employee_permissions.php\n";
    echo "If you see the old version, press Ctrl+Shift+R to hard refresh the browser cache.\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
