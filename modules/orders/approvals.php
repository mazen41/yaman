<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/database.php';
require_once '../../includes/check_permissions.php';

// --- 1. PERMISSIONS ---
$user_id = (int)($_SESSION['user_id'] ?? 0);
if (!canAccessOrderApprovalsPage($user_id)) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية للوصول لهذه الصفحة.';
    header('Location: ../../index.php');
    exit();
}

$can_approve = canApproveOrderApprovals($user_id);
$can_reject  = canRejectOrderApprovals($user_id);

// --- 2. INITIALIZATION & MESSAGES ---
$page_title = 'إدارة ومراجعة طلبات الاعتماد';

$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);
$success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

// --- 3. HANDLE PERMANENT DELETION (Rejected orders only) ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && (($_POST['action'] ?? '') === 'delete_rejected')) {
    $delete_id = (int)($_POST['approval_id'] ?? 0);
    try {
        $db->beginTransaction();
        $status_stmt = $db->prepare("SELECT status FROM order_approvals WHERE id = ? FOR UPDATE");
        $status_stmt->execute([$delete_id]);
        $delete_status = $status_stmt->fetchColumn();

        if ($delete_status !== 'rejected') {
            throw new Exception('يمكن حذف الطلبات المرفوضة فقط.');
        }

        $db->prepare("DELETE FROM order_approval_items WHERE approval_id = ?")->execute([$delete_id]);
        $db->prepare("DELETE FROM order_approvals_images WHERE approval_id = ?")->execute([$delete_id]);
        $db->prepare("DELETE FROM notifications WHERE related_id = ? AND related_table = 'order_approvals'")->execute([$delete_id]);
        $db->prepare("DELETE FROM order_approvals WHERE id = ? AND status = 'rejected'")->execute([$delete_id]);
        $db->commit();
        $_SESSION['success_message'] = 'تم حذف الطلب المرفوض نهائياً بنجاح.';
    } catch (Exception $e) {
        if ($db->inTransaction()) $db->rollBack();
        $_SESSION['error_message'] = 'فشل حذف الطلب: ' . $e->getMessage();
    }
    header('Location: approvals.php?' . http_build_query(array_diff_key($_GET, ['page' => true])));
    exit();
}

// --- 4. FILTERS & SORTING ---
$date_from          = trim($_GET['date_from'] ?? '');
$date_to            = trim($_GET['date_to'] ?? '');
$search             = trim($_GET['search'] ?? '');
$status_filter      = trim($_GET['status'] ?? 'pending');
$self_order_filter  = trim($_GET['self_order'] ?? '');
$sort_by            = trim($_GET['sort_by'] ?? 'created_at_desc');

$allowed_statuses = ['pending', 'pending_rejected', 'all', 'approved', 'rejected'];
if (!in_array($status_filter, $allowed_statuses, true)) {
    $status_filter = 'pending';
}

$allowed_sorts = ['created_at_desc', 'created_at_asc', 'amount_desc', 'amount_asc', 'id_desc', 'id_asc'];
if (!in_array($sort_by, $allowed_sorts, true)) {
    $sort_by = 'created_at_desc';
}

$has_active_filters = !empty($search) || !empty($date_from) || !empty($date_to) || ($status_filter !== 'pending') || !empty($self_order_filter) || ($sort_by !== 'created_at_desc');

// Fetch active bank accounts for quick modal approval
try {
    $bank_stmt = $db->query("SELECT id, bank_name, account_number, account_holder_name, currency FROM bank_accounts WHERE is_active = 1 ORDER BY bank_name");
    $bank_accounts = $bank_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $bank_accounts = [];
}

// --- 5. COMPUTE GLOBAL KPIS ---
$kpis = [
    'total_count'    => 0,
    'pending_count'  => 0,
    'approved_count' => 0,
    'rejected_count' => 0,
    'pending_amount' => 0.0,
    'today_count'    => 0,
];
try {
    $kpi_stmt = $db->query("SELECT 
        COUNT(oa.id) AS total_count,
        SUM(CASE WHEN oa.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
        SUM(CASE WHEN oa.status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
        SUM(CASE WHEN oa.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
        SUM(CASE WHEN oa.status = 'pending' THEN (oa.subtotal_amount - COALESCE(oa.automatic_discount_amount, 0) - COALESCE(oa.coupon_discount_amount, 0) + COALESCE(oa.shipping_cost, 0)) ELSE 0 END) AS pending_amount,
        SUM(CASE WHEN DATE(oa.created_at) = CURDATE() THEN 1 ELSE 0 END) AS today_count
    FROM order_approvals oa");
    $kpi_row = $kpi_stmt->fetch(PDO::FETCH_ASSOC);
    if ($kpi_row) {
        $kpis['total_count']    = (int)($kpi_row['total_count'] ?? 0);
        $kpis['pending_count']  = (int)($kpi_row['pending_count'] ?? 0);
        $kpis['approved_count'] = (int)($kpi_row['approved_count'] ?? 0);
        $kpis['rejected_count'] = (int)($kpi_row['rejected_count'] ?? 0);
        $kpis['pending_amount'] = (float)($kpi_row['pending_amount'] ?? 0);
        $kpis['today_count']    = (int)($kpi_row['today_count'] ?? 0);
    }
} catch (PDOException $e) {
    // Graceful fallback
}

// --- 6. FETCH PAGINATED DATA ---
try {
    $from_joins = "FROM order_approvals oa
                   LEFT JOIN customers c ON oa.customer_id = c.id
                   LEFT JOIN customer_types ct ON c.customer_type_id = ct.id";

    $where_clause = " WHERE 1=1";
    $params = [];

    if ($status_filter === 'pending_rejected') {
        $where_clause .= " AND oa.status IN ('pending', 'rejected')";
    } elseif ($status_filter === 'pending') {
        $where_clause .= " AND oa.status = 'pending'";
    } elseif ($status_filter !== 'all') {
        $where_clause .= " AND oa.status = ?";
        $params[] = $status_filter;
    }

    if ($search !== '') {
        $search_param = "%$search%";
        $where_clause .= " AND (
            oa.id LIKE ? 
            OR c.name LIKE ? 
            OR oa.customer_name LIKE ? 
            OR c.mobile_number LIKE ? 
            OR c.whatsapp_number LIKE ?
            OR c.customer_code LIKE ?
            OR oa.coupon_code LIKE ?
        )";
        array_push($params, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param, $search_param);
    }

    if ($date_from !== '') {
        $where_clause .= " AND DATE(oa.created_at) >= ?";
        $params[] = $date_from;
    }
    if ($date_to !== '') {
        $where_clause .= " AND DATE(oa.created_at) <= ?";
        $params[] = $date_to;
    }
    if ($self_order_filter === 'yes') {
        $where_clause .= " AND oa.customer_id IS NOT NULL";
    } elseif ($self_order_filter === 'no') {
        $where_clause .= " AND oa.customer_id IS NULL";
    }

    // Total filtered records
    $count_query = "SELECT COUNT(oa.id) " . $from_joins . $where_clause;
    $count_stmt = $db->prepare($count_query);
    $count_stmt->execute($params);
    $total_records = (int)$count_stmt->fetchColumn();

    // Pagination setup
    $records_per_page = 15;
    $total_pages = max(1, (int)ceil($total_records / $records_per_page));
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $page = max(1, min($page, $total_pages));
    $offset = ($page - 1) * $records_per_page;

    // Sort order mapping
    $order_sql = match($sort_by) {
        'created_at_asc'  => 'oa.created_at ASC, oa.id ASC',
        'amount_desc'     => '(oa.subtotal_amount - COALESCE(oa.automatic_discount_amount, 0) - COALESCE(oa.coupon_discount_amount, 0) + COALESCE(oa.shipping_cost, 0)) DESC',
        'amount_asc'      => '(oa.subtotal_amount - COALESCE(oa.automatic_discount_amount, 0) - COALESCE(oa.coupon_discount_amount, 0) + COALESCE(oa.shipping_cost, 0)) ASC',
        'id_desc'         => 'oa.id DESC',
        'id_asc'          => 'oa.id ASC',
        default           => 'oa.created_at DESC, oa.id DESC',
    };

    $query = "SELECT
        oa.id,
        oa.currency, oa.created_at, oa.customer_id, oa.status, oa.final_order_id,
        oa.notes, oa.admin_notes, oa.rejection_reason, oa.payment_proof_path,
        oa.subtotal_amount, oa.automatic_discount_amount, oa.shipping_cost,
        oa.automatic_discount_percentage, oa.paid_amount,
        COALESCE(c.name, oa.customer_name) AS customer_name,
        c.customer_code,
        c.mobile_number, c.whatsapp_number, c.email, c.address AS customer_address, c.city_name,
        ct.name AS customer_type_name_from_table,
        (oa.subtotal_amount - COALESCE(oa.automatic_discount_amount, 0) - COALESCE(oa.coupon_discount_amount, 0) + COALESCE(oa.shipping_cost, 0)) AS final_amount,
        oa.expected_delivery_date, oa.coupon_code, oa.coupon_discount_amount,
        (SELECT COUNT(*) FROM order_approval_items oai WHERE oai.approval_id = oa.id) AS items_count,
        (SELECT COALESCE(SUM(oai.item_count), 0) FROM order_approval_items oai WHERE oai.approval_id = oa.id) AS total_units,
        (SELECT TRIM(oai.product_link) FROM order_approval_items oai WHERE oai.approval_id = oa.id AND TRIM(COALESCE(oai.product_link,'')) <> '' ORDER BY oai.id ASC LIMIT 1) AS first_product_link,
        (SELECT TRIM(oai.additional_link) FROM order_approval_items oai WHERE oai.approval_id = oa.id AND TRIM(COALESCE(oai.additional_link,'')) <> '' ORDER BY oai.id ASC LIMIT 1) AS first_additional_link
    " . $from_joins . $where_clause . " ORDER BY " . $order_sql . " LIMIT " . (int)$records_per_page . " OFFSET " . (int)$offset;

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error_message = 'حدث خطأ أثناء استرجاع الطلبات: ' . $e->getMessage();
    $orders = [];
    $total_records = 0;
    $total_pages = 1;
    $page = 1;
}

// Consistent price formatter (19000 -> "19,000", 19000.5 -> "19,000.5", 19000.25 -> "19,000.25")
if (!function_exists('formatAppPrice')) {
    function formatAppPrice($amount, $currency = 'YER'): string {
        $val = (float)($amount ?? 0);
        $formatted = number_format($val, 2, '.', ',');
        $trimmed = rtrim(rtrim($formatted, '0'), '.');
        return $trimmed;
    }
}

// Compute visible page totals
$page_totals = ['final_amount' => 0.0, 'paid_amount' => 0.0, 'total_units' => 0];
foreach ($orders as $o) {
    $page_totals['final_amount'] += (float)($o['final_amount'] ?? 0);
    $page_totals['paid_amount']  += (float)($o['paid_amount'] ?? 0);
    $page_totals['total_units']  += (int)($o['total_units'] ?? 0);
}

include '../../includes/header.php';
?>

<!-- ==========================================
     CSS STYLES FOR MODERN APPROVALS DASHBOARD
     ========================================== -->
<style>
@import url('https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600&display=swap');

:root {
    --brand-gold: #C7A46D;
    --brand-gold-dark: #9e7f4e;
    --brand-gold-light: #fbf7ee;
    --primary-blue: #2563eb;
    --primary-blue-dark: #1d4ed8;
    --success-green: #10b981;
    --success-green-dark: #059669;
    --danger-red: #ef4444;
    --danger-red-dark: #dc2626;
    --warning-amber: #f59e0b;
    --card-bg: #ffffff;
    --surface-bg: #f8fafc;
    --border-color: #e2e8f0;
    --text-primary: #0f172a;
    --text-secondary: #64748b;
    --text-muted: #94a3b8;
}

* { box-sizing: border-box; }

.approvals-dashboard {
    font-family: 'Cairo', sans-serif;
    min-height: 100vh;
    background: #f1f5f9;
    padding: 1.5rem 1rem 3.5rem;
    direction: rtl;
    color: var(--text-primary);
}

/* Page Header Hero */
.app-hero {
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 50%, #1e3a5f 100%);
    border-radius: 20px;
    padding: 1.75rem 2rem;
    margin-bottom: 1.5rem;
    color: #ffffff;
    box-shadow: 0 10px 30px rgba(15, 23, 42, 0.25);
    position: relative;
    overflow: hidden;
}
.app-hero::before {
    content: '';
    position: absolute;
    inset: 0;
    background: radial-gradient(ellipse 70% 80% at 85% 40%, rgba(199, 164, 109, 0.15) 0%, transparent 70%);
    pointer-events: none;
}
.hero-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 1.25rem;
    flex-wrap: wrap;
    position: relative;
    z-index: 1;
}
.hero-title-area h1 {
    font-size: 1.65rem;
    font-weight: 800;
    margin: 0 0 0.25rem;
    color: #ffffff;
    display: flex;
    align-items: center;
    gap: 0.65rem;
}
.hero-title-area p {
    font-size: 0.875rem;
    color: #94a3b8;
    margin: 0;
}
.hero-actions {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    flex-wrap: wrap;
}

/* Glass Buttons */
.btn-glass {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.6rem 1.15rem;
    border-radius: 12px;
    font-size: 0.85rem;
    font-weight: 700;
    text-decoration: none;
    border: 1px solid rgba(255, 255, 255, 0.2);
    background: rgba(255, 255, 255, 0.1);
    color: #ffffff;
    backdrop-filter: blur(8px);
    transition: all 0.2s ease;
    cursor: pointer;
}
.btn-glass:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-1px);
    color: #ffffff;
}

