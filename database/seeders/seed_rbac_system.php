<?php
/**
 * RBAC System Seeder - Seeds all roles and permissions based on existing sidebar
 * Run this ONCE to populate the RBAC system
 */

require_once __DIR__ . '/../../config/database.php';

echo "Starting RBAC System Seeding...\n\n";

try {
    $db->beginTransaction();
    
    // 1. Seed Roles
    echo "1. Creating roles...\n";
    $roles = [
        ['name' => 'super_admin', 'display_name' => 'مدير النظام', 'description' => 'صلاحيات كاملة'],
        ['name' => 'manager', 'display_name' => 'مدير', 'description' => 'صلاحيات إدارية'],
        ['name' => 'employee', 'display_name' => 'موظف', 'description' => 'صلاحيات محدودة'],
        ['name' => 'accountant', 'display_name' => 'محاسب', 'description' => 'صلاحيات مالية'],
    ];
    
    $roleIds = [];
    foreach ($roles as $role) {
        $stmt = $db->prepare("INSERT INTO roles (name, display_name, description) VALUES (?, ?, ?) 
                              ON DUPLICATE KEY UPDATE display_name = VALUES(display_name)");
        $stmt->execute([$role['name'], $role['display_name'], $role['description']]);
        
        $stmt = $db->prepare("SELECT id FROM roles WHERE name = ?");
        $stmt->execute([$role['name']]);
        $roleIds[$role['name']] = $stmt->fetchColumn();
        echo "  ✓ {$role['display_name']}\n";
    }
    
    // 2. Seed Permissions (based on your exact sidebar)
    echo "\n2. Creating permissions...\n";
    $sidebar_modules = [
        ['name' => 'إدارة العملاء', 'route' => '/modules/customers/index.php', 'icon' => 'fas fa-users', 'key' => 'customers', 'module' => 'customers', 'permissions' => ['view', 'edit', 'add'], 'order' => 1],
        ['name' => 'فواتير العملاء', 'route' => '/modules/customers/show_invoices.php', 'icon' => 'fas fa-file-invoice-dollar', 'key' => 'customer_invoices', 'module' => 'customers', 'permissions' => ['view', 'edit', 'add'], 'order' => 2],
        ['name' => 'أنواع العملاء', 'route' => '/modules/customers/customer_types.php', 'icon' => 'fas fa-users-cog', 'key' => 'customer_types', 'module' => 'customers', 'permissions' => ['view', 'edit', 'add'], 'order' => 3],
        ['name' => 'المدن', 'route' => '/modules/customers/cities.php', 'icon' => 'fas fa-city', 'key' => 'cities', 'module' => 'customers', 'permissions' => ['view', 'edit', 'add'], 'order' => 4],
        ['name' => 'طلبات العملاء', 'route' => '/modules/orders/index.php', 'icon' => 'fas fa-shopping-bag', 'key' => 'orders', 'module' => 'orders', 'permissions' => ['view', 'edit', 'add'], 'order' => 5],
        ['name' => 'المراجعة المالية', 'route' => '/modules/orders/financial_review.php', 'icon' => 'fas fa-file-invoice-dollar', 'key' => 'financial_review', 'module' => 'financial', 'permissions' => ['view', 'edit'], 'order' => 6],
        ['name' => 'إدارة المشتريات', 'route' => '/modules/purchases/index.php', 'icon' => 'fas fa-shopping-cart', 'key' => 'purchases', 'module' => 'purchases', 'permissions' => ['view', 'edit', 'add'], 'order' => 7],
        ['name' => 'مجموعات الشراء', 'route' => '/modules/purchases/groups/index.php', 'icon' => 'fas fa-layer-group', 'key' => 'purchase_groups', 'module' => 'purchases', 'permissions' => ['view', 'edit', 'add'], 'order' => 8],
        ['name' => 'إدارة الموردين', 'route' => '/modules/purchases/suppliers.php', 'icon' => 'fas fa-truck', 'key' => 'suppliers', 'module' => 'purchases', 'permissions' => ['view', 'edit', 'add'], 'order' => 9],
        ['name' => 'سلات الشراء', 'route' => '/modules/purchases/show_baskets.php', 'icon' => 'fas fa-shopping-basket', 'key' => 'baskets', 'module' => 'purchases', 'permissions' => ['view', 'edit', 'add'], 'order' => 10],
        ['name' => 'إدارة بطاقات الشراء', 'route' => '/modules/purchase_cards/index.php', 'icon' => 'fas fa-credit-card', 'key' => 'purchase_cards', 'module' => 'purchases', 'permissions' => ['view', 'edit', 'add'], 'order' => 11],
        ['name' => 'بطاقات الهدية', 'route' => '/modules/loyalty-cards/index.php', 'icon' => 'fas fa-gift', 'key' => 'loyalty_cards', 'module' => 'loyalty', 'permissions' => ['view', 'edit', 'add'], 'order' => 12],
        ['name' => 'إدارة الشحن', 'route' => '/modules/shipping/index.php', 'icon' => 'fas fa-shipping-fast', 'key' => 'shipping', 'module' => 'shipping', 'permissions' => ['view', 'edit', 'add'], 'order' => 13],
        ['name' => 'رسائل الواتساب', 'route' => '/modules/whatsapp/send.php', 'icon' => 'fab fa-whatsapp', 'key' => 'whatsapp', 'module' => 'whatsapp', 'permissions' => ['view', 'add'], 'order' => 14],
        ['name' => 'إدارة المخزون', 'route' => '/modules/inventory/index.php', 'icon' => 'fas fa-boxes', 'key' => 'inventory', 'module' => 'inventory', 'permissions' => ['view', 'edit', 'add'], 'order' => 15],
        ['name' => 'الحسابات المالية', 'route' => '/modules/financial/index.php', 'icon' => 'fas fa-coins', 'key' => 'financial', 'module' => 'financial', 'permissions' => ['view', 'edit', 'add'], 'order' => 16],
        ['name' => 'إدارة الحسابات البنكية', 'route' => '/modules/payments/bank_accounts.php', 'icon' => 'fas fa-university', 'key' => 'bank_accounts', 'module' => 'financial', 'permissions' => ['view', 'edit', 'add'], 'order' => 17],
        ['name' => 'إدارة الموظفين', 'route' => '/modules/financial/employee-manage.php', 'icon' => 'fas fa-users-cog', 'key' => 'employees', 'module' => 'settings', 'permissions' => ['view', 'edit', 'add'], 'order' => 18],
        ['name' => 'صلاحيات الموظفين', 'route' => '/modules/financial/employee_permissions.php', 'icon' => 'fas fa-user-shield', 'key' => 'permissions', 'module' => 'settings', 'permissions' => ['view', 'edit'], 'order' => 19],
        ['name' => 'إدارة المصروفات', 'route' => '/modules/expenses/index.php', 'icon' => 'fas fa-money-bill-wave', 'key' => 'expenses', 'module' => 'expenses', 'permissions' => ['view', 'edit', 'add'], 'order' => 20],
        ['name' => 'إدارة الكوبونات', 'route' => '/modules/coupons/index.php', 'icon' => 'fas fa-ticket-alt', 'key' => 'coupons', 'module' => 'coupons', 'permissions' => ['view', 'edit', 'add'], 'order' => 21],
        ['name' => 'التقارير والطباعة', 'route' => '/modules/reports/index.php', 'icon' => 'fas fa-chart-bar', 'key' => 'reports', 'module' => 'reports', 'permissions' => ['view'], 'order' => 22],
        ['name' => 'إعدادات النظام', 'route' => '/modules/settings/index.php', 'icon' => 'fas fa-cog', 'key' => 'settings', 'module' => 'settings', 'permissions' => ['view', 'edit'], 'order' => 23],
    ];
    
    $permissionIds = [];
    foreach ($sidebar_modules as $page) {
        foreach ($page['permissions'] as $action) {
            $permKey = $page['key'] . '_' . $action;
            
            $stmt = $db->prepare("
                INSERT INTO permissions (permission_name, permission_key, module_name, module, permission_type)
                VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    permission_name = VALUES(permission_name),
                    module_name = VALUES(module_name)
            ");
            $stmt->execute([
                $page['name'] . ' - ' . $action,
                $permKey,
                $page['name'],
                $page['module'],
                $action
            ]);
            
            $stmt = $db->prepare("SELECT id FROM permissions WHERE permission_key = ?");
            $stmt->execute([$permKey]);
            $permissionIds[$permKey] = $stmt->fetchColumn();
        }
        echo "  ✓ {$page['name']}\n";
    }
    
    // 3. Assign permissions to Manager role (full access except settings)
    echo "\n3. Assigning permissions to Manager role...\n";
    $managerPerms = 0;
    foreach ($permissionIds as $key => $id) {
        // Manager gets all except permission management
        if (!str_contains($key, 'permissions_')) {
            $stmt = $db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            $stmt->execute([$roleIds['manager'], $id]);
            $managerPerms++;
        }
    }
    echo "  ✓ Assigned $managerPerms permissions\n";
    
    // 4. Assign permissions to Employee role (view only for most)
    echo "\n4. Assigning permissions to Employee role...\n";
    $employeePerms = 0;
    foreach ($permissionIds as $key => $id) {
        // Employee gets view permissions for customers, orders, inventory
        if (str_ends_with($key, '_view') && 
            (str_contains($key, 'customers') || str_contains($key, 'orders') || str_contains($key, 'inventory'))) {
            $stmt = $db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            $stmt->execute([$roleIds['employee'], $id]);
            $employeePerms++;
        }
    }
    echo "  ✓ Assigned $employeePerms permissions\n";
    
    // 5. Assign permissions to Accountant role (financial focus)
    echo "\n5. Assigning permissions to Accountant role...\n";
    $accountantPerms = 0;
    foreach ($permissionIds as $key => $id) {
        // Accountant gets financial, orders, reports
        if (str_contains($key, 'financial') || str_contains($key, 'orders') || 
            str_contains($key, 'reports') || str_contains($key, 'bank_accounts') ||
            str_contains($key, 'expenses')) {
            $stmt = $db->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
            $stmt->execute([$roleIds['accountant'], $id]);
            $accountantPerms++;
        }
    }
    echo "  ✓ Assigned $accountantPerms permissions\n";
    
    $db->commit();
    
    echo "\n✅ RBAC System seeded successfully!\n\n";
    echo "Summary:\n";
    echo "  - Roles created: " . count($roles) . "\n";
    echo "  - Permissions created: " . count($permissionIds) . "\n";
    echo "  - Manager permissions: $managerPerms\n";
    echo "  - Employee permissions: $employeePerms\n";
    echo "  - Accountant permissions: $accountantPerms\n";
    echo "\nNext steps:\n";
    echo "  1. Go to: https://taksoride.com/modules/financial/employee_permissions_rbac.php\n";
    echo "  2. Assign roles to users\n";
    echo "  3. Test the system\n";
    
} catch (Exception $e) {
    $db->rollBack();
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>
