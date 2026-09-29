<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require_once 'config/database.php';
require_once 'includes/check_permissions.php';

// Security: Check if user has permission to view dashboard
// If not, redirect to their first permitted page
if (!hasPermission($_SESSION['user_id'], 'dashboard', 'view')) {
    $redirect = getFirstPermittedPage($_SESSION['user_id']) ?? 'login.php';
    // Prevent infinite loop if dashboard IS the first permitted page (shouldn't happen if hasPermission returns false)
    if (basename($_SERVER['PHP_SELF']) !== basename($redirect)) {
        header("Location: $redirect");
        exit();
    }
}

// Check if user is admin - stats only for admin
$is_admin = isUserAdmin($_SESSION['user_id'], $db);

$page_title = 'الصفحة الرئيسية';

// System time as UTC+2 (current desired offset) for display, independent of server timezone
date_default_timezone_set('Asia/Aden');

$system_date = date('Y-m-d');
$system_datetime = date('Y-m-d H:i');

// Get real statistics from database with table existence checks
$customers_count = 0;
$today_orders = 0;
$active_coupons = 0;
$today_sales = 0;
$total_orders = 0;
$pending_orders = 0;
$total_products = 0;
$low_stock_products = 0;

try {
    // Check which tables exist
    $existing_tables = [];
    $tables_result = $db->query("SHOW TABLES");
    while ($row = $tables_result->fetch(PDO::FETCH_NUM)) {
        $existing_tables[] = $row[0];
    }
    
    // Get customers count if table exists
    if (in_array('customers', $existing_tables)) {
        $customers_count = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    }
    
    // Get orders statistics if table exists
    if (in_array('customer_orders', $existing_tables)) {
        $total_orders = $db->query("SELECT COUNT(*) FROM customer_orders")->fetchColumn();
        
        // Today's orders
        $today_orders = $db->query("
            SELECT COUNT(*) FROM customer_orders 
            WHERE DATE(created_at) = CURDATE()
        ")->fetchColumn();
        
        // Pending orders (new, pending, processing)
        $pending_orders = $db->query("
            SELECT COUNT(*) FROM customer_orders 
            WHERE status IN ('new', 'pending', 'processing')
        ")->fetchColumn();
        
        // Today's sales (all orders except cancelled)
        $today_sales = $db->query("
            SELECT COALESCE(SUM(final_amount), 0) FROM customer_orders 
            WHERE DATE(created_at) = CURDATE() 
            AND status NOT IN ('cancelled')
        ")->fetchColumn();
    }
    
    // Get coupons count if table exists
    if (in_array('coupons', $existing_tables)) {
        $active_coupons = $db->query("
            SELECT COUNT(*) FROM coupons 
            WHERE is_active = 1 
            AND start_date <= CURDATE() 
            AND end_date >= CURDATE()
        ")->fetchColumn();
    }
    
    // Get products statistics if table exists
    if (in_array('products', $existing_tables)) {
        $total_products = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
        
        // Low stock products
        $low_stock_products = $db->query("
            SELECT COUNT(*) FROM products 
            WHERE stock_quantity <= min_stock_level AND is_active = 1
        ")->fetchColumn();
    }
    
    // Additional error handling for specific queries
    $customers_count = $customers_count ?: 0;
    $today_orders = $today_orders ?: 0;
    $active_coupons = $active_coupons ?: 0;
    $today_sales = $today_sales ?: 0;
    $total_orders = $total_orders ?: 0;
    $pending_orders = $pending_orders ?: 0;
    $total_products = $total_products ?: 0;
    $low_stock_products = $low_stock_products ?: 0;
    
} catch (PDOException $e) {
    // Log error for debugging (in production, use proper logging)
    error_log("Dashboard Statistics Error: " . $e->getMessage());
    
    // All values remain 0 as initialized above
}

include 'includes/header.php';
?>

<div class="bg-gray-50 py-6 px-4 sm:px-6 lg:px-8" dir="rtl">
    <!-- Header -->
    <div class="bg-gradient-to-r from-green-600 to-green-700 shadow-lg rounded-lg mb-6 text-white">
        <div class="px-6 py-8">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                <div>
                    <h1 class="text-3xl font-bold">مرحباً بك في نظام إدارة يمان</h1>
                    <p class="text-green-100 mt-2 text-lg">نظام إدارة شامل ومتطور للأعمال والمؤسسات</p>
                </div>
                <div class="flex items-center gap-4">
                    <div class="hidden md:block">
                        <div class="bg-white bg-opacity-20 rounded-full p-4">
                            <i class="fas fa-chart-line text-4xl"></i>
                        </div>
                    </div>
                    <div class="bg-white bg-opacity-10 rounded-lg px-4 py-3 text-sm">
                        <div class="flex items-center gap-2 mb-1">
                            <i class="fas fa-clock"></i>
                            <span class="font-semibold">وقت النظام</span>
                        </div>
                        <div class="text-green-100 text-2xs md:text-xs mb-1">
                            بتوقيت اليمن - عدن (Asia/Aden)
                        </div>
                        <div class="text-green-100 text-xs md:text-sm dir-ltr">
                            <span id="system-time-display" data-system-timestamp="<?php echo (int)$system_timestamp; ?>">
                                <?php echo htmlspecialchars($system_datetime); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if ($customers_count == 0 && $total_orders == 0 && $total_products == 0): ?>
    <!-- Database Setup Notice -->
    <div class="bg-yellow-50 border-r-4 border-yellow-400 p-6 mb-6 rounded-lg">
        <div class="flex items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-triangle text-yellow-400 text-2xl"></i>
            </div>
            <div class="mr-4">
                <h3 class="text-lg font-medium text-yellow-800">مرحباً بك في نظام إدارة يمان!</h3>
                <p class="text-yellow-700 mt-2">
                    يبدو أن قاعدة البيانات فارغة. لرؤية الإحصائيات الحقيقية، يرجى إضافة بعض البيانات التجريبية.
                </p>
                <div class="mt-4">
                    <a href="check_database_structure.php" class="inline-flex items-center px-4 py-2 bg-yellow-600 text-white rounded-md hover:bg-yellow-700 transition-colors duration-200 ml-3">
                        <i class="fas fa-database ml-2"></i>
                        فحص قاعدة البيانات
                    </a>
                    <a href="add_sample_data.php" class="inline-flex items-center px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition-colors duration-200">
                        <i class="fas fa-plus ml-2"></i>
                        إضافة بيانات تجريبية
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Quick Stats - Admin Only -->
    <?php if ($is_admin): ?>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Total Customers -->
        <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 p-4 border-r-4 border-blue-500">
            <div class="flex items-center">
                <div class="bg-blue-100 rounded-full p-3">
                    <i class="fas fa-users text-blue-600 text-xl"></i>
                </div>
                <div class="mr-4">
                    <p class="text-sm text-gray-600 font-medium">إجمالي العملاء</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($customers_count, 0, '', ''); ?></p>
                    <p class="text-xs text-blue-600 mt-1">
                        <i class="fas fa-arrow-up text-xs"></i>
                        عميل مسجل
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Today's Orders -->
        <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 p-4 border-r-4 border-green-500">
            <div class="flex items-center">
                <div class="bg-green-100 rounded-full p-3">
                    <i class="fas fa-shopping-bag text-green-600 text-xl"></i>
                </div>
                <div class="mr-4">
                    <p class="text-sm text-gray-600 font-medium">الطلبات اليوم</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($today_orders, 0, '', ''); ?></p>
                    <p class="text-xs text-green-600 mt-1">
                        <i class="fas fa-calendar-day text-xs"></i>
                        <?php echo htmlspecialchars($system_date); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Active Coupons -->
        <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 p-4 border-r-4 border-purple-500">
            <div class="flex items-center">
                <div class="bg-purple-100 rounded-full p-3">
                    <i class="fas fa-ticket-alt text-purple-600 text-xl"></i>
                </div>
                <div class="mr-4">
                    <p class="text-sm text-gray-600 font-medium">الكوبونات النشطة</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($active_coupons, 0, '', ''); ?></p>
                    <p class="text-xs text-purple-600 mt-1">
                        <i class="fas fa-check-circle text-xs"></i>
                        كوبون متاح
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Today's Sales -->
        <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300 p-4 border-r-4 border-yellow-500">
            <div class="flex items-center">
                <div class="bg-yellow-100 rounded-full p-3">
                    <i class="fas fa-coins text-yellow-600 text-xl"></i>
                </div>
                <div class="mr-4">
                    <p class="text-sm text-gray-600 font-medium">المبيعات اليوم</p>
                    <p class="text-2xl font-bold text-gray-900"><?php echo number_format($today_sales, 0, '', ''); ?> ر.ي</p>
                    <p class="text-xs text-yellow-600 mt-1">
                        <i class="fas fa-chart-line text-xs"></i>
                        مبيعات مكتملة
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Stats Row -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Total Orders -->
        <div class="bg-white rounded-lg shadow p-3 border-l-4 border-indigo-400">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">إجمالي الطلبات</p>
                    <p class="text-lg font-bold text-indigo-600"><?php echo number_format($total_orders, 0, '', ''); ?></p>
                </div>
                <i class="fas fa-clipboard-list text-indigo-400 text-2xl"></i>
            </div>
        </div>
        
        <!-- Pending Orders -->
        <div class="bg-white rounded-lg shadow p-3 border-l-4 border-orange-400">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">طلبات معلقة</p>
                    <p class="text-lg font-bold text-orange-600"><?php echo number_format($pending_orders, 0, '', ''); ?></p>
                </div>
                <i class="fas fa-clock text-orange-400 text-2xl"></i>
            </div>
        </div>
        
        <!-- Total Products -->
        <div class="bg-white rounded-lg shadow p-3 border-l-4 border-teal-400">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">إجمالي المنتجات</p>
                    <p class="text-lg font-bold text-teal-600"><?php echo number_format($total_products, 0, '', ''); ?></p>
                </div>
                <i class="fas fa-boxes text-teal-400 text-2xl"></i>
            </div>
        </div>
        
        <!-- Low Stock Alert -->
        <div class="bg-white rounded-lg shadow p-3 border-l-4 <?php echo $low_stock_products > 0 ? 'border-red-400' : 'border-gray-300'; ?>">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-600">تنبيه مخزون منخفض</p>
                    <p class="text-lg font-bold <?php echo $low_stock_products > 0 ? 'text-red-600' : 'text-gray-500'; ?>">
                        <?php echo number_format($low_stock_products, 0, '', ''); ?>
                    </p>
                </div>
                <i class="fas fa-exclamation-triangle <?php echo $low_stock_products > 0 ? 'text-red-400' : 'text-gray-300'; ?> text-2xl"></i>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Modules Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <style>
            .module-card {
                transition: all 0.3s ease;
                border-top: 4px solid transparent;
            }
            .module-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            }
            .module-card.blue-card:hover { border-top-color: #2563eb; }
            .module-card.green-card:hover { border-top-color: #16a34a; }
            .module-card.purple-card:hover { border-top-color: #9333ea; }
            .module-card.yellow-card:hover { border-top-color: #ca8a04; }
            .module-card.red-card:hover { border-top-color: #dc2626; }
            .module-card.gray-card:hover { border-top-color: #4b5563; }
            .module-card.indigo-card:hover { border-top-color: #4f46e5; }
        </style>

        <?php if (hasPermission($_SESSION['user_id'], 'customers', 'view')): ?>
        <div class="bg-white shadow rounded-lg hover:shadow-lg transition-all duration-300 module-card blue-card">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="bg-blue-100 p-3 rounded-lg">
                        <i class="fas fa-users text-2xl text-blue-600"></i>
                    </div>
                    <div class="mr-4">
                        <h3 class="text-lg font-semibold text-gray-900">إدارة العملاء</h3>
                        <p class="text-gray-600 text-sm">بيانات العملاء والحسابات</p>
                    </div>
                </div>
                <p class="text-gray-600 mb-4">إدارة شاملة لبيانات العملاء، الحسابات، والمعاملات المالية مع تتبع تاريخ المشتريات.</p>
                <div class="module-button-container">
                    <a href="modules/customers/" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white border border-blue-700 rounded-md hover:bg-blue-700 hover:shadow-md transition-all duration-200 font-medium w-full justify-center">
                        <i class="fas fa-arrow-left ml-2"></i>
                        دخول الوحدة
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (hasPermission($_SESSION['user_id'], 'orders', 'view')): ?>
        <div class="bg-white shadow rounded-lg hover:shadow-lg transition-all duration-300 module-card green-card">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="bg-green-100 p-3 rounded-lg">
                        <i class="fas fa-shopping-bag text-2xl text-green-600"></i>
                    </div>
                    <div class="mr-4">
                        <h3 class="text-lg font-semibold text-gray-900">طلبات العملاء</h3>
                        <p class="text-gray-600 text-sm">إدارة الطلبات والمبيعات</p>
                    </div>
                </div>
                <p class="text-gray-600 mb-4">متابعة وإدارة طلبات العملاء، معالجة المبيعات، وتتبع حالة الطلبات من البداية للنهاية.</p>
                <a href="modules/orders/" class="inline-flex items-center px-5 py-2.5 bg-green-600 text-white border border-green-700 rounded-md hover:bg-green-700 hover:shadow-md transition-all duration-200 font-medium w-full justify-center">
                    <i class="fas fa-arrow-left ml-2"></i>
                    دخول الوحدة
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (hasPermission($_SESSION['user_id'], 'coupons', 'view')): ?>
        <div class="bg-white shadow rounded-lg hover:shadow-lg transition-all duration-300 module-card purple-card">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="bg-purple-100 p-3 rounded-lg">
                        <i class="fas fa-ticket-alt text-2xl text-purple-600"></i>
                    </div>
                    <div class="mr-4">
                        <h3 class="text-lg font-semibold text-gray-900">إدارة الكوبونات</h3>
                        <p class="text-gray-600 text-sm">كوبونات الخصم والعروض</p>
                    </div>
                </div>
                <p class="text-gray-600 mb-4">إدارة شاملة لكوبونات الخصم والعروض الترويجية مع تتبع الاستخدام والإحصائيات المفصلة.</p>
                <div class="flex space-x-2 space-x-reverse">
                    <a href="modules/coupons/index.php" class="flex-1 bg-purple-600 text-white px-4 py-2 rounded-lg text-center hover:bg-purple-700 transition duration-200 text-sm">
                        عرض الكوبونات
                    </a>
                    <a href="modules/coupons/add.php" class="flex-1 bg-purple-100 text-purple-700 px-4 py-2 rounded-lg text-center hover:bg-purple-200 transition duration-200 text-sm">
                        إضافة كوبون
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (hasPermission($_SESSION['user_id'], 'financial', 'view')): ?>
        <div class="bg-white shadow rounded-lg hover:shadow-lg transition-all duration-300 module-card red-card">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="bg-red-100 p-3 rounded-lg">
                        <i class="fas fa-coins text-2xl text-red-600"></i>
                    </div>
                    <div class="mr-4">
                        <h3 class="text-lg font-semibold text-gray-900">الحسابات المالية</h3>
                        <p class="text-gray-600 text-sm">التقارير المالية</p>
                    </div>
                </div>
                <p class="text-gray-600 mb-4">إدارة الحسابات المالية، التقارير، والميزانيات مع تحليل الأرباح والخسائر.</p>
                <a href="modules/financial/" class="inline-flex items-center px-5 py-2.5 bg-red-600 text-white border border-red-700 rounded-md hover:bg-red-700 hover:shadow-md transition-all duration-200 font-medium w-full justify-center">
                    <i class="fas fa-arrow-left ml-2"></i>
                    دخول الوحدة
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (hasPermission($_SESSION['user_id'], 'reports', 'view')): ?>
        <div class="bg-white shadow rounded-lg hover:shadow-lg transition-all duration-300 module-card gray-card">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="bg-gray-100 p-3 rounded-lg">
                        <i class="fas fa-chart-bar text-2xl text-gray-600"></i>
                    </div>
                    <div class="mr-4">
                        <h3 class="text-lg font-semibold text-gray-900">التقارير والطباعة</h3>
                        <p class="text-gray-600 text-sm">تقارير شاملة</p>
                    </div>
                </div>
                <p class="text-gray-600 mb-4">إنشاء التقارير المفصلة، الفواتير، والمستندات مع إمكانيات طباعة متقدمة.</p>
                <a href="modules/reports/" class="inline-flex items-center px-5 py-2.5 bg-gray-700 text-white border border-gray-800 rounded-md hover:bg-gray-800 hover:shadow-md transition-all duration-200 font-medium w-full justify-center">
                    <i class="fas fa-arrow-left ml-2"></i>
                    دخول الوحدة
                </a>
            </div>
        </div>
        <?php endif; ?>

        <?php if (hasPermission($_SESSION['user_id'], 'settings', 'view')): ?>
        <div class="bg-white shadow rounded-lg hover:shadow-lg transition-all duration-300 module-card gray-card">
            <div class="p-6">
                <div class="flex items-center mb-4">
                    <div class="bg-gray-100 p-3 rounded-lg">
                        <i class="fas fa-cog text-2xl text-gray-600"></i>
                    </div>
                    <div class="mr-4">
                        <h3 class="text-lg font-semibold text-gray-900">إعدادات النظام</h3>
                        <p class="text-gray-600 text-sm">تكوين النظام</p>
                    </div>
                </div>
                <p class="text-gray-600 mb-4">إعدادات وتكوين النظام، إدارة المستخدمين، والصلاحيات.</p>
                <a href="modules/settings/" class="inline-flex items-center px-5 py-2.5 bg-gray-700 text-white border border-gray-800 rounded-md hover:bg-gray-800 hover:shadow-md transition-all duration-200 font-medium w-full justify-center">
                    <i class="fas fa-arrow-left ml-2"></i>
                    دخول الوحدة
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
    // Realtime system time display based on server timestamp (with backend offset)
    (function() {
        const el = document.getElementById('system-time-display');
        if (!el) return;

        let ts = parseInt(el.getAttribute('data-system-timestamp'), 10) * 1000; // ms
        if (isNaN(ts)) return;

        function pad(n) { return n < 10 ? '0' + n : '' + n; }

        function tick() {
            const d = new Date(ts);
            const year = d.getFullYear();
            const month = pad(d.getMonth() + 1);
            const day = pad(d.getDate());
            const hours = pad(d.getHours());
            const mins = pad(d.getMinutes());

            el.textContent = hours + ':' + mins + ' ' + year + '-' + month + '-' + day;
            ts += 1000; // advance 1 second
        }

        tick();
        setInterval(tick, 1000);
    })();
</script>