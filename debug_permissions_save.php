<?php
/**
 * Debug why permissions aren't saving
 */

require_once 'config/database.php';

// Simulate a form submission
$test_permission_keys = [
    'customers_view',
    'customers_edit',
    'customers_add',
    'orders_view'
];

echo "=== Testing Permission Save Logic ===\n\n";

echo "1. Checking if permission keys exist in database:\n";
foreach ($test_permission_keys as $key) {
    $stmt = $db->prepare("SELECT id, permission_key, permission_name FROM permissions WHERE permission_key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo "   ✓ Found: $key (ID: {$result['id']}) - {$result['permission_name']}\n";
    } else {
        echo "   ✗ NOT FOUND: $key\n";
    }
}

echo "\n2. Checking all permission keys in database:\n";
$all_perms = $db->query("SELECT permission_key, permission_name FROM permissions WHERE permission_key LIKE '%_view' OR permission_key LIKE '%_edit' OR permission_key LIKE '%_add' ORDER BY permission_key LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

echo "   Total granular permissions: " . count($all_perms) . "\n";
foreach ($all_perms as $perm) {
    echo "   - {$perm['permission_key']}: {$perm['permission_name']}\n";
}

echo "\n3. Testing INSERT query:\n";
$user_id = 2; // Test user
$granted_by = 1; // Admin

try {
    $db->beginTransaction();
    
    $insert_stmt = $db->prepare("
        INSERT INTO user_permissions (user_id, permission_id, granted_by) 
        SELECT ?, id, ? FROM permissions WHERE permission_key = ?
    ");
    
    $test_key = 'customers_view';
    $insert_stmt->execute([$user_id, $granted_by, $test_key]);
    $rows = $insert_stmt->rowCount();
    
    echo "   Attempted to insert: $test_key\n";
    echo "   Rows affected: $rows\n";
    
    if ($rows > 0) {
        echo "   ✓ SUCCESS: Permission would be inserted\n";
    } else {
        echo "   ✗ FAILED: No rows inserted (permission key not found?)\n";
    }
    
    $db->rollBack(); // Don't actually save
    
} catch (PDOException $e) {
    $db->rollBack();
    echo "   ✗ ERROR: " . $e->getMessage() . "\n";
}

echo "\n4. Checking form field names:\n";
echo "   Expected POST field: permissions[]\n";
echo "   Expected values: customers_view, customers_edit, etc.\n";

echo "\n=== Debug Complete ===\n";
