<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

session_start();
require_once '../../config/database.php';
require_once '../../includes/check_permissions.php';

// ── Debug: ensure PDO throws visible exceptions ──
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$user_id = $_SESSION['user_id'] ?? 0;
if (!hasPermission($user_id, 'customer_summary', 'view')) {
    header('Location: ../../no-permissions.php'); exit();
}

$is_pdf = isset($_GET['pdf']);

// ── Fetch data for filters (SAME as customers/index.php) ─────────────────────
try {
    $customer_types = $db->query("SELECT id, name FROM customer_types WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
    $cities = $db->query("SELECT id, name FROM cities WHERE is_active = 1 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $customer_types = [];
    $cities = [];
}

// ── Get Filter and Sort Parameters (SAME names & defaults as customers/index.php) ──
$search               = $_GET['search']               ?? '';
$filter_type          = $_GET['filter_type']          ?? '';
$filter_city          = $_GET['filter_city']          ?? '';
$filter_date_from     = $_GET['filter_date_from']     ?? '';
$filter_date_to       = $_GET['filter_date_to']       ?? '';
$filter_status        = $_GET['filter_status']        ?? 'active'; // active, inactive, all — default: active
$filter_remaining_from = $_GET['filter_remaining_from'] ?? '';
$filter_all_delivered = isset($_GET['filter_all_delivered']) && $_GET['filter_all_delivered'] == '1';

// Sorting (SAME whitelist as customers/index.php, adapted column aliases for this report's query)
$sort_options = [
    'updated_at'       => 'c.updated_at',
    'total_amount'     => 'total_amount',
    'total_orders'     => 'total_orders',
    'remaining_amount' => 'total_remaining',
    'name_alpha'       => 'c.name',
];
$sort_by = $_GET['sort_by'] ?? 'updated_at';
$sort_column = $sort_options[$sort_by] ?? 'c.updated_at';

$sort_dir_options = ['DESC' => 'DESC', 'ASC' => 'ASC'];
$sort_dir = $_GET['sort_dir'] ?? 'DESC';
$sort_direction = $sort_dir_options[$sort_dir] ?? 'DESC';

// Determine if any advanced filter is active (SAME logic as customers/index.php)
$advanced_filters_active = !empty($filter_type) ||
                           !empty($filter_city) ||
                           !empty($filter_date_from) ||
                           !empty($filter_date_to) ||
                           !empty($filter_remaining_from) ||
                           $filter_all_delivered ||
                           ($sort_by != 'updated_at') ||
                           ($sort_dir != 'DESC') ||
                           ($filter_status != 'active');

// ── Build WHERE conditions (SAME logic as customers/index.php) ───────────────
$where_clauses = ["1=1"];
$params = [];
$having_clauses = [];
$having_params = [];

// Apply status filter (SAME as customers/index.php)
if ($filter_status == 'active') {
    $where_clauses[] = "c.is_active = 1";
} elseif ($filter_status == 'inactive') {
    $where_clauses[] = "c.is_active = 0";
}
// 'all' → no condition added (same behavior)

// Search filter (SAME fields as customers/index.php: name, customer_code, mobile_number)
if ($search) {
    $where_clauses[] = "(c.name LIKE ? OR c.customer_code LIKE ? OR c.mobile_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Customer type filter (SAME: c.customer_type_id FK)
if ($filter_type) {
    $where_clauses[] = "c.customer_type_id = ?";
    $params[] = $filter_type;
}

// City filter (SAME: c.city_id FK)
if ($filter_city) {
    $where_clauses[] = "c.city_id = ?";
    $params[] = $filter_city;
}

// Date range filters (SAME: DATE(c.created_at))
if ($filter_date_from) {
    $where_clauses[] = "DATE(c.created_at) >= ?";
    $params[] = $filter_date_from;
}
if ($filter_date_to) {
    $where_clauses[] = "DATE(c.created_at) <= ?";
    $params[] = $filter_date_to;
}

// Remaining amount HAVING filter (SAME as customers/index.php)
if ($filter_remaining_from !== '' && is_numeric($filter_remaining_from)) {
    $having_clauses[] = "COALESCE(SUM(co.final_amount - co.paid_amount), 0) >= ?";
    $having_params[] = $filter_remaining_from;
}

$where_sql = implode(" AND ", $where_clauses);
$having_sql = empty($having_clauses) ? '' : 'HAVING ' . implode(" AND ", $having_clauses);

// "all delivered" filter: only customers whose non-cancelled orders are ALL delivered
if ($filter_all_delivered) {
    $having_sql .= empty($having_clauses)
        ? ' HAVING SUM(CASE WHEN co.status != \'delivered\' AND co.status != \'cancelled\' THEN 1 ELSE 0 END) = 0 AND COUNT(DISTINCT co.id) > 0'
        : ' AND SUM(CASE WHEN co.status != \'delivered\' AND co.status != \'cancelled\' THEN 1 ELSE 0 END) = 0 AND COUNT(DISTINCT co.id) > 0';
}

$all_query_params = array_merge($params, $having_params);

// ── Execute the report query ─────────────────────────────────────────────────
// Note: This query preserves the report's original calculations (order status breakdown,
// last_order_number, etc.) while applying the synchronized WHERE/HAVING conditions.
$stmt = $db->prepare("
    SELECT c.id, c.name,
           COALESCE(c.notes, '') AS notes,
           ct.name AS customer_type_name,
           city.name AS city_name,
           c.customer_code, c.mobile_number, c.phone, c.alternative_number,
           COUNT(DISTINCT co.id) AS total_orders,
           COALESCE(SUM(CASE WHEN co.status='delivered' THEN 1 ELSE 0 END),0) AS delivered_count,
           COALESCE(SUM(CASE WHEN co.status IN('ready','ready_to_deliver','جاهز للتسليم') THEN 1 ELSE 0 END),0) AS ready_count,
           COALESCE(SUM(CASE WHEN co.status NOT IN('delivered','cancelled','ready_to_deliver','ready','جاهز للتسليم') THEN 1 ELSE 0 END),0) AS other_count,
           COALESCE(SUM(co.final_amount),0) AS total_amount,
           COALESCE(SUM(co.paid_amount),0)  AS total_paid,
           COALESCE(SUM(co.final_amount - co.paid_amount),0) AS total_remaining,
           (SELECT co2.order_number FROM customer_orders co2 WHERE co2.customer_id=c.id ORDER BY co2.created_at DESC LIMIT 1) AS last_order_number,
           (SELECT co2.final_amount  FROM customer_orders co2 WHERE co2.customer_id=c.id ORDER BY co2.created_at DESC LIMIT 1) AS last_order_amount
    FROM customers c
    LEFT JOIN customer_types ct ON c.customer_type_id = ct.id
    LEFT JOIN cities city ON c.city_id = city.id
    LEFT JOIN customer_orders co ON co.customer_id = c.id
    WHERE $where_sql
    GROUP BY c.id
    $having_sql
    ORDER BY $sort_column $sort_direction, c.created_at DESC
");
$stmt->execute($all_query_params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$ttl_orders    = array_sum(array_column($customers,'total_orders'));
$ttl_paid      = array_sum(array_column($customers,'total_paid'));
$ttl_remaining = array_sum(array_column($customers,'total_remaining'));
$ttl_amount    = array_sum(array_column($customers,'total_amount'));

$page_title = 'ملخص العملاء';
include '../../includes/header.php';
?>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
/* ── Reset & base ── */
*, *::before, *::after { box-sizing: border-box; }

.cs-wrap {
    direction: rtl;
    padding: 18px;
    font-family: 'Cairo', 'Segoe UI', Tahoma, Arial, sans-serif;
}

/* ── Header bar ── */
.cs-topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:16px; flex-wrap:wrap; gap:10px; }
.cs-topbar h2 { margin:0; font-size:20px; font-weight:800; color:#1e293b; display:flex; align-items:center; gap:8px; }
.cs-topbar h2 span.badge { background:#3b82f6; color:#fff; border-radius:20px; font-size:13px; padding:2px 10px; }
.cs-btn { display:inline-flex; align-items:center; gap:6px; padding:7px 16px; border-radius:7px; border:none; cursor:pointer; font-size:13px; font-weight:700; text-decoration:none; transition:opacity .15s; font-family:'Cairo',sans-serif; }
.cs-btn-primary  { background:#3b82f6; color:#fff; }
.cs-btn-secondary{ background:#e2e8f0; color:#475569; }
.cs-btn-success  { background:#10b981; color:#fff; }
.cs-btn-warning  { background:#f59e0b; color:#fff; }
.cs-btn:hover { opacity:.85; }

/* ── Filter card ── */
.cs-filter { background:#fff; border-radius:10px; box-shadow:0 1px 4px rgba(0,0,0,.08); padding:16px 20px; margin-bottom:16px; }
.cs-filter-grid { display:flex; flex-wrap:wrap; gap:12px; align-items:flex-end; }
.cs-filter-item { display:flex; flex-direction:column; gap:4px; min-width:140px; }
.cs-filter-item label { font-size:11px; font-weight:700; color:#64748b; }
.cs-filter-item select,
.cs-filter-item input { border:1px solid #cbd5e1; border-radius:7px; padding:7px 10px; font-size:13px; color:#1e293b; outline:none; background:#f8fafc; transition:border .15s; font-family:'Cairo',sans-serif; }
.cs-filter-item select:focus,
.cs-filter-item input:focus { border-color:#3b82f6; background:#fff; }
.cs-filter-actions { display:flex; gap:8px; align-items:center; padding-top:18px; }

/* ── Summary cards ── */
.cs-cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(160px,1fr)); gap:12px; margin-bottom:16px; }
.cs-card { background:#fff; border-radius:10px; box-shadow:0 1px 4px rgba(0,0,0,.08); padding:14px 18px; border-top:4px solid; }
.cs-card.blue  { border-color:#3b82f6; } .cs-card.green { border-color:#10b981; }
.cs-card.red   { border-color:#ef4444; } .cs-card.gold  { border-color:#f59e0b; }
.cs-card .lbl  { font-size:11px; color:#64748b; font-weight:600; margin-bottom:4px; }
.cs-card .val  { font-size:20px; font-weight:800; color:#1e293b; }

/* ── Redesigned Report Table ── */
.cs-table-wrap {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 1px 4px rgba(0,0,0,.08);
    overflow: hidden;
}

.cs-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    direction: rtl;
    font-family: 'Cairo', 'Segoe UI', Tahoma, Arial, sans-serif;
}

/* ── Main header row (row 1) ── */
.cs-table thead tr.hd-row-1 th {
    background: #1e293b;
    color: #fff;
    padding: 10px 8px;
    text-align: center;
    font-weight: 700;
    font-size: 12px;
    white-space: nowrap;
    border: 1px solid #334155;
}

/* ── Sub-header row (row 2) ── */
.cs-table thead tr.hd-row-2 th {
    background: #334155;
    color: #e2e8f0;
    padding: 7px 8px;
    text-align: center;
    font-weight: 600;
    font-size: 11px;
    white-space: nowrap;
    border: 1px solid #475569;
}

/* متبقي sub-header highlighted */
.cs-table thead tr.hd-row-2 th.sub-remaining {
    color: #fca5a5;
    font-weight: 800;
}

/* مدفوع sub-header */
.cs-table thead tr.hd-row-2 th.sub-paid {
    color: #86efac;
    font-weight: 700;
}

/* ── Body rows ── */
.cs-table tbody tr {
    border-bottom: 1px solid #f1f5f9;
    transition: background .12s;
}
.cs-table tbody tr:hover            { background: #f0f9ff; }
.cs-table tbody tr:nth-child(even)  { background: #f8fafc; }
.cs-table tbody tr:nth-child(even):hover { background: #e0f2fe; }

.cs-table td {
    padding: 9px 8px;
    vertical-align: middle;
    border: 1px solid #e2e8f0;
    text-align: center;
}

/* name cell align right */
.cs-table td.td-name { text-align: right; }

/* ── Foot ── */
.cs-table tfoot tr { background: #f1f5f9; font-weight: 800; border-top: 2px solid #cbd5e1; }
.cs-table tfoot td { padding: 10px 8px; border: 1px solid #e2e8f0; font-weight: 800; }

/* ── Number formatting in cells ── */
.num-remaining { color: #dc2626; font-weight: 800; }
.num-paid      { color: #059669; font-weight: 700; }
.num-zero      { color: #10b981; }

/* ── Badges / tags ── */
.tag { display:inline-block; border-radius:5px; padding:2px 8px; font-size:11px; font-weight:700; }
.tag-blue  { background:#dbeafe; color:#1d4ed8; }
.tag-green { background:#d1fae5; color:#065f46; }
.tag-gray  { background:#f1f5f9; color:#475569; }
.tag-red   { background:#fee2e2; color:#991b1b; }
.tag-gold  { background:#fef3c7; color:#92400e; }
.tag-purple{ background:#ede9fe; color:#5b21b6; }

/* ── "فارغة" note style ── */
.note-empty { color: #94a3b8; font-style: italic; font-size: 11px; }

/* ════════════════════════════════════════════════
   PRINT RULES
   ════════════════════════════════════════════════ */
@media print {
    /* Hide all no-print elements */
    .no-print { display: none !important; }

    /* Hide المجموعة column (col index 4 when counting from right in RTL = 4th <td>) */
    /* We use a class .col-group on both th and td for المجموعة */
    .col-group { display: none !important; }

    /* Hide مدفوع sub-column under تم الاستلام only */
    /* Class .col-delivered-paid on both th and td */
    .col-delivered-paid { display: none !important; }

    /* Page setup */
    @page { margin: 1cm; size: A4 landscape; }

    body, .cs-wrap { padding: 0; font-size: 11px; }

    .cs-cards { grid-template-columns: repeat(4,1fr); }

    .cs-table-wrap {
        box-shadow: none;
        border-radius: 0;
        border: 1px solid #ccc;
    }

    .cs-table { font-size: 10px; }

    /* Force dark header colors to print */
    .cs-table thead tr.hd-row-1 th {
        background: #1e293b !important;
        color: #fff !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .cs-table thead tr.hd-row-2 th {
        background: #334155 !important;
        color: #e2e8f0 !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }
    .cs-table thead tr.hd-row-2 th.sub-remaining {
        color: #fca5a5 !important;
    }

    /* Force zebra stripes */
    .cs-table tbody tr:nth-child(even) {
        background: #f9fafb !important;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .cs-card { box-shadow: none; border: 1px solid #e2e8f0; }

    /* Print title */
    .print-title-bar {
        display: block !important;
        text-align: center;
        font-size: 15px;
        font-weight: 800;
        margin-bottom: 10px;
        color: #1e293b;
    }
}

/* Hide print title on screen */
.print-title-bar { display: none; }
</style>

<div class="cs-wrap">

    <!-- Top bar -->
    <div class="cs-topbar no-print">
        <h2><i class="fas fa-users"></i> ملخص العملاء <span class="badge"><?= count($customers) ?></span></h2>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?php
            // Build PDF URL carrying all active filter parameters (same names)
            $pdf_params = http_build_query(array_filter([
                'search'               => $search,
                'filter_type'          => $filter_type,
                'filter_city'          => $filter_city,
                'filter_date_from'     => $filter_date_from,
                'filter_date_to'       => $filter_date_to,
                'filter_status'        => ($filter_status !== 'active') ? $filter_status : '',
                'filter_remaining_from' => $filter_remaining_from,
                'sort_by'              => ($sort_by !== 'updated_at') ? $sort_by : '',
                'sort_dir'             => ($sort_dir !== 'DESC') ? $sort_dir : '',
            ]));
            $pdf_url = 'customer_summary_pdf.php' . ($pdf_params ? '?' . $pdf_params : '');
            ?>
            <button type="button" id="toggleAdvancedFiltersBtn"
                class="cs-btn" style="background:#475569;color:#fff">
                <i class="fas fa-filter"></i>
                <span id="toggleText"><?php echo $advanced_filters_active ? 'إخفاء الفلاتر المتقدمة' : 'إظهار الفلاتر المتقدمة'; ?></span>
            </button>
            <a href="<?= htmlspecialchars($pdf_url) ?>" class="cs-btn cs-btn-success" target="_blank">
                <i class="fas fa-file-pdf"></i> تصدير PDF
            </a>
            <button type="button" onclick="window.print()" class="cs-btn" style="background:#7c3aed;color:#fff">
                <i class="fas fa-print"></i> طباعة
            </button>
            <a href="?" class="cs-btn cs-btn-secondary"><i class="fas fa-redo"></i> مسح الفلاتر</a>
        </div>
    </div>

    <!-- Filters (SAME structure and parameter names as customers/index.php) -->
    <div class="cs-filter no-print">
        <form method="GET">
            <!-- Always visible: Search -->
            <div class="cs-filter-grid" style="margin-bottom:12px">
                <div class="cs-filter-item" style="min-width:250px;flex-grow:1">
                    <label>بحث (اسم، كود، جوال)</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ابحث بالاسم, الكود, الجوال...">
                </div>
                <div class="cs-filter-actions" style="padding-top:18px">
                    <button type="submit" class="cs-btn cs-btn-primary"><i class="fas fa-search"></i> بحث</button>
                    <a href="?" class="cs-btn cs-btn-secondary">مسح</a>
                </div>
            </div>

            <!-- Collapsible Advanced Filters -->
            <div id="advancedFilters" style="display: <?php echo $advanced_filters_active ? 'block' : 'none'; ?>;">
                <div class="cs-filter-grid">
                    <!-- Customer Type Filter (SAME as customers/index.php: filter_type → customer_type_id) -->
                    <div class="cs-filter-item">
                        <label>نوع العميل</label>
                        <select name="filter_type">
                            <option value="">الكل</option>
                            <?php foreach ($customer_types as $type): ?>
                                <option value="<?php echo $type['id']; ?>" <?php if ($filter_type == $type['id']) echo 'selected'; ?>><?php echo htmlspecialchars($type['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- City Filter (SAME as customers/index.php: filter_city → city_id) -->
                    <div class="cs-filter-item">
                        <label>المحافظة</label>
                        <select name="filter_city">
                            <option value="">الكل</option>
                            <?php foreach ($cities as $c_item): ?>
                                <option value="<?php echo $c_item['id']; ?>" <?php if ($filter_city == $c_item['id']) echo 'selected'; ?>><?php echo htmlspecialchars($c_item['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date From Filter (SAME as customers/index.php) -->
                    <div class="cs-filter-item">
                        <label>من تاريخ</label>
                        <input type="date" name="filter_date_from" value="<?= htmlspecialchars($filter_date_from) ?>">
                    </div>

                    <!-- Date To Filter (SAME as customers/index.php) -->
                    <div class="cs-filter-item">
                        <label>إلى تاريخ</label>
                        <input type="date" name="filter_date_to" value="<?= htmlspecialchars($filter_date_to) ?>">
                    </div>

                    <!-- Remaining Amount Filter (SAME as customers/index.php: filter_remaining_from) -->
                    <div class="cs-filter-item">
                        <label>المتبقي يبدأ من</label>
                        <input type="number" name="filter_remaining_from" placeholder="0" value="<?= htmlspecialchars($filter_remaining_from) ?>">
                    </div>

                    <!-- Sort By (SAME options as customers/index.php) -->
                    <div class="cs-filter-item">
                        <label>ترتيب حسب</label>
                        <select name="sort_by">
                            <option value="updated_at" <?php if ($sort_by == 'updated_at') echo 'selected'; ?>>آخر تعديل</option>
                            <option value="name_alpha" <?php if ($sort_by == 'name_alpha') echo 'selected'; ?>>الاسم بالأبجدية</option>
                            <option value="total_amount" <?php if ($sort_by == 'total_amount') echo 'selected'; ?>>إجمالي المبلغ</option>
                            <option value="remaining_amount" <?php if ($sort_by == 'remaining_amount') echo 'selected'; ?>>المبلغ المتبقي</option>
                            <option value="total_orders" <?php if ($sort_by == 'total_orders') echo 'selected'; ?>>عدد الطلبات</option>
                        </select>
                    </div>

                    <!-- Sort Direction (SAME as customers/index.php) -->
                    <div class="cs-filter-item">
                        <label>الاتجاه</label>
                        <select name="sort_dir">
                            <option value="DESC" <?php if ($sort_dir == 'DESC') echo 'selected'; ?>>تنازلي</option>
                            <option value="ASC" <?php if ($sort_dir == 'ASC') echo 'selected'; ?>>تصاعدي</option>
                        </select>
                    </div>

                    <!-- Status Filter (SAME as customers/index.php: default='active') -->
                    <div class="cs-filter-item">
                        <label>الحالة</label>
                        <select name="filter_status">
                            <option value="active" <?php if ($filter_status == 'active') echo 'selected'; ?>>نشط</option>
                            <option value="inactive" <?php if ($filter_status == 'inactive') echo 'selected'; ?>>معطل</option>
                            <option value="all" <?php if ($filter_status == 'all') echo 'selected'; ?>>الكل</option>
                        </select>
                    </div>

                    <!-- All Delivered Filter -->
                    <div class='cs-filter-item' style='justify-content:flex-end;padding-bottom:4px'>
                        <label style='display:flex;align-items:center;gap:8px;cursor:pointer;font-size:12px;color:#1e293b;font-weight:700'>
                            <input type='checkbox' name='filter_all_delivered' value='1'
                                <?php if ($filter_all_delivered) echo 'checked'; ?>
                                style='width:16px;height:16px;accent-color:#10b981;cursor:pointer'>
                            كل طلباته تم الاستلام
                        </label>
                    </div>
                </div>
            </div>
        </form>
    </div>

    <!-- Summary cards -->
    <div class="cs-cards">
        <div class="cs-card blue">
            <div class="lbl">عدد العملاء</div>
            <div class="val"><?= count($customers) ?></div>
        </div>
        <div class="cs-card gold">
            <div class="lbl">إجمالي الطلبات</div>
            <div class="val"><?= number_format($ttl_orders) ?></div>
        </div>
    </div>

    <!-- Print-only title -->
    <div class="print-title-bar">ملخص العملاء — تاريخ الطباعة: <?= date('Y/m/d') ?></div>

    <!-- Table -->
    <div class="cs-table-wrap">
        <div style="overflow-x:auto">
        <table class="cs-table">
            <thead>
                <!--
                    Column layout (RTL right→left):
                    1. م         (rowspan 2)
                    2. الاسم      (rowspan 2)
                    3. الموقع     (rowspan 2)
                    4. المجموعة   (rowspan 2) — screen only, hidden on print
                    5. جميع الارقام (rowspan 2)
                    6. تم الاستلام  (colspan 2) → مدفوع [print-hidden] | متبقي
                    7. جاهز للتوصيل (colspan 2) → مدفوع | متبقي
                    8. باقي الحالات (colspan 2) → مدفوع | متبقي
                    9. ملاحظات    (rowspan 2)
                -->
                <tr class="hd-row-1">
                    <th rowspan="2" style="width:36px">م</th>
                    <th rowspan="2" style="min-width:130px; text-align:right">الاسم</th>
                    <th rowspan="2" style="min-width:80px">الموقع</th>
                    <th rowspan="2" class="col-group no-print" style="min-width:90px">المجموعة</th>
                    <th rowspan="2" style="min-width:80px">جميع<br>الارقام</th>
                    <th colspan="2" style="background:#1a3a5c; border-bottom:1px solid #2563eb">تم الاستلام</th>
                    <th colspan="2" style="background:#14532d; border-bottom:1px solid #16a34a">جاهز للتوصيل</th>
                    <th colspan="2" style="background:#4c1d95; border-bottom:1px solid #7c3aed">باقي الحالات</th>
                    <th rowspan="2" style="min-width:90px">ملاحظات</th>
                </tr>
                <tr class="hd-row-2">
                    <!-- تم الاستلام sub-cols -->
                    <th class="sub-paid   col-delivered-paid" style="background:#1a3a5c; width:72px">مدفوع</th>
                    <th class="sub-remaining"                 style="background:#1a3a5c; width:72px">متبقي</th>
                    <!-- جاهز للتوصيل sub-cols -->
                    <th class="sub-paid"  style="background:#14532d; width:72px">مدفوع</th>
                    <th class="sub-remaining" style="background:#14532d; width:72px">متبقي</th>
                    <!-- باقي الحالات sub-cols -->
                    <th class="sub-paid"  style="background:#4c1d95; width:72px">مدفوع</th>
                    <th class="sub-remaining" style="background:#4c1d95; width:72px">متبقي</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($customers)): ?>
                <tr>
                    <td colspan="12" style="text-align:center;padding:40px;color:#94a3b8;font-size:15px">
                        <i class="fas fa-inbox" style="font-size:30px;display:block;margin-bottom:8px"></i>
                        لا توجد نتائج مطابقة للفلاتر
                    </td>
                </tr>
            <?php else: ?>
            <?php foreach ($customers as $i => $c):
                // ── Compute per-status financials ──────────────────────────
                // We need delivered / ready / other paid+remaining
                // The main query only returns counts; we do a quick sub-query per customer
                // to get per-status paid/remaining
                try {
                    $fin_stmt = $db->prepare("
                        SELECT
                            SUM(CASE WHEN status='delivered'
                                     THEN paid_amount ELSE 0 END) AS delivered_paid,
                            SUM(CASE WHEN status='delivered'
                                     THEN (final_amount - paid_amount) ELSE 0 END) AS delivered_remaining,
                            SUM(CASE WHEN status IN('ready','ready_to_deliver','جاهز للتسليم')
                                     THEN paid_amount ELSE 0 END) AS ready_paid,
                            SUM(CASE WHEN status IN('ready','ready_to_deliver','جاهز للتسليم')
                                     THEN (final_amount - paid_amount) ELSE 0 END) AS ready_remaining,
                            SUM(CASE WHEN status NOT IN('delivered','cancelled','ready_to_deliver','ready','جاهز للتسليم')
                                     THEN paid_amount ELSE 0 END) AS other_paid,
                            SUM(CASE WHEN status NOT IN('delivered','cancelled','ready_to_deliver','ready','جاهز للتسليم')
                                     THEN (final_amount - paid_amount) ELSE 0 END) AS other_remaining
                        FROM customer_orders
                        WHERE customer_id = ?
                    ");
                    $fin_stmt->execute([$c['id']]);
                    $fin = $fin_stmt->fetch(PDO::FETCH_ASSOC);
                } catch (Exception $e) {
                    $fin = ['delivered_paid'=>0,'delivered_remaining'=>0,'ready_paid'=>0,'ready_remaining'=>0,'other_paid'=>0,'other_remaining'=>0];
                }

                $fin['delivered_paid']      = max(0, (float)($fin['delivered_paid'] ?? 0));
                $fin['delivered_remaining'] = max(0, (float)($fin['delivered_remaining'] ?? 0));
                $fin['ready_paid']          = max(0, (float)($fin['ready_paid'] ?? 0));
                $fin['ready_remaining']     = max(0, (float)($fin['ready_remaining'] ?? 0));
                $fin['other_paid']          = max(0, (float)($fin['other_paid'] ?? 0));
                $fin['other_remaining']     = max(0, (float)($fin['other_remaining'] ?? 0));

                // Build phones string — all numbers the customer has
                $phones = array_filter([
                    $c['mobile_number'] ?? '',
                    $c['phone'] ?? '',
                    $c['alternative_number'] ?? '',
                ]);
                $all_numbers_str = !empty($phones) ? implode(' / ', $phones) : '—';

                // Notes — show فارغة if empty
                $notes_val = trim($c['notes'] ?? '');
            ?>
                <tr>
                    <!-- م -->
                    <td style="color:#94a3b8; font-size:12px; font-weight:700"><?= $i + 1 ?></td>

                    <!-- الاسم -->
                    <td class="td-name">
                        <div style="font-weight:800; color:#1e293b; font-size:13px"><?= htmlspecialchars($c['name']) ?></div>
                        <?php if (!empty($c['mobile_number'])): ?>
                        <div style="font-size:10px; color:#64748b; margin-top:2px; direction:ltr; text-align:right">
                            <?= htmlspecialchars($c['mobile_number']) ?>
                        </div>
                        <?php endif; ?>
                    </td>

                    <!-- الموقع -->
                    <td>
                        <?= $c['city_name']
                            ? '<span class="tag tag-gray">'.htmlspecialchars($c['city_name']).'</span>'
                            : '<span style="color:#cbd5e1">—</span>' ?>
                    </td>

                    <!-- المجموعة — screen only -->
                    <td class="col-group no-print">
                        <?= $c['customer_type_name']
                            ? '<span class="tag tag-blue">'.htmlspecialchars($c['customer_type_name']).'</span>'
                            : '<span style="color:#cbd5e1">—</span>' ?>
                    </td>

                    <!-- جميع الارقام — mobile number -->
                    <td style="font-size:12px; color:#475569; text-align:center; direction:ltr;">
                        <?= htmlspecialchars($all_numbers_str) ?>
                    </td>

                    <!-- تم الاستلام: مدفوع [print-hidden, pdf-empty] -->
                    <td class="col-delivered-paid">
                        <?php if ($is_pdf): ?>
                            
                        <?php elseif ($fin['delivered_paid'] > 0): ?>
                            <span class="num-paid"><?= number_format($fin['delivered_paid'], 0) ?></span>
                        <?php else: ?>
                            <span class="num-zero">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- تم الاستلام: متبقي -->
                    <td>
                        <?php if ($fin['delivered_remaining'] > 0): ?>
                            <span class="num-remaining"><?= number_format($fin['delivered_remaining'], 0) ?></span>
                        <?php else: ?>
                            <span class="num-zero" style="font-size:14px">✓</span>
                        <?php endif; ?>
                    </td>

                    <!-- جاهز للتوصيل: مدفوع -->
                    <td>
                        <?php if ($fin['ready_paid'] > 0): ?>
                            <span class="num-paid"><?= number_format($fin['ready_paid'], 0) ?></span>
                        <?php else: ?>
                            <span class="num-zero">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- جاهز للتوصيل: متبقي -->
                    <td>
                        <?php if ($fin['ready_remaining'] > 0): ?>
                            <span class="num-remaining"><?= number_format($fin['ready_remaining'], 0) ?></span>
                        <?php else: ?>
                            <span class="num-zero" style="font-size:14px">✓</span>
                        <?php endif; ?>
                    </td>

                    <!-- باقي الحالات: مدفوع -->
                    <td>
                        <?php if ($fin['other_paid'] > 0): ?>
                            <span class="num-paid"><?= number_format($fin['other_paid'], 0) ?></span>
                        <?php else: ?>
                            <span class="num-zero">—</span>
                        <?php endif; ?>
                    </td>

                    <!-- باقي الحالات: متبقي -->
                    <td>
                        <?php if ($fin['other_remaining'] > 0): ?>
                            <span class="num-remaining"><?= number_format($fin['other_remaining'], 0) ?></span>
                        <?php else: ?>
                            <span class="num-zero" style="font-size:14px">✓</span>
                        <?php endif; ?>
                    </td>

                    <!-- ملاحظات -->
                    <td style="text-align:right; font-size:11px; color:#475569; max-width:120px; word-break:break-word">
                        <?php if (!empty($notes_val)): ?>
                            <?= htmlspecialchars($notes_val) ?>
                        <?php else: ?>
                            
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
        </div>
    </div>

</div>

<!-- Advanced Filters Toggle Script (SAME behavior as customers/index.php) -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleBtn = document.getElementById('toggleAdvancedFiltersBtn');
        const filtersDiv = document.getElementById('advancedFilters');
        const toggleText = document.getElementById('toggleText');

        const advancedFiltersActive = <?php echo $advanced_filters_active ? 'true' : 'false'; ?>;

        if (advancedFiltersActive) {
             filtersDiv.style.display = 'block';
             toggleText.textContent = 'إخفاء الفلاتر المتقدمة';
        } else {
             filtersDiv.style.display = 'none';
             toggleText.textContent = 'إظهار الفلاتر المتقدمة';
        }

        toggleBtn.addEventListener('click', function() {
            const isHidden = filtersDiv.style.display === 'none';
            if (isHidden) {
                filtersDiv.style.display = 'block';
                toggleText.textContent = 'إخفاء الفلاتر المتقدمة';
            } else {
                filtersDiv.style.display = 'none';
                toggleText.textContent = 'إظهار الفلاتر المتقدمة';
            }
        });
    });
</script>
<?php include '../../includes/footer.php'; ?>
