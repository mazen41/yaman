<?php
/**
 * customer_summary_pdf.php — Matches customer_summary.php exactly
 */
session_start();
require_once '../../config/database.php';
require_once '../../includes/check_permissions.php';

$user_id = $_SESSION['user_id'] ?? 0;
if (!hasPermission($user_id, 'reports', 'view')) {
    http_response_code(403); exit('غير مصرح');
}

$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    exit('مكتبة mPDF غير مثبتة. يرجى تنفيذ: composer require mpdf/mpdf في مجلد المشروع.');
}
require_once $autoload;

$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// ── Fetch filter labels ───────────────────────────────────────────────────────
try {
    $customer_types_map = $db->query("SELECT id, name FROM customer_types WHERE is_active = 1")->fetchAll(PDO::FETCH_KEY_PAIR);
    $cities_map         = $db->query("SELECT id, name FROM cities WHERE is_active = 1")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $customer_types_map = []; $cities_map = [];
}

// ── Filters (same as customer_summary.php) ───────────────────────────────────
$search                = $_GET['search']                ?? '';
$filter_type           = $_GET['filter_type']           ?? '';
$filter_city           = $_GET['filter_city']           ?? '';
$filter_date_from      = $_GET['filter_date_from']      ?? '';
$filter_date_to        = $_GET['filter_date_to']        ?? '';
$filter_status         = $_GET['filter_status']         ?? 'active';
$filter_remaining_from = $_GET['filter_remaining_from'] ?? '';

$sort_options  = ['updated_at'=>'c.updated_at','total_amount'=>'total_amount','total_orders'=>'total_orders','remaining_amount'=>'total_remaining','name_alpha'=>'c.name'];
$sort_by       = $_GET['sort_by']  ?? 'updated_at';
$sort_column   = $sort_options[$sort_by] ?? 'c.updated_at';
$sort_dir      = $_GET['sort_dir'] ?? 'DESC';
$sort_direction = ($sort_dir === 'ASC') ? 'ASC' : 'DESC';

// ── Selected rows mode (ids=1,2,3) — printed from the report page checkboxes ──
$selected_ids = [];
if (!empty($_GET['ids'])) {
    foreach (explode(',', (string)$_GET['ids']) as $v) {
        $v = trim($v);
        if (ctype_digit($v) && (int)$v > 0) $selected_ids[] = (int)$v;
    }
    $selected_ids = array_values(array_unique($selected_ids));
}

// ── WHERE / HAVING ────────────────────────────────────────────────────────────
$where_clauses = ["1=1"]; $params = []; $having_clauses = []; $having_params = [];

if ($filter_status == 'active')   { $where_clauses[] = "c.is_active = 1"; }
elseif ($filter_status == 'inactive') { $where_clauses[] = "c.is_active = 0"; }

if ($search) {
    $where_clauses[] = "(c.name LIKE ? OR c.customer_code LIKE ? OR c.mobile_number LIKE ?)";
    $sp = "%$search%"; $params[] = $sp; $params[] = $sp; $params[] = $sp;
}
if ($filter_type)  { $where_clauses[] = "c.customer_type_id = ?"; $params[] = $filter_type; }
if ($filter_city)  { $where_clauses[] = "c.city_id = ?";          $params[] = $filter_city; }
if ($filter_date_from) { $where_clauses[] = "DATE(c.created_at) >= ?"; $params[] = $filter_date_from; }
if ($filter_date_to)   { $where_clauses[] = "DATE(c.created_at) <= ?"; $params[] = $filter_date_to; }
if ($filter_remaining_from !== '' && is_numeric($filter_remaining_from)) {
    $having_clauses[] = "COALESCE(SUM(co.final_amount - co.paid_amount), 0) >= ?";
    $having_params[]  = $filter_remaining_from;
}

if ($selected_ids) {
    // Selected-rows mode: print exactly the checked customers, ignore other filters
    $where_sql  = "c.id IN (" . implode(',', $selected_ids) . ")";
    $having_sql = '';
    $all_query_params = [];
} else {
    $where_sql       = implode(" AND ", $where_clauses);
    $having_sql      = empty($having_clauses) ? '' : 'HAVING ' . implode(" AND ", $having_clauses);
    $all_query_params = array_merge($params, $having_params);
}

// ── Main query (same as customer_summary.php) ─────────────────────────────────
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
           COALESCE(SUM(co.final_amount - co.paid_amount),0) AS total_remaining
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

$ttl_orders    = array_sum(array_column($customers, 'total_orders'));
$ttl_paid      = array_sum(array_column($customers, 'total_paid'));
$ttl_remaining = array_sum(array_column($customers, 'total_remaining'));
$count         = count($customers);
$generated_at  = date('Y/m/d H:i:s');

