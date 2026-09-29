<?php
require_once 'config/database.php';

$user_id = 2;

echo "=== Verifying User Permissions ===\n\n";

// Check what's in user_permissions table
$stmt = $db->prepare("
    SELECT up.id, up.user_id, up.permission_id, p.permission_key, p.permission_name, p.permission_type
    FROM user_permissions up
    JOIN permissions p ON up.permission_id = p.id
    WHERE up.user_id = ?
    ORDER BY p.permission_key
");
$stmt->execute([$user_id]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "User ID: $user_id\n";
echo "Total permissions: " . count($results) . "\n\n";

if (count($results) > 0) {
    echo "Permissions list:\n";
    foreach ($results as $i => $row) {
        echo ($i + 1) . ". {$row['permission_key']} ({$row['permission_type']}) - {$row['permission_name']}\n";
    }
} else {
    echo "⚠️ NO PERMISSIONS FOUND for user $user_id!\n";
}

echo "\n=== Checking if permissions exist in permissions table ===\n";
$check = $db->query("SELECT COUNT(*) as count FROM permissions WHERE permission_key LIKE '%_view' OR permission_key LIKE '%_edit' OR permission_key LIKE '%_add'")->fetch();
echo "Total granular permissions in database: {$check['count']}\n";

echo "\n=== Sample permissions that should exist ===\n";
$samples = ['customers_view', 'customers_edit', 'customers_add', 'orders_view', 'orders_edit'];
foreach ($samples as $key) {
    $check = $db->prepare("SELECT id, permission_name FROM permissions WHERE permission_key = ?");
    $check->execute([$key]);
    $result = $check->fetch();
    if ($result) {
        echo "✓ $key exists (ID: {$result['id']})\n";
    } else {
        echo "✗ $key NOT FOUND\n";
    }
}
