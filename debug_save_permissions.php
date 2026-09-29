<?php
/**
 * Debug: Test saving permissions
 */
session_start();
require_once 'config/database.php';
header('Content-Type: text/html; charset=utf-8');

echo "<div dir='rtl' style='font-family: Arial; padding: 20px;'>";
echo "<h1>🔧 تشخيص حفظ الصلاحيات</h1>";

// Get all users
echo "<h2>قائمة المستخدمين:</h2>";
$users = $db->query("SELECT id, username, full_name, email, is_admin FROM users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
echo "<table border='1' cellpadding='5'>";
echo "<tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Is Admin</th><th>عدد الصلاحيات</th></tr>";

foreach ($users as $u) {
    $perm_count = $db->query("SELECT COUNT(*) FROM user_permissions WHERE user_id = {$u['id']}")->fetchColumn();
    echo "<tr>";
    echo "<td>{$u['id']}</td>";
    echo "<td>{$u['username']}</td>";
    echo "<td>{$u['full_name']}</td>";
    echo "<td>{$u['email']}</td>";
    echo "<td>{$u['is_admin']}</td>";
    echo "<td><strong>$perm_count</strong></td>";
    echo "</tr>";
}
echo "</table>";

// Test delete and insert for user 20
if (isset($_GET['test_user'])) {
    $test_user_id = intval($_GET['test_user']);
    echo "<h2>اختبار حذف وإضافة صلاحيات للمستخدم $test_user_id:</h2>";
    
    // Show current permissions
    echo "<h3>الصلاحيات الحالية:</h3>";
    $current = $db->prepare("SELECT up.*, p.permission_key FROM user_permissions up JOIN permissions p ON up.permission_id = p.id WHERE up.user_id = ?");
    $current->execute([$test_user_id]);
    $current_perms = $current->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($current_perms);
    echo "</pre>";
    
    if (isset($_GET['delete_all'])) {
        // Delete all permissions
        $del = $db->prepare("DELETE FROM user_permissions WHERE user_id = ?");
        $del->execute([$test_user_id]);
        echo "<p style='color:green;'>✓ تم حذف جميع الصلاحيات للمستخدم $test_user_id</p>";
        echo "<p><a href='?test_user=$test_user_id'>تحديث</a></p>";
    }
    
    if (isset($_GET['add_view_only'])) {
        // Add only view permission
        $db->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$test_user_id]);
        
        $perm_id = $db->query("SELECT id FROM permissions WHERE permission_key = 'customers_view'")->fetchColumn();
        if ($perm_id) {
            $db->prepare("INSERT INTO user_permissions (user_id, permission_id, granted_by) VALUES (?, ?, 1)")->execute([$test_user_id, $perm_id]);
            echo "<p style='color:green;'>✓ تم إضافة صلاحية customers_view فقط</p>";
        }
        echo "<p><a href='?test_user=$test_user_id'>تحديث</a></p>";
    }
    
    if (isset($_GET['add_all'])) {
        // Add all permissions
        $db->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$test_user_id]);
        
        $perms = ['customers_view', 'customers_edit', 'customers_add'];
        foreach ($perms as $p) {
            $perm_id = $db->query("SELECT id FROM permissions WHERE permission_key = '$p'")->fetchColumn();
            if ($perm_id) {
                $db->prepare("INSERT INTO user_permissions (user_id, permission_id, granted_by) VALUES (?, ?, 1)")->execute([$test_user_id, $perm_id]);
                echo "<p style='color:green;'>✓ تم إضافة: $p</p>";
            }
        }
        echo "<p><a href='?test_user=$test_user_id'>تحديث</a></p>";
    }
    
    echo "<h3>الإجراءات:</h3>";
    echo "<p><a href='?test_user=$test_user_id&delete_all=1' style='background:red; color:white; padding:10px; margin:5px;'>🗑️ حذف كل الصلاحيات</a></p>";
    echo "<p><a href='?test_user=$test_user_id&add_view_only=1' style='background:blue; color:white; padding:10px; margin:5px;'>👁️ إضافة عرض فقط</a></p>";
    echo "<p><a href='?test_user=$test_user_id&add_all=1' style='background:green; color:white; padding:10px; margin:5px;'>✅ إضافة الكل (عرض+تعديل+إضافة)</a></p>";
}

echo "<h2>اختر مستخدم للاختبار:</h2>";
foreach ($users as $u) {
    if ($u['is_admin'] == 0) {
        echo "<a href='?test_user={$u['id']}' style='display:inline-block; margin:5px; padding:10px; background:#f0f0f0; border-radius:5px;'>{$u['full_name']} (ID: {$u['id']})</a> ";
    }
}

echo "</div>";