// ── Helpers ───────────────────────────────────────────────────────────────────
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function n($v, $d = 0) { return number_format((float)$v, $d); }

// ── Filter description ────────────────────────────────────────────────────────
$fp = [];
if ($search)             $fp[] = 'بحث: ' . $search;
if ($filter_type)        $fp[] = 'نوع العميل: ' . ($customer_types_map[$filter_type] ?? $filter_type);
if ($filter_city)        $fp[] = 'المحافظة: ' . ($cities_map[$filter_city] ?? $filter_city);
if ($filter_date_from)   $fp[] = 'من: ' . $filter_date_from;
if ($filter_date_to)     $fp[] = 'إلى: ' . $filter_date_to;
if ($filter_status == 'active')   $fp[] = 'الحالة: نشط';
if ($filter_status == 'inactive') $fp[] = 'الحالة: معطل';
if ($filter_status == 'all')      $fp[] = 'الحالة: الكل';
if ($filter_remaining_from !== '' && is_numeric($filter_remaining_from)) $fp[] = 'المتبقي من: ' . n($filter_remaining_from);
if ($selected_ids) {
    $filter_text = 'عملاء محددون (' . count($selected_ids) . ' عميل)';
} elseif ($fp) {
    $filter_text = implode(' | ', $fp);
} else {
    $filter_text = 'جميع العملاء النشطين';
}

