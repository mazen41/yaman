<?php
/**
 * customer_summary_pdf.php
 * Server-side PDF generation for the Customer Summary report.
 * Uses mPDF (mpdf/mpdf) for proper Arabic/RTL support.
 *
 * Called via: customer_summary_pdf.php?[same GET params as customer_summary.php]
 * Outputs:    application/pdf download (customer_summary.pdf)
 *
 * IMPORTANT: Filter parameters are synchronized with modules/customers/index.php
 * to ensure the same customer population is selected.
 */

session_start();
require_once '../../config/database.php';
require_once '../../includes/check_permissions.php';

// ── Auth ──────────────────────────────────────────────────────────────────────
$user_id = $_SESSION['user_id'] ?? 0;
if (!hasPermission($user_id, 'reports', 'view')) {
    http_response_code(403);
    exit('غير مصرح');
}

// ── mPDF autoloader ───────────────────────────────────────────────────────────
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    exit('مكتبة mPDF غير مثبتة. يرجى تنفيذ: composer require mpdf/mpdf في مجلد المشروع.');
}
require_once $autoload;

// ── Fetch data for filter label resolution ────────────────────────────────────
try {
    $customer_types_map = $db->query("SELECT id, name FROM customer_types WHERE is_active = 1")->fetchAll(PDO::FETCH_KEY_PAIR);
    $cities_map = $db->query("SELECT id, name FROM cities WHERE is_active = 1")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $customer_types_map = [];
    $cities_map = [];
}

// ── Filters (SAME parameter names & logic as customer_summary.php / customers/index.php) ──
$search               = $_GET['search']               ?? '';
$filter_type          = $_GET['filter_type']          ?? '';
$filter_city          = $_GET['filter_city']          ?? '';
$filter_date_from     = $_GET['filter_date_from']     ?? '';
$filter_date_to       = $_GET['filter_date_to']       ?? '';
$filter_status        = $_GET['filter_status']        ?? 'active';
$filter_remaining_from = $_GET['filter_remaining_from'] ?? '';

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

// ── Build WHERE conditions (SAME as customer_summary.php) ─────────────────────
$where_clauses = ["1=1"];
$params = [];
$having_clauses = [];
$having_params = [];

if ($filter_status == 'active') {
    $where_clauses[] = "c.is_active = 1";
} elseif ($filter_status == 'inactive') {
    $where_clauses[] = "c.is_active = 0";
}

