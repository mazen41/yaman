<?php
require_once 'config/database.php';
header('Content-Type: text/html; charset=utf-8');

echo "<h2>فحص صلاحيات المستخدم 20 (yasson)</h2>";

// Check user_permissions for user 20
$stmt = $db->prepare("SELECT * FROM user_permissions WHERE user_id = 20");
$stmt->execute();
$perms = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<h3>صلاحيات المستخدم 20 في جدول user_permissions:</h3>";
echo "<pre>";
print_r($perms);
echo "</pre>";

// Check if user 20 exists
$user = $db->prepare("SELECT id, username, full_name, is_admin FROM users WHERE id = 20");
$user->execute();
$user_data = $user->fetch(PDO::FETCH_ASSOC);

echo "<h3>بيانات المستخدم:</h3>";
echo "<pre>";
print_r($user_data);
echo "</pre>";

// Try to add a test permission
echo "<h3>اختبار إضافة صلاحية:</h3>";

if (isset($_GET['add_test'])) {
    try {
        // Get customers_view permission id
        $perm = $db->query("SELECT id FROM permissions WHERE permission_key = 'customers_view'")->fetch();
        
        if ($perm) {
            $stmt = $db->prepare("INSERT IGNORE INTO user_permissions (user_id, permission_id, granted_by) VALUES (20, ?, 1)");
            $stmt->execute([$perm['id']]);
            echo "<p style='color:green;'>✓ تم إضافة صلاحية customers_view للمستخدم 20</p>";
            
            // Verify
            $check = $db->prepare("SELECT * FROM user_permissions WHERE user_id = 20");
            $check->execute();
            echo "<pre>";
            print_r($check->fetchAll(PDO::FETCH_ASSOC));
            echo "</pre>";
        } else {
            echo "<p style='color:red;'>❌ صلاحية customers_view غير موجودة!</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color:red;'>❌ خطأ: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p><a href='?add_test=1' style='background:#4CAF50; color:white; padding:10px 20px; text-decoration:none;'>🧪 اختبار إضافة صلاحية</a></p>";
}

// Show structure of user_permissions
echo "<h3>هيكل جدول user_permissions:</h3>";
$cols = $db->query("SHOW COLUMNS FROM user_permissions")->fetchAll(PDO::FETCH_ASSOC);
echo "<pre>";
print_r($cols);
echo "</pre>";
