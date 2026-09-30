<?php
session_start();

// Authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/database.php';

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$page_title = 'الحسابات البنكية';
$error_message = '';
$success_message = '';
$edit_mode = false;
$account_to_edit = null;

// Ensure account_id column allows NULL (safe to run multiple times)
try {
    $db->exec("ALTER TABLE bank_accounts MODIFY account_id INT NULL DEFAULT NULL");
} catch (PDOException $e) { /* Already nullable */ }

// Ensure show_in_checkout column exists (safe migration)
try {
    $cols = $db->query('DESCRIBE bank_accounts')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('show_in_checkout', $cols)) {
        $db->exec("ALTER TABLE bank_accounts ADD COLUMN show_in_checkout TINYINT(1) NOT NULL DEFAULT 1 AFTER is_active");
    }
} catch (PDOException $e) { /* Ignore */ }

// Create bank_transactions table if needed
try {
    $db->exec("CREATE TABLE IF NOT EXISTS bank_transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        bank_account_id INT NOT NULL,
        transaction_type VARCHAR(50) NOT NULL,
        amount DECIMAL(15, 3) NOT NULL,
        description TEXT,
        transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INT,
        FOREIGN KEY (bank_account_id) REFERENCES bank_accounts(id) ON DELETE CASCADE
    )");
} catch (PDOException $e) { /* Already exists */ }

// =============================================
// Handle POST actions: add, update, deposit
// =============================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    // CSRF check for all POST actions
    $post_csrf = $_POST['csrf_token'] ?? '';
    if (empty($post_csrf) || !hash_equals($csrf_token, $post_csrf)) {
        $_SESSION['error_message'] = 'رمز الحماية غير صالح. يرجى تحديث الصفحة والمحاولة مجدداً.';
        header('Location: bank_accounts.php');
        exit();
    }

    try {
        // --- ADD a new account ---
        if ($_POST['action'] === 'add') {
            $bank_name           = trim($_POST['bank_name'] ?? '');
            $account_name        = trim($_POST['account_name'] ?? '');
            $account_holder_name = trim($_POST['account_holder_name'] ?? '');
            $account_number      = trim($_POST['account_number'] ?? '');
            $iban                = trim($_POST['iban'] ?? '') ?: null;
            $initial_balance     = filter_input(INPUT_POST, 'initial_balance', FILTER_VALIDATE_FLOAT, ['options' => ['default' => 0]]);
            $show_in_checkout    = isset($_POST['show_in_checkout']) ? 1 : 0;

            if (empty($bank_name) || empty($account_name) || empty($account_holder_name) || empty($account_number)) {
                $error_message = 'يرجى تعبئة الحقول الإلزامية (البنك، اسم الحساب، صاحب الحساب، رقم الحساب).';
            } else {
                $stmt = $db->prepare(
                    "INSERT INTO bank_accounts (account_id, bank_name, account_name, account_holder_name, account_number, iban, initial_balance, current_balance, is_active, show_in_checkout, created_by)
                     VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)"
                );
                $stmt->execute([$bank_name, $account_name, $account_holder_name, $account_number, $iban, $initial_balance, $initial_balance, $show_in_checkout, $_SESSION['user_id']]);
                $_SESSION['success_message'] = "✓ تمت إضافة الحساب البنكي «{$bank_name}» بنجاح.";
                header('Location: bank_accounts.php');
                exit();
            }
        }

        // --- UPDATE an account ---
        elseif ($_POST['action'] === 'update' && isset($_POST['id'])) {
            $id                  = intval($_POST['id']);
            $bank_name           = trim($_POST['bank_name'] ?? '');
            $account_name        = trim($_POST['account_name'] ?? '');
            $account_holder_name = trim($_POST['account_holder_name'] ?? '');
            $account_number      = trim($_POST['account_number'] ?? '');
            $iban                = trim($_POST['iban'] ?? '') ?: null;
            $show_in_checkout    = isset($_POST['show_in_checkout']) ? 1 : 0;

            if (empty($bank_name) || empty($account_name) || empty($account_holder_name) || empty($account_number)) {
                $error_message = 'يرجى تعبئة الحقول الإلزامية.';
                $edit_mode = true;
                $account_to_edit = ['id' => $id, 'bank_name' => $bank_name];
            } else {
                $stmt = $db->prepare(
                    "UPDATE bank_accounts SET bank_name=?, account_name=?, account_holder_name=?, account_number=?, iban=?, show_in_checkout=?, updated_at=NOW() WHERE id=?"
                );
                $stmt->execute([$bank_name, $account_name, $account_holder_name, $account_number, $iban, $show_in_checkout, $id]);
                $_SESSION['success_message'] = "✓ تم تحديث بيانات الحساب البنكي بنجاح.";
                header('Location: bank_accounts.php');
                exit();
            }
        }

        // --- DEPOSIT money ---
        elseif ($_POST['action'] === 'deposit' && isset($_POST['account_id_deposit'])) {
            $deposit_acc_id      = intval($_POST['account_id_deposit']);
            $deposit_amount      = filter_input(INPUT_POST, 'deposit_amount', FILTER_VALIDATE_FLOAT);
            $deposit_description = trim($_POST['deposit_description'] ?? 'إيداع نقدي');

            if ($deposit_amount === false || $deposit_amount <= 0) {
                $_SESSION['error_message'] = 'مبلغ الإيداع غير صالح.';
            } else {
                $db->beginTransaction();
                $db->prepare("UPDATE bank_accounts SET current_balance = current_balance + ?, updated_at=NOW() WHERE id = ?")->execute([$deposit_amount, $deposit_acc_id]);
                $db->prepare("INSERT INTO bank_transactions (bank_account_id, transaction_type, amount, description, created_by) VALUES (?, 'deposit', ?, ?, ?)")->execute([$deposit_acc_id, $deposit_amount, $deposit_description, $_SESSION['user_id']]);
                $db->commit();
                $_SESSION['success_message'] = "✓ تم إيداع مبلغ " . number_format($deposit_amount, 2) . " بنجاح.";
            }
            header('Location: bank_accounts.php');
            exit();
        }

    } catch (PDOException $e) {
        if (isset($db) && $db->inTransaction()) $db->rollBack();
        $error_code = $e->errorInfo[1] ?? 0;
        if ($error_code == 1062 || strpos($e->getMessage(), '1062') !== false) {
            $error_message = 'رقم الحساب أو الآيبان مستخدم لحساب آخر مسبقاً.';
        } else {
            $error_message = 'فشل في تنفيذ العملية. يرجى المحاولة مرة أخرى.';
        }
    }
}