if ($search) {
    $where_clauses[] = "(c.name LIKE ? OR c.customer_code LIKE ? OR c.mobile_number LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}
if ($filter_type) {
    $where_clauses[] = "c.customer_type_id = ?";
    $params[] = $filter_type;
}
if ($filter_city) {
    $where_clauses[] = "c.city_id = ?";
    $params[] = $filter_city;
}
if ($filter_date_from) {
    $where_clauses[] = "DATE(c.created_at) >= ?";
    $params[] = $filter_date_from;
}
if ($filter_date_to) {
    $where_clauses[] = "DATE(c.created_at) <= ?";
    $params[] = $filter_date_to;
}
if ($filter_remaining_from !== '' && is_numeric($filter_remaining_from)) {
    $having_clauses[] = "COALESCE(SUM(co.final_amount - co.paid_amount), 0) >= ?";
    $having_params[] = $filter_remaining_from;
}

$where_sql = implode(" AND ", $where_clauses);
$having_sql = empty($having_clauses) ? '' : 'HAVING ' . implode(" AND ", $having_clauses);
$all_query_params = array_merge($params, $having_params);

// ── Query (SAME structure as customer_summary.php) ────────────────────────────
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

// ── Totals ────────────────────────────────────────────────────────────────────
$ttl_orders    = array_sum(array_column($customers, 'total_orders'));
$ttl_paid      = array_sum(array_column($customers, 'total_paid'));
$ttl_remaining = array_sum(array_column($customers, 'total_remaining'));
$ttl_amount    = array_sum(array_column($customers, 'total_amount'));
$count         = count($customers);

// ── Build active-filter description ──────────────────────────────────────────
$filter_parts = [];
if ($search)             $filter_parts[] = 'بحث: ' . $search;
if ($filter_type)        $filter_parts[] = 'نوع العميل: ' . ($customer_types_map[$filter_type] ?? $filter_type);
if ($filter_city)        $filter_parts[] = 'المحافظة: ' . ($cities_map[$filter_city] ?? $filter_city);
if ($filter_date_from)   $filter_parts[] = 'من تاريخ: ' . $filter_date_from;
if ($filter_date_to)     $filter_parts[] = 'إلى تاريخ: ' . $filter_date_to;
if ($filter_status == 'active')   $filter_parts[] = 'الحالة: نشط';
if ($filter_status == 'inactive') $filter_parts[] = 'الحالة: معطل';
if ($filter_status == 'all')      $filter_parts[] = 'الحالة: الكل';
if ($filter_remaining_from !== '' && is_numeric($filter_remaining_from))
    $filter_parts[] = 'المتبقي يبدأ من: ' . number_format($filter_remaining_from);
$filter_text = $filter_parts ? implode(' | ', $filter_parts) : 'جميع العملاء (نشط)';

// ── Helper: safe HTML-encode ──────────────────────────────────────────────────
function h($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
function n($v, $d = 0) { return number_format((float)$v, $d); }

// ── Build the HTML for the PDF ────────────────────────────────────────────────
$generated_at = date('Y-m-d H:i:s');

// ── Table rows ────────────────────────────────────────────────────────────────
$rows_html = '';
foreach ($customers as $i => $c) {
    $rem_style = $c['total_remaining'] > 0
        ? 'color:#b91c1c;font-weight:bold;'
        : 'color:#15803d;';

    $rem_val = $c['total_remaining'] > 0
        ? n($c['total_remaining'])
        : '✓';

    $city_val  = h($c['city_name']            ?: '—');
    $type_val  = h($c['customer_type_name']   ?: '—');
    $name_val  = h($c['name']);
    $mobile    = h($c['mobile_number']        ?: '');
    $code      = $c['customer_code'] ? ' · ' . h($c['customer_code']) : '';

    $bg = ($i % 2 === 0) ? '#ffffff' : '#f8fafc';

    $rows_html .= "
    <tr style=\"background:{$bg};\">
        <td style=\"text-align:center;color:#94a3b8;font-size:10px;\">" . ($i + 1) . "</td>
        <td>
            <strong style=\"font-size:12px;\">{$name_val}</strong>
            <br><span style=\"font-size:9px;color:#64748b;\">{$mobile}{$code}</span>
        </td>
        <td style=\"text-align:center;\">{$city_val}</td>
        <td style=\"text-align:center;\">{$type_val}</td>
        <td style=\"text-align:center;font-weight:bold;\">" . n($c['total_orders']) . "</td>
        <td style=\"text-align:center;color:#15803d;\">" . n($c['delivered_count']) . "</td>
        <td style=\"text-align:center;color:#92400e;\">" . n($c['ready_count']) . "</td>
        <td style=\"text-align:center;color:#5b21b6;\">" . n($c['other_count']) . "</td>
        <td style=\"text-align:center;color:#059669;font-weight:600;\">" . n($c['total_paid']) . "</td>
        <td style=\"text-align:center;{$rem_style}\">{$rem_val}</td>
    </tr>\n";
}

// ── Totals row ────────────────────────────────────────────────────────────────
$totals_html = "
    <tr style=\"background:#1e293b;color:#ffffff;font-weight:bold;\">
        <td colspan=\"4\" style=\"text-align:right;\">الإجمالي ({$count} عميل)</td>
        <td style=\"text-align:center;\">" . n($ttl_orders) . "</td>
        <td></td><td></td><td></td>
        <td style=\"text-align:center;color:#6ee7b7;\">" . n($ttl_paid) . "</td>
        <td style=\"text-align:center;color:#fca5a5;\">" . n($ttl_remaining) . "</td>
    </tr>";

// ── Summary cards row ─────────────────────────────────────────────────────────
$summary_html = "
<table width=\"100%\" cellpadding=\"8\" cellspacing=\"0\" style=\"margin-bottom:14px;border-collapse:collapse;\">
<tr>
    <td width=\"25%\" style=\"background:#dbeafe;border-radius:6px;text-align:center;border:1px solid #bfdbfe;\">
        <div style=\"font-size:9px;color:#1d4ed8;font-weight:700;\">عدد العملاء</div>
        <div style=\"font-size:18px;font-weight:900;color:#1e3a8a;\">{$count}</div>
    </td>
    <td width=\"5%\"></td>
    <td width=\"25%\" style=\"background:#fef3c7;border-radius:6px;text-align:center;border:1px solid #fde68a;\">
        <div style=\"font-size:9px;color:#92400e;font-weight:700;\">إجمالي الطلبات</div>
        <div style=\"font-size:18px;font-weight:900;color:#78350f;\">" . n($ttl_orders) . "</div>
    </td>
    <td width=\"5%\"></td>
    <td width=\"25%\" style=\"background:#d1fae5;border-radius:6px;text-align:center;border:1px solid #a7f3d0;\">
        <div style=\"font-size:9px;color:#065f46;font-weight:700;\">إجمالي المدفوع</div>
        <div style=\"font-size:18px;font-weight:900;color:#064e3b;\">" . n($ttl_paid) . "</div>
    </td>
    <td width=\"5%\"></td>
    <td width=\"25%\" style=\"background:#fee2e2;border-radius:6px;text-align:center;border:1px solid #fecaca;\">
        <div style=\"font-size:9px;color:#991b1b;font-weight:700;\">إجمالي المتبقي</div>
        <div style=\"font-size:18px;font-weight:900;color:#7f1d1d;\">" . n($ttl_remaining) . "</div>
    </td>
</tr>
</table>";

// ── Complete HTML document ────────────────────────────────────────────────────
$html = '
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; }
    body {
        font-family: "XB Zar", "DejaVu Sans", sans-serif;
        font-size: 11px;
        color: #1e293b;
        direction: rtl;
    }

    /* ── Page header (repeated on every page via mPDF @page) ── */
    .pdf-header {
        text-align: center;
        border-bottom: 3px solid #1e293b;
        padding-bottom: 8px;
        margin-bottom: 12px;
    }
    .pdf-header h1 {
        font-size: 18px;
        font-weight: 900;
        color: #1e293b;
        margin: 0 0 2px 0;
    }
    .pdf-header .sub {
        font-size: 10px;
        color: #64748b;
    }
    .pdf-header .filters {
        font-size: 9px;
        color: #475569;
        margin-top: 4px;
        background: #f1f5f9;
        padding: 3px 8px;
        border-radius: 4px;
        display: inline-block;
    }

    /* ── Main table ── */
    table.main-tbl {
        width: 100%;
        border-collapse: collapse;
        font-size: 10px;
    }
    table.main-tbl thead tr {
        background: #1e293b;
        color: #ffffff;
    }
    table.main-tbl thead th {
        padding: 7px 6px;
        font-size: 10px;
        font-weight: 700;
        white-space: nowrap;
    }
    table.main-tbl tbody td {
        padding: 6px 6px;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: middle;
    }
    table.main-tbl tfoot td {
        padding: 7px 6px;
        font-weight: 700;
    }

    /* ── Page footer ── */
    .pdf-footer {
        text-align: center;
        font-size: 9px;
        color: #94a3b8;
        border-top: 1px solid #e2e8f0;
        padding-top: 4px;
    }
</style>
</head>
<body>

<!-- Page Header -->
<div class="pdf-header">
    <h1>ملخص العملاء</h1>
    <div class="sub">Yaman Accounting Calculator &nbsp;|&nbsp; تاريخ التقرير: ' . h($generated_at) . '</div>
    <div class="filters">الفلتر: ' . h($filter_text) . '</div>
</div>

<!-- Summary Cards -->
' . $summary_html . '

<!-- Data Table -->
<table class="main-tbl">
    <thead>
        <tr>
            <th style="width:28px;">#</th>
            <th style="text-align:right;min-width:90px;">الاسم</th>
            <th style="text-align:center;width:60px;">المحافظة</th>
            <th style="text-align:center;width:55px;">الفئة</th>
            <th style="text-align:center;width:40px;">الطلبات</th>
            <th style="text-align:center;width:40px;">تسليم</th>
            <th style="text-align:center;width:40px;">جاهز</th>
            <th style="text-align:center;width:40px;">أخرى</th>
            <th style="text-align:center;width:65px;">مدفوع</th>
            <th style="text-align:center;width:65px;color:#fca5a5;">متبقي</th>
        </tr>
    </thead>
    <tbody>
        ' . ($rows_html ?: '<tr><td colspan="10" style="text-align:center;padding:20px;color:#94a3b8;">لا توجد بيانات</td></tr>') . '
    </tbody>
    ' . ($count ? '<tfoot>' . $totals_html . '</tfoot>' : '') . '
</table>

</body>
</html>
';

// ── Generate PDF with mPDF ────────────────────────────────────────────────────
use Mpdf\Mpdf;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;

// Temp dir for mPDF (inside the project so it's writable on XAMPP)
$mpdf_temp = __DIR__ . '/../../temp/mpdf';
if (!is_dir($mpdf_temp)) {
    mkdir($mpdf_temp, 0755, true);
}

$mpdf = new Mpdf([
    'mode'              => 'utf-8',
    'format'            => 'A4',
    'orientation'       => 'L',          // Landscape — 10 columns fit comfortably
    'margin_top'        => 10,
    'margin_bottom'     => 14,
    'margin_left'       => 10,
    'margin_right'      => 10,
    'autoScriptToLang'  => true,
    'autoLangToFont'    => true,
    'tempDir'           => $mpdf_temp,
]);

// ── RTL direction ─────────────────────────────────────────────────────────────
$mpdf->SetDirectionality('rtl');

// ── Page numbering in footer ──────────────────────────────────────────────────
$mpdf->SetHTMLFooter('
<table width="100%" style="font-size:8px;color:#94a3b8;border-top:1px solid #e2e8f0;">
    <tr>
        <td style="text-align:right;">Yaman Accounting Calculator — ملخص العملاء</td>
        <td style="text-align:center;">' . h($generated_at) . '</td>
        <td style="text-align:left;">صفحة {PAGENO} من {nbpg}</td>
    </tr>
</table>
');

// ── Write HTML & output ───────────────────────────────────────────────────────
$mpdf->WriteHTML($html);

$filename = 'customer_summary_' . date('Y-m-d_H-i-s') . '.pdf';
$mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
exit;