/* KPI Summary Cards */
.kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
    gap: 1rem;
    margin-bottom: 1.5rem;
}
.kpi-card {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 1.15rem 1.25rem;
    border: 1px solid var(--border-color);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
    display: flex;
    align-items: center;
    justify-content: space-between;
    transition: transform 0.2s, box-shadow 0.2s;
    text-decoration: none;
    color: inherit;
    position: relative;
    overflow: hidden;
}
.kpi-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.06);
}
.kpi-card::after {
    content: '';
    position: absolute;
    bottom: 0;
    right: 0;
    left: 0;
    height: 3px;
    background: transparent;
}
.kpi-card.pending::after { background: var(--warning-amber); }
.kpi-card.approved::after { background: var(--success-green); }
.kpi-card.rejected::after { background: var(--danger-red); }
.kpi-card.amount::after { background: var(--brand-gold); }
.kpi-card.today::after { background: var(--primary-blue); }

.kpi-info h3 {
    font-size: 0.775rem;
    font-weight: 700;
    color: var(--text-secondary);
    margin: 0 0 0.35rem;
}
.kpi-info .kpi-val {
    font-size: 1.5rem;
    font-weight: 800;
    color: var(--text-primary);
    line-height: 1.2;
    font-family: 'JetBrains Mono', 'Cairo', monospace;
}
.kpi-info .kpi-sub {
    font-size: 0.725rem;
    color: var(--text-muted);
    margin-top: 0.2rem;
}
.kpi-icon {
    width: 48px;
    height: 48px;
    border-radius: 14px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}
.kpi-card.pending .kpi-icon { background: #fffbeb; color: #d97706; }
.kpi-card.approved .kpi-icon { background: #ecfdf5; color: #059669; }
.kpi-card.rejected .kpi-icon { background: #fef2f2; color: #dc2626; }
.kpi-card.amount .kpi-icon { background: #fcf8ee; color: #9e7f4e; }
.kpi-card.today .kpi-icon { background: #eff6ff; color: #2563eb; }

/* Filter Container */
.filter-card {
    background: var(--card-bg);
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    margin-bottom: 1.5rem;
    border: 1px solid var(--border-color);
    box-shadow: 0 1px 6px rgba(0, 0, 0, 0.04);
}
.filter-grid {
    display: grid;
    grid-template-columns: 2fr 1.2fr 1fr 1fr 1fr 1fr auto;
    gap: 0.85rem;
    align-items: flex-end;
}
@media (max-width: 1200px) {
    .filter-grid {
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    }
}
.filter-group label {
    display: block;
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-secondary);
    margin-bottom: 0.35rem;
}
.filter-control {
    width: 100%;
    height: 2.6rem;
    padding: 0 0.85rem;
    border-radius: 10px;
    border: 1.5px solid var(--border-color);
    background: var(--surface-bg);
    font-family: 'Cairo', sans-serif;
    font-size: 0.85rem;
    color: var(--text-primary);
    outline: none;
    transition: border-color 0.2s, box-shadow 0.2s;
}
.filter-control:focus {
    border-color: var(--primary-blue);
    background: #ffffff;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
}
.filter-buttons {
    display: flex;
    gap: 0.5rem;
}
.btn-filter-apply {
    height: 2.6rem;
    padding: 0 1.25rem;
    border-radius: 10px;
    border: none;
    background: #0f172a;
    color: #ffffff;
    font-family: 'Cairo', sans-serif;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    transition: all 0.2s;
}
.btn-filter-apply:hover {
    background: #1e293b;
    transform: translateY(-1px);
}
.btn-filter-reset {
    height: 2.6rem;
    padding: 0 0.9rem;
    border-radius: 10px;
    border: 1.5px solid var(--border-color);
    background: #ffffff;
    color: var(--text-secondary);
    font-family: 'Cairo', sans-serif;
    font-size: 0.85rem;
    font-weight: 700;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.2s;
}
.btn-filter-reset:hover {
    background: #f1f5f9;
    color: var(--danger-red);
    border-color: #fca5a5;
}

/* Status Filter Pill Bar */
.status-pill-bar {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1rem;
    padding-top: 0.85rem;
    border-top: 1px dashed var(--border-color);
    flex-wrap: wrap;
}
.status-pill-bar span.pill-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-muted);
    margin-left: 0.35rem;
}
.status-pill {
    padding: 0.35rem 0.85rem;
    border-radius: 50px;
    font-size: 0.775rem;
    font-weight: 700;
    text-decoration: none;
    color: var(--text-secondary);
    background: #f1f5f9;
    border: 1px solid transparent;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
}
.status-pill:hover {
    background: #e2e8f0;
    color: var(--text-primary);
}
.status-pill.active {
    background: #0f172a;
    color: #ffffff;
}
.status-pill .pill-count {
    background: rgba(0, 0, 0, 0.08);
    padding: 1px 6px;
    border-radius: 10px;
    font-size: 0.7rem;
    font-family: 'JetBrains Mono', monospace;
}
.status-pill.active .pill-count {
    background: rgba(255, 255, 255, 0.25);
    color: #ffffff;
}

/* Main Table Container */
.orders-table-wrapper {
    background: var(--card-bg);
    border-radius: 18px;
    border: 1px solid var(--border-color);
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
    overflow: hidden;
    margin-bottom: 1.5rem;
}
.table-meta-header {
    padding: 1rem 1.5rem;
    background: #ffffff;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
}
.table-meta-title {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.table-meta-title .meta-badge {
    background: #f1f5f9;
    color: var(--text-secondary);
    padding: 0.2rem 0.65rem;
    border-radius: 20px;
    font-size: 0.75rem;
    font-weight: 700;
    font-family: 'JetBrains Mono', monospace;
}

/* Modern Clean Table */
.modern-app-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    text-align: right;
}
.modern-app-table thead th {
    background: #f8fafc;
    color: var(--text-secondary);
    font-size: 0.775rem;
    font-weight: 800;
    letter-spacing: 0.3px;
    padding: 0.85rem 1rem;
    border-bottom: 1.5px solid var(--border-color);
    white-space: nowrap;
}
.modern-app-table tbody td {
    padding: 1rem;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
    font-size: 0.85rem;
    color: var(--text-primary);
    transition: background 0.15s;
}
.modern-app-table tbody tr:hover td {
    background: #f8fafc;
}
.modern-app-table tbody tr:last-child td {
    border-bottom: none;
}

/* Order Column Styling */
.order-id-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    font-family: 'JetBrains Mono', monospace;
    font-size: 0.9rem;
    font-weight: 800;
    color: #1e293b;
    text-decoration: none;
}
.order-date-time {
    display: flex;
    align-items: center;
    gap: 0.35rem;
    font-size: 0.725rem;
    color: var(--text-muted);
    margin-top: 0.25rem;
}

/* Customer Column */
.customer-cell-main {
    display: flex;
    flex-direction: column;
    gap: 0.2rem;
}
.customer-name-link {
    font-weight: 800;
    color: #1e293b;
    text-decoration: none;
    transition: color 0.2s;
    display: flex;
    align-items: center;
    gap: 0.4rem;
}
.customer-name-link:hover {
    color: var(--primary-blue);
}
.customer-meta-row {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.725rem;
    color: var(--text-secondary);
    flex-wrap: wrap;
}
.phone-copy-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.45rem;
    background: #f1f5f9;
    border: 1px solid var(--border-color);
    border-radius: 6px;
    font-size: 0.7rem;
    font-family: 'JetBrains Mono', monospace;
    color: #475569;
    cursor: pointer;
    transition: all 0.15s;
}
.phone-copy-btn:hover {
    background: #e2e8f0;
    color: var(--text-primary);
}

