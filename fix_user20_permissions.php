<?php
/**
 * إضافة صلاحيات للمستخدم 20 (yasson) مباشرة
 */
require_once 'config/database.php';
header('Content-Type: text/html; charset=utf-8');

echo "<div dir='rtl' style='font-family: Arial; padding: 20px;'>";
echo "<h1>🔧 إضافة صلاحيات للمستخدم yasson (ID: 20)</h1>";

// Permissions to add
$permissions_to_add = [
    'customers_view',
    'customers_edit', 
    'customers_add',
    'orders_view',
    'orders_edit',
    'orders_add'
];

try {
    $db->beginTransaction();
    
    // First, ensure all permissions exist
    $ensure_stmt = $db->prepare("
        INSERT IGNORE INTO permissions (permission_key, permission_type, module_name, description)
        VALUES (?, ?, ?, ?)
    ");
    
    $permission_labels = ['view' => 'عرض', 'edit' => 'تعديل', 'add' => 'إضافة'];
    
    foreach ($permissions_to_add as $perm_key) {
        $parts = explode('_', $perm_key);
        $perm_type = array_pop($parts);
        $module_key = implode('_', $parts);
        $description = ($permission_labels[$perm_type] ?? $perm_type) . ' ' . $module_key;
        
        $ensure_stmt->execute([$perm_key, $perm_type, $module_key, $description]);
        echo "<p>✓ تأكيد وجود: $perm_key</p>";
    }
    
    // Delete existing permissions for user 20
    $db->prepare("DELETE FROM user_permissions WHERE user_id = 20")->execute();
    echo "<p>✓ حذف الصلاحيات القديمة</p>";
    
    // Insert new permissions
    $insert_stmt = $db->prepare("
        INSERT INTO user_permissions (user_id, permission_id, granted_by)
        SELECT 20, id, 1 FROM permissions WHERE permission_key = ?
    ");
    
    $count = 0;
    foreach ($permissions_to_add as $perm_key) {
        $insert_stmt->execute([$perm_key]);
        if ($insert_stmt->rowCount() > 0) {
            $count++;
            echo "<p style='color:green;'>✓ تم إضافة: $perm_key</p>";
        } else {
            echo "<p style='color:orange;'>⚠️ لم يتم إضافة: $perm_key (قد تكون موجودة)</p>";
        }
    }
    
    $db->commit();
    
    echo "<h2 style='color:green;'>✅ تم إضافة $count صلاحية بنجاح!</h2>";
    
    // Verify
    echo "<h3>التحقق من الصلاحيات:</h3>";
    $verify = $db->prepare("
        SELECT up.*, p.permission_key 
        FROM user_permissions up 
        JOIN permissions p ON up.permission_id = p.id 
        WHERE up.user_id = 20
    ");
    $verify->execute();
    $results = $verify->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<ul>";
    foreach ($results as $r) {
        echo "<li><strong>{$r['permission_key']}</strong></li>";
    }
    echo "</ul>";
    
    echo "<p><a href='/test_permissions.php'>🔍 اختبار الصلاحيات الآن</a></p>";
    echo "<p><a href='/modules/customers/'>📋 فتح صفحة العملاء</a></p>";
    
} catch (PDOException $e) {
    $db->rollBack();
    echo "<p style='color:red;'>❌ خطأ: " . $e->getMessage() . "</p>";
}

echo "</div>";
