<?php
/**
 * financial_review_pdf.php
 * Server-side PDF export for the Financial Review page.
 * Uses mPDF with full Arabic/RTL support, A4 Landscape format,
 * summary KPI cards, filtered table of transactions, and page numbering.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('غير مصرح');
}

require_once __DIR__ . '/../../config/database.php';

// Check mPDF autoloader
$autoload = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    exit('مكتبة mPDF غير مثبتة. يرجى تثبيت الحزم عبر Composer.');
}
require_once $autoload;

// --- Timezone & helper ---
date_default_timezone_set('Asia/Aden');

function formatToYemenTimePdf($dateString, $format = 'Y-m-d h:i A') {
    if (empty($dateString)) return '-';
    try {
        $date = new DateTime($dateString, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone('Asia/Aden'));
        return $date->format($format);
    } catch (Exception $e) {
        return $dateString;
    }
}

function isModifiedHistoryRecordPdf($transaction) {
    $history_types = ['order_status_history', 'order_state_history'];
    if (!in_array($transaction['transaction_type'] ?? '', $history_types, true)) {
        return false;
    }
    $status = trim((string)($transaction['status'] ?? ''));
    $reference = trim((string)($transaction['reference_number'] ?? ''));
    $modified_values = ['معدل', 'modified', 'modifed'];
    // Since mid-2026-06, orders/edit.php logs the NEW status key with an edit note
    // instead of status='modified' — classify those rows as modified too.
    $notes = trim((string)($transaction['notes'] ?? ''));
    $is_edit_note = ($transaction['transaction_type'] ?? '') === 'order_status_history'
        && (mb_strpos($notes, 'تم إجراء التعديلات التالية') === 0 || mb_strpos($notes, 'تم حفظ الطلب بدون تغييرات') === 0);
    return $is_edit_note || in_array($status, $modified_values, true) || in_array($reference, $modified_values, true);
}

// Payment method translations
$payment_methods_ar = [
    'cash'              => 'نقداً',
    'transfer'          => 'تحويل بنكي',
    'bank_transfer'     => 'تحويل بنكي',
    'kuraimi'           => 'الكريمي',
    'al_kuraimi'        => 'الكريمي',
    'cacc_bank'         => 'كاك بنك',
    'yemen_kuwait_bank' => 'بنك اليمن والكويت',
    'wallet'            => 'محفظة إلكترونية',
    'check'             => 'شيك',
    'other'             => 'أخرى',
    'غير محدد'          => 'غير محدد'
];

$status_map = [
    'new'              => 'جديد',
    'pending'          => 'انتظار',
    'approved'         => 'معتمد',
    'completed'        => 'مكتمل',
    'cancelled'        => 'ملغي',
    'paid'             => 'مدفوع',
    'active'           => 'نشط',
    'processing'       => 'قيد المعالجة',
    'modified'         => 'معدل',
    'deleted'          => 'محذوف',
    'delivered'        => 'تم التسليم',
    'purchased'        => 'تم الشراء',
    'ready_to_deliver' => 'جاهز للتوصيل',
];

// Capture filters
$filter               = $_GET['filter'] ?? 'all';
$search_query         = trim($_GET['q'] ?? '');
$status_filter        = $_GET['status'] ?? '';
$review_status_filter = $_GET['review_status'] ?? 'all';
$amount_filter        = $_GET['amount'] ?? '';
$date_from            = $_GET['date_from'] ?? '';
$date_to              = $_GET['date_to'] ?? '';
$bank_account_filter  = $_GET['bank_account'] ?? '';

try {
    $orders_query = "SELECT DISTINCT o.id, CAST('order' AS CHAR(50)) COLLATE utf8mb4_unicode_ci as transaction_type, CAST(o.order_number AS CHAR(255)) COLLATE utf8mb4_unicode_ci as transaction_number, CAST(o.invoice_number AS CHAR(255)) COLLATE utf8mb4_unicode_ci as reference_number, o.customer_id, CAST(o.final_amount AS DECIMAL(10,2)) as amount, CAST(o.status AS CHAR(50)) COLLATE utf8mb4_unicode_ci as status, CAST(COALESCE(o.payment_method, 'غير محدد') AS CHAR(100)) COLLATE utf8mb4_unicode_ci as payment_method, CAST(COALESCE(o.review_status, 'pending') AS CHAR(50)) COLLATE utf8mb4_unicode_ci as review_status, o.reviewed_at, o.reviewed_by, CAST(COALESCE(o.review_note, '') AS CHAR(1000)) COLLATE utf8mb4_unicode_ci as review_note, o.created_at, o.updated_at, CAST(COALESCE(c.name, '') AS CHAR(255)) COLLATE utf8mb4_unicode_ci as customer_name, CAST(COALESCE(c.mobile_number, '') AS CHAR(50)) COLLATE utf8mb4_unicode_ci as mobile_number, CAST(COALESCE(u.username, '') AS CHAR(100)) COLLATE utf8mb4_unicode_ci as reviewed_by_name, CAST(NULL AS CHAR(255)) as bank_name, CAST(NULL AS CHAR(2000)) as notes FROM customer_orders o LEFT JOIN customers c ON o.customer_id = c.id LEFT JOIN users u ON o.reviewed_by = u.id";

    $baskets_query = "SELECT DISTINCT pb.id, CAST('basket' AS CHAR(50)) COLLATE utf8mb4_unicode_ci as transaction_type, CAST(pb.basket_code AS CHAR(255)) COLLATE utf8mb4_unicode_ci as transaction_number, CAST(pb.currency AS CHAR(255)) COLLATE utf8mb4_unicode_ci as reference_number, NULL as customer_id, CAST(COALESCE(pb.grand_total_yer, pb.final_amount) AS DECIMAL(10,2)) as amount, CAST(pb.status AS CHAR(50)) COLLATE utf8mb4_unicode_ci as status, CAST(CONCAT(COALESCE(pb.sar_amount,''), '|', COALESCE(pb.currency,'YER')) AS CHAR(100)) COLLATE utf8mb4_unicode_ci as payment_method, CAST(COALESCE(pb.review_status, 'pending') AS CHAR(50)) COLLATE utf8mb4_unicode_ci as review_status, pb.reviewed_at, pb.reviewed_by, CAST(COALESCE(pb.review_note, '') AS CHAR(1000)) COLLATE utf8mb4_unicode_ci as review_note, pb.created_at, pb.updated_at, CAST('سلة شراء' AS CHAR(255)) COLLATE utf8mb4_unicode_ci as customer_name, CAST('' AS CHAR(50)) COLLATE utf8mb4_unicode_ci as mobile_number, CAST(COALESCE(u.username, '') AS CHAR(100)) COLLATE utf8mb4_unicode_ci as reviewed_by_name, CAST(NULL AS CHAR(255)) as bank_name, CAST(NULL AS CHAR(2000)) as notes FROM purchase_baskets pb LEFT JOIN users u ON pb.reviewed_by = u.id";

    $expenses_query = "SELECT DISTINCT e.id, CAST('expense' AS CHAR(50)) COLLATE utf8mb4_unicode_ci as transaction_type, CAST(e.expense_number AS CHAR(255)) COLLATE utf8mb4_unicode_ci as transaction_number, CAST(e.reference_number AS CHAR(255)) COLLATE utf8mb4_unicode_ci as reference_number, NULL as customer_id, CAST(e.amount AS DECIMAL(10,2)) as amount, CAST(e.status AS CHAR(50)) COLLATE utf8mb4_unicode_ci as status, CAST(COALESCE(e.payment_method, 'غير محدد') AS CHAR(100)) COLLATE utf8mb4_unicode_ci as payment_method, CAST(COALESCE(e.review_status, 'pending') AS CHAR(50)) COLLATE utf8mb4_unicode_ci as review_status, e.reviewed_at, e.reviewed_by, CAST(COALESCE(e.review_note, '') AS CHAR(1000)) COLLATE utf8mb4_unicode_ci as review_note, e.created_at, e.updated_at, CAST(COALESCE(e.vendor_name, 'مصروف') AS CHAR(255)) COLLATE utf8mb4_unicode_ci as customer_name, CAST(COALESCE(e.vendor_phone, '') AS CHAR(50)) COLLATE utf8mb4_unicode_ci as mobile_number, CAST(COALESCE(u.username, '') AS CHAR(100)) COLLATE utf8mb4_unicode_ci as reviewed_by_name, CAST(COALESCE(ba.bank_name, e.bank_name, '') AS CHAR(255)) as bank_name, CAST(NULL AS CHAR(2000)) as notes FROM expenses e LEFT JOIN users u ON e.reviewed_by = u.id LEFT JOIN bank_accounts ba ON e.bank_account_id = ba.id";

    $payments_query = "SELECT DISTINCT cp.id, CAST('payment' AS CHAR(50)) COLLATE utf8mb4_unicode_ci as transaction_type, CAST(cp.payment_number AS CHAR(255)) COLLATE utf8mb4_unicode_ci as transaction_number, CAST(cp.reference_number AS CHAR(255)) COLLATE utf8mb4_unicode_ci as reference_number, cp.customer_id, CAST(cp.amount AS DECIMAL(10,2)) as amount, CAST('paid' AS CHAR(50)) COLLATE utf8mb4_unicode_ci as status, CAST(COALESCE(cp.payment_method, 'غير محدد') AS CHAR(100)) COLLATE utf8mb4_unicode_ci as payment_method, CAST(COALESCE(cp.review_status, 'pending') AS CHAR(50)) COLLATE utf8mb4_unicode_ci as review_status, cp.reviewed_at, cp.reviewed_by, CAST(COALESCE(cp.review_note, '') AS CHAR(1000)) COLLATE utf8mb4_unicode_ci as review_note, cp.created_at, cp.updated_at, CAST(COALESCE(c.name, 'دفعة عميل') AS CHAR(255)) COLLATE utf8mb4_unicode_ci as customer_name, CAST(COALESCE(c.mobile_number, '') AS CHAR(50)) COLLATE utf8mb4_unicode_ci as mobile_number, CAST(COALESCE(u.username, '') AS CHAR(100)) COLLATE utf8mb4_unicode_ci as reviewed_by_name, CAST(COALESCE(ba.bank_name, '') AS CHAR(255)) COLLATE utf8mb4_unicode_ci as bank_name, CAST(NULL AS CHAR(2000)) as notes FROM customer_payments cp LEFT JOIN customers c ON cp.customer_id = c.id LEFT JOIN users u ON cp.reviewed_by = u.id LEFT JOIN bank_accounts ba ON cp.bank_account_id = ba.id";

    $order_status_history_query = "
        SELECT 
            osh.id, 
            CAST('order_status_history' AS CHAR(50)) COLLATE utf8mb4_unicode_ci as transaction_type, 
            CAST(CONCAT('تغيير حالة طلب #', co.order_number) AS CHAR(255)) COLLATE utf8mb4_unicode_ci as transaction_number, 
            CAST(osh.status AS CHAR(255)) COLLATE utf8mb4_unicode_ci as reference_number,
            osh.order_id as customer_id, 
            CAST(0 AS DECIMAL(10,2)) as amount, 
            CAST(osh.status AS CHAR(50)) COLLATE utf8mb4_unicode_ci as status,
            CAST('سجل حالات' AS CHAR(100)) COLLATE utf8mb4_unicode_ci as payment_method, 
            CAST(COALESCE(osh.review_status, 'pending') AS CHAR(50)) COLLATE utf8mb4_unicode_ci as review_status, 
            osh.reviewed_at, 
            osh.reviewed_by, 
            CAST(COALESCE(osh.review_note, '') AS CHAR(1000)) COLLATE utf8mb4_unicode_ci as review_note, 
            osh.created_at, 
            osh.created_at as updated_at, 
            CAST(COALESCE(c.name, 'غير محدد') AS CHAR(255)) COLLATE utf8mb4_unicode_ci as customer_name, 
            CAST(COALESCE(c.mobile_number, '') AS CHAR(50)) COLLATE utf8mb4_unicode_ci as mobile_number, 
            CAST(COALESCE(u.username, '') AS CHAR(100)) COLLATE utf8mb4_unicode_ci as reviewed_by_name,
            CAST(NULL AS CHAR(255)) as bank_name,
            CAST(COALESCE(osh.notes, '') AS CHAR(2000)) COLLATE utf8mb4_unicode_ci as notes
        FROM order_status_history osh
        LEFT JOIN customer_orders co ON osh.order_id = co.id
        LEFT JOIN customers c ON co.customer_id = c.id
        LEFT JOIN users u ON osh.reviewed_by = u.id
    ";

    $order_state_history_query = "
        SELECT 
            osh.id, 
            CAST('order_state_history' AS CHAR(50)) COLLATE utf8mb4_unicode_ci as transaction_type, 
            CAST(CONCAT('تغيير حالة طلب (جديد) #', co.order_number) AS CHAR(255)) COLLATE utf8mb4_unicode_ci as transaction_number, 
            CAST(osh.status AS CHAR(255)) COLLATE utf8mb4_unicode_ci as reference_number,
            osh.order_id as customer_id, 
            CAST(0 AS DECIMAL(10,2)) as amount, 
            CAST(osh.status AS CHAR(50)) COLLATE utf8mb4_unicode_ci as status,
            CAST('سجل حالات (جديد)' AS CHAR(100)) COLLATE utf8mb4_unicode_ci as payment_method, 
            CAST(COALESCE(osh.review_status, 'pending') AS CHAR(50)) COLLATE utf8mb4_unicode_ci as review_status, 
            osh.reviewed_at, 
            osh.reviewed_by, 
            CAST(COALESCE(u.username, '') AS CHAR(100)) COLLATE utf8mb4_unicode_ci as review_note, 
            osh.created_at, 
            osh.created_at as updated_at, 
            CAST(COALESCE(c.name, 'غير محدد') AS CHAR(255)) COLLATE utf8mb4_unicode_ci as customer_name, 
            CAST(COALESCE(c.mobile_number, '') AS CHAR(50)) COLLATE utf8mb4_unicode_ci as mobile_number, 
            CAST(COALESCE(u.username, '') AS CHAR(100)) COLLATE utf8mb4_unicode_ci as reviewed_by_name,
            CAST(NULL AS CHAR(255)) as bank_name,
            CAST(COALESCE(osh.notes, '') AS CHAR(2000)) COLLATE utf8mb4_unicode_ci as notes
        FROM order_state_history osh
        LEFT JOIN customer_orders co ON osh.order_id = co.id
        LEFT JOIN customers c ON co.customer_id = c.id
        LEFT JOIN users u ON osh.reviewed_by = u.id 
    ";

    $combined_query = "($orders_query) UNION ALL ($baskets_query) UNION ALL ($expenses_query) UNION ALL ($payments_query) UNION ALL ($order_status_history_query) UNION ALL ($order_state_history_query)";

    $where_clauses = [];
    $params = [];

    if ($bank_account_filter === 'cash') {
        $where_clauses[] = "(bank_name = '' OR bank_name IS NULL)";
    } elseif (!empty($bank_account_filter)) {
        $where_clauses[] = "bank_name = :bank_account_filter";
        $params[':bank_account_filter'] = $bank_account_filter;
    }

    if (!empty($where_clauses)) {
        $combined_query = "SELECT * FROM ($combined_query) AS all_transactions WHERE " . implode(' AND ', $where_clauses);
    }
    $combined_query .= " ORDER BY created_at DESC";

    $stmt = $db->prepare($combined_query);
    $stmt->execute($params);
    $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Apply in-memory filters
    $transactions = array_filter($transactions, function ($t) use ($search_query, $status_filter, $date_from, $date_to, $amount_filter) {
        if ($status_filter !== '' && ($t['status'] ?? '') !== $status_filter) return false;
        if ($amount_filter !== '') {
            if (floatval($t['amount']) != floatval($amount_filter)) return false;
        }
        if (!empty($date_from) || !empty($date_to)) {
            $created_date = formatToYemenTimePdf($t['created_at'] ?? '', 'Y-m-d');
            if ($created_date === '-') { $created_date = substr($t['created_at'] ?? '', 0, 10); }
            if (!empty($date_from) && $created_date < $date_from) return false;
            if (!empty($date_to) && $created_date > $date_to) return false;
        }
        if ($search_query !== '') {
            $haystack = strtolower(($t['transaction_number'] ?? '') . ' ' . ($t['reference_number'] ?? '') . ' ' . ($t['customer_name'] ?? '') . ' ' . ($t['mobile_number'] ?? '') . ' ' . ($t['status'] ?? ''));
            if (strpos($haystack, strtolower($search_query)) === false) return false;
        }
        return true;
    });

    $filtered_transactions = array_values($transactions);
    $modified_history_transactions = array_values(array_filter($filtered_transactions, 'isModifiedHistoryRecordPdf'));
    $main_transactions = array_values(array_filter($filtered_transactions, fn($t) => !isModifiedHistoryRecordPdf($t)));

    // Counts
    $orders_count   = count(array_filter($main_transactions, fn($t) => $t['transaction_type'] === 'order'));
    $baskets_count  = count(array_filter($main_transactions, fn($t) => $t['transaction_type'] === 'basket'));
    $expenses_count = count(array_filter($main_transactions, fn($t) => $t['transaction_type'] === 'expense'));
    $payments_count = count(array_filter($main_transactions, fn($t) => $t['transaction_type'] === 'payment'));

    // Financial KPI Totals
    $orders_amount   = array_sum(array_map(fn($t) => floatval($t['amount']), array_filter($main_transactions, fn($t) => $t['transaction_type'] === 'order')));
    $baskets_amount  = array_sum(array_map(fn($t) => floatval($t['amount']), array_filter($main_transactions, fn($t) => $t['transaction_type'] === 'basket')));
    $expenses_amount = array_sum(array_map(fn($t) => floatval($t['amount']), array_filter($main_transactions, fn($t) => $t['transaction_type'] === 'expense')));
    $payments_amount = array_sum(array_map(fn($t) => floatval($t['amount']), array_filter($main_transactions, fn($t) => $t['transaction_type'] === 'payment')));

    // Filter by tab
    if ($filter === 'modified_history') {
        $transactions = $modified_history_transactions;
    } elseif ($filter === 'order_history') {
        $transactions = array_values(array_filter($main_transactions, fn($t) => $t['transaction_type'] === 'order_status_history' || $t['transaction_type'] === 'order_state_history'));
    } elseif ($filter !== 'all') {
        $transactions = array_values(array_filter($main_transactions, fn($t) => $t['transaction_type'] === $filter));
    } else {
        $transactions = $main_transactions;
    }

    // Apply review status filter
    if ($review_status_filter !== 'all' && $review_status_filter !== '') {
        $transactions = array_values(array_filter($transactions, fn($t) => ($t['review_status'] ?? 'pending') === $review_status_filter));
    }

    $displayed_total_amount = array_sum(array_map(fn($t) => floatval($t['amount']), $transactions));

} catch (PDOException $e) {
    http_response_code(500);
    exit('Database Error: ' . $e->getMessage());
}

// Build Active Filters string for PDF Header
$active_filters = [];
$filter_names = [
    'all' => 'جميع العمليات',
    'order' => 'طلبات العملاء فقط',
    'basket' => 'سلال الشراء فقط',
    'expense' => 'المصروفات فقط',
    'payment' => 'الدفعات فقط',
    'order_history' => 'سجل الحالات',
    'modified_history' => 'Modified History'
];
$active_filters[] = 'القسم: ' . ($filter_names[$filter] ?? $filter);
if ($review_status_filter === 'pending') $active_filters[] = 'المراجعة: قيد المراجعة';
elseif ($review_status_filter === 'reviewed') $active_filters[] = 'المراجعة: تمت المراجعة';
if ($date_from) $active_filters[] = 'من: ' . $date_from;
if ($date_to) $active_filters[] = 'إلى: ' . $date_to;
if ($search_query) $active_filters[] = 'بحث: ' . $search_query;
if ($bank_account_filter) $active_filters[] = 'الحساب: ' . ($bank_account_filter === 'cash' ? 'نقداً' : $bank_account_filter);
$filter_text = implode(' | ', $active_filters);

$generated_at = date('Y-m-d H:i:s');

// Helper
function h_pdf($str) { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
function n_pdf($v) { return number_format((float)$v, 2); }

// Build Table Rows
$rows_html = '';
foreach ($transactions as $i => $t) {
    $bg = ($i % 2 === 0) ? '#ffffff' : '#f8fafc';

    $type_text = '';
    switch ($t['transaction_type']) {
        case 'order': $type_text = 'طلب'; break;
        case 'basket': $type_text = 'سلة'; break;
        case 'expense': $type_text = 'مصروف'; break;
        case 'payment': $type_text = 'دفعة'; break;
        case 'order_status_history': $type_text = 'سجل (قديم)'; break;
        case 'order_state_history': $type_text = 'سجل (جديد)'; break;
        default: $type_text = $t['transaction_type']; break;
    }

    $status_text = $status_map[$t['status']] ?? $t['status'];
    $rev_status_text = ($t['review_status'] === 'reviewed') ? 'تمت المراجعة' : 'قيد المراجعة';
    $rev_color = ($t['review_status'] === 'reviewed') ? '#059669' : '#d97706';

    $method_key = strtolower($t['payment_method'] ?? '');
    $method_ar = $payment_methods_ar[$method_key] ?? ($t['payment_method'] ?: 'غير محدد');

    $cust = h_pdf($t['customer_name'] ?? '-');
    $bank = h_pdf($t['bank_name'] ?: '-');
    $date = formatToYemenTimePdf($t['created_at'], 'Y/m/d H:i');
    $amount_str = ($t['amount'] > 0) ? n_pdf($t['amount']) : '-';

    $rows_html .= "
    <tr style=\"background: {$bg};\">
        <td style=\"text-align:center;color:#64748b;font-size:9px;\">" . ($i + 1) . "</td>
        <td style=\"text-align:center;font-weight:bold;\">" . h_pdf($type_text) . "</td>
        <td style=\"font-weight:bold;\">" . h_pdf($t['transaction_number']) . "</td>
        <td>{$cust}</td>
        <td style=\"text-align:center;\">{$bank}</td>
        <td style=\"text-align:center;font-size:9px;\">{$date}</td>
        <td style=\"text-align:center;\">" . h_pdf($status_text) . "</td>
        <td style=\"text-align:center;color:{$rev_color};font-weight:bold;\">{$rev_status_text}</td>
        <td style=\"font-size:9px;\">" . h_pdf($method_ar) . "</td>
        <td style=\"text-align:center;font-weight:bold;color:#047857;\">{$amount_str}</td>
    </tr>\n";
}

// Build HTML content
$html = '
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<style>
    * { box-sizing: border-box; }
    body {
        font-family: "XB Zar", "DejaVu Sans", sans-serif;
        font-size: 10px;
        color: #1e293b;
        direction: rtl;
    }
    .pdf-header {
        text-align: center;
        border-bottom: 3px solid #059669;
        padding-bottom: 8px;
        margin-bottom: 12px;
    }
    .pdf-header h1 {
        font-size: 18px;
        font-weight: 900;
        color: #065f46;
        margin: 0 0 4px 0;
    }
    .pdf-header .sub {
        font-size: 10px;
        color: #64748b;
    }
    .pdf-header .filters {
        font-size: 9px;
        color: #065f46;
        background: #ecfdf5;
        border: 1px solid #a7f3d0;
        padding: 4px 10px;
        border-radius: 4px;
        margin-top: 6px;
        display: inline-block;
    }
    table.data-tbl {
        width: 100%;
        border-collapse: collapse;
        font-size: 9.5px;
    }
    table.data-tbl thead tr {
        background: #065f46;
        color: #ffffff;
    }
    table.data-tbl thead th {
        padding: 6px 5px;
        font-size: 9.5px;
        font-weight: bold;
        white-space: nowrap;
    }
    table.data-tbl tbody td {
        padding: 5px;
        border-bottom: 1px solid #e2e8f0;
        vertical-align: middle;
    }
    table.data-tbl tfoot tr {
        background: #065f46;
        color: #ffffff;
        font-weight: bold;
    }
    table.data-tbl tfoot td {
        padding: 6px 5px;
    }
</style>
</head>
<body>

<div class="pdf-header">
    <h1>المراجعة المالية الشاملة</h1>
    <div class="sub">Yaman Accounting Calculator &nbsp;|&nbsp; تاريخ الاستخراج: ' . h_pdf($generated_at) . '</div>
    <div class="filters">' . h_pdf($filter_text) . '</div>
</div>

<!-- KPI Cards -->
<table width="100%" cellpadding="6" cellspacing="0" style="margin-bottom:12px;border-collapse:collapse;">
<tr>
    <td width="23%" style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:6px;text-align:center;">
        <div style="font-size:9px;color:#1e40af;font-weight:bold;">إجمالي الطلبات (' . $orders_count . ')</div>
        <div style="font-size:14px;font-weight:bold;color:#1e3a8a;">' . n_pdf($orders_amount) . ' <span style="font-size:8px;">ر.ي</span></div>
    </td>
    <td width="2%"></td>
    <td width="23%" style="background:#ecfdf5;border:1px solid #a7f3d0;border-radius:6px;text-align:center;">
        <div style="font-size:9px;color:#065f46;font-weight:bold;">إجمالي المقبوضات (' . $payments_count . ')</div>
        <div style="font-size:14px;font-weight:bold;color:#064e3b;">' . n_pdf($payments_amount) . ' <span style="font-size:8px;">ر.ي</span></div>
    </td>
    <td width="2%"></td>
    <td width="23%" style="background:#fef2f2;border:1px solid #fecaca;border-radius:6px;text-align:center;">
        <div style="font-size:9px;color:#991b1b;font-weight:bold;">إجمالي المصروفات (' . $expenses_count . ')</div>
        <div style="font-size:14px;font-weight:bold;color:#7f1d1d;">' . n_pdf($expenses_amount) . ' <span style="font-size:8px;">ر.ي</span></div>
    </td>
    <td width="2%"></td>
    <td width="25%" style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:6px;text-align:center;">
        <div style="font-size:9px;color:#5b21b6;font-weight:bold;">إجمالي المعروض (' . count($transactions) . ')</div>
        <div style="font-size:14px;font-weight:bold;color:#4c1d95;">' . n_pdf($displayed_total_amount) . ' <span style="font-size:8px;">ر.ي</span></div>
    </td>
</tr>
</table>

<!-- Data Table -->
<table class="data-tbl">
    <thead>
        <tr>
            <th style="width:25px;">#</th>
            <th style="width:45px;">النوع</th>
            <th style="width:75px;">رقم العملية</th>
            <th style="text-align:right;">العميل/المورد</th>
            <th style="width:70px;">الحساب البنكي</th>
            <th style="width:85px;">تاريخ الإنشاء</th>
            <th style="width:55px;">الحالة</th>
            <th style="width:65px;">المراجعة</th>
            <th style="width:75px;">طريقة الدفع</th>
            <th style="width:75px;">المبلغ (ر.ي)</th>
        </tr>
    </thead>
    <tbody>
        ' . ($rows_html ?: '<tr><td colspan="10" style="text-align:center;padding:20px;color:#94a3b8;">لا توجد بيانات مطابقة</td></tr>') . '
    </tbody>
    <tfoot>
        <tr>
            <td colspan="9" style="text-align:right;">الإجمالي للعمليات المعروضة (' . count($transactions) . ' عملية)</td>
            <td style="text-align:center;color:#6ee7b7;">' . n_pdf($displayed_total_amount) . '</td>
        </tr>
    </tfoot>
</table>

</body>
</html>
';

use Mpdf\Mpdf;

$mpdf_temp = __DIR__ . '/../../temp/mpdf';
if (!is_dir($mpdf_temp)) {
    mkdir($mpdf_temp, 0755, true);
}

$mpdf = new Mpdf([
    'mode'             => 'utf-8',
    'format'           => 'A4',
    'orientation'      => 'L', // Landscape for wide transaction tables
    'margin_top'       => 10,
    'margin_bottom'    => 14,
    'margin_left'      => 10,
    'margin_right'     => 10,
    'autoScriptToLang' => true,
    'autoLangToFont'   => true,
    'tempDir'          => $mpdf_temp,
]);

$mpdf->SetDirectionality('rtl');

$mpdf->SetHTMLFooter('
<table width="100%" style="font-size:8px;color:#94a3b8;border-top:1px solid #e2e8f0;">
    <tr>
        <td style="text-align:right;">Yaman Accounting Calculator — المراجعة المالية الشاملة</td>
        <td style="text-align:center;">' . h_pdf($generated_at) . '</td>
        <td style="text-align:left;">صفحة {PAGENO} من {nbpg}</td>
    </tr>
</table>
');

$mpdf->WriteHTML($html);

$filename = 'financial_review_' . date('Y-m-d_H-i-s') . '.pdf';
$mpdf->Output($filename, \Mpdf\Output\Destination::DOWNLOAD);
exit;
