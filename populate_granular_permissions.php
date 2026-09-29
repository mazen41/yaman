<?php
/**
 * Populate Granular Permissions (works with existing table structure)
 */

require_once 'config/database.php';

try {
    echo "Starting granular permissions population...\n";
    
    // Define all modules with granular permissions
    $modules = [
        ['key' => 'customers', 'name' => 'إدارة العملاء', 'types' => ['view', 'edit', 'add']],
        ['key' => 'customer_invoices', 'name' => 'فواتير العملاء', 'types' => ['view', 'edit', 'add']],
        ['key' => 'customer_types', 'name' => 'أنواع العملاء', 'types' => ['view', 'edit', 'add']],
        ['key' => 'cities', 'name' => 'المدن', 'types' => ['view', 'edit', 'add']],
        ['key' => 'orders', 'name' => 'طلبات العملاء', 'types' => ['view', 'edit', 'add']],
        ['key' => 'financial_review', 'name' => 'المراجعة المالية', 'types' => ['view', 'edit']],
        ['key' => 'purchases', 'name' => 'إدارة المشتريات', 'types' => ['view', 'edit', 'add']],
        ['key' => 'purchase_groups', 'name' => 'مجموعات الشراء', 'types' => ['view', 'edit', 'add']],
        ['key' => 'suppliers', 'name' => 'إدارة الموردين', 'types' => ['view', 'edit', 'add']],
        ['key' => 'baskets', 'name' => 'سلات الشراء', 'types' => ['view', 'edit', 'add']],
        ['key' => 'purchase_cards', 'name' => 'إدارة بطاقات الشراء', 'types' => ['view', 'edit', 'add']],
        ['key' => 'loyalty_cards', 'name' => 'بطاقات الهدية', 'types' => ['view', 'edit', 'add']],
        ['key' => 'shipping', 'name' => 'إدارة الشحن', 'types' => ['view', 'edit', 'add']],
        ['key' => 'whatsapp', 'name' => 'رسائل الواتساب', 'types' => ['view', 'add']],
        ['key' => 'inventory', 'name' => 'إدارة المخزون', 'types' => ['view', 'edit', 'add']],
        ['key' => 'financial', 'name' => 'الحسابات المالية', 'types' => ['view', 'edit', 'add']],
        ['key' => 'bank_accounts', 'name' => 'إدارة الحسابات البنكية', 'types' => ['view', 'edit', 'add']],
        ['key' => 'employees', 'name' => 'إدارة الموظفين', 'types' => ['view', 'edit', 'add']],
        ['key' => 'permissions', 'name' => 'صلاحيات الموظفين', 'types' => ['view', 'edit']],
        ['key' => 'expenses', 'name' => 'إدارة المصروفات', 'types' => ['view', 'edit', 'add']],
        ['key' => 'coupons', 'name' => 'إدارة الكوبونات', 'types' => ['view', 'edit', 'add']],
        ['key' => 'reports', 'name' => 'التقارير والطباعة', 'types' => ['view']],
        ['key' => 'settings', 'name' => 'إعدادات النظام', 'types' => ['view', 'edit']],
    ];
    
    // Insert permissions for each module
    $stmt = $db->prepare("
        INSERT INTO permissions (permission_key, permission_name, permission_type, module_name, module, description)
        VALUES (?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
            permission_name = VALUES(permission_name),
            permission_type = VALUES(permission_type),
            module_name = VALUES(module_name),
            module = VALUES(module),
            description = VALUES(description)
    ");
    
    $count = 0;
    foreach ($modules as $module) {
        foreach ($module['types'] as $type) {
            $permission_key = $module['key'] . '_' . $type;
            $permission_name = $module['name'] . ' - ' . getTypeLabel($type);
            $description = "صلاحية {$type} لـ {$module['name']}";
            
            $stmt->execute([
                $permission_key,
                $permission_name,
                $type,
                $module['key'],  // module_name
                $module['key'],  // module (for compatibility)
                $description
            ]);
            $count++;
        }
    }
    
    echo "✓ Inserted/Updated $count permissions\n";
    
    echo "\n✅ Migration completed successfully!\n";
    echo "Total permissions created: $count\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

function getTypeLabel($type) {
    $labels = [
        'view' => 'عرض',
        'edit' => 'تعديل',
        'add' => 'إضافة',
        'delete' => 'حذف',
        'full' => 'كامل'
    ];
    return $labels[$type] ?? $type;
}
