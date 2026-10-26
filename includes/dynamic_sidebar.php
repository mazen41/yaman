<?php
/**
 * Dynamic Sidebar with Permissions
 * Rendered inside the sidebar in includes/header.php.
 * Only shows modules the user has permission to access.
 */

// Load app-path helper (yaman_app_path) if not already loaded
if (!function_exists('yaman_app_path')) {
    require_once __DIR__ . '/PermissionManager.php';
}

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
    ['name' => 'رموز SKU للطلبات', 'route' => '/modules/orders/skus.php', 'icon' => 'fas fa-barcode', 'key' => 'orders_skus', 'section' => 'modules'],
    ['name' => 'إدارة الفرز', 'route' => '/modules/sorting/index.php', 'icon' => 'fas fa-qrcode', 'key' => 'sorting', 'section' => 'modules'],
    ['name' => 'بطاقات العملاء', 'route' => '/modules/customers/customer_cards.php', 'icon' => 'fas fa-id-card', 'key' => 'customer_cards', 'section' => 'modules'],
    ['name' => 'الموافقات', 'route' => '/modules/orders/approvals.php', 'icon' => 'fas fa-check-circle', 'key' => 'order_approval', 'section' => 'modules'],
    ['name' => 'حاسبه يمان', 'route' => '/modules/customer_text/copying.php', 'icon' => 'fas fa-copy', 'key' => 'calculations', 'section' => 'modules'],
    ['name' => 'رسالة التأكيد', 'route' => '/modules/settings/client_message_template.php', 'icon' => 'fas fa-whatsapp', 'key' => 'whatsapp_templates_admin_notification', 'section' => 'modules'],
    ['name' => 'رساله تأكيد المتجر', 'route' => '/modules/settings/checkout_template_admin.php', 'icon' => 'fas fa-file-invoice', 'key' => 'checkout_template_admin', 'section' => 'shop_management'],
    // Shop management (dropdown)
    ['name' => 'الألوان', 'route' => '/modules/colors/index.php', 'icon' => 'fas fa-palette', 'key' => 'colors', 'section' => 'shop_management'],
    ['name' => 'إدارة طلبات المتجر', 'route' => '/modules/orders/shop_orders_manage.php', 'icon' => 'fas fa-shopping-basket', 'key' => 'shop_orders', 'section' => 'shop_management'],
    ['name' => 'المنتجات', 'route' => '/modules/products/index.php', 'icon' => 'fas fa-box', 'key' => 'products', 'section' => 'shop_management'],
    ['name' => 'تصنيفات المنتجات', 'route' => '/modules/categories/index.php', 'icon' => 'fas fa-tags', 'key' => 'product_categories', 'section' => 'shop_management'],
    ['name' => 'سمات المنتجات', 'route' => '/modules/attributes/index.php', 'icon' => 'fas fa-sliders-h', 'key' => 'product_attributes', 'section' => 'shop_management'],
    ['name' => 'سلايدر المنتجات', 'route' => '/modules/settings/product_slides.php', 'icon' => 'fas fa-images', 'key' => 'product_slider', 'section' => 'shop_management'],
    ['name' => 'سلايدر صفحة الدفع', 'route' => '/modules/settings/checkout_slides.php', 'icon' => 'fas fa-images', 'key' => 'checkout_slides', 'section' => 'shop_management'],
    ['name' => 'كوبونات المتجر', 'route' => '/modules/shop_coupons/index.php', 'icon' => 'fas fa-ticket-alt', 'key' => 'coupons', 'section' => 'shop_management'],

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
    ['name' => 'إداره الحالات', 'route' => '/modules/manage_status/manage_status.php', 'icon' => 'fas fa-tasks', 'key' => 'status', 'section' => 'modules'],
    // إدارة المخزون (hidden as requested)
    // ['name' => 'إدارة المخزون', 'route' => '/modules/inventory/index.php', 'icon' => 'fas fa-boxes', 'key' => 'inventory', 'section' => 'modules'],
    ['name' => 'الحسابات المالية', 'route' => '/modules/financial/index.php', 'icon' => 'fas fa-coins', 'key' => 'financial', 'section' => 'modules'],
    ['name' => 'إقفال السنة المالية', 'route' => '/modules/financial/year_close.php', 'icon' => 'fas fa-calendar-check', 'key' => 'financial', 'section' => 'modules'],
    ['name' => 'إدارة الحسابات البنكية', 'route' => '/modules/payments/bank_accounts.php', 'icon' => 'fas fa-university', 'key' => 'bank_accounts', 'section' => 'modules'],
    ['name' => 'إدارة الموظفين', 'route' => '/modules/financial/employee-manage.php', 'icon' => 'fas fa-users-cog', 'key' => 'employees', 'section' => 'modules'],
    ['name' => 'صلاحيات الموظفين', 'route' => '/modules/financial/employee-permissions.php', 'icon' => 'fas fa-user-shield', 'key' => 'permissions', 'section' => 'modules'],
    ['name' => 'إدارة المصروفات', 'route' => '/modules/expenses/index.php', 'icon' => 'fas fa-money-bill-wave', 'key' => 'expenses', 'section' => 'modules'],
    ['name' => 'إدارة الكوبونات', 'route' => '/modules/coupons/index.php', 'icon' => 'fas fa-ticket-alt', 'key' => 'coupons', 'section' => 'modules'],
    ['name' => 'التقارير والطباعة', 'route' => '/modules/reports/index.php', 'icon' => 'fas fa-chart-bar', 'key' => 'reports', 'section' => 'reports'],
    ['name' => 'ملخص العملاء', 'route' => '/modules/reports/customer_summary.php', 'icon' => 'fas fa-users', 'key' => 'customer_summary', 'section' => 'reports'],
    ['name' => 'إعدادات النظام', 'route' => '/modules/settings/index.php', 'icon' => 'fas fa-cog', 'key' => 'settings', 'section' => 'settings'],
    ['name' => 'القيود اليومية', 'route' => '/modules/accounting/journal.php', 'icon' => 'fas fa-book', 'key' => 'journal', 'section' => 'accounting'],
    ['name' => 'إعدادات الحسابات', 'route' => '/modules/accounting/settings.php', 'icon' => 'fas fa-cogs', 'key' => 'accounting_settings', 'section' => 'accounting'],
    ['name' => 'نسخة احتياطية للنظام', 'route' => '/modules/backup/backup.php', 'icon' => 'fas fa-database', 'key' => 'system_backup', 'section' => 'modules'],
    ['name' => 'تحويلات', 'route' => '/modules/financial/universal_transfer.php', 'icon' => 'fas fa-exchange-alt', 'key' => 'transfering', 'section' => 'modules'],
];

