<?php
// modules/payments/customer_refund.php (P8): pay customers we owe money to.
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}
require_once '../../config/database.php';
require_once '../../includes/customer_refund_helpers.php';
require_once '../../includes/customer_refund_save.php';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];
$user_id = (int)$_SESSION['user_id'];
$error = '';

// ---------- SAVE (POST only) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $post_csrf = $_POST['csrf_token'] ?? '';
    if ($post_csrf === '' || !hash_equals($csrf_token, $post_csrf)) {
        $error = 'انتهت الجلسة أو الطلب غير صالح. أعد المحاولة.';
    } else {
        // One-time form token: a double submit finds it already used.
        $form_token = $_POST['form_token'] ?? '';
        $expected = $_SESSION['cr_form_token'] ?? '';
        unset($_SESSION['cr_form_token']);
        if ($expected === '' || $form_token === '' || !hash_equals($expected, $form_token)) {
            $_SESSION['success_message'] = 'تم حفظ هذه الدفعة مسبقاً أو الطلب مكرر. لم يتم حفظ شيء جديد.';
            header('Location: bank_accounts.php');
            exit();
        }
        try {
            $refund_id = crSavePayout(
                $db,
                (int)($_POST['bank_id'] ?? 0),
                (int)($_POST['customer_id'] ?? 0),
                (float)str_replace(',', '', $_POST['amount'] ?? '0'),
                trim($_POST['note'] ?? ''),
                $user_id
            );
            $_SESSION['success_message'] = "✓ تم دفع المستحقات للعميل. رقم الدفعة #{$refund_id}.";
            header('Location: bank_accounts.php');
            exit();
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

// ---------- which step ----------
$bank_id = (int)($_GET['bank_id'] ?? 0);
$customer_id = (int)($_GET['customer_id'] ?? 0);
$amount_raw = str_replace(',', '', trim($_GET['amount'] ?? ''));
$has_amount = $amount_raw !== '';

$banks = $db->query("SELECT id, account_name, bank_name, current_balance FROM bank_accounts WHERE is_active = 1 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
$bank = null;
foreach ($banks as $b) { if ((int)$b['id'] === $bank_id) $bank = $b; }

$customer = null;
$owed_orders = [];
$total_owed = 0.0;
if ($bank && $customer_id) {
    $st = $db->prepare("SELECT id, name FROM customers WHERE id = ?");
    $st->execute([$customer_id]);
    $customer = $st->fetch(PDO::FETCH_ASSOC);
    $owed_orders = crOwedOrders($db, $customer_id);
    foreach ($owed_orders as $o) $total_owed += (float)$o['owed'];
    $total_owed = round($total_owed, 3);
}

$customers_owed = ($bank && !$customer_id) ? crCustomersOwed($db) : [];

$amount = null;
if ($customer && $has_amount) {
    if (!is_numeric($amount_raw)) {
        $error = 'أدخل مبلغاً صحيحاً.';
    } else {
        $amount = round((float)$amount_raw, 3);
        if ($amount <= 0) {
            $error = 'المبلغ يجب أن يكون أكبر من صفر.';
        } elseif ($amount > $total_owed + 0.0005) {
            $error = 'المبلغ أكبر من المستحق للعميل (' . number_format($total_owed, 3) . ').';
        } elseif ($amount > (float)$bank['current_balance'] + 0.0005) {
            $error = 'المبلغ أكبر من رصيد البنك (' . number_format((float)$bank['current_balance'], 3) . ').';
        }
    }
}

// Form token for the confirm screen (one use).
$form_token = '';
if ($customer && $amount !== null && $error === '') {
    $form_token = bin2hex(random_bytes(16));
    $_SESSION['cr_form_token'] = $form_token;
}

include '../../includes/header.php';
?>
<style>
    /* Same button rules as modules/payments/bank_accounts.php, so the page's buttons match the rest of the app. */
    .btn { display:inline-flex; align-items:center; gap:.45rem; padding:.65rem 1.25rem; border-radius:8px;
           font-family:'Cairo', Arial, sans-serif; font-size:.875rem; font-weight:700; border:none; cursor:pointer;
           text-decoration:none; transition:all .18s; white-space:nowrap; }
    .btn:disabled { opacity:.6; cursor:not-allowed; }
    .btn-sm { padding:.4rem .8rem; font-size:.8rem; }
    .btn-primary { background:#3b82f6; color:#fff; }
    .btn-primary:hover { background:#1d4ed8; }
    .btn-success { background:#16a34a; color:#fff; }
    .btn-success:hover { background:#15803d; }
    .btn-secondary { background:#e5e7eb; color:#1f2937; }
    .btn-secondary:hover { background:#d1d5db; }
</style>
<div class="container" dir="rtl" style="max-width:900px;margin:20px auto;padding:0 16px;">
    <h2 style="margin-bottom:6px;"><i class="fas fa-hand-holding-usd"></i> دفع مستحقات عميل</h2>
    <p style="color:#6b7280;margin-top:0;">ادفع للعملاء المبالغ التي لنا عليهم، بدءاً من الطلب الأقدم.</p>

    <?php if ($error): ?>
        <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;padding:10px 14px;border-radius:8px;margin-bottom:14px;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <?php if (!$bank): ?>
        <!-- STEP 1: choose bank -->
        <div class="card" style="padding:16px;border:1px solid #e5e7eb;border-radius:10px;">
            <h3>الخطوة 1: اختر الحساب البنكي</h3>
            <form method="get">
                <select name="bank_id" class="form-control" required>
                    <option value="">-- اختر --</option>
                    <?php foreach ($banks as $b): ?>
                        <option value="<?php echo (int)$b['id']; ?>">
                            <?php echo htmlspecialchars($b['account_name'] ?: $b['bank_name']); ?>
                            (رصيد: <?php echo number_format((float)$b['current_balance'], 3); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" type="submit" style="margin-top:10px;">التالي</button>
            </form>
        </div>

    <?php elseif (!$customer): ?>
        <!-- STEP 2: choose customer (only customers we owe) -->
        <div class="card" style="padding:16px;border:1px solid #e5e7eb;border-radius:10px;">
            <p>الحساب: <strong><?php echo htmlspecialchars($bank['account_name'] ?: $bank['bank_name']); ?></strong>
               — الرصيد: <strong dir="ltr"><?php echo number_format((float)$bank['current_balance'], 3); ?></strong></p>
            <h3>الخطوة 2: اختر العميل (المستحق له مبالغ فقط)</h3>
            <?php if (!$customers_owed): ?>
                <p>لا يوجد عملاء لهم مبالغ مستحقة.</p>
            <?php else: ?>
                <table style="width:100%;border-collapse:collapse;">
                    <thead><tr style="background:#f3f4f6;">
                        <th style="padding:8px;text-align:right;">العميل</th>
                        <th style="padding:8px;text-align:right;">عدد الطلبات</th>
                        <th style="padding:8px;text-align:right;">المستحق</th>
                        <th></th></tr></thead>
                    <tbody>
                    <?php foreach ($customers_owed as $c): ?>
                        <tr style="border-top:1px solid #e5e7eb;">
                            <td style="padding:8px;"><?php echo htmlspecialchars($c['name'] ?? ('#' . $c['customer_id'])); ?></td>
                            <td style="padding:8px;"><?php echo (int)$c['orders']; ?></td>
                            <td style="padding:8px;" dir="ltr"><?php echo number_format($c['total_owed'], 3); ?></td>
                            <td style="padding:8px;">
                                <a class="btn btn-sm btn-primary" href="?bank_id=<?php echo $bank_id; ?>&customer_id=<?php echo (int)$c['customer_id']; ?>">اختيار</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

    <?php elseif ($amount === null || $error !== ''): ?>
        <!-- STEP 3: amount -->
        <div class="card" style="padding:16px;border:1px solid #e5e7eb;border-radius:10px;">
            <p>العميل: <strong><?php echo htmlspecialchars($customer['name']); ?></strong>
               — إجمالي المستحق: <strong dir="ltr"><?php echo number_format($total_owed, 3); ?></strong></p>
            <table style="width:100%;border-collapse:collapse;margin-bottom:14px;">
                <thead><tr style="background:#f3f4f6;">
                    <th style="padding:6px;text-align:right;">رقم الطلب</th>
                    <th style="padding:6px;text-align:right;">التاريخ</th>
                    <th style="padding:6px;text-align:right;">النهائي</th>
                    <th style="padding:6px;text-align:right;">المدفوع</th>
                    <th style="padding:6px;text-align:right;">المستحق</th></tr></thead>
                <tbody>
                <?php foreach ($owed_orders as $o): ?>
                    <tr style="border-top:1px solid #e5e7eb;">
                        <td style="padding:6px;"><?php echo htmlspecialchars($o['order_number']); ?></td>
                        <td style="padding:6px;"><?php echo htmlspecialchars($o['order_date']); ?></td>
                        <td style="padding:6px;" dir="ltr"><?php echo number_format((float)$o['final_amount'], 3); ?></td>
                        <td style="padding:6px;" dir="ltr"><?php echo number_format((float)$o['paid_amount'], 3); ?></td>
                        <td style="padding:6px;" dir="ltr"><?php echo number_format((float)$o['owed'], 3); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <form method="get">
                <input type="hidden" name="bank_id" value="<?php echo $bank_id; ?>">
                <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                <label>المبلغ المراد دفعه (أقصى حد <span dir="ltr"><?php echo number_format(min($total_owed, (float)$bank['current_balance']), 3); ?></span>)</label>
                <input type="number" name="amount" step="0.001" min="0.001" class="form-control" dir="ltr" required value="<?php echo htmlspecialchars($amount_raw); ?>">
                <button class="btn btn-primary" type="submit" style="margin-top:10px;">مراجعة</button>
            </form>
        </div>

    <?php else: ?>
        <!-- STEP 4: confirm and save -->
        <?php
            $owed_after = round($total_owed - $amount, 3);
            $bank_after = round((float)$bank['current_balance'] - $amount, 3);
        ?>
        <div class="card" style="padding:16px;border:1px solid #e5e7eb;border-radius:10px;">
            <h3>الخطوة 4: تأكيد الدفع</h3>
            <table style="width:100%;border-collapse:collapse;">
                <tr><td style="padding:6px;">العميل</td><td style="padding:6px;"><strong><?php echo htmlspecialchars($customer['name']); ?></strong></td></tr>
                <tr><td style="padding:6px;">الحساب البنكي</td><td style="padding:6px;"><?php echo htmlspecialchars($bank['account_name'] ?: $bank['bank_name']); ?></td></tr>
                <tr><td style="padding:6px;">المبلغ المدفوع</td><td style="padding:6px;" dir="ltr"><strong><?php echo number_format($amount, 3); ?></strong></td></tr>
                <tr><td style="padding:6px;">المستحق قبل الدفع</td><td style="padding:6px;" dir="ltr"><?php echo number_format($total_owed, 3); ?></td></tr>
                <tr><td style="padding:6px;">المستحق بعد الدفع</td><td style="padding:6px;" dir="ltr"><?php echo number_format($owed_after, 3); ?></td></tr>
                <tr><td style="padding:6px;">رصيد البنك بعد الدفع</td><td style="padding:6px;" dir="ltr"><?php echo number_format($bank_after, 3); ?></td></tr>
            </table>
            <form method="post" style="margin-top:14px;">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="form_token" value="<?php echo htmlspecialchars($form_token); ?>">
                <input type="hidden" name="bank_id" value="<?php echo $bank_id; ?>">
                <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                <input type="hidden" name="amount" value="<?php echo htmlspecialchars(number_format($amount, 3, '.', '')); ?>">
                <label>ملاحظة (اختياري)</label>
                <input type="text" name="note" class="form-control" maxlength="255">
                <button class="btn btn-success" type="submit" style="margin-top:10px;">حفظ الدفع</button>
                <a class="btn btn-secondary" href="bank_accounts.php" style="margin-right:8px;">إلغاء</a>
            </form>
        </div>
    <?php endif; ?>
</div>
<?php include '../../includes/footer.php'; ?>
