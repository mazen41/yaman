<?php
/**
 * Dynamic Sidebar with Permissions
 * This replaces the static sidebar in header.php
 * Only shows modules the user has permission to access
 */

// Initialize permission manager if not already done
if (!isset($permissionManager) && isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/PermissionManager.php';
    $permissionManager = new PermissionManager($db, $_SESSION['user_id']);
}

// All sidebar modules with their permission keys
$all_sidebar_modules = [
    ['name' => 'الصفحة الرئيسية', 'route' => '/index.php', 'icon' => 'fas fa-home', 'key' => 'dashboard', 'section' => 'main'],
    ['name' => 'إدارة العملاء', 'route' => '/modules/customers/index.php', 'icon' => 'fas fa-users', 'key' => 'customers', 'section' => 'modules'],
    // فواتير العملاء (hidden as requested)
    // ['name' => 'فواتير العملاء', 'route' => '/modules/customers/show_invoices.php', 'icon' => 'fas fa-file-invoice-dollar', 'key' => 'customer_invoices', 'section' => 'modules'],
    ['name' => 'أنواع العملاء', 'route' => '/modules/customers/customer_types.php', 'icon' => 'fas fa-users-cog', 'key' => 'customer_types', 'section' => 'modules'],
    ['name' => 'المدن', 'route' => '/modules/customers/cities.php', 'icon' => 'fas fa-city', 'key' => 'cities', 'section' => 'modules'],
    ['name' => 'طلبات العملاء', 'route' => '/modules/orders/index.php', 'icon' => 'fas fa-shopping-bag', 'key' => 'orders', 'section' => 'modules'],
    ['name' => 'المراجعة المالية', 'route' => '/modules/orders/financial_review.php', 'icon' => 'fas fa-file-invoice-dollar', 'key' => 'financial_review', 'section' => 'modules'],
    // إدارة المشتريات (hidden as requested)
    // ['name' => 'إدارة المشتريات', 'route' => '/modules/purchases/index.php', 'icon' => 'fas fa-shopping-cart', 'key' => 'purchases', 'section' => 'modules'],
    ['name' => 'مجموعات الشراء', 'route' => '/modules/purchases/groups/index.php', 'icon' => 'fas fa-layer-group', 'key' => 'purchase_groups', 'section' => 'modules'],
    // إدارة الموردين (hidden as requested)
    // ['name' => 'إدارة الموردين', 'route' => '/modules/purchases/suppliers.php', 'icon' => 'fas fa-truck', 'key' => 'suppliers', 'section' => 'modules'],
    ['name' => 'سلات الشراء', 'route' => '/modules/purchases/show_baskets.php', 'icon' => 'fas fa-shopping-basket', 'key' => 'baskets', 'section' => 'modules'],
    ['name' => 'إدارة بطاقات الشراء', 'route' => '/modules/purchase_cards/index.php', 'icon' => 'fas fa-credit-card', 'key' => 'purchase_cards', 'section' => 'modules'],
    ['name' => 'بطاقات الهدية', 'route' => '/modules/loyalty-cards/index.php', 'icon' => 'fas fa-gift', 'key' => 'loyalty_cards', 'section' => 'modules'],
    ['name' => 'إدارة الشحن', 'route' => '/modules/shipping/index.php', 'icon' => 'fas fa-shipping-fast', 'key' => 'shipping', 'section' => 'modules'],
    ['name' => 'رسائل الواتساب', 'route' => '/modules/whatsapp/send.php', 'icon' => 'fab fa-whatsapp', 'key' => 'whatsapp', 'section' => 'modules'],
    // إدارة المخزون (hidden as requested)
    // ['name' => 'إدارة المخزون', 'route' => '/modules/inventory/index.php', 'icon' => 'fas fa-boxes', 'key' => 'inventory', 'section' => 'modules'],
    ['name' => 'الحسابات المالية', 'route' => '/modules/financial/index.php', 'icon' => 'fas fa-coins', 'key' => 'financial', 'section' => 'modules'],
    ['name' => 'إدارة الحسابات البنكية', 'route' => '/modules/payments/bank_accounts.php', 'icon' => 'fas fa-university', 'key' => 'bank_accounts', 'section' => 'modules'],
    ['name' => 'إدارة الموظفين', 'route' => '/modules/financial/employee-manage.php', 'icon' => 'fas fa-users-cog', 'key' => 'employees', 'section' => 'modules'],
    ['name' => 'صلاحيات الموظفين', 'route' => '/modules/financial/employee-permissions.php', 'icon' => 'fas fa-user-shield', 'key' => 'permissions', 'section' => 'modules'],
    ['name' => 'إدارة المصروفات', 'route' => '/modules/expenses/index.php', 'icon' => 'fas fa-money-bill-wave', 'key' => 'expenses', 'section' => 'modules'],
    ['name' => 'إدارة الكوبونات', 'route' => '/modules/coupons/index.php', 'icon' => 'fas fa-ticket-alt', 'key' => 'coupons', 'section' => 'modules'],
    ['name' => 'التقارير والطباعة', 'route' => '/modules/reports/index.php', 'icon' => 'fas fa-chart-bar', 'key' => 'reports', 'section' => 'reports'],
    ['name' => 'إعدادات النظام', 'route' => '/modules/settings/index.php', 'icon' => 'fas fa-cog', 'key' => 'settings', 'section' => 'settings'],
];