/* Badges */
.app-badge {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.7rem;
    border-radius: 50px;
    font-size: 0.75rem;
    font-weight: 700;
    white-space: nowrap;
}
.badge-status-pending { background: #fef3c7; color: #92400e; border: 1px solid #fde68a; }
.badge-status-approved { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; }
.badge-status-rejected { background: #fee2e2; color: #991b1b; border: 1px solid #fecaca; }

.badge-tag {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 600;
    background: #f1f5f9;
    color: #475569;
}
.badge-customer-type {
    background: #ede9fe;
    color: #6d28d9;
}

/* External Link Icons */
.link-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.65rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 700;
    text-decoration: none;
    transition: all 0.2s;
}
.link-pill.primary {
    background: #eff6ff;
    color: #1d4ed8;
    border: 1px solid #dbeafe;
}
.link-pill.primary:hover {
    background: #dbeafe;
    transform: translateY(-1px);
}
.link-pill.secondary {
    background: #fffbeb;
    color: #b45309;
    border: 1px solid #fef3c7;
}
.link-pill.secondary:hover {
    background: #fef3c7;
    transform: translateY(-1px);
}

/* Amounts */
.amount-stack {
    display: flex;
    flex-direction: column;
    gap: 0.15rem;
}
.amount-main {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--brand-gold-dark);
    font-family: 'JetBrains Mono', monospace;
}
.amount-sub {
    font-size: 0.725rem;
    color: var(--text-muted);
}
.amount-paid {
    font-size: 0.9rem;
    font-weight: 700;
    color: var(--success-green-dark);
    font-family: 'JetBrains Mono', monospace;
}
.amount-remaining {
    font-size: 0.725rem;
    color: var(--danger-red);
    font-weight: 600;
}

/* Proof Thumbnail */
.proof-thumb-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.35rem 0.7rem;
    border-radius: 8px;
    background: #eff6ff;
    border: 1px solid #bfdbfe;
    color: #1d4ed8;
    font-size: 0.75rem;
    font-weight: 700;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.15s;
}
.proof-thumb-btn:hover {
    background: #dbeafe;
}

/* Action Buttons Group */
.table-actions-group {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    justify-content: flex-end;
}
.btn-action-sm {
    height: 32px;
    padding: 0 0.75rem;
    border-radius: 8px;
    font-size: 0.75rem;
    font-weight: 700;
    font-family: 'Cairo', sans-serif;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    text-decoration: none;
    transition: all 0.15s ease;
    white-space: nowrap;
}
.btn-action-primary {
    background: linear-gradient(135deg, #059669 0%, #10b981 100%);
    color: #ffffff;
}
.btn-action-primary:hover {
    background: #047857;
    transform: translateY(-1px);
    box-shadow: 0 3px 10px rgba(16, 185, 129, 0.3);
}
.btn-action-secondary {
    background: #f1f5f9;
    color: #1e293b;
    border: 1px solid var(--border-color);
}
.btn-action-secondary:hover {
    background: #e2e8f0;
}
.btn-action-danger {
    background: #fef2f2;
    color: #dc2626;
    border: 1px solid #fecaca;
}
.btn-action-danger:hover {
    background: #fee2e2;
    color: #b91c1c;
}

/* Empty State */
.empty-state-box {
    padding: 4rem 2rem;
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
}
.empty-icon-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: #f8fafc;
    border: 2px dashed #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    color: #94a3b8;
    margin-bottom: 1rem;
}
.empty-state-box h3 {
    font-size: 1.15rem;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0 0 0.35rem;
}
.empty-state-box p {
    font-size: 0.85rem;
    color: var(--text-secondary);
    max-width: 400px;
    margin: 0 0 1.25rem;
}

/* Pagination Bar */
.pagination-container {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.15rem 1.5rem;
    background: #ffffff;
    border-top: 1px solid var(--border-color);
    flex-wrap: wrap;
    gap: 1rem;
}
.pagination-info {
    font-size: 0.8rem;
    color: var(--text-secondary);
    font-weight: 600;
}
.pagination-links {
    display: flex;
    align-items: center;
    gap: 0.3rem;
}
.page-btn {
    min-width: 34px;
    height: 34px;
    padding: 0 0.65rem;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: #ffffff;
    color: var(--text-primary);
    font-size: 0.8rem;
    font-weight: 700;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
    transition: all 0.15s;
    font-family: 'JetBrains Mono', 'Cairo', sans-serif;
}
.page-btn:hover {
    background: #f1f5f9;
    border-color: #cbd5e1;
}
.page-btn.active {
    background: #0f172a;
    color: #ffffff;
    border-color: #0f172a;
}
.page-btn.disabled {
    opacity: 0.4;
    pointer-events: none;
}

/* Slide Drawer for Quick Inspection */
.app-drawer-overlay {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.65);
    backdrop-filter: blur(4px);
    z-index: 999;
    opacity: 0;
    visibility: hidden;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}
.app-drawer-overlay.open {
    opacity: 1;
    visibility: visible;
}
.app-drawer {
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    width: 620px;
    max-width: 100vw;
    background: #ffffff;
    z-index: 1000;
    box-shadow: 20px 0 50px rgba(0, 0, 0, 0.25);
    transform: translateX(-100%);
    transition: transform 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
    direction: rtl;
}
.app-drawer-overlay.open .app-drawer {
    transform: translateX(0);
}
.drawer-header {
    padding: 1.25rem 1.5rem;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    color: #ffffff;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.drawer-header h3 {
    margin: 0;
    font-size: 1.15rem;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.drawer-close-btn {
    background: rgba(255, 255, 255, 0.15);
    border: none;
    color: #ffffff;
    width: 34px;
    height: 34px;
    border-radius: 8px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.15s;
}
.drawer-close-btn:hover {
    background: rgba(255, 255, 255, 0.3);
}
.drawer-body {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
    background: #f8fafc;
}
.drawer-footer {
    padding: 1rem 1.5rem;
    background: #ffffff;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

/* Modals */
.app-modal {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.7);
    backdrop-filter: blur(5px);
    z-index: 1050;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    opacity: 0;
    visibility: hidden;
    transition: all 0.25s ease;
}
.app-modal.open {
    opacity: 1;
    visibility: visible;
}
.modal-dialog {
    background: #ffffff;
    border-radius: 20px;
    width: 100%;
    max-width: 540px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    overflow: hidden;
    direction: rtl;
    transform: scale(0.95);
    transition: transform 0.25s ease;
}
.app-modal.open .modal-dialog {
    transform: scale(1);
}
.modal-header-custom {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.modal-header-custom h4 {
    margin: 0;
    font-size: 1.1rem;
    font-weight: 800;
}
.modal-body-custom {
    padding: 1.5rem;
}
.modal-footer-custom {
    padding: 1rem 1.5rem;
    background: #f8fafc;
    border-top: 1px solid var(--border-color);
    display: flex;
    justify-content: flex-end;
    gap: 0.75rem;
}

/* Lightbox Image Preview */
#mediaLightbox {
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.88);
    backdrop-filter: blur(8px);
    z-index: 2000;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 2rem;
    cursor: zoom-out;
}
#lightboxMediaContainer {
    max-width: 90vw;
    max-height: 88vh;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 25px 60px rgba(0,0,0,0.5);
    position: relative;
    cursor: default;
}
#lightboxMediaContainer img, #lightboxMediaContainer video {
    max-width: 100%;
    max-height: 85vh;
    display: block;
    object-fit: contain;
}

/* Copy Toast */
.copy-toast {
    position: fixed;
    bottom: 2rem;
    left: 50%;
    transform: translateX(-50%) translateY(100px);
    background: #0f172a;
    color: #ffffff;
    padding: 0.65rem 1.25rem;
    border-radius: 50px;
    font-size: 0.825rem;
    font-weight: 700;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
    z-index: 3000;
    opacity: 0;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    pointer-events: none;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}
.copy-toast.show {
    transform: translateX(-50%) translateY(0);
    opacity: 1;
}

