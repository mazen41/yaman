<?php
require_once 'config/database.php';

$test_permissions = ['customers_view', 'customers_edit'];
$user_id = 2;
$granted_by = 1;

echo "=== Testing Permission Insertion ===\n\n";

foreach ($test_permissions as $permission_key) {
    echo "Testing: $permission_key\n";
    
    // Check if permission exists
    $check = $db->prepare("SELECT id, permission_key, permission_name FROM permissions WHERE permission_key = ?");
    $check->execute([$permission_key]);
    $perm = $check->fetch(PDO::FETCH_ASSOC);
    
    if ($perm) {
        echo "  ✓ Permission exists: ID={$perm['id']}, Name={$perm['permission_name']}\n";
        
        // Try to insert
        try {
            $insert = $db->prepare("
                INSERT INTO user_permissions (user_id, permission_id, granted_by) 
                SELECT ?, id, ? FROM permissions WHERE permission_key = ?
            ");
            $insert->execute([$user_id, $granted_by, $permission_key]);
            $rows = $insert->rowCount();
            
            if ($rows > 0) {
                echo "  ✓ Successfully inserted (would insert $rows row)\n";
                // Rollback to not actually save
                $db->exec("DELETE FROM user_permissions WHERE user_id = $user_id AND permission_id = {$perm['id']}");
            } else {
                echo "  ✗ Insert returned 0 rows (permission might already exist)\n";
            }
        } catch (PDOException $e) {
            echo "  ✗ Insert failed: " . $e->getMessage() . "\n";
        }
    } else {
        echo "  ✗ Permission NOT FOUND in database!\n";
    }
    echo "\n";
}

echo "=== Checking existing user permissions ===\n";
$existing = $db->prepare("
    SELECT up.id, p.permission_key, p.permission_name 
    FROM user_permissions up
    JOIN permissions p ON up.permission_id = p.id
    WHERE up.user_id = ?
");
$existing->execute([$user_id]);
$results = $existing->fetchAll(PDO::FETCH_ASSOC);

echo "User $user_id has " . count($results) . " permissions:\n";
foreach ($results as $r) {
    echo "  - {$r['permission_key']}: {$r['permission_name']}\n";
}