// Prefix routes with the app folder (e.g. /yaman locally, nothing on the live root)
foreach ($all_sidebar_modules as $i => $m) {
    $all_sidebar_modules[$i]['route'] = yaman_app_path($m['route']);
}

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

            if (strpos($key, '.') !== false) {
                $parts = explode('.', $key);
                $user_permission_keys[] = $parts[0];
            } elseif (strpos($key, '_') !== false) {
                $base_key = preg_replace('/(_(view|edit|add|delete|manage|create|approve|reject))$/', '', $key);
                $user_permission_keys[] = $base_key;
            } else {
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

    // Dashboard is always visible (header also links to it, so it is hidden here)
    if ($module['key'] === 'dashboard') {
        continue;
    }

    if (in_array($module['key'], $user_permission_keys)) {
        $visible_modules[] = $module;
    }
}

// Group by section
$grouped_modules = [
    'main' => [],
    'shop_management' => [],
    'modules' => [],
    'reports' => [],
    'settings' => [],
    'accounting' => []
];

foreach ($visible_modules as $module) {
    $section = $module['section'] ?? 'modules';
    if (!isset($grouped_modules[$section])) {
        $section = 'modules';
    }
    $grouped_modules[$section][] = $module;
}

$current_page = basename($_SERVER['PHP_SELF'] ?? '');
$is_route_active = function (string $route) {
    return strpos($_SERVER['PHP_SELF'] ?? '', $route) !== false;
};

