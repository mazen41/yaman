<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/check_permissions.php';

$user_id = $_SESSION['user_id'] ?? 0;
if (!hasPermission($user_id, 'reports', 'view')) {
    header('Location: ../../index.php'); exit();
}

$search        = trim($_GET['search']        ?? '');
$group         = trim($_GET['group']         ?? '');
$city          = trim($_GET['city']          ?? '');
$ctype         = trim($_GET['ctype']         ?? '');
$currency      = trim($_GET['currency']      ?? '');
$has_remaining = trim($_GET['has_remaining'] ?? '');
$sort          = trim($_GET['sort']          ?? 'name');

$groups = $db->query("SELECT DISTINCT customer_group FROM customers WHERE customer_group IS NOT NULL AND customer_group!='' ORDER BY customer_group")->fetchAll(PDO::FETCH_COLUMN);
$cities = $db->query("SELECT DISTINCT city_name FROM customers WHERE city_name IS NOT NULL AND city_name!='' ORDER BY city_name")->fetchAll(PDO::FETCH_COLUMN);

$where  = ['1=1'];
$params = [];
if ($search)   { $where[]='(c.name LIKE ? OR c.mobile_number LIKE ? OR c.customer_code LIKE ?)'; $params[]="%$search%"; $params[]="%$search%"; $params[]="%$search%"; }
if ($group)    { $where[]='c.customer_group = ?'; $params[]=$group; }
if ($city)     { $where[]='c.city_name = ?';      $params[]=$city; }
if ($ctype)    { $where[]='c.customer_type = ?';  $params[]=$ctype; }
if ($currency) { $where[]='c.currency = ?';       $params[]=$currency; }
$where_sql = implode(' AND ', $where);

$allowed = ['name','total_orders','total_remaining','total_paid','total_amount'];
if (!in_array($sort,$allowed)) $sort='name';
$sort_dir = $sort==='name' ? 'ASC' : 'DESC';
$having   = $has_remaining==='1' ? 'HAVING total_remaining > 0' : ($has_remaining==='0' ? 'HAVING total_remaining <= 0' : '');

$stmt = $db->prepare("
    SELECT c.id, c.name, c.city_name, c.customer_group, c.mobile_number,
           c.customer_code, c.customer_type, c.currency, c.current_balance,
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
    LEFT JOIN customer_orders co ON co.customer_id=c.id
    WHERE $where_sql
    GROUP BY c.id $having
    ORDER BY $sort $sort_dir
");
$stmt->execute($params);
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
        <div style="display:flex;gap:8px">
            <?php
            // Build PDF URL carrying all active filter parameters
            $pdf_params = http_build_query(array_filter([
                'search'        => $search,
                'group'         => $group,
                'city'          => $city,
                'ctype'         => $ctype,
                'currency'      => $currency,
                'has_remaining' => $has_remaining,
                'sort'          => ($sort !== 'name') ? $sort : '',
            ]));
            $pdf_url = 'customer_summary_pdf.php' . ($pdf_params ? '?' . $pdf_params : '');
            ?>
            <a href="<?= htmlspecialchars($pdf_url) ?>" class="cs-btn cs-btn-success" target="_blank">
                <i class="fas fa-file-pdf"></i> تصدير PDF
            </a>
            <a href="?" class="cs-btn cs-btn-secondary"><i class="fas fa-redo"></i> مسح الفلاتر</a>
        </div>
    </div>

    <!-- Filters -->
    <div class="cs-filter no-print">
        <form method="GET">
            <div class="cs-filter-grid">

                <div class="cs-filter-item" style="min-width:200px">
                    <label>بحث (اسم / موبايل / كود)</label>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="اكتب للبحث...">
                </div>

                <div class="cs-filter-item">
                    <label>المجموعة</label>
                    <select name="group">
                        <option value="">الكل</option>
                        <?php foreach($groups as $g): ?>
                        <option value="<?= htmlspecialchars($g) ?>" <?= $group===$g?'selected':'' ?>><?= htmlspecialchars($g) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="cs-filter-item">
                    <label>المدينة</label>
                    <select name="city">
                        <option value="">الكل</option>
                        <?php foreach($cities as $ct): ?>
                        <option value="<?= htmlspecialchars($ct) ?>" <?= $city===$ct?'selected':'' ?>><?= htmlspecialchars($ct) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="cs-filter-item">
                    <label>نوع العميل</label>
                    <select name="ctype">
                        <option value="">الكل</option>
                        <option value="individual" <?= $ctype==='individual'?'selected':'' ?>>فرد</option>
                        <option value="company"    <?= $ctype==='company'?'selected':'' ?>>شركة</option>
                    </select>
                </div>

                <div class="cs-filter-item">
                    <label>العملة</label>
                    <select name="currency">
                        <option value="">الكل</option>
                        <option value="YER" <?= $currency==='YER'?'selected':'' ?>>ريال يمني</option>
                        <option value="SAR" <?= $currency==='SAR'?'selected':'' ?>>ريال سعودي</option>
                    </select>
                </div>

                <div class="cs-filter-item">
                    <label>المتبقي</label>
                    <select name="has_remaining">
                        <option value="">الكل</option>
                        <option value="1" <?= $has_remaining==='1'?'selected':'' ?>>عليهم متبقي</option>
                        <option value="0" <?= $has_remaining==='0'?'selected':'' ?>>لا يوجد متبقي</option>
                    </select>
                </div>

                <div class="cs-filter-item">
                    <label>الترتيب حسب</label>
                    <select name="sort">
                        <option value="name"            <?= $sort==='name'?'selected':'' ?>>الاسم</option>
                        <option value="total_orders"    <?= $sort==='total_orders'?'selected':'' ?>>عدد الطلبات</option>
                        <option value="total_remaining" <?= $sort==='total_remaining'?'selected':'' ?>>المتبقي</option>
                        <option value="total_paid"      <?= $sort==='total_paid'?'selected':'' ?>>المدفوع</option>
                        <option value="total_amount"    <?= $sort==='total_amount'?'selected':'' ?>>إجمالي الطلبات</option>
                    </select>
                </div>

                <div class="cs-filter-actions">
                    <button type="submit" class="cs-btn cs-btn-primary"><i class="fas fa-search"></i> بحث</button>
                    <a href="?" class="cs-btn cs-btn-secondary">مسح</a>
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
                    <th style="text-align:right">الموقع</th>
                    <th style="text-align:right">المجموعة</th>
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
                    <td><?= $c['customer_group'] ? '<span class="tag tag-blue">'.htmlspecialchars($c['customer_group']).'</span>' : '<span style="color:#cbd5e1">—</span>' ?></td>
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
<?php include '../../includes/footer.php'; ?>
