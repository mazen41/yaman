<?php
/**
 * Fix Permissions System - Creates/Updates permissions table with all required entries
 * Run once via browser, then DELETE this file for security.
 */

session_start();
require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h1>إصلاح نظام الصلاحيات</h1>";
echo "<pre style='direction:ltr; text-align:left;'>";

try {
    // 1) Check if permissions table exists
    $tables = $db->query("SHOW TABLES LIKE 'permissions'")->fetchAll();
    
    if (empty($tables)) {
        echo "Creating permissions table...\n";
        $db->exec("
            CREATE TABLE permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                permission_key VARCHAR(100) NOT NULL UNIQUE,
                permission_type VARCHAR(50) NOT NULL,
                module_name VARCHAR(100) NOT NULL,
                description VARCHAR(255),
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ permissions table created.\n";
    } else {
        echo "✓ permissions table exists.\n";
    }
    
    // 2) Check if user_permissions table exists
    $tables2 = $db->query("SHOW TABLES LIKE 'user_permissions'")->fetchAll();
    
    if (empty($tables2)) {
        echo "Creating user_permissions table...\n";
        $db->exec("
            CREATE TABLE user_permissions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                permission_id INT NOT NULL,
                granted_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_permission (user_id, permission_id),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✓ user_permissions table created.\n";
    } else {
        echo "✓ user_permissions table exists.\n";
    }
    
    // 3) Define all sidebar modules with their permissions (matching dynamic_sidebar.php)
    $sidebar_modules = [
        ['key' => 'customers', 'name' => 'إدارة العملاء', 'permissions' => ['view', 'edit', 'add']],
        ['key' => 'customer_invoices', 'name' => 'فواتير العملاء', 'permissions' => ['view', 'edit', 'add']],
        ['key' => 'customer_types', 'name' => 'أنواع العملاء', 'permissions' => ['view', 'edit', 'add']],
        ['key' => 'cities', 'name' => 'المدن', 'permissions' => ['view', 'edit', 'add']],
        ['key' => 'orders', 'name' => 'طلبات العملاء', 'permissions' => ['view', 'edit', 'add']],
        ['key' => 'financial_review', 'name' => 'المراجعة المالية', 'permissions' => ['view', 'edit']],
        ['key' => 'purchases', 'name' => 'إدارة المشتريات', 'permissions' => ['view', 'edit', 'add']],
        ['key' => 'purchase_groups', 'name' => 'مجموعات الشراء', 'permissions' => ['view', 'edit', 'add']],
        ['key' => 'suppliers', 'name' => 'إدارة الموردين', 'permissions' => ['view', 'edit', 'add']],
        ['key' => 'baskets', 'name' => 'سلات الشراء', 'permissions' => ['view', 'edit', 'add']],
        ['key' => 'purchase_cards', 'name' => 'إدارة بطاقات الشراء', 'permissions' => ['view', 'edit', 'add']],
        ['key' => 'loyalty_cards', 'name' => 'بطاقات الهدية', 'permissions' => ['view', 'edit', 'add']],
        ['key' => 'shipping', 'name' => 'إدارة الشحن', 'permissions' => ['view', 'edit', 'add']],
        ['key' => 'whatsapp', 'name' => 'رسائل الواتساب', 'permissions' => ['view', 'add']],
        ['key' => 'inventory', 'name' => 'إدارة المخزون', 'permissions' => ['view', 'edit', 'add']],
        ['key' => 'financial', 'name' => 'الحسابات المالية', 'permissions' => ['view', 'edit', 'add']],
        ['key' => 'bank_accounts', 'name' => 'إدارة الحسابات البنكية', 'permissions' => ['view', 'edit', 'add']],
        ['key' => 'employees', 'name' => 'إدارة الموظفين', 'permissions' => ['view', 'edit', 'add']],
        ['key' => 'permissions', 'name' => 'صلاحيات الموظفين', 'permissions' => ['view', 'edit']],
        ['key' => 'expenses', 'name' => 'إدارة المصروفات', 'permissions' => ['view', 'edit', 'add']],
        ['key' => 'coupons', 'name' => 'إدارة الكوبونات', 'permissions' => ['view', 'edit', 'add']],
        ['key' => 'reports', 'name' => 'التقارير والطباعة', 'permissions' => ['view']],
        ['key' => 'settings', 'name' => 'إعدادات النظام', 'permissions' => ['view', 'edit']],
    ];
    
    $permission_labels = [
        'view' => 'عرض',
        'edit' => 'تعديل',
        'add' => 'إضافة'
    ];
    
    // 4) Insert all permissions
    echo "\nInserting/Updating permissions...\n";
    
    $insert_stmt = $db->prepare("
        INSERT IGNORE INTO permissions (permission_key, permission_type, module_name, description)
        VALUES (?, ?, ?, ?)
    ");
    
    $count = 0;
    foreach ($sidebar_modules as $module) {
        foreach ($module['permissions'] as $perm_type) {
            $permission_key = $module['key'] . '_' . $perm_type;
            $description = $permission_labels[$perm_type] . ' ' . $module['name'];
            
            $insert_stmt->execute([
                $permission_key,
                $perm_type,
                $module['key'],
                $description
            ]);
            
            if ($insert_stmt->rowCount() > 0) {
                echo "  + Added: $permission_key\n";
                $count++;
            }
        }
    }
    
    echo "\n✓ Added $count new permissions.\n";
    
    // 5) Show current permissions count
    $total = $db->query("SELECT COUNT(*) FROM permissions")->fetchColumn();
    echo "✓ Total permissions in database: $total\n";
    
    // 6) Show sample permissions
    echo "\nSample permissions:\n";
    $sample = $db->query("SELECT permission_key, module_name, permission_type FROM permissions LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($sample as $p) {
        echo "  - {$p['permission_key']} ({$p['module_name']}.{$p['permission_type']})\n";
    }
    
    echo "\n</pre>";
    echo "<h2 style='color:green;'>✓ تم إصلاح نظام الصلاحيات بنجاح!</h2>";
    echo "<p>الآن يمكنك:</p>";
    echo "<ol>";
    echo "<li>فتح صفحة <a href='/modules/financial/employee_permissions.php'>إدارة صلاحيات الموظفين</a></li>";
    echo "<li>اختيار موظف من القائمة</li>";
    echo "<li>تحديد الصلاحيات المطلوبة (عرض - تعديل - إضافة) لكل صفحة</li>";
    echo "<li>حفظ الصلاحيات</li>";
    echo "</ol>";
    echo "<p style='color:red;'><strong>مهم:</strong> احذف هذا الملف بعد الانتهاء!</p>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