// =============================================
// Handle GET actions: edit, toggle_status, delete
// =============================================
if (isset($_GET['action'])) {
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($id > 0) {
        if ($_GET['action'] === 'edit') {
            $edit_stmt = $db->prepare("SELECT * FROM bank_accounts WHERE id = ?");
            $edit_stmt->execute([$id]);
            $fetched = $edit_stmt->fetch(PDO::FETCH_ASSOC);
            if ($fetched) {
                $edit_mode = true;
                $account_to_edit = $fetched;
            } else {
                $_SESSION['error_message'] = 'الحساب المطلوب غير موجود.';
                header('Location: bank_accounts.php');
                exit();
            }
        }

        if ($_GET['action'] === 'toggle_status') {
            try {
                $status_stmt = $db->prepare("SELECT is_active FROM bank_accounts WHERE id = ?");
                $status_stmt->execute([$id]);
                $cur = $status_stmt->fetchColumn();
                $new = $cur == 1 ? 0 : 1;
                $db->prepare("UPDATE bank_accounts SET is_active=?, updated_at=NOW() WHERE id=?")->execute([$new, $id]);
                $_SESSION['success_message'] = $new ? '✓ تم تفعيل الحساب.' : '✓ تم إلغاء تفعيل الحساب.';
            } catch (PDOException $e) {
                $_SESSION['error_message'] = 'فشل تغيير حالة الحساب.';
            }
            header('Location: bank_accounts.php');
            exit();
        }

        if ($_GET['action'] === 'delete') {
            try {
                // Check if account is referenced in payments
                $ref_stmt = $db->prepare("SELECT COUNT(*) FROM customer_payments WHERE bank_account_id = ?");
                $ref_stmt->execute([$id]);
                $ref_count = (int)$ref_stmt->fetchColumn();

                if ($ref_count > 0) {
                    $_SESSION['error_message'] = "لا يمكن حذف هذا الحساب لأنه مرتبط بـ {$ref_count} سجل دفع. يمكنك إلغاء تفعيله بدلاً من الحذف.";
                } else {
                    $db->prepare("DELETE FROM bank_accounts WHERE id = ?")->execute([$id]);
                    $_SESSION['success_message'] = '✓ تم حذف الحساب البنكي بنجاح.';
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = 'فشل في حذف الحساب. قد يكون مرتبطاً بسجلات أخرى.';
            }
            header('Location: bank_accounts.php');
            exit();
        }
    }
}

// Flash messages
if (isset($_SESSION['success_message'])) { $success_message = $_SESSION['success_message']; unset($_SESSION['success_message']); }
if (isset($_SESSION['error_message']))   { $error_message   = $_SESSION['error_message'];   unset($_SESSION['error_message']); }

// Fetch all bank accounts with stats
try {
    $accounts_stmt = $db->prepare("SELECT * FROM bank_accounts ORDER BY bank_name ASC");
    $accounts_stmt->execute();
    $accounts = $accounts_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $accounts = [];
    $error_message = 'فشل في تحميل قائمة الحسابات البنكية.';
}

// Compute stats
$total_count    = count($accounts);
$visible_count  = count(array_filter($accounts, fn($a) => $a['show_in_checkout'] == 1 && $a['is_active'] == 1));
$hidden_count   = $total_count - $visible_count;
$active_count   = count(array_filter($accounts, fn($a) => $a['is_active'] == 1));

// Form values
$val_bank_name           = $_POST['bank_name']           ?? ($account_to_edit['bank_name']           ?? '');
$val_account_name        = $_POST['account_name']        ?? ($account_to_edit['account_name']        ?? '');
$val_account_holder_name = $_POST['account_holder_name'] ?? ($account_to_edit['account_holder_name'] ?? '');
$val_account_number      = $_POST['account_number']      ?? ($account_to_edit['account_number']      ?? '');
$val_iban                = $_POST['iban']                ?? ($account_to_edit['iban']                ?? '');
$val_initial_balance     = $_POST['initial_balance']     ?? ($account_to_edit['initial_balance']     ?? '0.000');
$val_show_in_checkout    = isset($_POST['show_in_checkout']) ? 1 : ($account_to_edit['show_in_checkout'] ?? 1);
$val_id                  = $_POST['id']                  ?? ($account_to_edit['id']                  ?? 0);

include '../../includes/header.php';
?>

<style>
    :root {
        --blue-primary: #3b82f6;
        --blue-dark: #1d4ed8;
        --green-ok: #16a34a;
        --green-light: #dcfce7;
        --red-danger: #dc2626;
        --red-light: #fee2e2;
        --amber: #d97706;
        --amber-light: #fef3c7;
        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-400: #9ca3af;
        --gray-600: #4b5563;
        --gray-800: #1f2937;
        --shadow-card: 0 1px 3px rgba(0,0,0,.06), 0 4px 16px rgba(0,0,0,.06);
        --shadow-hover: 0 4px 20px rgba(0,0,0,.12);
        --radius: 12px;
        --radius-sm: 8px;
    }

    * { box-sizing: border-box; }
    body { background: var(--gray-100); font-family: 'Cairo', Arial, sans-serif; }

    /* ============ TOAST ============ */
    #toast-container {
        position: fixed;
        top: 1.5rem;
        left: 50%;
        transform: translateX(-50%);
        z-index: 9999;
        display: flex;
        flex-direction: column;
        gap: .5rem;
        align-items: center;
        pointer-events: none;
    }
    .toast {
        display: flex;
        align-items: center;
        gap: .65rem;
        padding: .75rem 1.25rem;
        border-radius: 10px;
        font-size: .875rem;
        font-weight: 600;
        box-shadow: 0 4px 20px rgba(0,0,0,.15);
        pointer-events: auto;
        animation: toastIn .25s ease forwards;
        max-width: 400px;
        width: max-content;
    }
    .toast.success { background: #166534; color: #fff; }
    .toast.error   { background: #991b1b; color: #fff; }
    .toast.info    { background: #1e40af; color: #fff; }
    @keyframes toastIn  { from { opacity:0; transform:translateY(-12px); } to { opacity:1; transform:translateY(0); } }
    @keyframes toastOut { from { opacity:1; transform:translateY(0); }     to { opacity:0; transform:translateY(-12px); } }

    /* ============ PAGE HEADER ============ */
    .page-hero {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        border-radius: var(--radius);
        padding: 2rem 2rem 1.75rem;
        margin-bottom: 1.75rem;
        position: relative;
        overflow: hidden;
    }
    .page-hero::before {
        content: '';
        position: absolute;
        inset: 0;
        background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E") repeat;
    }
    .page-hero-title { font-size: 1.75rem; font-weight: 800; color: #fff; margin: 0 0 .4rem; }
    .page-hero-subtitle { color: #94a3b8; font-size: .95rem; margin: 0 0 1.5rem; }
    .hero-meta {
        display: flex;
        gap: .75rem;
        flex-wrap: wrap;
        align-items: center;
    }
    .hero-badge {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .35rem .85rem;
        border-radius: 20px;
        font-size: .8rem;
        font-weight: 700;
        letter-spacing: .02em;
    }
    .hero-badge.total   { background: rgba(255,255,255,.1); color: #e2e8f0; border: 1px solid rgba(255,255,255,.15); }
    .hero-badge.visible { background: rgba(22,163,74,.2);   color: #4ade80; border: 1px solid rgba(74,222,128,.2); }
    .hero-badge.hidden  { background: rgba(239,68,68,.2);   color: #f87171; border: 1px solid rgba(248,113,113,.2); }

    /* ============ KPI CARDS ============ */
    .kpi-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 1rem;
        margin-bottom: 1.75rem;
    }
    .kpi-card {
        background: #fff;
        border-radius: var(--radius);
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 1rem;
        box-shadow: var(--shadow-card);
        transition: box-shadow .2s, transform .2s;
    }
    .kpi-card:hover { box-shadow: var(--shadow-hover); transform: translateY(-2px); }
    .kpi-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.25rem;
        flex-shrink: 0;
    }
    .kpi-icon.blue   { background: #dbeafe; color: #1d4ed8; }
    .kpi-icon.green  { background: #dcfce7; color: #166534; }
    .kpi-icon.gray   { background: #f1f5f9; color: #64748b; }
    .kpi-label { font-size: .78rem; color: var(--gray-600); font-weight: 600; margin-bottom: .15rem; }
    .kpi-value { font-size: 1.75rem; font-weight: 800; color: var(--gray-800); line-height: 1; }

    /* ============ SECTION CARD ============ */
    .section-card {
        background: #fff;
        border-radius: var(--radius);
        box-shadow: var(--shadow-card);
        margin-bottom: 1.75rem;
        overflow: hidden;
    }
    .section-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--gray-200);
    }
    .section-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--gray-800);
        display: flex;
        align-items: center;
        gap: .6rem;
        margin: 0;
    }
    .section-title i { color: var(--blue-primary); }

    /* ============ FILTER BAR ============ */
    .filter-bar {
        display: flex;
        align-items: center;
        gap: .75rem;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--gray-200);
        flex-wrap: wrap;
        background: var(--gray-50);
    }
    .search-wrap { position: relative; flex: 1; min-width: 200px; }
    .search-wrap i { position: absolute; right: .9rem; top: 50%; transform: translateY(-50%); color: var(--gray-400); font-size: .9rem; }
    .search-input {
        width: 100%;
        padding: .6rem 2.4rem .6rem .75rem;
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-sm);
        font-family: 'Cairo', sans-serif;
        font-size: .875rem;
        background: #fff;
        transition: border-color .2s, box-shadow .2s;
    }
    .search-input:focus { outline: none; border-color: var(--blue-primary); box-shadow: 0 0 0 3px rgba(59,130,246,.15); }
    .filter-tabs { display: flex; gap: .35rem; }
    .ftab {
        padding: .45rem .9rem;
        border-radius: 20px;
        font-size: .8rem;
        font-weight: 700;
        cursor: pointer;
        border: 1px solid var(--gray-200);
        background: #fff;
        color: var(--gray-600);
        transition: all .18s;
        white-space: nowrap;
    }
    .ftab.active, .ftab:hover { background: var(--blue-primary); color: #fff; border-color: var(--blue-primary); }

    /* ============ ACCOUNT CARDS GRID ============ */
    .accounts-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
        gap: 1.25rem;
        padding: 1.5rem;
    }

    /* ============ ACCOUNT CARD ============ */
    .account-card {
        background: #fff;
        border: 1.5px solid var(--gray-200);
        border-radius: var(--radius);
        overflow: hidden;
        transition: box-shadow .2s, transform .2s, border-color .2s;
        position: relative;
    }
    .account-card:hover { box-shadow: var(--shadow-hover); transform: translateY(-3px); border-color: #c7d2fe; }
    .account-card.is-hidden-checkout { opacity: .85; border-style: dashed; }

    .ac-badge-bar {
        display: flex;
        align-items: center;
        gap: .5rem;
        padding: .65rem 1rem;
        background: var(--gray-50);
        border-bottom: 1px solid var(--gray-200);
        flex-wrap: wrap;
    }
    .ac-status-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        flex-shrink: 0;
    }
    .ac-status-dot.on  { background: #22c55e; box-shadow: 0 0 0 2px #dcfce7; }
    .ac-status-dot.off { background: #9ca3af; box-shadow: 0 0 0 2px #f3f4f6; }

    .badge {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .25rem .65rem;
        border-radius: 20px;
        font-size: .72rem;
        font-weight: 700;
    }
    .badge.checkout-on  { background: #dcfce7; color: #166534; }
    .badge.checkout-off { background: #f1f5f9; color: #64748b; }
    .badge.active       { background: #dbeafe; color: #1e40af; }
    .badge.inactive     { background: #fee2e2; color: #991b1b; }

    .ac-body { padding: 1.1rem 1.1rem .75rem; }
    .ac-bank-name {
        font-size: 1.05rem;
        font-weight: 800;
        color: var(--gray-800);
        margin: 0 0 .25rem;
        display: flex;
        align-items: center;
        gap: .5rem;
    }
    .ac-bank-name i { color: var(--blue-primary); font-size: .95rem; }
    .ac-holder { font-size: .82rem; color: var(--gray-600); margin: 0 0 .9rem; font-weight: 600; }

    .ac-field { margin-bottom: .6rem; }
    .ac-field-label {
        font-size: .7rem;
        color: var(--gray-400);
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .05em;
        margin-bottom: .15rem;
    }
    .ac-field-value {
        display: flex;
        align-items: center;
        gap: .5rem;
        background: var(--gray-50);
        border: 1px solid var(--gray-200);
        border-radius: var(--radius-sm);
        padding: .45rem .7rem;
    }
    .ac-field-value .number {
        font-family: 'Courier New', monospace;
        font-size: .88rem;
        font-weight: 700;
        color: #1e40af;
        flex: 1;
        direction: ltr;
        text-align: left;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .copy-btn {
        background: none;
        border: none;
        cursor: pointer;
        color: var(--gray-400);
        font-size: .8rem;
        padding: .2rem .4rem;
        border-radius: 4px;
        transition: all .15s;
        flex-shrink: 0;
    }
    .copy-btn:hover { color: var(--blue-primary); background: #eff6ff; }
    .copy-btn.copied { color: var(--green-ok); }

    .ac-footer {
        border-top: 1px solid var(--gray-200);
        padding: .75rem 1.1rem;
        display: flex;
        align-items: center;
        gap: .6rem;
        flex-wrap: wrap;
        background: var(--gray-50);
    }

    /* ============ TOGGLE SWITCH ============ */
    .toggle-wrap {
        display: flex;
        align-items: center;
        gap: .5rem;
        flex-shrink: 0;
    }
    .toggle-label { font-size: .75rem; font-weight: 700; color: var(--gray-600); white-space: nowrap; }
    .toggle-switch {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
        flex-shrink: 0;
    }
    .toggle-switch input { opacity: 0; width: 0; height: 0; }
    .toggle-track {
        position: absolute;
        cursor: pointer;
        inset: 0;
        border-radius: 24px;
        background: #d1d5db;
        transition: background .2s;
    }
    .toggle-track::before {
        content: '';
        position: absolute;
        width: 18px;
        height: 18px;
        right: 3px;
        top: 3px;
        background: #fff;
        border-radius: 50%;
        transition: transform .2s, right .2s;
        box-shadow: 0 1px 3px rgba(0,0,0,.2);
    }
    .toggle-switch input:checked + .toggle-track { background: #22c55e; }
    .toggle-switch input:checked + .toggle-track::before { transform: translateX(-20px); }
    .toggle-switch.saving .toggle-track { background: #93c5fd; cursor: wait; }
    .toggle-switch.saving .toggle-track::before { animation: pulse .6s ease infinite; }
    @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

    .toggle-state-text {
        font-size: .72rem;
        font-weight: 700;
        white-space: nowrap;
    }
    .toggle-state-text.on  { color: #166534; }
    .toggle-state-text.off { color: #6b7280; }

    /* ============ ACTION BUTTONS ============ */
    .ac-actions { display: flex; gap: .4rem; margin-right: auto; }
    .btn-icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: var(--radius-sm);
        border: none;
        cursor: pointer;
        font-size: .85rem;
        text-decoration: none;
        transition: all .18s;
    }
    .btn-icon.edit   { background: #fff7ed; color: #c2410c; border: 1px solid #fed7aa; }
    .btn-icon.edit:hover   { background: #c2410c; color: #fff; }
    .btn-icon.deposit { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
    .btn-icon.deposit:hover { background: #166534; color: #fff; }
    .btn-icon.delete { background: #fff1f2; color: #be123c; border: 1px solid #fecdd3; }
    .btn-icon.delete:hover { background: #be123c; color: #fff; }
    .btn-icon.toggle-active { background: #f0f9ff; color: #0369a1; border: 1px solid #bae6fd; }
    .btn-icon.toggle-active:hover { background: #0369a1; color: #fff; }

    /* ============ FORM CARD ============ */
    .form-card {
        background: #fff;
        border-radius: var(--radius);
        box-shadow: var(--shadow-card);
        margin-bottom: 1.75rem;
        overflow: hidden;
        border-top: 3px solid var(--blue-primary);
    }
    .form-card.edit-mode { border-top-color: var(--amber); }
    .form-card-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .form-card-header h2 {
        font-size: 1rem;
        font-weight: 700;
        color: var(--gray-800);
        display: flex;
        align-items: center;
        gap: .6rem;
        margin: 0;
    }
    .form-card-header h2 i { color: var(--blue-primary); }
    .form-card.edit-mode .form-card-header h2 i { color: var(--amber); }

    .form-body { padding: 1.5rem; }
    .form-section-title {
        font-size: .78rem;
        font-weight: 800;
        color: var(--gray-400);
        text-transform: uppercase;
        letter-spacing: .08em;
        margin: 0 0 1rem;
        padding-bottom: .5rem;
        border-bottom: 1px solid var(--gray-200);
    }
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
        margin-bottom: 1rem;
    }
    .form-group { margin-bottom: 0; }
    .form-group label {
        display: block;
        font-size: .82rem;
        font-weight: 700;
        color: var(--gray-800);
        margin-bottom: .4rem;
    }
    .form-group label .req { color: #dc2626; }
    .form-control {
        width: 100%;
        padding: .65rem 1rem;
        border: 1.5px solid var(--gray-200);
        border-radius: var(--radius-sm);
        font-family: 'Cairo', sans-serif;
        font-size: .9rem;
        background: #fff;
        transition: border-color .2s, box-shadow .2s;
        color: var(--gray-800);
    }
    .form-control:focus { outline: none; border-color: var(--blue-primary); box-shadow: 0 0 0 3px rgba(59,130,246,.15); }

    /* checkout toggle in form */
    .checkout-toggle-row {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        border-radius: var(--radius-sm);
        border: 1.5px solid var(--gray-200);
        background: var(--gray-50);
        margin-top: 1rem;
    }
    .checkout-toggle-row .info { flex: 1; }
    .checkout-toggle-row .info strong { display: block; font-size: .88rem; color: var(--gray-800); margin-bottom: .15rem; }
    .checkout-toggle-row .info span { font-size: .78rem; color: var(--gray-400); }

    /* ============ BUTTONS ============ */
    .btn {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        padding: .65rem 1.25rem;
        border-radius: var(--radius-sm);
        font-family: 'Cairo', sans-serif;
        font-size: .875rem;
        font-weight: 700;
        border: none;
        cursor: pointer;
        text-decoration: none;
        transition: all .18s;
        white-space: nowrap;
    }
    .btn-primary  { background: var(--blue-primary); color: #fff; }
    .btn-primary:hover  { background: var(--blue-dark); transform: translateY(-1px); }
    .btn-warning  { background: #d97706; color: #fff; }
    .btn-warning:hover  { background: #b45309; }
    .btn-secondary { background: var(--gray-200); color: var(--gray-800); }
    .btn-secondary:hover { background: var(--gray-200); }
    .btn-danger   { background: var(--red-danger); color: #fff; }
    .btn-danger:hover   { background: #b91c1c; }
    .btn-success  { background: var(--green-ok); color: #fff; }
    .btn-success:hover  { background: #15803d; }
    .btn-outline  { background: transparent; color: var(--blue-primary); border: 1.5px solid var(--blue-primary); }
    .btn-outline:hover  { background: #dbeafe; }

    /* ============ EMPTY STATE ============ */
    .empty-state {
        text-align: center;
        padding: 4rem 2rem;
    }
    .empty-state-icon {
        width: 80px;
        height: 80px;
        background: var(--gray-100);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 2rem;
        color: var(--gray-400);
    }
    .empty-state h3 { font-size: 1.15rem; font-weight: 700; color: var(--gray-800); margin: 0 0 .5rem; }
    .empty-state p { font-size: .875rem; color: var(--gray-600); margin: 0 0 1.5rem; }

    /* ============ MODAL ============ */
    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.55);
        z-index: 2000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
        backdrop-filter: blur(2px);
    }
    .modal-overlay.open { display: flex; }
    .modal-box {
        background: #fff;
        border-radius: var(--radius);
        width: 100%;
        max-width: 480px;
        box-shadow: 0 20px 60px rgba(0,0,0,.2);
        animation: modalIn .25s ease;
    }
    @keyframes modalIn { from{ opacity:0; transform:scale(.96) translateY(10px); } to{ opacity:1; transform:scale(1) translateY(0); } }
    .modal-header {
        padding: 1.25rem 1.5rem;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    .modal-header h3 { font-size: 1rem; font-weight: 800; color: var(--gray-800); margin: 0; display: flex; align-items: center; gap: .5rem; }
    .modal-close { background: none; border: none; font-size: 1.25rem; cursor: pointer; color: var(--gray-400); line-height: 1; border-radius: 6px; padding: .25rem; }
    .modal-close:hover { color: var(--gray-800); background: var(--gray-100); }
    .modal-body { padding: 1.5rem; }
    .modal-footer { padding: 1rem 1.5rem; border-top: 1px solid var(--gray-200); display: flex; gap: .75rem; justify-content: flex-end; }

    /* ============ ALERTS ============ */
    .alert {
        display: flex;
        align-items: flex-start;
        gap: .75rem;
        padding: .9rem 1.1rem;
        border-radius: var(--radius-sm);
        font-size: .875rem;
        margin-bottom: 1rem;
        font-weight: 600;
    }
    .alert-success { background: var(--green-light); color: #166534; border: 1px solid #86efac; }
    .alert-danger  { background: var(--red-light);   color: #991b1b; border: 1px solid #fca5a5; }

    /* ============ RESPONSIVE ============ */
    @media (max-width: 900px) {
        .kpi-grid { grid-template-columns: 1fr 1fr; }
        .form-row { grid-template-columns: 1fr; }
        .accounts-grid { grid-template-columns: 1fr; padding: 1rem; }
    }
    @media (max-width: 600px) {
        .kpi-grid { grid-template-columns: 1fr; }
        .page-hero { padding: 1.5rem; }
        .page-hero-title { font-size: 1.35rem; }
        .filter-bar { flex-direction: column; align-items: stretch; }
        .ac-footer { flex-wrap: wrap; }
        .form-body { padding: 1rem; }
    }
</style>

<!-- Toast Container -->
<div id="toast-container"></div>

<div class="container-fluid py-4 px-3 px-md-4" dir="rtl">

    <!-- ===== PAGE HERO ===== -->
    <div class="page-hero">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h1 class="page-hero-title"><i class="fas fa-university me-2"></i>الحسابات البنكية</h1>
                <p class="page-hero-subtitle">إدارة الحسابات البنكية المتاحة لتحويلات العملاء والتحكم في ظهورها أثناء الدفع.</p>
                <div class="hero-meta">
                    <span class="hero-badge total"><i class="fas fa-layer-group"></i> الإجمالي: <?php echo $total_count; ?></span>
                    <span class="hero-badge visible"><i class="fas fa-eye"></i> ظاهر للعملاء: <?php echo $visible_count; ?></span>
                    <span class="hero-badge hidden"><i class="fas fa-eye-slash"></i> مخفي: <?php echo $hidden_count; ?></span>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="transfer.php" class="btn btn-outline" style="color:#94a3b8;border-color:#475569;">
                    <i class="fas fa-exchange-alt"></i> تحويل بين الحسابات
                </a>
                <button class="btn btn-primary" onclick="openAddModal()">
                    <i class="fas fa-plus"></i> إضافة حساب بنكي
                </button>
            </div>
        </div>
    </div>

    <!-- ===== FLASH MESSAGES ===== -->
    <?php if ($success_message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error_message); ?></div>
    <?php endif; ?>

    <!-- ===== KPI CARDS ===== -->
    <div class="kpi-grid">
        <div class="kpi-card">
            <div class="kpi-icon blue"><i class="fas fa-university"></i></div>
            <div>
                <div class="kpi-label">إجمالي الحسابات</div>
                <div class="kpi-value"><?php echo $total_count; ?></div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon green"><i class="fas fa-eye"></i></div>
            <div>
                <div class="kpi-label">ظاهر للعملاء في الدفع</div>
                <div class="kpi-value"><?php echo $visible_count; ?></div>
            </div>
        </div>
        <div class="kpi-card">
            <div class="kpi-icon gray"><i class="fas fa-eye-slash"></i></div>
            <div>
                <div class="kpi-label">مخفي عن العملاء</div>
                <div class="kpi-value"><?php echo $hidden_count; ?></div>
            </div>
        </div>
    </div>

    <!-- ===== ACCOUNTS LIST ===== -->
    <div class="section-card">
        <div class="section-header">
            <h2 class="section-title"><i class="fas fa-list-ul"></i> قائمة الحسابات البنكية</h2>
        </div>

        <!-- Filter Bar -->
        <div class="filter-bar">
            <div class="search-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="search-input" class="search-input" placeholder="ابحث باسم البنك، صاحب الحساب، رقم الحساب، IBAN..." oninput="filterAccounts()">
            </div>
            <div class="filter-tabs">
                <button class="ftab active" onclick="setFilter('all', this)">الكل (<?php echo $total_count; ?>)</button>
                <button class="ftab" onclick="setFilter('visible', this)">ظاهر (<?php echo $visible_count; ?>)</button>
                <button class="ftab" onclick="setFilter('hidden', this)">مخفي (<?php echo $hidden_count; ?>)</button>
            </div>
        </div>

        <!-- Accounts Grid -->
        <?php if (empty($accounts)): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fas fa-university"></i></div>
                <h3>لا توجد حسابات بنكية</h3>
                <p>أضف حساباً بنكياً لاستخدامه في خيارات التحويل البنكي للعملاء.</p>
                <button class="btn btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> إضافة حساب بنكي</button>
            </div>
        <?php else: ?>
        <div class="accounts-grid" id="accounts-grid">
            <?php foreach ($accounts as $account): ?>
            <?php
                $is_checkout_visible = ($account['show_in_checkout'] == 1 && $account['is_active'] == 1);
                $card_class = !$is_checkout_visible ? 'is-hidden-checkout' : '';
                $data_name = strtolower($account['bank_name'] . ' ' . $account['account_holder_name'] . ' ' . $account['account_name'] . ' ' . $account['account_number'] . ' ' . $account['iban']);
                $data_filter = $is_checkout_visible ? 'visible' : 'hidden';
            ?>
            <div class="account-card <?php echo $card_class; ?>"
                 data-search="<?php echo htmlspecialchars($data_name); ?>"
                 data-filter="<?php echo $data_filter; ?>"
                 id="card-<?php echo $account['id']; ?>">

                <!-- Badge bar -->
                <div class="ac-badge-bar">
                    <div class="ac-status-dot <?php echo $is_checkout_visible ? 'on' : 'off'; ?>" id="dot-<?php echo $account['id']; ?>"></div>
                    <span class="badge <?php echo $is_checkout_visible ? 'checkout-on' : 'checkout-off'; ?>" id="checkout-badge-<?php echo $account['id']; ?>">
                        <i class="fas fa-<?php echo $is_checkout_visible ? 'eye' : 'eye-slash'; ?>"></i>
                        <?php echo $is_checkout_visible ? 'ظاهر في صفحة الدفع' : 'مخفي عن الدفع'; ?>
                    </span>
                    <?php if ($account['is_active']): ?>
                        <span class="badge active ms-auto"><i class="fas fa-circle" style="font-size:.5rem"></i> مفعّل</span>
                    <?php else: ?>
                        <span class="badge inactive ms-auto"><i class="fas fa-ban" style="font-size:.65rem"></i> معطّل</span>
                    <?php endif; ?>
                </div>

                <!-- Card Body -->
                <div class="ac-body">
                    <h3 class="ac-bank-name"><i class="fas fa-university"></i> <?php echo htmlspecialchars($account['bank_name']); ?></h3>
                    <p class="ac-holder"><i class="fas fa-user-tie" style="color:#9ca3af;font-size:.8rem;margin-left:.3rem"></i><?php echo htmlspecialchars($account['account_holder_name']); ?></p>

                    <!-- Account Number -->
                    <div class="ac-field">
                        <div class="ac-field-label">رقم الحساب</div>
                        <div class="ac-field-value">
                            <span class="number" id="acnum-<?php echo $account['id']; ?>"><?php echo htmlspecialchars($account['account_number']); ?></span>
                            <button class="copy-btn" onclick="copyText('acnum-<?php echo $account['id']; ?>', this)" title="نسخ رقم الحساب"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>

                    <!-- IBAN -->
                    <?php if (!empty($account['iban'])): ?>
                    <div class="ac-field">
                        <div class="ac-field-label">IBAN</div>
                        <div class="ac-field-value">
                            <span class="number" id="iban-<?php echo $account['id']; ?>"><?php echo htmlspecialchars($account['iban']); ?></span>
                            <button class="copy-btn" onclick="copyText('iban-<?php echo $account['id']; ?>', this)" title="نسخ IBAN"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Currency & Balance -->
                    <?php if (!empty($account['currency'])): ?>
                    <div class="d-flex align-items-center gap-3 mt-2">
                        <span style="font-size:.75rem;color:var(--gray-400);font-weight:600;">
                            <i class="fas fa-coins" style="margin-left:.3rem"></i><?php echo htmlspecialchars($account['currency']); ?>
                        </span>
                        <span style="font-size:.75rem;color:var(--gray-400);font-weight:600;">
                            <i class="fas fa-wallet" style="margin-left:.3rem"></i>
                            الرصيد: <?php echo number_format($account['current_balance'], 2); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Card Footer: Toggle + Actions -->
                <div class="ac-footer">
                    <!-- Checkout visibility toggle -->
                    <div class="toggle-wrap">
                        <span class="toggle-label">صفحة الدفع</span>
                        <label class="toggle-switch" id="toggle-sw-<?php echo $account['id']; ?>">
                            <input type="checkbox"
                                   <?php echo ($account['show_in_checkout'] == 1) ? 'checked' : ''; ?>
                                   onchange="toggleCheckoutVisibility(<?php echo $account['id']; ?>, this)"
                                   <?php echo ($account['is_active'] == 0) ? 'disabled title="فعّل الحساب أولاً"' : ''; ?>>
                            <span class="toggle-track"></span>
                        </label>
                        <span class="toggle-state-text <?php echo $is_checkout_visible ? 'on' : 'off'; ?>" id="toggle-text-<?php echo $account['id']; ?>">
                            <?php echo $is_checkout_visible ? '✓ ظاهر' : '○ مخفي'; ?>
                        </span>
                    </div>

                    <!-- Action buttons -->
                    <div class="ac-actions">
                        <button class="btn-icon deposit"
                                onclick="openDepositModal(<?php echo $account['id']; ?>, '<?php echo htmlspecialchars($account['bank_name'] . ' - ' . $account['account_name'], ENT_QUOTES); ?>')"
                                title="إيداع مبلغ">
                            <i class="fas fa-money-bill-wave"></i>
                        </button>
                        <button class="btn-icon edit"
                                onclick="openEditModal(<?php echo htmlspecialchars(json_encode($account), ENT_QUOTES); ?>)"
                                title="تعديل">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="bank_accounts.php?action=toggle_status&id=<?php echo $account['id']; ?>"
                           class="btn-icon toggle-active"
                           title="<?php echo $account['is_active'] ? 'إلغاء التفعيل' : 'تفعيل'; ?>"
                           onclick="return confirm('<?php echo $account['is_active'] ? 'إلغاء تفعيل هذا الحساب؟' : 'تفعيل هذا الحساب؟'; ?>')">
                            <i class="fas fa-<?php echo $account['is_active'] ? 'pause-circle' : 'play-circle'; ?>"></i>
                        </a>
                        <button class="btn-icon delete"
                                onclick="openDeleteModal(<?php echo $account['id']; ?>, '<?php echo htmlspecialchars($account['bank_name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($account['account_number'], ENT_QUOTES); ?>')"
                                title="حذف">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- No results state -->
        <div id="no-results" class="empty-state" style="display:none;">
            <div class="empty-state-icon"><i class="fas fa-search"></i></div>
            <h3>لا توجد نتائج</h3>
            <p>لا يوجد حساب مطابق لبحثك الحالي.</p>
        </div>
        <?php endif; ?>
    </div>

</div><!-- /container -->

<!-- =============================================
     MODAL: ADD ACCOUNT
     ============================================= -->
<div class="modal-overlay" id="modal-add">
    <div class="modal-box">
        <form method="POST" action="bank_accounts.php" id="form-add">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="modal-header">
                <h3><i class="fas fa-plus-circle" style="color:var(--blue-primary)"></i> إضافة حساب بنكي جديد</h3>
                <button type="button" class="modal-close" onclick="closeModal('modal-add')">&times;</button>
            </div>
            <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                <!-- معلومات البنك -->
                <p class="form-section-title">معلومات البنك</p>
                <div class="form-row">
                    <div class="form-group">
                        <label for="add_bank_name"><span class="req">*</span> اسم البنك / المحفظة</label>
                        <input type="text" id="add_bank_name" name="bank_name" class="form-control" placeholder="مثال: بنك الراجحي" required>
                    </div>
                    <div class="form-group">
                        <label for="add_account_name"><span class="req">*</span> اسم الحساب</label>
                        <input type="text" id="add_account_name" name="account_name" class="form-control" placeholder="مثال: الحساب الرئيسي" required>
                    </div>
                </div>
                <!-- معلومات الحساب -->
                <p class="form-section-title" style="margin-top:1.25rem">معلومات الحساب</p>
                <div class="form-row">
                    <div class="form-group">
                        <label for="add_account_holder_name"><span class="req">*</span> اسم صاحب الحساب</label>
                        <input type="text" id="add_account_holder_name" name="account_holder_name" class="form-control" placeholder="الاسم كما في البنك" required>
                    </div>
                    <div class="form-group">
                        <label for="add_account_number"><span class="req">*</span> رقم الحساب</label>
                        <input type="text" id="add_account_number" name="account_number" class="form-control" placeholder="XXXX-XXXX-XXXX" required dir="ltr">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="add_iban">رقم IBAN (اختياري)</label>
                        <input type="text" id="add_iban" name="iban" class="form-control" placeholder="SA00 0000 0000 0000 0000 00" dir="ltr">
                    </div>
                    <div class="form-group">
                        <label for="add_initial_balance">الرصيد الافتتاحي</label>
                        <input type="number" step="0.01" min="0" id="add_initial_balance" name="initial_balance" class="form-control" value="0" dir="ltr">
                    </div>
                </div>
                <!-- إعدادات العرض -->
                <p class="form-section-title" style="margin-top:1.25rem">إعدادات العرض في صفحة الدفع</p>
                <div class="checkout-toggle-row">
                    <div class="info">
                        <strong>عرض في صفحة الدفع</strong>
                        <span>عند التفعيل، يظهر هذا الحساب للعملاء في صفحة إتمام الطلب</span>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="show_in_checkout" id="add_show_in_checkout" checked>
                        <span class="toggle-track"></span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-add')"><i class="fas fa-times"></i> إلغاء</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> إضافة الحساب</button>
            </div>
        </form>
    </div>
</div>

<!-- =============================================
     MODAL: EDIT ACCOUNT
     ============================================= -->
<div class="modal-overlay" id="modal-edit">
    <div class="modal-box">
        <form method="POST" action="bank_accounts.php" id="form-edit">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="id" id="edit_id">
            <div class="modal-header">
                <h3><i class="fas fa-edit" style="color:var(--amber)"></i> تعديل الحساب البنكي</h3>
                <button type="button" class="modal-close" onclick="closeModal('modal-edit')">&times;</button>
            </div>
            <div class="modal-body" style="max-height:70vh;overflow-y:auto;">
                <p class="form-section-title">معلومات البنك</p>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_bank_name"><span class="req">*</span> اسم البنك / المحفظة</label>
                        <input type="text" id="edit_bank_name" name="bank_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_account_name"><span class="req">*</span> اسم الحساب</label>
                        <input type="text" id="edit_account_name" name="account_name" class="form-control" required>
                    </div>
                </div>
                <p class="form-section-title" style="margin-top:1.25rem">معلومات الحساب</p>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_account_holder_name"><span class="req">*</span> اسم صاحب الحساب</label>
                        <input type="text" id="edit_account_holder_name" name="account_holder_name" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit_account_number"><span class="req">*</span> رقم الحساب</label>
                        <input type="text" id="edit_account_number" name="account_number" class="form-control" required dir="ltr">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label for="edit_iban">رقم IBAN (اختياري)</label>
                        <input type="text" id="edit_iban" name="iban" class="form-control" dir="ltr">
                    </div>
                    <div class="form-group">
                        <label>الرصيد الحالي (للعرض فقط)</label>
                        <input type="text" id="edit_current_balance" class="form-control" readonly style="background:#f9fafb;color:#6b7280;" dir="ltr">
                    </div>
                </div>
                <p class="form-section-title" style="margin-top:1.25rem">إعدادات العرض في صفحة الدفع</p>
                <div class="checkout-toggle-row">
                    <div class="info">
                        <strong>عرض في صفحة الدفع</strong>
                        <span>عند التفعيل، يظهر هذا الحساب للعملاء في صفحة إتمام الطلب</span>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" name="show_in_checkout" id="edit_show_in_checkout">
                        <span class="toggle-track"></span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-edit')"><i class="fas fa-times"></i> إلغاء</button>
                <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> حفظ التغييرات</button>
            </div>
        </form>
    </div>
</div>

<!-- =============================================
     MODAL: DEPOSIT
     ============================================= -->
<div class="modal-overlay" id="modal-deposit">
    <div class="modal-box">
        <form method="POST" action="bank_accounts.php">
            <input type="hidden" name="action" value="deposit">
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <input type="hidden" name="account_id_deposit" id="deposit_account_id">
            <div class="modal-header">
                <h3><i class="fas fa-money-bill-wave" style="color:var(--green-ok)"></i> إيداع مبلغ مالي</h3>
                <button type="button" class="modal-close" onclick="closeModal('modal-deposit')">&times;</button>
            </div>
            <div class="modal-body">
                <p style="font-size:.875rem;color:var(--gray-600);margin-bottom:1.25rem;">
                    <i class="fas fa-university" style="color:var(--blue-primary);margin-left:.4rem"></i>
                    الحساب: <strong id="deposit_account_name" style="color:var(--gray-800)"></strong>
                </p>
                <div class="form-group" style="margin-bottom:1rem">
                    <label for="deposit_amount"><span class="req">*</span> مبلغ الإيداع</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" name="deposit_amount" id="deposit_amount" required dir="ltr" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label for="deposit_description">الوصف (اختياري)</label>
                    <textarea class="form-control" name="deposit_description" id="deposit_description" rows="2" placeholder="مثال: إيداع من العميل فلان"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-deposit')"><i class="fas fa-times"></i> إلغاء</button>
                <button type="submit" class="btn btn-success"><i class="fas fa-check"></i> تأكيد الإيداع</button>
            </div>
        </form>
    </div>
</div>

<!-- =============================================
     MODAL: DELETE CONFIRM
     ============================================= -->
<div class="modal-overlay" id="modal-delete">
    <div class="modal-box" style="max-width:420px">
        <div class="modal-header">
            <h3><i class="fas fa-trash" style="color:var(--red-danger)"></i> حذف الحساب البنكي</h3>
            <button type="button" class="modal-close" onclick="closeModal('modal-delete')">&times;</button>
        </div>
        <div class="modal-body">
            <div style="text-align:center;padding:.5rem 0">
                <div style="width:56px;height:56px;background:#fee2e2;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 1rem;font-size:1.5rem;color:#dc2626">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <p style="font-size:.9rem;color:var(--gray-800);font-weight:700;margin-bottom:.5rem">هل أنت متأكد من حذف هذا الحساب؟</p>
                <div style="background:var(--gray-50);border:1px solid var(--gray-200);border-radius:8px;padding:.75rem;margin-bottom:.75rem">
                    <p style="margin:0;font-size:.85rem;font-weight:800;color:var(--gray-800)" id="delete-bank-name"></p>
                    <p style="margin:.25rem 0 0;font-size:.78rem;color:var(--gray-600);direction:ltr;text-align:left" id="delete-account-num"></p>
                </div>
                <p style="font-size:.78rem;color:var(--gray-400)">لا يمكن التراجع عن هذه العملية. إذا كان الحساب مرتبطاً بسجلات دفع فلن يتم حذفه.</p>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('modal-delete')">إلغاء</button>
            <a href="#" id="delete-confirm-link" class="btn btn-danger"><i class="fas fa-trash"></i> حذف الحساب</a>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = <?php echo json_encode($csrf_token); ?>;

/* ===== TOAST ===== */
function showToast(message, type = 'success') {
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    const icon = type === 'success' ? 'check-circle' : type === 'error' ? 'times-circle' : 'info-circle';
    toast.innerHTML = `<i class="fas fa-${icon}"></i> ${message}`;
    container.appendChild(toast);
    setTimeout(() => {
        toast.style.animation = 'toastOut .25s ease forwards';
        setTimeout(() => toast.remove(), 250);
    }, 3500);
}

/* ===== MODALS ===== */
function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
}
// Close on overlay click
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});
// Close on Escape
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => closeModal(m.id));
    }
});

function openAddModal() { openModal('modal-add'); }

function openEditModal(account) {
    document.getElementById('edit_id').value = account.id;
    document.getElementById('edit_bank_name').value = account.bank_name || '';
    document.getElementById('edit_account_name').value = account.account_name || '';
    document.getElementById('edit_account_holder_name').value = account.account_holder_name || '';
    document.getElementById('edit_account_number').value = account.account_number || '';
    document.getElementById('edit_iban').value = account.iban || '';
    document.getElementById('edit_current_balance').value = account.current_balance ? parseFloat(account.current_balance).toFixed(2) : '0.00';
    document.getElementById('edit_show_in_checkout').checked = account.show_in_checkout == 1;
    openModal('modal-edit');
}

function openDepositModal(accountId, accountName) {
    document.getElementById('deposit_account_id').value = accountId;
    document.getElementById('deposit_account_name').textContent = accountName;
    document.getElementById('deposit_amount').value = '';
    document.getElementById('deposit_description').value = '';
    openModal('modal-deposit');
    setTimeout(() => document.getElementById('deposit_amount').focus(), 200);
}

function openDeleteModal(id, bankName, accountNum) {
    document.getElementById('delete-bank-name').textContent = bankName;
    document.getElementById('delete-account-num').textContent = accountNum;
    document.getElementById('delete-confirm-link').href = `bank_accounts.php?action=delete&id=${id}`;
    openModal('modal-delete');
}

/* ===== COPY TO CLIPBOARD ===== */
function copyText(elementId, btn) {
    const text = document.getElementById(elementId).textContent.trim();
    navigator.clipboard.writeText(text).then(() => {
        const origHTML = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i>';
        btn.classList.add('copied');
        showToast('تم النسخ: ' + text.substring(0, 20) + (text.length > 20 ? '...' : ''), 'success');
        setTimeout(() => {
            btn.innerHTML = origHTML;
            btn.classList.remove('copied');
        }, 2000);
    }).catch(() => {
        showToast('فشل النسخ. يرجى المحاولة يدوياً.', 'error');
    });
}

/* ===== CHECKOUT VISIBILITY TOGGLE (AJAX) ===== */
function toggleCheckoutVisibility(accountId, checkbox) {
    const newValue = checkbox.checked ? 1 : 0;
    const sw = document.getElementById(`toggle-sw-${accountId}`);
    const textEl = document.getElementById(`toggle-text-${accountId}`);
    const badgeEl = document.getElementById(`checkout-badge-${accountId}`);
    const dotEl = document.getElementById(`dot-${accountId}`);
    const card = document.getElementById(`card-${accountId}`);

    // Disable during save
    checkbox.disabled = true;
    sw.classList.add('saving');

    const formData = new FormData();
    formData.append('account_id', accountId);
    formData.append('show_in_checkout', newValue);
    formData.append('csrf_token', CSRF_TOKEN);

    fetch('api/toggle_checkout_visibility.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    })
    .then(res => res.json())
    .then(data => {
        sw.classList.remove('saving');
        checkbox.disabled = false;

        if (data.success) {
            const isOn = data.show_in_checkout === 1;

            // Update text
            textEl.textContent = isOn ? '✓ ظاهر' : '○ مخفي';
            textEl.className = `toggle-state-text ${isOn ? 'on' : 'off'}`;

            // Update badge
            badgeEl.className = `badge ${isOn ? 'checkout-on' : 'checkout-off'}`;
            badgeEl.innerHTML = `<i class="fas fa-${isOn ? 'eye' : 'eye-slash'}"></i> ${isOn ? 'ظاهر في صفحة الدفع' : 'مخفي عن الدفع'}`;

            // Update dot
            dotEl.className = `ac-status-dot ${isOn ? 'on' : 'off'}`;

            // Update card class
            if (isOn) {
                card.classList.remove('is-hidden-checkout');
                card.dataset.filter = 'visible';
            } else {
                card.classList.add('is-hidden-checkout');
                card.dataset.filter = 'hidden';
            }

            showToast(data.message, 'success');
        } else {
            // Revert
            checkbox.checked = !newValue;
            showToast(data.message || 'فشل تحديث الحالة', 'error');
        }
    })
    .catch(() => {
        sw.classList.remove('saving');
        checkbox.disabled = false;
        checkbox.checked = !newValue; // Revert on network error
        showToast('خطأ في الاتصال. يرجى المحاولة مرة أخرى.', 'error');
    });
}

/* ===== SEARCH & FILTER ===== */
let currentFilter = 'all';

function setFilter(filter, btn) {
    currentFilter = filter;
    document.querySelectorAll('.ftab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    filterAccounts();
}

function filterAccounts() {
    const query = document.getElementById('search-input').value.trim().toLowerCase();
    const cards = document.querySelectorAll('#accounts-grid .account-card');
    let visible = 0;

    cards.forEach(card => {
        const searchData = card.dataset.search.toLowerCase();
        const filterData = card.dataset.filter; // 'visible' or 'hidden'
        const matchSearch = !query || searchData.includes(query);
        const matchFilter = currentFilter === 'all' || filterData === currentFilter;
        const show = matchSearch && matchFilter;
        card.style.display = show ? '' : 'none';
        if (show) visible++;
    });

    document.getElementById('no-results').style.display = visible === 0 && cards.length > 0 ? 'block' : 'none';
}
</script>

<?php include '../../includes/footer.php'; ?>