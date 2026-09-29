<?php
require_once 'config/database.php';

$user_id = 2;

echo "=== Checking JOIN Issue ===\n\n";

// Step 1: Check user_permissions table
echo "1. Checking user_permissions table:\n";
$up = $db->prepare("SELECT * FROM user_permissions WHERE user_id = ?");
$up->execute([$user_id]);
$up_results = $up->fetchAll(PDO::FETCH_ASSOC);
echo "   Found " . count($up_results) . " entries\n";
foreach ($up_results as $row) {
    echo "   - ID: {$row['id']}, permission_id: {$row['permission_id']}\n";
}

// Step 2: Check if those permission_ids exist in permissions table
echo "\n2. Checking if permission_ids exist in permissions table:\n";
foreach ($up_results as $row) {
    $perm_id = $row['permission_id'];
    $check = $db->prepare("SELECT id, permission_key, permission_name FROM permissions WHERE id = ?");
    $check->execute([$perm_id]);
    $perm = $check->fetch(PDO::FETCH_ASSOC);
    
    if ($perm) {
        echo "   ✓ permission_id $perm_id exists: {$perm['permission_key']} - {$perm['permission_name']}\n";
    } else {
        echo "   ✗ permission_id $perm_id NOT FOUND in permissions table!\n";
    }
}

// Step 3: Try the JOIN query
echo "\n3. Testing the JOIN query:\n";
$join = $db->prepare("
    SELECT p.permission_key, p.permission_type, p.module_name
    FROM user_permissions up
    JOIN permissions p ON up.permission_id = p.id
    WHERE up.user_id = ?
");
$join->execute([$user_id]);
$join_results = $join->fetchAll(PDO::FETCH_ASSOC);
echo "   JOIN returned " . count($join_results) . " rows\n";
foreach ($join_results as $row) {
    echo "   - {$row['permission_key']} ({$row['permission_type']})\n";
}

if (count($up_results) > 0 && count($join_results) == 0) {
    echo "\n⚠️ PROBLEM FOUND: user_permissions has data but JOIN returns nothing!\n";
    echo "This means the permission_ids in user_permissions don't match any ids in permissions table.\n";
}