// ── Build table rows ──────────────────────────────────────────────────────────
$rows_html = '';
foreach ($customers as $i => $c) {

    // Per-status financials — 4 statuses matching customer_summary.php
    try {
        $fin_stmt = $db->prepare("
            SELECT
                SUM(CASE WHEN status IN('delivered','تم الاستلام') THEN 1 ELSE 0 END) AS delivered_orders,
                SUM(CASE WHEN status IN('delivered','تم الاستلام') THEN paid_amount ELSE 0 END) AS delivered_paid,
                SUM(CASE WHEN status IN('delivered','تم الاستلام') THEN (final_amount-paid_amount) ELSE 0 END) AS delivered_remaining,
                SUM(CASE WHEN status IN('ready','ready_to_deliver','جاهز للتسليم','جاهز للتوصيل') THEN 1 ELSE 0 END) AS ready_orders,
                SUM(CASE WHEN status IN('ready','ready_to_deliver','جاهز للتسليم','جاهز للتوصيل') THEN paid_amount ELSE 0 END) AS ready_paid,
                SUM(CASE WHEN status IN('ready','ready_to_deliver','جاهز للتسليم','جاهز للتوصيل') THEN (final_amount-paid_amount) ELSE 0 END) AS ready_remaining,
                SUM(CASE WHEN status IN('تم الشراء','purchased','processing') THEN 1 ELSE 0 END) AS purchased_orders,
                SUM(CASE WHEN status IN('تم الشراء','purchased','processing') THEN paid_amount ELSE 0 END) AS purchased_paid,
                SUM(CASE WHEN status IN('تم الشراء','purchased','processing') THEN (final_amount-paid_amount) ELSE 0 END) AS purchased_remaining,
                SUM(CASE WHEN status IN('new','جديد') THEN 1 ELSE 0 END) AS new_orders,
                SUM(CASE WHEN status IN('new','جديد') THEN paid_amount ELSE 0 END) AS new_paid,
                SUM(CASE WHEN status IN('new','جديد') THEN (final_amount-paid_amount) ELSE 0 END) AS new_remaining
            FROM customer_orders WHERE customer_id = ?
        ");
        $fin_stmt->execute([$c['id']]);
        $fin = $fin_stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $fin = ['delivered_orders'=>0,'delivered_paid'=>0,'delivered_remaining'=>0,
                'ready_orders'=>0,'ready_paid'=>0,'ready_remaining'=>0,
                'purchased_orders'=>0,'purchased_paid'=>0,'purchased_remaining'=>0,
                'new_orders'=>0,'new_paid'=>0,'new_remaining'=>0];
    }
    foreach (['delivered_paid','delivered_remaining','ready_paid','ready_remaining',
              'purchased_paid','purchased_remaining','new_paid','new_remaining'] as $k)
        $fin[$k] = max(0, (float)($fin[$k] ?? 0));
    foreach (['delivered_orders','ready_orders','purchased_orders','new_orders'] as $k)
        $fin[$k] = (int)($fin[$k] ?? 0);

    // All phone numbers
    $phones = array_filter([
        $c['mobile_number'] ?? '',
        $c['phone'] ?? '',
        $c['alternative_number'] ?? '',
    ]);
    $phone_str = !empty($phones) ? h(implode(' / ', $phones)) : '—';

    $bg    = ($i % 2 === 0) ? '#ffffff' : '#e8edf2';
    $notes = trim($c['notes'] ?? '');
    $location = implode(' - ', array_filter([$c['city_name']??'', $c['customer_code']??'']));

    // cell helpers
    $cell_rem = fn($v) => $v > 0 ? '<span style="color:#b91c1c;font-weight:800;">'.n($v).'</span>' : '<span style="color:#10b981;">✓</span>';
    $cell_paid = fn($v) => $v > 0 ? '<span style="color:#059669;">'.n($v).'</span>' : '&nbsp;';
    // "جاهز للتوصيل" paid column always shows the actual figure (including 0), per request
    $cell_paid_ready = fn($v) => '<span style="color:#059669;">'.n($v).'</span>';

    $rows_html .= "
    <tr style=\"background:{$bg};\">
        <td style=\"text-align:center;color:#1e293b;font-size:9px;font-weight:700;\">" . ($i + 1) . "</td>
        <td style=\"text-align:right;\">
            <strong style=\"font-size:11px;color:#1e293b;\">" . h($c['name']) . "</strong>
            " . ($c['mobile_number'] ? '<br><span style="font-size:9px;color:#475569;direction:ltr;">' . h($c['mobile_number']) . '</span>' : '') . "
            " . (!empty($c['alternative_number']) ? '<br><span style="font-size:8px;color:#64748b;direction:ltr;">بديل: ' . h($c['alternative_number']) . '</span>' : '') . "
        </td>
        <td style=\"text-align:center;color:#1e293b;\">" . ($location ? h($location) : '—') . "</td>
        <td style=\"text-align:center;font-weight:700;\">" . $fin['delivered_orders'] . "</td>
        <td style=\"text-align:center;\">&nbsp;</td>
        <td style=\"text-align:center;\">" . $cell_rem($fin['delivered_remaining']) . "</td>
        <td style=\"text-align:center;font-weight:700;\">" . $fin['ready_orders'] . "</td>
        <td style=\"text-align:center;\">" . $cell_paid_ready($fin['ready_paid']) . "</td>
        <td style=\"text-align:center;\">" . $cell_rem($fin['ready_remaining']) . "</td>
        <td style=\"text-align:center;font-weight:700;\">" . $fin['purchased_orders'] . "</td>
        <td style=\"text-align:center;\">&nbsp;</td>
        <td style=\"text-align:center;\">" . $cell_rem($fin['purchased_remaining']) . "</td>
        <td style=\"text-align:center;font-weight:700;\">" . $fin['new_orders'] . "</td>
        <td style=\"text-align:center;\">&nbsp;</td>
        <td style=\"text-align:center;\">" . $cell_rem($fin['new_remaining']) . "</td>
        <td style=\"text-align:right;font-size:9px;color:#1e293b;\">" . ($notes ? h($notes) : '') . "</td>
    </tr>\n";
}

// ── Summary cards ─────────────────────────────────────────────────────────────
$summary_html = '
<table width="100%" cellpadding="10" cellspacing="0" style="margin-bottom:14px;border-collapse:separate;border-spacing:6px;">
<tr>
    <td style="background:#dbeafe;border-radius:6px;text-align:center;border:1px solid #bfdbfe;width:25%;">
        <div style="font-size:9px;color:#1d4ed8;font-weight:700;">عدد العملاء</div>
        <div style="font-size:18px;font-weight:900;color:#1e3a8a;">' . $count . '</div>
    </td>
    <td style="background:#fef3c7;border-radius:6px;text-align:center;border:1px solid #fde68a;width:25%;">
        <div style="font-size:9px;color:#92400e;font-weight:700;">إجمالي الطلبات</div>
        <div style="font-size:18px;font-weight:900;color:#78350f;">' . n($ttl_orders) . '</div>
    </td>

</tr>
</table>';

// ── Totals row ────────────────────────────────────────────────────────────────
$totals_html = '
<tr style="background:#1e293b;color:#ffffff;font-weight:bold;">
    <td colspan="3" style="text-align:right;">الإجمالي (' . $count . ' عميل)</td>
    <td style="text-align:center;">' . n($ttl_orders) . '</td>
    <td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td><td></td>
</tr>';

// ── Full HTML ─────────────────────────────────────────────────────────────────
$html = '<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head><meta charset="UTF-8">
<style>
* { box-sizing:border-box; }
body {
    font-family:"XB Zar","DejaVu Sans",sans-serif;
    font-size:11px;
    color:#1e293b;
    direction:rtl;
}
.pdf-header {
    text-align:center;
    border-bottom:3px solid #1e293b;
    padding-bottom:8px;
    margin-bottom:12px;
}
.pdf-header h1 { font-size:17px;font-weight:900;color:#1e293b;margin:0 0 2px 0; }
.pdf-header .sub { font-size:9px;color:#64748b; }
.pdf-header .filters { font-size:9px;color:#475569;background:#f1f5f9;padding:3px 8px;border-radius:4px;display:inline-block;margin-top:4px; }
table.main-tbl { width:100%;border-collapse:collapse;font-size:10px; }
table.main-tbl thead th { padding:6px 5px;font-size:9px;font-weight:700;white-space:nowrap;border:1px solid #475569; }
table.main-tbl tbody td { padding:5px 5px;border:1px solid #e2e8f0;vertical-align:middle; }
table.main-tbl tfoot td { padding:6px 5px;border:1px solid #e2e8f0;font-weight:700; }
</style>
</head>
<body>

<div class="pdf-header">
    <h1>ملخص العملاء</h1>
    <div class="sub">تاريخ التقرير: ' . h($generated_at) . '</div>
    <div class="filters">الفلتر: ' . h($filter_text) . '</div>
</div>

' . $summary_html . '

<table class="main-tbl">
<thead>
    <tr>
        <th rowspan="2" style="width:26px;background:#1e293b;color:#ffffff;">م</th>
        <th rowspan="2" style="min-width:100px;text-align:right;background:#1e293b;color:#ffffff;">الاسم</th>
        <th rowspan="2" style="width:70px;background:#1e293b;color:#ffffff;">الموقع - العنوان</th>
        <th colspan="3" style="background:#1a3a5c;color:#ffffff;text-align:center;">تم الاستلام</th>
        <th colspan="3" style="background:#14532d;color:#ffffff;text-align:center;">جاهز للتوصيل</th>
        <th colspan="3" style="background:#4c1d95;color:#ffffff;text-align:center;">تم الشراء</th>
        <th colspan="3" style="background:#7c2d12;color:#ffffff;text-align:center;">جديد</th>
        <th rowspan="2" style="min-width:60px;background:#1e293b;color:#ffffff;">ملاحظات</th>
    </tr>
    <tr>
        <th style="width:46px;background:#1a3a5c;color:#ffffff;">عدد الطلبات</th>
        <th style="width:50px;background:#1a3a5c;color:#86efac;">مدفوع</th>
        <th style="width:50px;background:#1a3a5c;color:#fca5a5;font-weight:800;">متبقي</th>
        <th style="width:46px;background:#14532d;color:#ffffff;">عدد الطلبات</th>
        <th style="width:50px;background:#14532d;color:#86efac;">مدفوع</th>
        <th style="width:50px;background:#14532d;color:#fca5a5;font-weight:800;">متبقي</th>
        <th style="width:46px;background:#4c1d95;color:#ffffff;">عدد الطلبات</th>
        <th style="width:50px;background:#4c1d95;color:#86efac;">مدفوع</th>
        <th style="width:50px;background:#4c1d95;color:#fca5a5;font-weight:800;">متبقي</th>
        <th style="width:46px;background:#7c2d12;color:#ffffff;">عدد الطلبات</th>
        <th style="width:50px;background:#7c2d12;color:#86efac;">مدفوع</th>
        <th style="width:50px;background:#7c2d12;color:#fca5a5;font-weight:800;">متبقي</th>
    </tr>
</thead>
<tbody>
    ' . ($rows_html ?: '<tr><td colspan="16" style="text-align:center;padding:20px;color:#94a3b8;">لا توجد بيانات</td></tr>') . '
</tbody>
' . ($count ? '<tfoot>' . $totals_html . '</tfoot>' : '') . '
</table>

</body></html>';

// ── mPDF ──────────────────────────────────────────────────────────────────────
use Mpdf\Mpdf;

$mpdf_temp = __DIR__ . '/../../temp/mpdf';
if (!is_dir($mpdf_temp)) mkdir($mpdf_temp, 0755, true);

$mpdf = new Mpdf([
    'mode'             => 'utf-8',
    'format'           => 'A4',
    'orientation'      => 'L',
    'margin_top'       => 10,
    'margin_bottom'    => 14,
    'margin_left'      => 8,
    'margin_right'     => 8,
    'autoScriptToLang' => true,
    'autoLangToFont'   => true,
    'tempDir'          => $mpdf_temp,
]);

$mpdf->SetDirectionality('rtl');

$mpdf->SetHTMLFooter('
<table width="100%" style="font-size:8px;color:#94a3b8;border-top:1px solid #e2e8f0;">
<tr>
    <td style="text-align:right;">ملخص العملاء</td>
    <td style="text-align:center;">' . h($generated_at) . '</td>
    <td style="text-align:left;">صفحة {PAGENO} من {nbpg}</td>
</tr>
</table>');

$mpdf->WriteHTML($html);
$mpdf->Output('customer_summary_' . date('Y-m-d_H-i-s') . '.pdf', \Mpdf\Output\Destination::DOWNLOAD);
exit;