/* Mobile responsive card list */
@media (max-width: 991px) {
    .modern-app-table, .modern-app-table thead, .modern-app-table tbody, .modern-app-table tr, .modern-app-table td {
        display: block;
    }
    .modern-app-table thead {
        display: none;
    }
    .modern-app-table tbody tr {
        background: #ffffff;
        border-radius: 16px;
        margin: 1rem;
        padding: 1.25rem;
        border: 1px solid var(--border-color);
        box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    }
    .modern-app-table tbody td {
        padding: 0.5rem 0;
        border: none;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    .modern-app-table tbody td::before {
        content: attr(data-label);
        font-weight: 700;
        color: var(--text-secondary);
        font-size: 0.775rem;
    }
    .table-actions-group {
        width: 100%;
        justify-content: flex-start;
        margin-top: 0.75rem;
        padding-top: 0.75rem;
        border-top: 1px dashed var(--border-color);
    }
}

/* ── Phones (≤767px): tighten spacing, buttons, modals & drawer ── */
@media (max-width: 767px) {
    .approvals-dashboard { padding: 0.75rem 0.5rem 2.5rem; }

    /* Hero header */
    .app-hero { padding: 1.15rem 1rem; border-radius: 14px; margin-bottom: 1rem; }
    .hero-title-area h1 { font-size: 1.2rem; gap: 0.4rem; }
    .hero-title-area p { font-size: 0.78rem; }
    .hero-actions { width: 100%; }
    .hero-actions .btn-glass { flex: 1 1 auto; justify-content: center; padding: 0.55rem 0.8rem; font-size: 0.78rem; }

    /* KPI cards: single column, tighter */
    .kpi-grid { grid-template-columns: 1fr; gap: 0.6rem; margin-bottom: 1rem; }
    .kpi-card { padding: 0.85rem 1rem; border-radius: 12px; }
    .kpi-icon { width: 38px; height: 38px; font-size: 1rem; border-radius: 10px; }
    .kpi-info .kpi-val { font-size: 1.2rem; }

    /* Filter panel: one control per row, full-width buttons */
    .filter-card { padding: 1rem 0.85rem; border-radius: 12px; margin-bottom: 1rem; }
    .filter-grid { grid-template-columns: 1fr; gap: 0.7rem; }
    .filter-buttons { width: 100%; }
    .filter-buttons > * { flex: 1; justify-content: center; }
    .status-pill-bar { gap: 0.35rem; }
    .status-pill { font-size: 0.7rem; padding: 0.3rem 0.6rem; }

    /* Orders list (stacked cards from 991px block) — tighter on phones */
    .orders-table-wrapper { border-radius: 12px; margin-bottom: 1rem; }
    .table-meta-header { padding: 0.85rem 1rem; gap: 0.5rem; }
    .modern-app-table tbody tr { margin: 0.6rem 0.5rem; padding: 1rem; border-radius: 12px; }
    .modern-app-table tbody td { flex-wrap: wrap; gap: 0.25rem; }
    .table-actions-group { flex-wrap: wrap; gap: 0.35rem; }
    .table-actions-group .btn-action-sm,
    .table-actions-group form { flex: 1 1 45%; }
    .table-actions-group .btn-action-sm { justify-content: center; }

    /* Modals: fit small screens, scroll if tall */
    .app-modal { padding: 0.5rem; }
    .modal-dialog { max-width: 100%; max-height: 92vh; overflow-y: auto; border-radius: 16px; }
    .modal-header-custom { padding: 1rem; }
    .modal-header-custom h4 { font-size: 0.95rem; }
    .modal-body-custom { padding: 1rem; }
    .modal-body-custom .grid-cols-2 { grid-template-columns: 1fr; }
    .modal-footer-custom { padding: 0.85rem 1rem; flex-wrap: wrap; }
    .modal-footer-custom > * { flex: 1; justify-content: center; }

    /* Side drawer: full width on phones */
    .app-drawer { width: 100vw; }
    .drawer-header { padding: 1rem; }
    .drawer-body { padding: 1rem; }
    .drawer-footer { padding: 0.85rem 1rem; }

    /* Lightbox & pagination */
    #mediaLightbox { padding: 0.75rem; }
    #lightboxMediaContainer { max-width: 96vw; }
    .pagination-container { padding: 0.85rem 1rem; justify-content: center; }
    .pagination-links { flex-wrap: wrap; justify-content: center; }
}
</style>

<!-- ==========================================
     MAIN DASHBOARD HTML
     ========================================== -->
<div class="approvals-dashboard" dir="rtl">

    <!-- Flash Messages -->
    <?php if ($success_message): ?>
        <div class="mb-4 p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <i class="fas fa-check-circle text-emerald-600 text-xl"></i>
                <span class="font-bold text-sm"><?php echo htmlspecialchars($success_message); ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-emerald-500 hover:text-emerald-800"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="mb-4 p-4 rounded-xl bg-red-50 border border-red-200 text-red-800 flex items-center justify-between shadow-sm">
            <div class="flex items-center gap-3">
                <i class="fas fa-exclamation-circle text-red-600 text-xl"></i>
                <span class="font-bold text-sm"><?php echo htmlspecialchars($error_message); ?></span>
            </div>
            <button onclick="this.parentElement.remove()" class="text-red-500 hover:text-red-800"><i class="fas fa-times"></i></button>
        </div>
    <?php endif; ?>

    <!-- WhatsApp Pending Notification Banner -->
    <?php if (!empty($_SESSION['whatsapp_pending'])): 
        $wa_pending = $_SESSION['whatsapp_pending'];
        unset($_SESSION['whatsapp_pending']);
        $wa_encoded_msg = urlencode($wa_pending['message']);
    ?>
    <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border-2 border-emerald-300 shadow-sm">
        <div class="flex items-center gap-2 mb-3 text-emerald-900 font-extrabold text-sm">
            <i class="fab fa-whatsapp text-emerald-500 text-xl"></i>
            <span><?php echo htmlspecialchars($wa_pending['label']); ?></span>
            <span class="text-xs text-emerald-700 font-normal mr-2">يمكنك إرسال إشعار فوري للعميل عبر الواتساب:</span>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php foreach ($wa_pending['phones'] as $phone): 
                $clean_phone = preg_replace('/[^0-9]/', '', $phone);
                if (strlen($clean_phone) > 0 && $clean_phone[0] === '0') {
                    $clean_phone = '967' . substr($clean_phone, 1);
                }
                $wa_link = 'https://wa.me/' . $clean_phone . '?text=' . $wa_encoded_msg;
            ?>
            <a href="<?php echo htmlspecialchars($wa_link); ?>" target="_blank"
               class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-sm">
                <i class="fab fa-whatsapp text-sm"></i>
                إرسال إلى <?php echo htmlspecialchars($phone); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- PAGE HERO HEADER -->
    <div class="app-hero">
        <div class="hero-content">
            <div class="hero-title-area">
                <h1>
                    <i class="fas fa-shield-alt text-amber-400"></i>
                    مركز اعتماد ومراجعة الطلبات
                </h1>
                <p>مراجعة واعتماد طلبات العملاء وتحويلها إلى فواتير وطلبات تنفيذ مؤكدة</p>
            </div>
            <div class="hero-actions">
                <a href="approvals.php" class="btn-glass" title="تحديث القائمة">
                    <i class="fas fa-sync-alt"></i>
                    تحديث
                </a>
                <a href="index.php" class="btn-glass" title="الذهاب لطلبات العملاء">
                    <i class="fas fa-list-alt"></i>
                    كل طلبات العملاء
                </a>
                <?php if ($can_approve): ?>
                <a href="shop_orders_manage.php" class="btn-glass" title="طلبات المتجر">
                    <i class="fas fa-store"></i>
                    طلبات المتجر
                </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- SUMMARY / KPI CARDS -->
    <div class="kpi-grid">
        <a href="approvals.php?status=pending" class="kpi-card pending">
            <div class="kpi-info">
                <h3>طلبات قيد المراجعة</h3>
                <div class="kpi-val"><?php echo number_format($kpis['pending_count']); ?></div>
                <div class="kpi-sub">تحتاج لاتخاذ إجراء فوري</div>
            </div>
            <div class="kpi-icon"><i class="fas fa-clock"></i></div>
        </a>

        <div class="kpi-card amount">
            <div class="kpi-info">
                <h3>قيمة الطلبات المعلقة</h3>
                <div class="kpi-val"><?php echo formatAppPrice($kpis['pending_amount']); ?></div>
                <div class="kpi-sub">ريال يمني (إجمالي مستحق)</div>
            </div>
            <div class="kpi-icon"><i class="fas fa-wallet"></i></div>
        </div>

        <a href="approvals.php?status=all&date_from=<?php echo date('Y-m-d'); ?>&date_to=<?php echo date('Y-m-d'); ?>" class="kpi-card today">
            <div class="kpi-info">
                <h3>طلبات اليوم</h3>
                <div class="kpi-val"><?php echo number_format($kpis['today_count']); ?></div>
                <div class="kpi-sub"><?php echo date('Y-m-d'); ?></div>
            </div>
            <div class="kpi-icon"><i class="fas fa-calendar-day"></i></div>
        </a>

        <a href="approvals.php?status=approved" class="kpi-card approved">
            <div class="kpi-info">
                <h3>الطلبات المعتمدة</h3>
                <div class="kpi-val"><?php echo number_format($kpis['approved_count']); ?></div>
                <div class="kpi-sub">تم تحويلها لأوامر شراء</div>
            </div>
            <div class="kpi-icon"><i class="fas fa-check-double"></i></div>
        </a>

        <a href="approvals.php?status=rejected" class="kpi-card rejected">
            <div class="kpi-info">
                <h3>الطلبات المرفوضة</h3>
                <div class="kpi-val"><?php echo number_format($kpis['rejected_count']); ?></div>
                <div class="kpi-sub">تم رفضها مع ذكر السبب</div>
            </div>
            <div class="kpi-icon"><i class="fas fa-times-circle"></i></div>
        </a>
    </div>

    <!-- FILTER & SEARCH PANEL -->
    <div class="filter-card">
        <form method="GET" action="approvals.php" id="filtersForm">
            <div class="filter-grid">
                <div class="filter-group">
                    <label><i class="fas fa-search text-gray-400 ml-1"></i> البحث السريع</label>
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                           class="filter-control" placeholder="رقم الطلب، اسم العميل، الهاتف، كود العميل...">
                </div>

                <div class="filter-group">
                    <label><i class="fas fa-sort-amount-down text-gray-400 ml-1"></i> الترتيب</label>
                    <select name="sort_by" class="filter-control">
                        <option value="created_at_desc" <?php echo $sort_by === 'created_at_desc' ? 'selected' : ''; ?>>الأحدث أولاً</option>
                        <option value="created_at_asc" <?php echo $sort_by === 'created_at_asc' ? 'selected' : ''; ?>>الأقدم أولاً</option>
                        <option value="amount_desc" <?php echo $sort_by === 'amount_desc' ? 'selected' : ''; ?>>الأعلى قيمة</option>
                        <option value="amount_asc" <?php echo $sort_by === 'amount_asc' ? 'selected' : ''; ?>>الأقل قيمة</option>
                        <option value="id_desc" <?php echo $sort_by === 'id_desc' ? 'selected' : ''; ?>>رقم الطلب (تنازلي)</option>
                        <option value="id_asc" <?php echo $sort_by === 'id_asc' ? 'selected' : ''; ?>>رقم الطلب (تصاعدي)</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label><i class="fas fa-user-tag text-gray-400 ml-1"></i> نوع الطلب</label>
                    <select name="self_order" class="filter-control">
                        <option value="" <?php echo $self_order_filter === '' ? 'selected' : ''; ?>>كل الطلبات</option>
                        <option value="yes" <?php echo $self_order_filter === 'yes' ? 'selected' : ''; ?>>طلبات العملاء الذاتية</option>
                        <option value="no" <?php echo $self_order_filter === 'no' ? 'selected' : ''; ?>>طلبات بدون عميل مسجل</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label><i class="far fa-calendar-alt text-gray-400 ml-1"></i> من تاريخ</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="filter-control">
                </div>

                <div class="filter-group">
                    <label><i class="far fa-calendar-alt text-gray-400 ml-1"></i> إلى تاريخ</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="filter-control">
                </div>

                <div class="filter-group">
                    <label><i class="fas fa-tag text-gray-400 ml-1"></i> الحالة</label>
                    <select name="status" class="filter-control" id="statusFilterSelect">
                        <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>قيد المراجعة فقط</option>
                        <option value="pending_rejected" <?php echo $status_filter === 'pending_rejected' ? 'selected' : ''; ?>>قيد المراجعة + المرفوضة</option>
                        <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>كل الحالات</option>
                        <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>المعتمدة فقط</option>
                        <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>المرفوضة فقط</option>
                    </select>
                </div>

                <div class="filter-buttons">
                    <button type="submit" class="btn-filter-apply">
                        <i class="fas fa-filter"></i>
                        تطبيق
                    </button>
                    <?php if ($has_active_filters): ?>
                    <a href="approvals.php" class="btn-filter-reset" title="إلغاء الفلاتر">
                        <i class="fas fa-redo"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Status Pill Navigation -->
            <div class="status-pill-bar">
                <span class="pill-label">تصفية سريعة بالحالة:</span>
                <?php
                $status_tabs = [
                    'pending'          => ['label' => 'قيد المراجعة', 'icon' => 'fa-clock', 'count' => $kpis['pending_count']],
                    'pending_rejected' => ['label' => 'المعلقة والمرفوضة', 'icon' => 'fa-tasks', 'count' => ($kpis['pending_count'] + $kpis['rejected_count'])],
                    'approved'         => ['label' => 'المعتمدة', 'icon' => 'fa-check-circle', 'count' => $kpis['approved_count']],
                    'rejected'         => ['label' => 'المرفوضة', 'icon' => 'fa-times-circle', 'count' => $kpis['rejected_count']],
                    'all'              => ['label' => 'الكل', 'icon' => 'fa-layer-group', 'count' => $kpis['total_count']],
                ];
                foreach ($status_tabs as $sk => $sinfo):
                    $is_act = ($status_filter === $sk);
                    $url_params = $_GET;
                    $url_params['status'] = $sk;
                    unset($url_params['page']);
                ?>
                <a href="?<?php echo http_build_query($url_params); ?>" 
                   class="status-pill <?php echo $is_act ? 'active' : ''; ?>">
                    <i class="fas <?php echo $sinfo['icon']; ?>"></i>
                    <span><?php echo $sinfo['label']; ?></span>
                    <span class="pill-count"><?php echo $sinfo['count']; ?></span>
                </a>
                <?php endforeach; ?>
            </div>
        </form>
    </div>

    <!-- MAIN ORDERS TABLE CARD -->
    <div class="orders-table-wrapper">
        <div class="table-meta-header">
            <div class="table-meta-title">
                <i class="fas fa-inbox text-blue-600"></i>
                <span>قائمة طلبات الاعتماد</span>
                <span class="meta-badge"><?php echo number_format($total_records); ?> طلب</span>
            </div>

            <?php if (!empty($orders)): ?>
            <div class="text-xs text-gray-500 font-bold flex items-center gap-4">
                <span>إجمالي مبالغ الصفحة: <strong class="text-gray-900"><?php echo formatAppPrice($page_totals['final_amount']); ?> YER</strong></span>
                <span class="hidden sm:inline">|</span>
                <span class="hidden sm:inline">المدفوع: <strong class="text-emerald-700"><?php echo formatAppPrice($page_totals['paid_amount']); ?> YER</strong></span>
            </div>
            <?php endif; ?>
        </div>

        <div style="overflow-x: auto;">
            <table class="modern-app-table">
                <thead>
                    <tr>
                        <th style="width: 120px;">رقم الطلب</th>
                        <th style="width: 130px;">الحالة</th>
                        <th>العميل</th>
                        <th style="width: 90px; text-align: center;">القطع</th>
                        <th style="width: 130px;">الروابط</th>
                        <th style="width: 140px;">الإجمالي الصافي</th>
                        <th style="width: 120px;">المدفوع</th>
                        <th style="width: 100px; text-align: center;">إثبات الدفع</th>
                        <th>ملاحظات</th>
                        <th style="width: 220px; text-align: left;">الإجراءات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="10">
                                <div class="empty-state-box">
                                    <div class="empty-icon-circle">
                                        <i class="fas fa-check-circle"></i>
                                    </div>
                                    <h3>لا توجد طلبات اعتماد مطابقة للفلاتر</h3>
                                    <p>
                                        <?php if ($status_filter === 'pending'): ?>
                                            رائع! تم اعتماد ومراجعة جميع الطلبات المعلقة ولا توجد طلبات تحتاج لاتخاذ إجراء حالياً.
                                        <?php else: ?>
                                            لم يتم العثور على أي نتائج وفقاً لمعايير البحث والتصفية المحددة.
                                        <?php endif; ?>
                                    </p>
                                    <?php if ($has_active_filters): ?>
                                        <a href="approvals.php" class="btn-filter-apply">
                                            <i class="fas fa-redo"></i> عرض الطلبات المعلقة
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($orders as $order): 
                            $curr = htmlspecialchars($order['currency'] ?: 'YER');
                            $st = $order['status'] ?? 'pending';
                            $status_map = [
                                'pending'  => ['label' => 'قيد المراجعة', 'icon' => 'fa-clock', 'class' => 'badge-status-pending'],
                                'approved' => ['label' => 'تم الاعتماد', 'icon' => 'fa-check-circle', 'class' => 'badge-status-approved'],
                                'rejected' => ['label' => 'مرفوض', 'icon' => 'fa-times-circle', 'class' => 'badge-status-rejected'],
                            ];
                            $st_info = $status_map[$st] ?? ['label' => $st, 'icon' => 'fa-info-circle', 'class' => 'badge-status-pending'];

                            $cust_name = trim($order['customer_name'] ?? '');
                            if ($cust_name === '') { $cust_name = 'عميل غير مسجل'; }

                            $mob = trim($order['mobile_number'] ?? '');
                            $final_amt = (float)($order['final_amount'] ?? 0);
                            $paid_amt = (float)($order['paid_amount'] ?? 0);
                            $rem_amt = max(0, $final_amt - $paid_amt);
                            $proof = trim($order['payment_proof_path'] ?? '');
                            $prim_link = trim($order['first_product_link'] ?? '');
                            $sec_link = trim($order['first_additional_link'] ?? '');
                        ?>
                        <tr id="row-<?php echo (int)$order['id']; ?>">
                            <!-- Order ID & Date -->
                            <td data-label="رقم الطلب">
                                <span class="order-id-badge">
                                    #<?php echo (int)$order['id']; ?>
                                </span>
                                <div class="order-date-time" title="<?php echo htmlspecialchars($order['created_at']); ?>">
                                    <i class="far fa-clock"></i>
                                    <span><?php echo date('Y-m-d H:i', strtotime($order['created_at'])); ?></span>
                                </div>
                                <?php if (!empty($order['final_order_id'])): ?>
                                    <a href="view.php?id=<?php echo (int)$order['final_order_id']; ?>" 
                                       target="_blank"
                                       class="badge-tag mt-1 hover:bg-blue-100 text-blue-700" title="عرض الطلب النهائي المؤكد">
                                        <i class="fas fa-shopping-bag"></i> أمر #<?php echo (int)$order['final_order_id']; ?>
                                    </a>
                                <?php endif; ?>
                            </td>

                            <!-- Status -->
                            <td data-label="الحالة">
                                <span class="app-badge <?php echo $st_info['class']; ?>">
                                    <i class="fas <?php echo $st_info['icon']; ?>"></i>
                                    <?php echo $st_info['label']; ?>
                                </span>
                            </td>

                            <!-- Customer Information -->
                            <td data-label="العميل">
                                <div class="customer-cell-main">
                                    <div class="flex items-center gap-2">
                                        <?php if (!empty($order['customer_id'])): ?>
                                            <a href="../customers/view.php?id=<?php echo (int)$order['customer_id']; ?>" 
                                               target="_blank" class="customer-name-link" title="فتح صفحة العميل">
                                                <i class="far fa-user text-gray-400 text-xs"></i>
                                                <?php echo htmlspecialchars($cust_name); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="font-bold text-gray-800">
                                                <?php echo htmlspecialchars($cust_name); ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!empty($order['customer_type_name_from_table'])): ?>
                                            <span class="badge-tag badge-customer-type">
                                                <?php echo htmlspecialchars($order['customer_type_name_from_table']); ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="customer-meta-row">
                                        <?php if (!empty($order['customer_code'])): ?>
                                            <span class="text-gray-400 font-mono">ID: <?php echo htmlspecialchars($order['customer_code']); ?></span>
                                        <?php endif; ?>

                                        <?php if ($mob !== ''): ?>
                                            <button type="button" class="phone-copy-btn" 
                                                    onclick="copyPhone('<?php echo htmlspecialchars($mob); ?>')" 
                                                    title="اضغط لنسخ رقم الهاتف">
                                                <i class="fas fa-phone-alt text-[10px]"></i>
                                                <span><?php echo htmlspecialchars($mob); ?></span>
                                                <i class="far fa-copy text-[10px]"></i>
                                            </button>
                                        <?php endif; ?>

                                        <?php if (!empty($order['city_name'])): ?>
                                            <span class="text-gray-400"><i class="fas fa-map-marker-alt text-[10px]"></i> <?php echo htmlspecialchars($order['city_name']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>

                            <!-- Items / Units Count -->
                            <td data-label="القطع" style="text-align: center;">
                                <div class="font-bold text-gray-800 text-sm font-mono">
                                    <?php echo (int)$order['items_count']; ?> بند
                                </div>
                                <div class="text-[11px] text-gray-500 font-mono">
                                    (<?php echo (int)$order['total_units']; ?> قطعة)
                                </div>
                            </td>

                            <!-- Links -->
                            <td data-label="الروابط">
                                <div class="flex items-center gap-1">
                                    <?php if ($prim_link !== ''): ?>
                                        <a href="<?php echo htmlspecialchars($prim_link); ?>" target="_blank" 
                                           class="link-pill primary" title="فتح رابط المنتج الأساسي">
                                            <i class="fas fa-external-link-alt text-xs"></i>
                                            <span>الرابط</span>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($sec_link !== ''): ?>
                                        <a href="<?php echo htmlspecialchars($sec_link); ?>" target="_blank" 
                                           class="link-pill secondary" title="فتح الرابط الإضافي">
                                            <i class="fas fa-link text-xs"></i>
                                            <span>إضافي</span>
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($prim_link === '' && $sec_link === ''): ?>
                                        <span class="text-gray-400 text-xs">-</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Final Total -->
                            <td data-label="الإجمالي الصافي">
                                <div class="amount-stack">
                                    <span class="amount-main"><?php echo formatAppPrice($final_amt); ?> <small class="text-xs font-normal"><?php echo $curr; ?></small></span>
                                    <?php if ((float)($order['automatic_discount_amount'] ?? 0) > 0 || (float)($order['coupon_discount_amount'] ?? 0) > 0): ?>
                                        <span class="text-[11px] text-emerald-600 font-medium">
                                            خصم: -<?php echo formatAppPrice((float)($order['automatic_discount_amount'] ?? 0) + (float)($order['coupon_discount_amount'] ?? 0)); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Paid / Remaining -->
                            <td data-label="المدفوع">
                                <div class="amount-stack">
                                    <span class="amount-paid"><?php echo formatAppPrice($paid_amt); ?></span>
                                    <?php if ($rem_amt > 0.01): ?>
                                        <span class="amount-remaining">متبقي: <?php echo formatAppPrice($rem_amt); ?></span>
                                    <?php else: ?>
                                        <span class="text-[11px] text-emerald-600 font-semibold">مكتمل الدفع</span>
                                    <?php endif; ?>
                                </div>
                            </td>

                            <!-- Payment Proof -->
                            <td data-label="إثبات الدفع" style="text-align: center;">
                                <?php if ($proof !== ''): 
                                    $proof_ext = strtolower(pathinfo($proof, PATHINFO_EXTENSION));
                                    $is_video = in_array($proof_ext, ['mp4', 'mov', 'webm', 'ogg'], true);
                                    $is_pdf = ($proof_ext === 'pdf');
                                    $proof_url = '../../' . ltrim(htmlspecialchars($proof), '/');
                                ?>
                                    <button type="button" class="proof-thumb-btn" 
                                            onclick="openMediaPreview('<?php echo $proof_url; ?>', '<?php echo $is_video ? 'video' : ($is_pdf ? 'pdf' : 'image'); ?>')" 
                                            title="معاينة إثبات الدفع">
                                        <i class="fas <?php echo $is_video ? 'fa-play-circle text-purple-600' : ($is_pdf ? 'fa-file-pdf text-red-600' : 'fa-image text-blue-600'); ?>"></i>
                                        <span>عرض</span>
                                    </button>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">لا يوجد</span>
                                <?php endif; ?>
                            </td>

                            <!-- Notes / Rejection Reason -->
                            <td data-label="ملاحظات" style="max-width: 180px;">
                                <?php if ($st === 'rejected' && !empty($order['rejection_reason'])): ?>
                                    <div class="text-xs text-red-700 bg-red-50 p-1.5 rounded border border-red-200" title="<?php echo htmlspecialchars($order['rejection_reason']); ?>">
                                        <strong class="font-bold">سبب الرفض:</strong>
                                        <?php echo mb_substr(htmlspecialchars($order['rejection_reason']), 0, 35) . (mb_strlen($order['rejection_reason']) > 35 ? '...' : ''); ?>
                                    </div>
                                <?php elseif (!empty($order['notes'])): ?>
                                    <div class="text-xs text-gray-600" title="<?php echo htmlspecialchars($order['notes']); ?>">
                                        <?php echo mb_substr(htmlspecialchars($order['notes']), 0, 40) . (mb_strlen($order['notes']) > 40 ? '...' : ''); ?>
                                    </div>
                                <?php else: ?>
                                    <span class="text-gray-400 text-xs">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- Actions Group -->
                            <td data-label="الإجراءات">
                                <div class="table-actions-group">
                                    <!-- Quick Inspect Drawer Button -->
                                    <button type="button" class="btn-action-sm btn-action-secondary" 
                                            onclick="openOrderDrawer(<?php echo (int)$order['id']; ?>)" 
                                            title="معاينة سريعة لكافة تفاصيل الطلب">
                                        <i class="fas fa-eye text-blue-600"></i>
                                        <span>معاينة</span>
                                    </button>

                                    <!-- Full Review Page Link -->
                                    <a href="view_approval.php?id=<?php echo (int)$order['id']; ?>" 
                                       class="btn-action-sm btn-action-secondary" 
                                       title="فتح صفحة المراجعة والتعديل التفصيلي">
                                        <i class="fas fa-sliders-h text-amber-600"></i>
                                        <span>تعديل</span>
                                    </a>

                                    <?php if ($st === 'pending' && $can_approve): ?>
                                    <!-- Quick Modal Approve Button -->
                                    <button type="button" class="btn-action-sm btn-action-primary" 
                                            onclick="openQuickApproveModal(<?php echo (int)$order['id']; ?>, '<?php echo htmlspecialchars($cust_name, ENT_QUOTES); ?>', <?php echo $paid_amt; ?>, <?php echo $final_amt; ?>)" 
                                            title="اعتماد سريع للطلب">
                                        <i class="fas fa-check"></i>
                                        <span>اعتماد</span>
                                    </button>
                                    <?php endif; ?>

                                    <?php if ($st === 'pending' && $can_reject): ?>
                                    <!-- Quick Modal Reject Button -->
                                    <button type="button" class="btn-action-sm btn-action-danger" 
                                            onclick="openQuickRejectModal(<?php echo (int)$order['id']; ?>, '<?php echo htmlspecialchars($cust_name, ENT_QUOTES); ?>', '<?php echo htmlspecialchars($mob, ENT_QUOTES); ?>')" 
                                            title="رفض الطلب مع ذكر السبب">
                                        <i class="fas fa-ban"></i>
                                        <span>رفض</span>
                                    </button>
                                    <?php endif; ?>

                                    <?php if ($st === 'rejected'): ?>
                                    <!-- Permanent Deletion for Rejected Orders -->
                                    <form method="POST" action="" onsubmit="return confirm('تحذير: سيتم حذف هذا الطلب المرفوض نهائياً وبشكل كامل من قاعدة البيانات. هل أنت متأكد؟');" style="display:inline;">
                                        <input type="hidden" name="action" value="delete_rejected">
                                        <input type="hidden" name="approval_id" value="<?php echo (int)$order['id']; ?>">
                                        <button type="submit" class="btn-action-sm btn-action-danger" title="حذف نهائي">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- PAGINATION SECTION -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination-container">
            <div class="pagination-info">
                عرض <strong><?php echo number_format($offset + 1); ?></strong> إلى 
                <strong><?php echo number_format(min($offset + $records_per_page, $total_records)); ?></strong> 
                من أصل <strong><?php echo number_format($total_records); ?></strong> طلب
            </div>

            <div class="pagination-links">
                <?php
                $page_params = $_GET;
                // Previous button
                if ($page > 1) {
                    $page_params['page'] = $page - 1;
                    echo '<a href="?' . http_build_query($page_params) . '" class="page-btn"><i class="fas fa-chevron-right text-xs"></i></a>';
                } else {
                    echo '<span class="page-btn disabled"><i class="fas fa-chevron-right text-xs"></i></span>';
                }

                // Page numbers
                $range = 2;
                $start_p = max(1, $page - $range);
                $end_p = min($total_pages, $page + $range);

                if ($start_p > 1) {
                    $page_params['page'] = 1;
                    echo '<a href="?' . http_build_query($page_params) . '" class="page-btn">1</a>';
                    if ($start_p > 2) echo '<span class="px-1 text-gray-400">...</span>';
                }

                for ($i = $start_p; $i <= $end_p; $i++) {
                    $page_params['page'] = $i;
                    $active_cls = ($i === $page) ? 'active' : '';
                    echo '<a href="?' . http_build_query($page_params) . '" class="page-btn ' . $active_cls . '">' . $i . '</a>';
                }

                if ($end_p < $total_pages) {
                    if ($end_p < $total_pages - 1) echo '<span class="px-1 text-gray-400">...</span>';
                    $page_params['page'] = $total_pages;
                    echo '<a href="?' . http_build_query($page_params) . '" class="page-btn">' . $total_pages . '</a>';
                }

                // Next button
                if ($page < $total_pages) {
                    $page_params['page'] = $page + 1;
                    echo '<a href="?' . http_build_query($page_params) . '" class="page-btn"><i class="fas fa-chevron-left text-xs"></i></a>';
                } else {
                    echo '<span class="page-btn disabled"><i class="fas fa-chevron-left text-xs"></i></span>';
                }
                ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- ==========================================
     ORDER DETAILS SIDE DRAWER
     ========================================== -->
<div class="app-drawer-overlay" id="orderDrawerOverlay" onclick="closeOrderDrawer(event)">
    <div class="app-drawer" onclick="event.stopPropagation()">
        <div class="drawer-header">
            <h3>
                <i class="fas fa-receipt text-amber-400"></i>
                <span id="drawerTitle">معاينة الطلب #...</span>
            </h3>
            <button type="button" class="drawer-close-btn" onclick="closeOrderDrawer()">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <div class="drawer-body" id="drawerBody">
            <div class="flex items-center justify-center p-12 text-gray-400">
                <i class="fas fa-spinner fa-spin text-3xl"></i>
            </div>
        </div>

        <div class="drawer-footer" id="drawerFooter">
            <button type="button" class="btn-action-sm btn-action-secondary" onclick="closeOrderDrawer()">
                إغلاق
            </button>
            <a href="#" id="drawerFullReviewLink" class="btn-action-sm btn-action-primary">
                <i class="fas fa-external-link-alt text-xs"></i>
                مراجعة وتعديل كامل
            </a>
        </div>
    </div>
</div>

<!-- ==========================================
     MODAL: QUICK APPROVAL CONFIRMATION
     ========================================== -->
<div class="app-modal" id="quickApproveModal">
    <div class="modal-dialog">
        <div class="modal-header-custom bg-emerald-50">
            <h4 class="text-emerald-900 flex items-center gap-2">
                <i class="fas fa-check-circle text-emerald-600"></i>
                اعتماد الطلب <span id="approveModalOrderId" class="font-mono">#0</span>
            </h4>
            <button type="button" onclick="closeQuickApproveModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <form id="quickApproveForm" method="POST" action="api/approve_order.php" onsubmit="return handleApproveSubmit(event)">
            <input type="hidden" name="approval_id" id="approveFormApprovalId" value="">
            
            <div class="modal-body-custom space-y-4">
                <div class="p-3 bg-gray-50 rounded-xl border border-gray-100 flex justify-between items-center text-sm">
                    <div>
                        <span class="text-gray-500 block text-xs">العميل:</span>
                        <strong id="approveModalCustomerName" class="text-gray-900">-</strong>
                    </div>
                    <div class="text-left">
                        <span class="text-gray-500 block text-xs">إجمالي الطلب:</span>
                        <strong id="approveModalTotalAmount" class="text-amber-700 font-mono">-</strong>
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="block text-xs font-bold text-gray-700">طريقة الدفع <span class="text-red-500">*</span></label>
                    <select name="payment_method" id="quickApprovePaymentMethod" class="filter-control" required onchange="toggleQuickApprovePaymentFields()">
                        <option value="" disabled selected>-- اختر طريقة الدفع --</option>
                        <option value="cash">نقدي (الصندوق)</option>
                        <option value="transfer">تحويل بنكي / إيداع</option>
                        <option value="customer_card">بطاقة العميل</option>
                    </select>
                </div>

                <div id="quickBankDiv" class="p-3 bg-blue-50 rounded-xl border border-blue-100 hidden">
                    <label class="block text-xs font-bold text-blue-900 mb-1">الحساب البنكي المستلم <span class="text-red-500">*</span></label>
                    <select name="bank_account_id" id="quickBankSelect" class="filter-control">
                        <option value="">-- اختر الحساب البنكي --</option>
                        <?php foreach ($bank_accounts as $bank): ?>
                            <option value="<?php echo (int)$bank['id']; ?>">
                                <?php echo htmlspecialchars($bank['bank_name'] . ' (' . $bank['currency'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="quickCardDiv" class="p-3 bg-purple-50 rounded-xl border border-purple-100 hidden">
                    <label class="block text-xs font-bold text-purple-900 mb-1">رقم بطاقة العميل <span class="text-red-500">*</span></label>
                    <input type="text" name="customer_card_number" id="quickCardInput" class="filter-control" placeholder="أدخل رقم البطاقة">
                    <div id="quickCardBalanceInfo" class="text-xs text-purple-800 mt-1 font-bold"></div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">المبلغ المدفوع</label>
                        <input type="number" step="0.01" name="paid_amount" id="quickApprovePaidAmount" class="filter-control font-mono font-bold" required>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">رقم المرجع (اختياري)</label>
                        <input type="text" name="reference_number" class="filter-control" placeholder="رقم الحوالة/الشيك">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">ملاحظات الإدارة (اختياري)</label>
                    <textarea name="admin_notes" rows="2" class="filter-control" style="height:auto; padding:0.5rem;" placeholder="ملاحظات داخلية حول الاعتماد..."></textarea>
                </div>

                <!-- Container where fetched item inputs and metadata will be injected before submit -->
                <div id="quickApproveHiddenPayload"></div>
            </div>

            <div class="modal-footer-custom">
                <button type="button" class="btn-action-sm btn-action-secondary" onclick="closeQuickApproveModal()">إلغاء</button>
                <button type="submit" id="quickApproveSubmitBtn" class="btn-action-sm btn-action-primary">
                    <i class="fas fa-check"></i> تأكيد الاعتماد
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================
     MODAL: QUICK REJECTION CONFIRMATION
     ========================================== -->
<div class="app-modal" id="quickRejectModal">
    <div class="modal-dialog">
        <div class="modal-header-custom bg-red-50">
            <h4 class="text-red-900 flex items-center gap-2">
                <i class="fas fa-ban text-red-600"></i>
                رفض الطلب <span id="rejectModalOrderId" class="font-mono">#0</span>
            </h4>
            <button type="button" onclick="closeQuickRejectModal()" class="text-gray-400 hover:text-gray-600"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="api/reject_order.php" onsubmit="return handleRejectSubmit(event)">
            <input type="hidden" name="approval_id" id="rejectFormApprovalId" value="">
            <input type="hidden" name="whatsapp_contacts_form" value="1">

            <div class="modal-body-custom space-y-4">
                <div class="p-3 bg-red-50/50 rounded-xl border border-red-100 text-xs text-red-800">
                    هل أنت متأكد من رفض هذا الطلب؟ سيتم إشعار العميل وحفظ سبب الرفض في السجل.
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-800 mb-1">سبب الرفض (إلزامي - سيظهر للعميل) <span class="text-red-500">*</span></label>
                    <textarea name="rejection_reason" id="quickRejectReason" rows="3" class="filter-control" style="height:auto; padding:0.6rem;" required placeholder="يرجى كتابة سبب الرفض بوضوح..."></textarea>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">ملاحظات الإدارة (داخلية فقط - اختياري)</label>
                    <textarea name="admin_notes" rows="2" class="filter-control" style="height:auto; padding:0.6rem;" placeholder="ملاحظات داخلية للموظفين..."></textarea>
                </div>

                <div id="quickRejectWhatsAppPicker">
                    <label class="block text-xs font-bold text-gray-700 mb-1.5"><i class="fab fa-whatsapp text-emerald-600 ml-1"></i> إرسال إشعار واتساب إلى:</label>
                    <div class="p-3 bg-gray-50 rounded-xl border border-gray-200 space-y-2 text-xs">
                        <label class="flex items-center gap-2 cursor-pointer font-bold" id="waMobileOption">
                            <input type="checkbox" name="whatsapp_contacts[]" value="mobile" checked class="rounded text-emerald-600">
                            <span>الجوال: <span id="rejectModalPhone" class="font-mono text-gray-800"></span></span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="modal-footer-custom">
                <button type="button" class="btn-action-sm btn-action-secondary" onclick="closeQuickRejectModal()">إلغاء</button>
                <button type="submit" id="quickRejectSubmitBtn" class="btn-action-sm btn-action-danger">
                    <i class="fas fa-ban"></i> تأكيد الرفض
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================
     MEDIA LIGHTBOX (IMAGES & VIDEOS)
     ========================================== -->
<div id="mediaLightbox" onclick="closeMediaPreview()">
    <div id="lightboxMediaContainer" onclick="event.stopPropagation()">
        <!-- Injected via JS -->
    </div>
</div>

<!-- ==========================================
     COPY FEEDBACK TOAST
     ========================================== -->
<div id="copyToast" class="copy-toast">
    <i class="fas fa-check-circle text-emerald-400"></i>
    <span id="copyToastText">تم نسخ الرقم بنجاح</span>
</div>

<!-- ==========================================
     CLIENT JAVASCRIPT & AJAX LOGIC
     ========================================== -->
<script>
// --- Helper: Format numbers without trailing decimal zeroes ---
function formatPriceJs(val) {
    const num = parseFloat(val) || 0;
    return num.toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

// --- Copy Phone Number with Toast Feedback ---
function copyPhone(phone) {
    if (!phone) return;
    navigator.clipboard.writeText(phone).then(() => {
        showToast('تم نسخ رقم الهاتف: ' + phone);
    }).catch(() => {
        // Fallback
        const el = document.createElement('textarea');
        el.value = phone;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        showToast('تم نسخ رقم الهاتف: ' + phone);
    });
}

function showToast(msg) {
    const toast = document.getElementById('copyToast');
    const toastText = document.getElementById('copyToastText');
    if (toast && toastText) {
        toastText.textContent = msg;
        toast.classList.add('show');
        setTimeout(() => toast.classList.remove('show'), 2200);
    }
}

// --- Media Lightbox Preview ---
function openMediaPreview(url, type) {
    const box = document.getElementById('mediaLightbox');
    const container = document.getElementById('lightboxMediaContainer');
    if (!box || !container) return;

    if (type === 'video') {
        container.innerHTML = `
            <video controls autoplay class="rounded-xl shadow-2xl max-w-full max-h-[85vh]">
                <source src="${url}" type="video/mp4">
                متصفحك لا يدعم تشغيل هذا الفيديو.
            </video>
        `;
    } else if (type === 'pdf') {
        window.open(url, '_blank');
        return;
    } else {
        container.innerHTML = `<img src="${url}" class="rounded-xl shadow-2xl" alt="إثبات الدفع">`;
    }

    box.style.display = 'flex';
}

function closeMediaPreview() {
    const box = document.getElementById('mediaLightbox');
    const container = document.getElementById('lightboxMediaContainer');
    if (box) box.style.display = 'none';
    if (container) container.innerHTML = '';
}

// Escape key closes modals and drawers
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeMediaPreview();
        closeOrderDrawer();
        closeQuickApproveModal();
        closeQuickRejectModal();
    }
});

// --- Order Details Drawer (AJAX Inspection) ---
async function openOrderDrawer(approvalId) {
    const overlay = document.getElementById('orderDrawerOverlay');
    const title = document.getElementById('drawerTitle');
    const body = document.getElementById('drawerBody');
    const fullLink = document.getElementById('drawerFullReviewLink');

    if (!overlay || !body) return;

    title.textContent = `معاينة الطلب #${approvalId}`;
    fullLink.href = `view_approval.php?id=${approvalId}`;
    body.innerHTML = `
        <div class="flex flex-col items-center justify-center py-20 text-gray-400">
            <i class="fas fa-circle-notch fa-spin text-3xl mb-3 text-blue-600"></i>
            <span class="text-sm font-bold text-gray-600">جاري تحميل بيانات الطلب #${approvalId}...</span>
        </div>
    `;
    overlay.classList.add('open');

    try {
        const resp = await fetch(`api/get_approval_details.php?id=${approvalId}`);
        const result = await resp.json();

        if (!result.success || !result.data) {
            throw new Error(result.message || 'فشل جلب تفاصيل الطلب.');
        }

        renderDrawerContent(result.data);
    } catch (err) {
        body.innerHTML = `
            <div class="p-6 bg-red-50 border border-red-200 rounded-2xl text-red-700 text-sm">
                <i class="fas fa-exclamation-triangle text-xl mb-2"></i>
                <p class="font-bold">تعذر تحميل بيانات الطلب</p>
                <p class="text-xs text-red-600 mt-1">${err.message}</p>
            </div>
        `;
    }
}

function closeOrderDrawer(e) {
    if (e && e.target && e.target !== document.getElementById('orderDrawerOverlay')) return;
    const overlay = document.getElementById('orderDrawerOverlay');
    if (overlay) overlay.classList.remove('open');
}

function renderDrawerContent(data) {
    const body = document.getElementById('drawerBody');
    if (!body) return;

    const curr = data.currency || 'YER';
    const subtotal = parseFloat(data.calc_subtotal) || 0;
    const discount = parseFloat(data.calc_total_discount) || 0;
    const shipping = parseFloat(data.shipping_cost) || 0;
    const finalAmount = parseFloat(data.calc_final_amount) || 0;
    const paidAmount = parseFloat(data.paid_amount) || 0;
    const remaining = parseFloat(data.calc_remaining) || 0;

    let itemsHtml = '';
    if (data.items && data.items.length > 0) {
        itemsHtml = data.items.map((item, idx) => `
            <div class="p-3 bg-white rounded-xl border border-gray-200 mb-2 text-xs space-y-1.5 shadow-sm">
                <div class="flex justify-between items-start">
                    <strong class="text-gray-900 font-bold">بند #${idx + 1}</strong>
                    <span class="font-mono font-bold text-amber-700">${formatPriceJs(item.total)} ${curr}</span>
                </div>
                ${item.product_link ? `
                    <div class="text-gray-600 truncate">
                        <i class="fas fa-link text-blue-500 ml-1"></i>
                        <a href="${item.product_link}" target="_blank" class="text-blue-600 hover:underline">${item.product_link}</a>
                    </div>
                ` : '<div class="text-gray-400">بدون رابط مباشر</div>'}
                <div class="flex justify-between text-gray-500 pt-1 border-t border-gray-100">
                    <span>الكمية: <strong class="text-gray-800 font-mono">${item.item_count || 1}</strong></span>
                    ${item.notes ? `<span class="truncate max-w-[200px]" title="${item.notes}">ملاحظة: ${item.notes}</span>` : ''}
                </div>
            </div>
        `).join('');
    } else {
        itemsHtml = '<div class="p-4 bg-gray-100 rounded-xl text-center text-xs text-gray-500">لا توجد بنود مسجلة لهذا الطلب</div>';
    }

    let proofHtml = '<span class="text-xs text-gray-400">لم يتم إرفاق إثبات دفع</span>';
    if (data.payment_proof_path) {
        const proofUrl = '../../' + data.payment_proof_path.replace(/^\/+/, '');
        const isVid = data.payment_proof_path.match(/\.(mp4|mov|webm|ogg)$/i);
        const isPdf = data.payment_proof_path.match(/\.pdf$/i);
        proofHtml = `
            <div class="mt-2">
                <button type="button" onclick="openMediaPreview('${proofUrl}', '${isVid ? 'video' : (isPdf ? 'pdf' : 'image')}')"
                        class="inline-flex items-center gap-2 px-3 py-1.5 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg text-xs font-bold text-blue-800 transition">
                    <i class="fas ${isVid ? 'fa-play' : (isPdf ? 'fa-file-pdf' : 'fa-image')}"></i>
                    معاينة الإثبات المرفق
                </button>
            </div>
        `;
    }

    body.innerHTML = `
        <div class="space-y-4">
            <!-- Customer Card -->
            <div class="p-4 bg-white rounded-2xl border border-gray-200 shadow-sm space-y-2">
                <div class="flex items-center justify-between border-b pb-2">
                    <h4 class="font-extrabold text-sm text-gray-800 flex items-center gap-2">
                        <i class="fas fa-user-circle text-blue-600"></i> بيانات العميل
                    </h4>
                    ${data.customer_type_name_from_db ? `<span class="badge-tag badge-customer-type">${data.customer_type_name_from_db}</span>` : ''}
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div><span class="text-gray-400">الاسم:</span> <strong class="text-gray-800">${data.display_customer_name || '-'}</strong></div>
                    <div><span class="text-gray-400">الكود:</span> <span class="font-mono text-gray-800">${data.customer_code || '-'}</span></div>
                    <div><span class="text-gray-400">الجوال:</span> <span class="font-mono text-gray-800">${data.mobile_number || '-'}</span></div>
                    <div><span class="text-gray-400">المدينة:</span> <span class="text-gray-800">${data.city_name || '-'}</span></div>
                </div>
                ${data.customer_address ? `<div class="text-xs text-gray-600 pt-1 border-t"><i class="fas fa-map-marker-alt text-gray-400 ml-1"></i> ${data.customer_address}</div>` : ''}
            </div>

            <!-- Items Card -->
            <div class="p-4 bg-white rounded-2xl border border-gray-200 shadow-sm">
                <div class="flex justify-between items-center mb-3">
                    <h4 class="font-extrabold text-sm text-gray-800 flex items-center gap-2">
                        <i class="fas fa-boxes text-amber-500"></i> المنتجات المطلوبة (${data.items ? data.items.length : 0})
                    </h4>
                </div>
                ${itemsHtml}
            </div>

            <!-- Payment & Proof Card -->
            <div class="p-4 bg-white rounded-2xl border border-gray-200 shadow-sm space-y-2">
                <h4 class="font-extrabold text-sm text-gray-800 flex items-center gap-2 border-b pb-2">
                    <i class="fas fa-receipt text-emerald-600"></i> إثبات الدفع والتحويل
                </h4>
                ${proofHtml}
            </div>

            <!-- Financial Summary Card -->
            <div class="p-4 bg-white rounded-2xl border border-gray-200 shadow-sm space-y-2 text-xs">
                <h4 class="font-extrabold text-sm text-gray-800 flex items-center gap-2 border-b pb-2">
                    <i class="fas fa-calculator text-blue-600"></i> الحساب المالي
                </h4>
                <div class="flex justify-between text-gray-600">
                    <span>المجموع الفرعي:</span>
                    <span class="font-mono font-bold">${formatPriceJs(subtotal)} ${curr}</span>
                </div>
                ${discount > 0 ? `
                <div class="flex justify-between text-emerald-600">
                    <span>إجمالي الخصم:</span>
                    <span class="font-mono font-bold">-${formatPriceJs(discount)} ${curr}</span>
                </div>
                ` : ''}
                <div class="flex justify-between text-gray-600">
                    <span>رسوم الشحن:</span>
                    <span class="font-mono font-bold">+${formatPriceJs(shipping)} ${curr}</span>
                </div>
                <div class="flex justify-between font-extrabold text-sm text-gray-900 pt-2 border-t">
                    <span>الصافي النهائي:</span>
                    <span class="font-mono text-amber-700">${formatPriceJs(finalAmount)} ${curr}</span>
                </div>
                <div class="flex justify-between text-emerald-700 font-bold">
                    <span>المبلغ المدفوع:</span>
                    <span class="font-mono">${formatPriceJs(paidAmount)} ${curr}</span>
                </div>
                ${remaining > 0 ? `
                <div class="flex justify-between text-red-600 font-bold">
                    <span>المتبقي:</span>
                    <span class="font-mono">${formatPriceJs(remaining)} ${curr}</span>
                </div>
                ` : `
                <div class="text-center text-emerald-700 font-bold pt-1">
                    ✓ مدفوع بالكامل
                </div>
                `}
            </div>

            <!-- Notes -->
            ${data.notes ? `
            <div class="p-3 bg-amber-50/70 border border-amber-200 rounded-xl text-xs text-amber-900">
                <strong>ملاحظات العميل:</strong> ${data.notes}
            </div>
            ` : ''}
            ${data.admin_notes ? `
            <div class="p-3 bg-blue-50/70 border border-blue-200 rounded-xl text-xs text-blue-900">
                <strong>ملاحظات الإدارة:</strong> ${data.admin_notes}
            </div>
            ` : ''}
        </div>
    `;
}

// --- Quick Approve Modal Logic ---
let quickApproveCache = null;

async function openQuickApproveModal(approvalId, custName, paidAmount, finalAmount) {
    const modal = document.getElementById('quickApproveModal');
    const orderIdSpan = document.getElementById('approveModalOrderId');
    const formAppId = document.getElementById('approveFormApprovalId');
    const custNameSpan = document.getElementById('approveModalCustomerName');
    const totalSpan = document.getElementById('approveModalTotalAmount');
    const paidInput = document.getElementById('quickApprovePaidAmount');
    const payloadContainer = document.getElementById('quickApproveHiddenPayload');
    const submitBtn = document.getElementById('quickApproveSubmitBtn');

    if (!modal) return;

    orderIdSpan.textContent = '#' + approvalId;
    formAppId.value = approvalId;
    custNameSpan.textContent = custName || 'عميل';
    totalSpan.textContent = formatPriceJs(finalAmount) + ' YER';
    paidInput.value = paidAmount || 0;
    payloadContainer.innerHTML = '';
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التحضير...';

    modal.classList.add('open');

    // Fetch approval details to construct complete backend payload
    try {
        const resp = await fetch(`api/get_approval_details.php?id=${approvalId}`);
        const res = await resp.json();
        if (!res.success || !res.data) {
            throw new Error(res.message || 'فشل جلب بيانات الطلب.');
        }

        quickApproveCache = res.data;
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="fas fa-check"></i> تأكيد الاعتماد';
    } catch (e) {
        alert('تعذر تحميل بيانات الطلب: ' + e.message);
        closeQuickApproveModal();
    }
}

function closeQuickApproveModal() {
    const modal = document.getElementById('quickApproveModal');
    if (modal) modal.classList.remove('open');
    quickApproveCache = null;
}

function toggleQuickApprovePaymentFields() {
    const method = document.getElementById('quickApprovePaymentMethod').value;
    const bankDiv = document.getElementById('quickBankDiv');
    const bankSelect = document.getElementById('quickBankSelect');
    const cardDiv = document.getElementById('quickCardDiv');
    const cardInput = document.getElementById('quickCardInput');

    bankDiv.classList.add('hidden');
    bankSelect.required = false;
    cardDiv.classList.add('hidden');
    cardInput.required = false;

    if (method === 'transfer') {
        bankDiv.classList.remove('hidden');
        bankSelect.required = true;
    } else if (method === 'customer_card') {
        cardDiv.classList.remove('hidden');
        cardInput.required = true;
    }
}

// Live card balance check
let cardCheckTimer = null;
document.getElementById('quickCardInput')?.addEventListener('input', function() {
    clearTimeout(cardCheckTimer);
    const cardNum = this.value.trim();
    const info = document.getElementById('quickCardBalanceInfo');
    if (cardNum.length >= 6) {
        if (info) info.textContent = '...جار التحقق من الرصيد';
        cardCheckTimer = setTimeout(() => {
            fetch(`api/check_card_balance.php?card_number=${encodeURIComponent(cardNum)}`)
                .then(r => r.json())
                .then(d => {
                    if (d.success && d.card) {
                        info.textContent = `الرصيد المتاح: ${formatPriceJs(d.card.current_balance)} ريال`;
                        info.setAttribute('data-balance', d.card.current_balance);
                    } else {
                        info.textContent = d.message || 'البطاقة غير موجودة أو غير نشطة.';
                        info.removeAttribute('data-balance');
                    }
                })
                .catch(() => {
                    info.textContent = 'تعذر الاتصال بالخادم.';
                });
        }, 500);
    } else {
        if (info) {
            info.textContent = '';
            info.removeAttribute('data-balance');
        }
    }
});

function handleApproveSubmit(e) {
    if (!quickApproveCache) {
        alert('جاري تجهيز البيانات، الرجاء الانتظار...');
        e.preventDefault();
        return false;
    }

    const form = document.getElementById('quickApproveForm');
    const payloadContainer = document.getElementById('quickApproveHiddenPayload');
    const method = document.getElementById('quickApprovePaymentMethod').value;
    const paid = parseFloat(document.getElementById('quickApprovePaidAmount').value) || 0;

    if (!method) {
        alert('يرجى اختيار طريقة الدفع.');
        e.preventDefault();
        return false;
    }

    if (paid > 0 && method === 'transfer') {
        const bank = document.getElementById('quickBankSelect').value;
        if (!bank) {
            alert('يرجى اختيار الحساب البنكي المستلم.');
            e.preventDefault();
            return false;
        }
    }

    if (paid > 0 && method === 'customer_card') {
        const cardInfo = document.getElementById('quickCardBalanceInfo');
        const bal = parseFloat(cardInfo?.getAttribute('data-balance') || '0');
        if (paid > bal) {
            alert(`خطأ: المبلغ المدفوع (${formatPriceJs(paid)}) يتجاوز رصيد البطاقة المتاح (${formatPriceJs(bal)}).`);
            e.preventDefault();
            return false;
        }
    }

    if (!confirm('هل أنت متأكد من اعتماد هذا الطلب وإنشاء فاتورة رسمية؟')) {
        e.preventDefault();
        return false;
    }

    // Build items payload
    payloadContainer.innerHTML = '';
    const items = quickApproveCache.items || [];
    if (items.length === 0) {
        alert('لا يمكن اعتماد طلب بدون منتجات. يرجى مراجعته أولاً.');
        e.preventDefault();
        return false;
    }

    items.forEach((item, index) => {
        for (const k in item) {
            if (k !== 'id' && k !== 'approval_id') {
                const inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = `items[${index}][${k}]`;
                inp.value = item[k] !== null && item[k] !== undefined ? item[k] : '';
                payloadContainer.appendChild(inp);
            }
        }
    });

    const fields = [
        'notes', 'shipping_cost', 'expected_delivery_date', 'coupon_code',
        'automatic_discount_percentage', 'automatic_discount_amount',
        'coupon_discount_amount', 'payment_proof_path'
    ];
    fields.forEach(f => {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = f;
        inp.value = quickApproveCache[f] !== null && quickApproveCache[f] !== undefined ? quickApproveCache[f] : '';
        payloadContainer.appendChild(inp);
    });

    const delImg = document.createElement('input');
    delImg.type = 'hidden';
    delImg.name = 'deleted_images';
    delImg.value = '[]';
    payloadContainer.appendChild(delImg);

    const submitBtn = document.getElementById('quickApproveSubmitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري التنفيذ...';
    return true;
}

// --- Quick Reject Modal Logic ---
function openQuickRejectModal(approvalId, custName, phone) {
    const modal = document.getElementById('quickRejectModal');
    const orderIdSpan = document.getElementById('rejectModalOrderId');
    const formAppId = document.getElementById('rejectFormApprovalId');
    const phoneSpan = document.getElementById('rejectModalPhone');
    const waOption = document.getElementById('waMobileOption');

    if (!modal) return;

    orderIdSpan.textContent = '#' + approvalId;
    formAppId.value = approvalId;
    document.getElementById('quickRejectReason').value = '';

    if (phone && phone.trim() !== '') {
        phoneSpan.textContent = phone;
        waOption.style.display = 'flex';
    } else {
        waOption.style.display = 'none';
    }

    modal.classList.add('open');
}

function closeQuickRejectModal() {
    const modal = document.getElementById('quickRejectModal');
    if (modal) modal.classList.remove('open');
}

function handleRejectSubmit(e) {
    const reason = document.getElementById('quickRejectReason').value.trim();
    if (!reason) {
        alert('سبب الرفض إلزامي.');
        e.preventDefault();
        return false;
    }
    if (!confirm('هل أنت متأكد من تأكيد رفض هذا الطلب؟')) {
        e.preventDefault();
        return false;
    }
    const btn = document.getElementById('quickRejectSubmitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الرفض...';
    return true;
}

// Ensure theme toggle cleanup as done previously in project
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('button[title*="الوضع"], button[title*="مظلم"], button[title*="داكن"], #darkModeToggle, #themeToggle, .theme-toggle-btn').forEach(function (el) {
        el.remove();
    });
});
</script>

<?php include '../../includes/footer.php'; ?>