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
    if (basename($_SERVER['PHP_SELF']) !== basename($redirect)) {
        header("Location: $redirect");
        exit();
    }
}

// Check if user is admin - stats only for admin
$is_admin = isUserAdmin($_SESSION['user_id'], $db);

$page_title = 'الصفحة الرئيسية';

// System time as UTC+3 (Asia/Aden) for display, independent of server timezone
date_default_timezone_set('Asia/Aden');

$system_date = date('Y-m-d');
$system_datetime = date('Y-m-d H:i');
$system_timestamp = time();

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
    error_log("Dashboard Statistics Error: " . $e->getMessage());
}

include 'includes/header.php';
?>

<style>
    .dash {
        font-family: 'Cairo', 'Segoe UI', Tahoma, Arial, sans-serif;
        max-width: 1200px;
        margin: 0 auto;
        padding: 24px 20px 40px;
        display: flex;
        flex-direction: column;
        gap: 24px;
        color: #1f2937;
    }
    .dash .fa, .dash .fas, .dash .far, .dash .fab { font-family: 'Font Awesome 5 Free', 'Font Awesome 5 Brands'; }

    /* Hero */
    .hero {
        background: linear-gradient(135deg, #16a34a 0%, #15803d 100%);
        color: #fff;
        border-radius: 16px;
        padding: 28px 28px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
        box-shadow: 0 6px 20px rgba(21, 128, 61, 0.2);
    }
    .hero h1 { margin: 0; font-size: 1.6rem; font-weight: 800; }
    .hero p { margin: 6px 0 0; color: #dcfce7; font-size: 0.95rem; }
    .clock {
        background: rgba(255, 255, 255, 0.15);
        border-radius: 12px;
        padding: 12px 16px;
        min-width: 180px;
    }
    .clock .label { font-size: 0.8rem; color: #dcfce7; display: flex; align-items: center; gap: 6px; }
    .clock .time { font-size: 1.1rem; font-weight: 700; margin-top: 4px; direction: ltr; text-align: right; }

    /* Notice */
    .notice {
        background: #fffbeb;
        border: 1px solid #fde68a;
        border-radius: 12px;
        padding: 18px 20px;
        display: flex;
        gap: 14px;
        align-items: flex-start;
    }
    .notice i { color: #d97706; font-size: 1.4rem; margin-top: 2px; }
    .notice h3 { margin: 0; font-size: 1rem; font-weight: 700; color: #92400e; }
    .notice p { margin: 4px 0 12px; color: #a16207; font-size: 0.9rem; }
    .notice .actions { display: flex; gap: 10px; flex-wrap: wrap; }

    /* Section titles */
    .section-title {
        font-size: 0.85rem;
        font-weight: 700;
        color: #6b7280;
        text-transform: uppercase;
        letter-spacing: .04em;
        margin: 0 0 12px;
    }

    /* Stat cards */
    .stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
    .stat {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 14px;
        padding: 18px;
        display: flex;
        align-items: center;
        gap: 14px;
    }
    .stat .ico {
        width: 46px; height: 46px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.1rem;
        flex-shrink: 0;
    }
    .stat .lbl { font-size: 0.85rem; color: #6b7280; font-weight: 600; }
    .stat .val { font-size: 1.5rem; font-weight: 800; color: #111827; line-height: 1.2; margin-top: 2px; }
    .stat .val small { font-size: 0.8rem; font-weight: 600; color: #6b7280; margin-right: 4px; }

    .tone-blue   .ico { background: #eff6ff; color: #2563eb; }
    .tone-green  .ico { background: #ecfdf5; color: #16a34a; }
    .tone-purple .ico { background: #faf5ff; color: #9333ea; }
    .tone-amber  .ico { background: #fffbeb; color: #d97706; }
    .tone-indigo .ico { background: #eef2ff; color: #4f46e5; }
    .tone-orange .ico { background: #fff7ed; color: #ea580c; }
    .tone-teal   .ico { background: #f0fdfa; color: #0d9488; }
    .tone-red    .ico { background: #fef2f2; color: #dc2626; }
    .tone-gray   .ico { background: #f3f4f6; color: #9ca3af; }

    .stats-secondary { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; }
    .stats-secondary .stat { padding: 14px 16px; }
    .stats-secondary .stat .val { font-size: 1.2rem; }

    /* Module cards */
    .modules-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 18px; }
    .module {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        padding: 22px;
        display: flex;
        flex-direction: column;
        gap: 14px;
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
    }
    .module:hover { transform: translateY(-3px); box-shadow: 0 10px 24px rgba(0,0,0,0.08); }
    .module .head { display: flex; align-items: center; gap: 14px; }
    .module .ico {
        width: 48px; height: 48px;
        border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.2rem;
        flex-shrink: 0;
    }
    .module h3 { margin: 0; font-size: 1.05rem; font-weight: 700; color: #111827; }
    .module .sub { margin: 2px 0 0; font-size: 0.85rem; color: #6b7280; }
    .module p.desc { margin: 0; color: #4b5563; font-size: 0.9rem; line-height: 1.6; flex: 1; }
    .module .btns { display: flex; gap: 10px; margin-top: auto; }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        height: 42px;
        padding: 0 16px;
        border-radius: 10px;
        font-size: 0.9rem;
        font-weight: 600;
        text-decoration: none;
        flex: 1;
        white-space: nowrap;
        transition: background-color .15s;
    }
    .btn-main { color: #fff; }
    .btn-soft { color: inherit; }

    .m-blue   .ico { background: #eff6ff; color: #2563eb; }  .m-blue   .btn-main { background: #2563eb; } .m-blue   .btn-main:hover { background: #1d4ed8; }
    .m-green  .ico { background: #ecfdf5; color: #16a34a; }  .m-green  .btn-main { background: #16a34a; } .m-green  .btn-main:hover { background: #15803d; }
    .m-purple .ico { background: #faf5ff; color: #9333ea; }  .m-purple .btn-main { background: #9333ea; } .m-purple .btn-main:hover { background: #7e22ce; }
    .m-red    .ico { background: #fef2f2; color: #dc2626; }  .m-red    .btn-main { background: #dc2626; } .m-red    .btn-main:hover { background: #b91c1c; }
    .m-gray   .ico { background: #f3f4f6; color: #4b5563; }  .m-gray   .btn-main { background: #374151; } .m-gray   .btn-main:hover { background: #1f2937; }

    .m-purple .btn-soft { background: #faf5ff; color: #7e22ce; }
    .m-purple .btn-soft:hover { background: #f3e8ff; }

    .empty-modules {
        grid-column: 1 / -1;
        text-align: center;
        padding: 40px 20px;
        color: #6b7280;
        background: #fff;
        border: 1px dashed #d1d5db;
        border-radius: 14px;
    }

    @media (max-width: 1024px) {
        .stats-grid, .stats-secondary { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .modules-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    }
    @media (max-width: 640px) {
        .dash { padding: 16px 12px 32px; gap: 18px; }
        .stats-grid, .stats-secondary, .modules-grid { grid-template-columns: 1fr; }
        .hero h1 { font-size: 1.3rem; }
    }
</style>

<div class="dash" dir="rtl">

    <!-- Hero -->
    <section class="hero">
        <div>
            <h1>مرحباً بك في نظام إدارة يمان</h1>
            <p>نظام إدارة شامل للأعمال والمؤسسات</p>
        </div>
        <div class="clock">
            <div class="label"><i class="fas fa-clock"></i> وقت النظام (عدن)</div>
            <div class="time">
                <span id="system-time-display" data-system-timestamp="<?php echo (int)$system_timestamp; ?>">
                    <?php echo htmlspecialchars($system_datetime); ?>
                </span>
            </div>
        </div>
    </section>

    <?php if ($customers_count == 0 && $total_orders == 0 && $total_products == 0): ?>
    <!-- Database Setup Notice -->
    <section class="notice">
        <i class="fas fa-exclamation-triangle"></i>
        <div>
            <h3>قاعدة البيانات فارغة</h3>
            <p>لرؤية الإحصائيات الحقيقية، يرجى إضافة بعض البيانات التجريبية.</p>
            <div class="actions">
                <a href="check_database_structure.php" class="btn btn-soft" style="flex:none; background:#fde68a; color:#92400e;">
                    <i class="fas fa-database"></i> فحص قاعدة البيانات
                </a>
                <a href="add_sample_data.php" class="btn btn-main" style="flex:none; background:#16a34a;">
                    <i class="fas fa-plus"></i> إضافة بيانات تجريبية
                </a>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($is_admin): ?>
    <!-- Key Stats -->
    <section>
        <h2 class="section-title">نظرة عامة</h2>
        <div class="stats-grid">
            <div class="stat tone-blue">
                <div class="ico"><i class="fas fa-users"></i></div>
                <div>
                    <div class="lbl">إجمالي العملاء</div>
                    <div class="val"><?php echo number_format($customers_count, 0, '', ''); ?></div>
                </div>
            </div>
            <div class="stat tone-green">
                <div class="ico"><i class="fas fa-shopping-bag"></i></div>
                <div>
                    <div class="lbl">الطلبات اليوم</div>
                    <div class="val"><?php echo number_format($today_orders, 0, '', ''); ?></div>
                </div>
            </div>
            <div class="stat tone-purple">
                <div class="ico"><i class="fas fa-ticket-alt"></i></div>
                <div>
                    <div class="lbl">الكوبونات النشطة</div>
                    <div class="val"><?php echo number_format($active_coupons, 0, '', ''); ?></div>
                </div>
            </div>
            <div class="stat tone-amber">
                <div class="ico"><i class="fas fa-coins"></i></div>
                <div>
                    <div class="lbl">المبيعات اليوم</div>
                    <div class="val"><?php echo number_format($today_sales, 0, '', ''); ?><small>ر.ي</small></div>
                </div>
            </div>
        </div>
    </section>

    <!-- Secondary Stats -->
    <section class="stats-secondary">
        <div class="stat tone-indigo">
            <div class="ico"><i class="fas fa-clipboard-list"></i></div>
            <div>
                <div class="lbl">إجمالي الطلبات</div>
                <div class="val"><?php echo number_format($total_orders, 0, '', ''); ?></div>
            </div>
        </div>
        <div class="stat tone-orange">
            <div class="ico"><i class="fas fa-clock"></i></div>
            <div>
                <div class="lbl">طلبات معلقة</div>
                <div class="val"><?php echo number_format($pending_orders, 0, '', ''); ?></div>
            </div>
        </div>
        <div class="stat tone-teal">
            <div class="ico"><i class="fas fa-boxes"></i></div>
            <div>
                <div class="lbl">إجمالي المنتجات</div>
                <div class="val"><?php echo number_format($total_products, 0, '', ''); ?></div>
            </div>
        </div>
        <div class="stat <?php echo $low_stock_products > 0 ? 'tone-red' : 'tone-gray'; ?>">
            <div class="ico"><i class="fas fa-exclamation-triangle"></i></div>
            <div>
                <div class="lbl">مخزون منخفض</div>
                <div class="val"><?php echo number_format($low_stock_products, 0, '', ''); ?></div>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <!-- Modules -->
    <section>
        <h2 class="section-title">الوحدات</h2>
        <div class="modules-grid">
            <?php
            $modules_shown = 0;

            if (hasPermission($_SESSION['user_id'], 'customers', 'view')): $modules_shown++; ?>
            <div class="module m-blue">
                <div class="head">
                    <div class="ico"><i class="fas fa-users"></i></div>
                    <div>
                        <h3>إدارة العملاء</h3>
                        <p class="sub">بيانات العملاء والحسابات</p>
                    </div>
                </div>
                <p class="desc">إدارة بيانات العملاء والحسابات والمعاملات المالية مع تتبع تاريخ المشتريات.</p>
                <div class="btns">
                    <a href="modules/customers/" class="btn btn-main">دخول الوحدة <i class="fas fa-arrow-left"></i></a>
                </div>
            </div>
            <?php endif; ?>

            <?php if (hasPermission($_SESSION['user_id'], 'orders', 'view')): $modules_shown++; ?>
            <div class="module m-green">
                <div class="head">
                    <div class="ico"><i class="fas fa-shopping-bag"></i></div>
                    <div>
                        <h3>طلبات العملاء</h3>
                        <p class="sub">إدارة الطلبات والمبيعات</p>
                    </div>
                </div>
                <p class="desc">متابعة الطلبات ومعالجة المبيعات وتتبع حالة كل طلب من البداية حتى التسليم.</p>
                <div class="btns">
                    <a href="modules/orders/" class="btn btn-main">دخول الوحدة <i class="fas fa-arrow-left"></i></a>
                </div>
            </div>
            <?php endif; ?>

            <?php if (hasPermission($_SESSION['user_id'], 'coupons', 'view')): $modules_shown++; ?>
            <div class="module m-purple">
                <div class="head">
                    <div class="ico"><i class="fas fa-ticket-alt"></i></div>
                    <div>
                        <h3>إدارة الكوبونات</h3>
                        <p class="sub">كوبونات الخصم والعروض</p>
                    </div>
                </div>
                <p class="desc">إدارة كوبونات الخصم والعروض الترويجية مع متابعة الاستخدام.</p>
                <div class="btns">
                    <a href="modules/coupons/index.php" class="btn btn-main">عرض الكوبونات</a>
                    <a href="modules/coupons/add.php" class="btn btn-soft">إضافة كوبون</a>
                </div>
            </div>
            <?php endif; ?>

            <?php if (hasPermission($_SESSION['user_id'], 'financial', 'view')): $modules_shown++; ?>
            <div class="module m-red">
                <div class="head">
                    <div class="ico"><i class="fas fa-coins"></i></div>
                    <div>
                        <h3>الحسابات المالية</h3>
                        <p class="sub">التقارير المالية</p>
                    </div>
                </div>
                <p class="desc">إدارة الحسابات المالية والتقارير والميزانيات مع تحليل الأرباح والخسائر.</p>
                <div class="btns">
                    <a href="modules/financial/" class="btn btn-main">دخول الوحدة <i class="fas fa-arrow-left"></i></a>
                </div>
            </div>
            <?php endif; ?>

            <?php if (hasPermission($_SESSION['user_id'], 'reports', 'view')): $modules_shown++; ?>
            <div class="module m-gray">
                <div class="head">
                    <div class="ico"><i class="fas fa-chart-bar"></i></div>
                    <div>
                        <h3>التقارير والطباعة</h3>
                        <p class="sub">تقارير شاملة</p>
                    </div>
                </div>
                <p class="desc">إنشاء التقارير المفصلة والفواتير والمستندات مع خيارات طباعة متقدمة.</p>
                <div class="btns">
                    <a href="modules/reports/" class="btn btn-main">دخول الوحدة <i class="fas fa-arrow-left"></i></a>
                </div>
            </div>
            <?php endif; ?>

            <?php if (hasPermission($_SESSION['user_id'], 'settings', 'view')): $modules_shown++; ?>
            <div class="module m-gray">
                <div class="head">
                    <div class="ico"><i class="fas fa-cog"></i></div>
                    <div>
                        <h3>إعدادات النظام</h3>
                        <p class="sub">تكوين النظام</p>
                    </div>
                </div>
                <p class="desc">إعدادات النظام وإدارة المستخدمين والصلاحيات.</p>
                <div class="btns">
                    <a href="modules/settings/" class="btn btn-main">دخول الوحدة <i class="fas fa-arrow-left"></i></a>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($modules_shown === 0): ?>
            <div class="empty-modules">
                <i class="fas fa-lock" style="font-size:2rem; color:#d1d5db; display:block; margin-bottom:10px;"></i>
                <p style="margin:0;">لا توجد وحدات متاحة لحسابك حالياً. يرجى التواصل مع المدير.</p>
            </div>
            <?php endif; ?>
        </div>
    </section>

</div>

<?php include 'includes/footer.php'; ?>
<script>
    // Realtime system time display based on server timestamp
    (function() {
        const el = document.getElementById('system-time-display');
        if (!el) return;

        let ts = parseInt(el.getAttribute('data-system-timestamp'), 10) * 1000; // ms
        if (isNaN(ts)) return;

        // Always format in Yemen/Aden time, regardless of the browser's timezone
        const fmt = new Intl.DateTimeFormat('en-CA', {
            timeZone: 'Asia/Aden',
            year: 'numeric', month: '2-digit', day: '2-digit',
            hour: '2-digit', minute: '2-digit', hour12: false
        });

        function tick() {
            const parts = {};
            fmt.formatToParts(new Date(ts)).forEach(p => { parts[p.type] = p.value; });
            const hour = parts.hour === '24' ? '00' : parts.hour;
            el.textContent = hour + ':' + parts.minute + ' ' + parts.year + '-' + parts.month + '-' + parts.day;
            ts += 1000;
        }

        tick();
        setInterval(tick, 1000);
    })();
</script>
