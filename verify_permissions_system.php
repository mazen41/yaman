<?php
/**
 * Permission System Verification Tool
 * Tests the entire permission system logic
 */
session_start();
require_once 'config/database.php';
require_once 'includes/check_permissions.php';

header('Content-Type: text/html; charset=utf-8');

$user_id = $_SESSION['user_id'] ?? 0;

echo "<div dir='rtl' style='font-family: Arial; padding: 20px; max-width: 1200px; margin: 0 auto;'>";
echo "<h1 style='color: #1e40af;'>🔍 فحص نظام الصلاحيات</h1>";

// Current user info
echo "<div style='background: #f0f9ff; padding: 15px; border-radius: 10px; margin-bottom: 20px;'>";
echo "<h2>👤 المستخدم الحالي</h2>";
if ($user_id > 0) {
    $user = $db->prepare("SELECT id, username, full_name, email, is_admin FROM users WHERE id = ?");
    $user->execute([$user_id]);
    $user_data = $user->fetch(PDO::FETCH_ASSOC);
    
    echo "<p><strong>ID:</strong> {$user_data['id']}</p>";
    echo "<p><strong>الاسم:</strong> {$user_data['full_name']}</p>";
    echo "<p><strong>البريد:</strong> {$user_data['email']}</p>";
    echo "<p><strong>Admin:</strong> " . ($user_data['is_admin'] ? '✅ نعم' : '❌ لا') . "</p>";
    
    $is_admin = isUserAdmin($user_id, $db);
    echo "<p><strong>isUserAdmin():</strong> " . ($is_admin ? '✅ TRUE' : '❌ FALSE') . "</p>";
} else {
    echo "<p style='color: red;'>❌ لم يتم تسجيل الدخول!</p>";
}
echo "</div>";

// All modules to test
$modules = [
    'dashboard' => 'الصفحة الرئيسية',
    'customers' => 'إدارة العملاء',
    'customer_types' => 'أنواع العملاء',
    'cities' => 'المدن',
    'orders' => 'طلبات العملاء',
    'financial_review' => 'المراجعة المالية',
    'purchase_groups' => 'مجموعات الشراء',
    'baskets' => 'سلات الشراء',
    'purchase_cards' => 'بطاقات الشراء',
    'loyalty_cards' => 'بطاقات الهدية',
    'shipping' => 'إدارة الشحن',
    'whatsapp' => 'رسائل الواتساب',
    'financial' => 'الحسابات المالية',
    'coupons' => 'الكوبونات',
    'reports' => 'التقارير',
    'settings' => 'الإعدادات',
];

// Test permissions for current user
echo "<h2>📋 صلاحيات المستخدم الحالي</h2>";
echo "<table style='width: 100%; border-collapse: collapse; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>";
echo "<thead style='background: #1e40af; color: white;'>";
echo "<tr><th style='padding: 12px; text-align: right;'>الوحدة</th>";
echo "<th style='padding: 12px; text-align: center;'>عرض</th>";
echo "<th style='padding: 12px; text-align: center;'>تعديل</th>";
echo "<th style='padding: 12px; text-align: center;'>إضافة</th></tr>";
echo "</thead><tbody>";

foreach ($modules as $key => $name) {
    $view = hasPermission($user_id, $key, 'view');
    $edit = hasPermission($user_id, $key, 'edit');
    $add = hasPermission($user_id, $key, 'add');
    
    $row_bg = ($view || $edit || $add) ? '#f0fdf4' : '#fef2f2';
    
    echo "<tr style='background: $row_bg; border-bottom: 1px solid #e5e7eb;'>";
    echo "<td style='padding: 10px; font-weight: bold;'>$name <span style='color: #6b7280; font-size: 12px;'>($key)</span></td>";
    echo "<td style='padding: 10px; text-align: center;'>" . ($view ? '✅' : '❌') . "</td>";
    echo "<td style='padding: 10px; text-align: center;'>" . ($edit ? '✅' : '❌') . "</td>";
    echo "<td style='padding: 10px; text-align: center;'>" . ($add ? '✅' : '❌') . "</td>";
    echo "</tr>";
}
echo "</tbody></table>";

// Show saved permissions in database
echo "<h2 style='margin-top: 30px;'>💾 الصلاحيات المحفوظة في قاعدة البيانات</h2>";
$saved = $db->prepare("
    SELECT p.permission_key, p.permission_type, p.module_name
    FROM user_permissions up
    JOIN permissions p ON up.permission_id = p.id
    WHERE up.user_id = ?
    ORDER BY p.permission_key
");
$saved->execute([$user_id]);
$saved_perms = $saved->fetchAll(PDO::FETCH_ASSOC);

if (empty($saved_perms)) {
    echo "<p style='background: #fef3c7; padding: 15px; border-radius: 10px; color: #92400e;'>";
    echo "⚠️ لا توجد صلاحيات محفوظة لهذا المستخدم في جدول user_permissions";
    echo "</p>";
} else {
    echo "<div style='display: flex; flex-wrap: wrap; gap: 10px;'>";
    foreach ($saved_perms as $p) {
        $type = $p['permission_type'];
        $colors = [
            'view' => ['bg' => '#dbeafe', 'text' => '#1e40af'],
            'edit' => ['bg' => '#fef3c7', 'text' => '#92400e'],
            'add' => ['bg' => '#d1fae5', 'text' => '#065f46']
        ];
        $c = $colors[$type] ?? ['bg' => '#f3f4f6', 'text' => '#374151'];
        echo "<span style='background: {$c['bg']}; color: {$c['text']}; padding: 8px 15px; border-radius: 20px; font-size: 14px; font-weight: bold;'>";
        echo "{$p['permission_key']}";
        echo "</span>";
    }
    echo "</div>";
}

// Test specific scenarios
echo "<h2 style='margin-top: 30px;'>🧪 اختبار السيناريوهات</h2>";
echo "<div style='background: #f8fafc; padding: 20px; border-radius: 10px;'>";

$tests = [
    ['module' => 'customers', 'action' => 'view', 'desc' => 'هل يمكن فتح صفحة العملاء؟'],
    ['module' => 'customers', 'action' => 'add', 'desc' => 'هل يظهر زر إضافة عميل؟'],
    ['module' => 'customers', 'action' => 'edit', 'desc' => 'هل تظهر أزرار التعديل؟'],
    ['module' => 'orders', 'action' => 'view', 'desc' => 'هل يمكن فتح صفحة الطلبات؟'],
    ['module' => 'orders', 'action' => 'add', 'desc' => 'هل يظهر زر إنشاء طلب؟'],
];

foreach ($tests as $test) {
    $result = hasPermission($user_id, $test['module'], $test['action']);
    $icon = $result ? '✅' : '❌';
    $color = $result ? '#065f46' : '#991b1b';
    echo "<p style='margin: 10px 0;'><span style='color: $color; font-weight: bold;'>$icon</span> {$test['desc']}</p>";
}
echo "</div>";

// Quick links
echo "<h2 style='margin-top: 30px;'>🔗 روابط سريعة للاختبار</h2>";
echo "<div style='display: flex; flex-wrap: wrap; gap: 10px;'>";
$links = [
    '/modules/customers/' => 'العملاء',
    '/modules/customers/customer_types.php' => 'أنواع العملاء',
    '/modules/orders/' => 'الطلبات',
    '/modules/purchases/show_baskets.php' => 'السلات',
    '/modules/financial/employee-permissions.php' => 'إدارة الصلاحيات',
];
foreach ($links as $url => $label) {
    echo "<a href='$url' style='background: #1e40af; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: bold;'>$label</a>";
}
echo "</div>";

echo "</div>";