// Get user's permissions from database
$user_permission_keys = [];
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $db->prepare("
            SELECT p.permission_key 
            FROM user_permissions up
            JOIN permissions p ON up.permission_id = p.id
            WHERE up.user_id = ?
        ");
        $stmt->execute([$_SESSION['user_id']]);
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // Extract base module from permission key
            // Supports both formats: "customers.manage" and "customers_view"
            $key = $row['permission_key'];
            
            // Try dot notation first
            if (strpos($key, '.') !== false) {
                $parts = explode('.', $key);
                $user_permission_keys[] = $parts[0];
            } 
            // Try underscore notation
            elseif (strpos($key, '_') !== false) {
                // Remove action suffix (_view, _edit, _add, _delete, _manage)
                $base_key = preg_replace('/(_(view|edit|add|delete|manage|create))$/', '', $key);
                $user_permission_keys[] = $base_key;
            }
            else {
                // No delimiter, use as is
                $user_permission_keys[] = $key;
            }
        }
        $user_permission_keys = array_unique($user_permission_keys);
    } catch (PDOException $e) {
        error_log("Error loading permissions: " . $e->getMessage());
    }
}

// Check if user is admin
$is_admin = false;
if (isset($_SESSION['user_id'])) {
    try {
        $admin_check = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
        $admin_check->execute([$_SESSION['user_id']]);
        $user_data = $admin_check->fetch(PDO::FETCH_ASSOC);
        $is_admin = ($user_data && $user_data['is_admin'] == 1);
    } catch (PDOException $e) {
        error_log("Error checking admin status: " . $e->getMessage());
    }
}

// Filter modules based on permissions
$visible_modules = [];
foreach ($all_sidebar_modules as $module) {
    // Admin sees everything
    if ($is_admin) {
        $visible_modules[] = $module;
        continue;
    }
    
    // Dashboard is always visible
    if ($module['key'] === 'dashboard') {
        $visible_modules[] = $module;
        continue;
    }
    
    // Check if user has permission for this module
    if (in_array($module['key'], $user_permission_keys)) {
        $visible_modules[] = $module;
    }
}

// Group by section
$grouped_modules = [
    'main' => [],
    'modules' => [],
    'reports' => [],
    'settings' => []
];

foreach ($visible_modules as $module) {
    $section = $module['section'] ?? 'modules';
    $grouped_modules[$section][] = $module;
}
?>

<!-- Dynamic Sidebar -->
<div class="space-y-3">
    <!-- Main Section -->
    <?php if (!empty($grouped_modules['main'])): ?>
        <?php foreach ($grouped_modules['main'] as $module): ?>
            <a href="<?php echo htmlspecialchars($module['route']); ?>" 
               class="sidebar-link block w-full <?php echo strpos($_SERVER['PHP_SELF'], $module['route']) !== false ? 'active' : ''; ?>">
                <i class="<?php echo htmlspecialchars($module['icon']); ?> sidebar-icon"></i>
                <span><?php echo htmlspecialchars($module['name']); ?></span>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
    
    <!-- Modules Section -->
    <?php if (!empty($grouped_modules['modules'])): ?>
        <div class="pt-3 mt-3 border-t border-gray-200">
            <h3 class="text-xs uppercase text-gray-600 font-bold px-3 mb-3">الوحدات</h3>
            <?php foreach ($grouped_modules['modules'] as $module): ?>
                <a href="<?php echo htmlspecialchars($module['route']); ?>" 
                   class="sidebar-link block w-full <?php echo strpos($_SERVER['PHP_SELF'], $module['route']) !== false ? 'active' : ''; ?>">
                    <i class="<?php echo htmlspecialchars($module['icon']); ?> sidebar-icon"></i>
                    <span><?php echo htmlspecialchars($module['name']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Reports Section -->
    <?php if (!empty($grouped_modules['reports'])): ?>
        <div class="pt-3 mt-3 border-t border-gray-200">
            <h3 class="text-xs uppercase text-gray-600 font-bold px-3 mb-3">التقارير</h3>
            <?php foreach ($grouped_modules['reports'] as $module): ?>
                <a href="<?php echo htmlspecialchars($module['route']); ?>" 
                   class="sidebar-link block w-full <?php echo strpos($_SERVER['PHP_SELF'], $module['route']) !== false ? 'active' : ''; ?>">
                    <i class="<?php echo htmlspecialchars($module['icon']); ?> sidebar-icon"></i>
                    <span><?php echo htmlspecialchars($module['name']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <!-- Settings Section -->
    <?php if (!empty($grouped_modules['settings'])): ?>
        <div class="pt-3 mt-3 border-t border-gray-200">
            <h3 class="text-xs uppercase text-gray-600 font-bold px-3 mb-3">الإعدادات</h3>
            <?php foreach ($grouped_modules['settings'] as $module): ?>
                <a href="<?php echo htmlspecialchars($module['route']); ?>" 
                   class="sidebar-link block w-full <?php echo strpos($_SERVER['PHP_SELF'], $module['route']) !== false ? 'active' : ''; ?>">
                    <i class="<?php echo htmlspecialchars($module['icon']); ?> sidebar-icon"></i>
                    <span><?php echo htmlspecialchars($module['name']); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php if (empty($visible_modules) || count($visible_modules) <= 1): ?>
        <div class="text-center py-8 px-4">
            <i class="fas fa-lock text-4xl text-gray-300 mb-3"></i>
            <p class="text-gray-500 text-sm">لا توجد صلاحيات</p>
            <p class="text-gray-400 text-xs mt-1">يرجى التواصل مع المدير</p>
        </div>
    <?php endif; ?>
</div>
