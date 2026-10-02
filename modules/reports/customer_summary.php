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
if (!hasPermission($user_id, 'reports', 'view')) {
    header('Location: ../../index.php'); exit();
}

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
$all_query_params = array_merge($params, $having_params);

// ── Execute the report query ─────────────────────────────────────────────────
// Note: This query preserves the report's original calculations (order status breakdown,
// last_order_number, etc.) while applying the synchronized WHERE/HAVING conditions.
$stmt = $db->prepare("
    SELECT c.id, c.name,
           ct.name AS customer_type_name,
           city.name AS city_name,
           c.customer_code, c.mobile_number,
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
<style>
/* ── Reset & base ── */
*{box-sizing:border-box}
.cs-wrap{direction:rtl;padding:18px;font-family:'Segoe UI',Tahoma,Arial,sans-serif}

/* ── Header bar ── */
.cs-topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:10px}
.cs-topbar h2{margin:0;font-size:20px;font-weight:700;color:#1e293b;display:flex;align-items:center;gap:8px}
.cs-topbar h2 span.badge{background:#3b82f6;color:#fff;border-radius:20px;font-size:13px;padding:2px 10px}
.cs-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 16px;border-radius:7px;border:none;cursor:pointer;font-size:13px;font-weight:600;text-decoration:none;transition:opacity .15s}
.cs-btn-primary{background:#3b82f6;color:#fff}
.cs-btn-secondary{background:#e2e8f0;color:#475569}
.cs-btn-success{background:#10b981;color:#fff}
.cs-btn-warning{background:#f59e0b;color:#fff}
.cs-btn:hover{opacity:.85}

/* ── Filter card ── */
.cs-filter{background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,.08);padding:16px 20px;margin-bottom:16px}
.cs-filter-grid{display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end}
.cs-filter-item{display:flex;flex-direction:column;gap:4px;min-width:140px}
.cs-filter-item label{font-size:11px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.4px}
.cs-filter-item select,
.cs-filter-item input{border:1px solid #cbd5e1;border-radius:7px;padding:7px 10px;font-size:13px;color:#1e293b;outline:none;background:#f8fafc;transition:border .15s}
.cs-filter-item select:focus,
.cs-filter-item input:focus{border-color:#3b82f6;background:#fff}
.cs-filter-actions{display:flex;gap:8px;align-items:center;padding-top:18px}

/* ── Summary cards ── */
.cs-cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:16px}
.cs-card{background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,.08);padding:14px 18px;border-top:4px solid}
.cs-card.blue{border-color:#3b82f6}.cs-card.green{border-color:#10b981}.cs-card.red{border-color:#ef4444}.cs-card.gold{border-color:#f59e0b}
.cs-card .lbl{font-size:11px;color:#64748b;font-weight:600;margin-bottom:4px}
.cs-card .val{font-size:20px;font-weight:800;color:#1e293b}

/* ── Table ── */
.cs-table-wrap{background:#fff;border-radius:10px;box-shadow:0 1px 4px rgba(0,0,0,.08);overflow:hidden}
.cs-table{width:100%;border-collapse:collapse;font-size:13px}
.cs-table thead tr{background:#1e293b;color:#fff}
.cs-table thead th{padding:11px 10px;white-space:nowrap;font-weight:600;font-size:12px}
.cs-table thead th a{color:#94a3b8;text-decoration:none;font-size:10px;margin-right:3px}
.cs-table thead th a:hover{color:#fff}
.cs-table tbody tr{border-bottom:1px solid #f1f5f9;transition:background .1s}
.cs-table tbody tr:hover{background:#f8fafc}
.cs-table tbody tr:nth-child(even){background:#f9fafb}
.cs-table tbody tr:nth-child(even):hover{background:#f1f5f9}
.cs-table td{padding:9px 10px;vertical-align:middle}
.cs-table tfoot tr{background:#f1f5f9;font-weight:700;border-top:2px solid #e2e8f0}
.cs-table tfoot td{padding:10px}

/* badges */
.tag{display:inline-block;border-radius:5px;padding:2px 8px;font-size:11px;font-weight:600}
.tag-blue{background:#dbeafe;color:#1d4ed8}
.tag-green{background:#d1fae5;color:#065f46}
.tag-gray{background:#f1f5f9;color:#475569}
.tag-red{background:#fee2e2;color:#991b1b}

/* print */
@media print{
    .no-print{display:none!important}
    .cs-wrap{padding:0}
    .cs-cards{grid-template-columns:repeat(4,1fr)}
    .cs-table{font-size:11px}
    .cs-table thead tr{background:#1e293b!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .cs-table tbody tr:nth-child(even){background:#f9fafb!important;-webkit-print-color-adjust:exact;print-color-adjust:exact}
    .cs-card{box-shadow:none;border:1px solid #e2e8f0}
}
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
        <div class="cs-card green">
            <div class="lbl">إجمالي المدفوع</div>
            <div class="val"><?= number_format($ttl_paid) ?></div>
        </div>
        <div class="cs-card red">
            <div class="lbl">إجمالي المتبقي</div>
            <div class="val"><?= number_format($ttl_remaining) ?></div>
        </div>
    </div>

    <!-- Table -->
    <div class="cs-table-wrap">
        <div style="overflow-x:auto">
        <table class="cs-table">
            <thead>
                <tr>
                    <th style="width:40px">#</th>
                    <th style="text-align:right">الاسم</th>
                    <th style="text-align:right">المحافظة</th>
                    <th style="text-align:right">الفئة</th>
                    <th style="text-align:center">جميع الطلبات</th>
                    <th style="text-align:center">تم الاستلام</th>
                    <th style="text-align:center">جاهز للتوصيل</th>
                    <th style="text-align:center">باقي الحالات</th>
                    <th style="text-align:center">مدفوع</th>
                    <th style="text-align:center;color:#fca5a5">متبقي</th>
                    <th style="text-align:right" class="no-print">آخر طلب</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($customers)): ?>
                <tr><td colspan="11" style="text-align:center;padding:40px;color:#94a3b8;font-size:15px"><i class="fas fa-inbox" style="font-size:30px;display:block;margin-bottom:8px"></i>لا توجد نتائج</td></tr>
            <?php else: ?>
            <?php foreach ($customers as $i => $c): ?>
                <tr>
                    <td style="color:#94a3b8;font-size:12px"><?= $i+1 ?></td>
                    <td>
                        <div style="font-weight:700;color:#1e293b"><?= htmlspecialchars($c['name']) ?></div>
                        <div style="font-size:11px;color:#64748b;margin-top:2px">
                            <?= htmlspecialchars($c['mobile_number'] ?? '') ?>
                            <?php if($c['customer_code']): ?> &nbsp;·&nbsp; <span style="color:#94a3b8"><?= htmlspecialchars($c['customer_code']) ?></span><?php endif; ?>
                        </div>
                    </td>
                    <td><?= $c['city_name'] ? '<span class="tag tag-gray">'.htmlspecialchars($c['city_name']).'</span>' : '<span style="color:#cbd5e1">—</span>' ?></td>
                    <td><?= $c['customer_type_name'] ? '<span class="tag tag-blue">'.htmlspecialchars($c['customer_type_name']).'</span>' : '<span style="color:#cbd5e1">—</span>' ?></td>
                    <td style="text-align:center;font-weight:700;font-size:15px"><?= $c['total_orders'] ?></td>
                    <td style="text-align:center"><span class="tag tag-green"><?= $c['delivered_count'] ?></span></td>
                    <td style="text-align:center"><span class="tag" style="background:#fef3c7;color:#92400e"><?= $c['ready_count'] ?></span></td>
                    <td style="text-align:center"><span class="tag" style="background:#ede9fe;color:#5b21b6"><?= $c['other_count'] ?></span></td>
                    <td style="text-align:center;color:#059669;font-weight:600"><?= number_format($c['total_paid'],0) ?></td>
                    <td style="text-align:center">
                        <?php if($c['total_remaining'] > 0): ?>
                            <span style="color:#dc2626;font-weight:800"><?= number_format($c['total_remaining'],0) ?></span>
                        <?php else: ?>
                            <span style="color:#10b981;font-weight:600">✓</span>
                        <?php endif; ?>
                    </td>
                    <td class="no-print" style="font-size:12px;color:#64748b">
                        <?php if($c['last_order_number']): ?>
                            <div><?= htmlspecialchars($c['last_order_number']) ?></div>
                            <div style="color:#10b981;font-size:11px"><?= number_format($c['last_order_amount'],0) ?></div>
                        <?php else: ?><span style="color:#cbd5e1">—</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
            <?php if (!empty($customers)): ?>
            <tfoot>
                <tr>
                    <td colspan="4" style="text-align:right;color:#475569">الإجمالي (<?= count($customers) ?> عميل)</td>
                    <td style="text-align:center"><?= number_format($ttl_orders) ?></td>
                    <td></td><td></td><td></td>
                    <td style="text-align:center;color:#059669"><?= number_format($ttl_paid) ?></td>
                    <td style="text-align:center;color:#dc2626"><?= number_format($ttl_remaining) ?></td>
                    <td class="no-print"></td>
                </tr>
            </tfoot>
            <?php endif; ?>
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
