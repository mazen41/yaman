<?php
session_start();
require_once 'config/database.php';
require_once 'includes/check_permissions.php';

header('Content-Type: text/html; charset=utf-8');

echo "<div dir='rtl' style='font-family: Arial; padding: 20px;'>";
echo "<h1>🔍 اختبار الصلاحيات</h1>";

$user_id = $_SESSION['user_id'] ?? 0;
echo "<p><strong>User ID:</strong> $user_id</p>";

if ($user_id == 0) {
    echo "<p style='color:red;'>❌ لم يتم تسجيل الدخول!</p>";
    exit;
}

// Get user info
$user = $db->prepare("SELECT * FROM users WHERE id = ?");
$user->execute([$user_id]);
$user_data = $user->fetch(PDO::FETCH_ASSOC);
echo "<p><strong>Username:</strong> " . ($user_data['username'] ?? 'N/A') . "</p>";
echo "<p><strong>Is Admin:</strong> " . ($user_data['is_admin'] ?? '0') . "</p>";

echo "<h2>صلاحيات العملاء (customers)</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>الصلاحية</th><th>Permission Key</th><th>النتيجة</th></tr>";

$perms = ['view', 'edit', 'add'];
foreach ($perms as $p) {
    $key = "customers_$p";
    $result = hasPermission($user_id, 'customers', $p);
    $color = $result ? 'green' : 'red';
    $icon = $result ? '✅' : '❌';
    echo "<tr><td>$p</td><td>$key</td><td style='color:$color;'>$icon " . ($result ? 'نعم' : 'لا') . "</td></tr>";
}
echo "</table>";

echo "<h2>صلاحيات الطلبات (orders)</h2>";
echo "<table border='1' cellpadding='10'>";
echo "<tr><th>الصلاحية</th><th>Permission Key</th><th>النتيجة</th></tr>";

foreach ($perms as $p) {
    $key = "orders_$p";
    $result = hasPermission($user_id, 'orders', $p);
    $color = $result ? 'green' : 'red';
    $icon = $result ? '✅' : '❌';
    echo "<tr><td>$p</td><td>$key</td><td style='color:$color;'>$icon " . ($result ? 'نعم' : 'لا') . "</td></tr>";
}
echo "</table>";

echo "<h2>الصلاحيات المحفوظة لهذا المستخدم</h2>";
$stmt = $db->prepare("
    SELECT p.permission_key, p.permission_type, p.module_name 
    FROM user_permissions up 
    JOIN permissions p ON up.permission_id = p.id 
    WHERE up.user_id = ?
");
$stmt->execute([$user_id]);
$user_perms = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($user_perms)) {
    echo "<p style='color:orange;'>⚠️ لا توجد صلاحيات محفوظة لهذا المستخدم!</p>";
} else {
    echo "<ul>";
    foreach ($user_perms as $up) {
        echo "<li><strong>{$up['permission_key']}</strong> ({$up['permission_type']})</li>";
    }
    echo "</ul>";
}

echo "<h2>اختبار الدوال القديمة</h2>";
echo "<ul>";
echo "<li>canViewCustomers(): " . (canViewCustomers() ? '✅ نعم' : '❌ لا') . "</li>";
echo "<li>canEditCustomers(): " . (canEditCustomers() ? '✅ نعم' : '❌ لا') . "</li>";
echo "<li>canAddCustomers(): " . (canAddCustomers() ? '✅ نعم' : '❌ لا') . "</li>";
echo "<li>canViewOrders(): " . (canViewOrders() ? '✅ نعم' : '❌ لا') . "</li>";
echo "<li>canEditOrders(): " . (canEditOrders() ? '✅ نعم' : '❌ لا') . "</li>";
echo "<li>canAddOrders(): " . (canAddOrders() ? '✅ نعم' : '❌ لا') . "</li>";
echo "</ul>";

echo "</div>";
