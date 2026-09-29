<?php
require_once 'config/database.php';

echo "<h2>Checking Permissions System</h2><pre>";

// Check roles
echo "=== ROLES ===\n";
$stmt = $db->query("SELECT * FROM roles");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($roles as $role) {
    echo "ID: {$role['id']}, Name: {$role['name']}, Display: {$role['display_name']}\n";
}

// Check permissions
echo "\n=== PERMISSIONS (first 10) ===\n";
$stmt = $db->query("SELECT * FROM permissions LIMIT 10");
$perms = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($perms as $perm) {
    echo "ID: {$perm['id']}, Key: {$perm['permission_key']}, Name: {$perm['permission_name']}\n";
}

// Check role_permissions for user 11
echo "\n=== ROLE_PERMISSIONS for User 11 ===\n";
$stmt = $db->query("
    SELECT u.full_name, u.role_id, r.display_name as role_name,
           p.permission_key, rp.can_view, rp.can_add, rp.can_edit
    FROM users u
    LEFT JOIN roles r ON u.role_id = r.id
    LEFT JOIN role_permissions rp ON r.id = rp.role_id
    LEFT JOIN permissions p ON rp.permission_id = p.id
    WHERE u.id = 11
");
$user_perms = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($user_perms)) {
    echo "No permissions found for user 11\n";
} else {
    foreach ($user_perms as $up) {
        echo "User: {$up['full_name']}, Role: {$up['role_name']}, ";
        echo "Permission: {$up['permission_key']}, ";
        echo "View: {$up['can_view']}, Add: {$up['can_add']}, Edit: {$up['can_edit']}\n";
    }
}

// Check what's in role_permissions table
echo "\n=== ALL ROLE_PERMISSIONS ===\n";
$stmt = $db->query("SELECT COUNT(*) as total FROM role_permissions");
$count = $stmt->fetch();
echo "Total entries: {$count['total']}\n";

$stmt = $db->query("
    SELECT rp.*, r.display_name as role_name, p.permission_key
    FROM role_permissions rp
    JOIN roles r ON rp.role_id = r.id
    JOIN permissions p ON rp.permission_id = p.id
    LIMIT 20
");
$all_rp = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($all_rp as $rp) {
    echo "Role: {$rp['role_name']}, Permission: {$rp['permission_key']}, ";
    echo "View: {$rp['can_view']}, Add: {$rp['can_add']}, Edit: {$rp['can_edit']}\n";
}

echo "</pre>";
?>