// Renders one sidebar link
$render_link = function (array $module) use ($is_route_active) {
    $active = $is_route_active($module['route']) ? ' is-active' : '';
    echo '<a href="' . htmlspecialchars($module['route']) . '" class="dsb-link' . $active . '">';
    echo '<span class="dsb-icon"><i class="' . htmlspecialchars($module['icon']) . '"></i></span>';
    echo '<span class="dsb-text">' . htmlspecialchars($module['name']) . '</span>';
    echo '</a>';
};

// Does any module inside a list match the current page?
$any_active = function (array $list) use ($is_route_active) {
    foreach ($list as $m) {
        if ($is_route_active($m['route'])) return true;
    }
    return false;
};

$shop_is_active = $any_active($grouped_modules['shop_management']);
$total_visible = count($visible_modules);
?>

<style>
    .dsb { display: flex; flex-direction: column; gap: 18px; font-family: 'Cairo', 'Segoe UI', Tahoma, Arial, sans-serif; }
    .dsb .fa, .dsb .fas, .dsb .far, .dsb .fab { font-family: 'Font Awesome 6 Free', 'Font Awesome 6 Brands'; }

    .dsb-section-title {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: .06em;
        text-transform: uppercase;
        color: #9ca3af;
        padding: 0 12px;
        margin: 0 0 8px;
    }
    .dsb-group { display: flex; flex-direction: column; gap: 2px; }

    /* Link */
    .dsb-link {
        position: relative;
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 9px 12px;
        border-radius: 10px;
        color: #374151;
        font-size: 0.92rem;
        font-weight: 500;
        text-decoration: none;
        transition: background-color .15s ease, color .15s ease;
    }
    .dsb-link:hover { background: #f5f5f4; color: #111827; }
    .dsb-link.is-active {
        background: #faf6ee;
        color: #8a6a3a;
        font-weight: 700;
    }
    .dsb-link.is-active::before {
        content: '';
        position: absolute;
        right: -10px;
        top: 8px;
        bottom: 8px;
        width: 4px;
        border-radius: 4px;
        background: #C7A46D;
    }

    .dsb-icon {
        width: 32px; height: 32px;
        border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        background: #f3f4f6;
        color: #6b7280;
        font-size: 0.85rem;
        flex-shrink: 0;
        transition: background-color .15s ease, color .15s ease;
    }
    .dsb-link:hover .dsb-icon { background: #faf6ee; color: #C7A46D; }
    .dsb-link.is-active .dsb-icon { background: #C7A46D; color: #fff; }

    .dsb-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    /* Dropdown */
    .dsb-dropdown-btn {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 9px 12px;
        border: none;
        border-radius: 10px;
        background: transparent;
        color: #374151;
        font-size: 0.92rem;
        font-weight: 600;
        font-family: inherit;
        cursor: pointer;
        transition: background-color .15s ease;
    }
    .dsb-dropdown-btn:hover { background: #f5f5f4; }
    .dsb-dropdown-btn.is-active { color: #8a6a3a; }
    .dsb-dropdown-left { display: flex; align-items: center; gap: 12px; }
    .dsb-dropdown-btn .chev {
        color: #9ca3af;
        font-size: 0.75rem;
        transition: transform .2s ease;
    }
    .dsb-dropdown-btn.open .chev { transform: rotate(-90deg); }
    .dsb-dropdown-content {
        display: flex;
        flex-direction: column;
        gap: 2px;
        margin: 4px 0 4px 10px;
        padding-right: 12px;
        border-right: 2px solid #f1f5f9;
    }
    .dsb-dropdown-content.hidden { display: none; }
    .dsb-dropdown-content .dsb-link { padding: 7px 10px; font-size: 0.88rem; }
    .dsb-dropdown-content .dsb-icon { width: 28px; height: 28px; font-size: 0.8rem; }

    .dsb-empty {
        text-align: center;
        padding: 28px 16px;
        color: #6b7280;
        font-size: 0.9rem;
    }
    .dsb-empty i { display: block; font-size: 1.8rem; color: #d1d5db; margin-bottom: 8px; }

    /* Dark mode */
    html.dark .dsb-link,
    html.dark .dsb-dropdown-btn { color: #e5e7eb; }
    html.dark .dsb-link:hover,
    html.dark .dsb-dropdown-btn:hover { background: #3b444b; color: #fff; }
    html.dark .dsb-link.is-active { background: #3b444b; color: #C7A46D; }
    html.dark .dsb-icon { background: #353839; color: #9ca3af; }
    html.dark .dsb-link:hover .dsb-icon { background: #414a4c; color: #C7A46D; }
    html.dark .dsb-link.is-active .dsb-icon { background: #C7A46D; color: #fff; }
    html.dark .dsb-dropdown-content { border-right-color: #414a4c; }
    html.dark .dsb-section-title { color: #6b7280; }
</style>

<div class="dsb" id="dynamicSidebar">

    <?php if (!empty($grouped_modules['modules'])): ?>
    <div>
        <h3 class="dsb-section-title">الوحدات</h3>
        <div class="dsb-group">
            <?php foreach ($grouped_modules['modules'] as $module) { $render_link($module); } ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($grouped_modules['shop_management'])): ?>
    <div>
        <button type="button"
                class="dsb-dropdown-btn <?php echo $shop_is_active ? 'is-active open' : ''; ?>"
                onclick="dsbToggle(this)" aria-expanded="<?php echo $shop_is_active ? 'true' : 'false'; ?>">
            <span class="dsb-dropdown-left">
                <span class="dsb-icon"><i class="fas fa-store"></i></span>
                <span>إدارة المتجر</span>
            </span>
            <i class="fas fa-chevron-down chev"></i>
        </button>
        <div class="dsb-dropdown-content <?php echo $shop_is_active ? '' : 'hidden'; ?>">
            <?php foreach ($grouped_modules['shop_management'] as $module) { $render_link($module); } ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($grouped_modules['accounting'])): ?>
    <div>
        <h3 class="dsb-section-title">الحسابات</h3>
        <div class="dsb-group">
            <?php foreach ($grouped_modules['accounting'] as $module) { $render_link($module); } ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($grouped_modules['reports'])): ?>
    <div>
        <h3 class="dsb-section-title">التقارير</h3>
        <div class="dsb-group">
            <?php foreach ($grouped_modules['reports'] as $module) { $render_link($module); } ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($grouped_modules['settings'])): ?>
    <div>
        <h3 class="dsb-section-title">الإعدادات</h3>
        <div class="dsb-group">
            <?php foreach ($grouped_modules['settings'] as $module) { $render_link($module); } ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($total_visible === 0): ?>
    <div class="dsb-empty">
        <i class="fas fa-lock"></i>
        <p style="margin:0;">لا توجد صلاحيات</p>
        <p style="margin:4px 0 0; font-size:0.8rem; color:#9ca3af;">يرجى التواصل مع المدير</p>
    </div>
    <?php endif; ?>
</div>

<script>
    function dsbToggle(btn) {
        const content = btn.nextElementSibling;
        const isOpen = btn.classList.toggle('open');
        content.classList.toggle('hidden', !isOpen);
        btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    }
    // Keep toggleDropdown working if other code still calls it
    function toggleDropdown(id) {
        const content = document.getElementById(id);
        if (!content) return;
        const btn = content.previousElementSibling;
        if (btn) dsbToggle(btn);
    }
</script>
