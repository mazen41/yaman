<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/database.php';
require_once '../../includes/account_helpers.php';
require_once '../../includes/financial_calc.php';

$page_title = 'التقرير المالي (الميزانية)';
$today_date = date('Y-m-d');
$error_message = '';

// Owner rule: cancelled orders are never "due to customers" (they only count as revenue).
// Note: the database currently has no cancelled status, so this rule currently excludes nothing.
$excluded_due_statuses = ['cancelled', 'canceled'];

$daily_cash_balance = 0.0;
$transfer_payments = 0.0;
$daily_bank_balance = 0.0;
$prepaid_cards_balance = 0.0;
$customer_outstanding_balance = 0.0;
$total_assets = 0.0;

$liab_rows = [];
$journal_liabilities = 0.0;
$due_rows = [];
$due_to_customers = 0.0;
$total_liabilities = 0.0;

$eq_rows = [];
$capital = 0.0;
$retained_earnings = 0.0;
$total_equity = 0.0;

$difference = 0.0;
$balanced = true;

try {
    // ---- ASSETS ----
    // Cash receipts, all time (customer payments by method)
    $daily_cash_balance = (float)$db->query(
        "SELECT COALESCE(SUM(amount), 0) FROM customer_payments WHERE payment_method = 'cash'"
    )->fetchColumn();

    // Bank transfers: information only (already inside the bank balances)
    $transfer_payments = (float)$db->query(
        "SELECT COALESCE(SUM(amount), 0) FROM customer_payments WHERE payment_method = 'transfer'"
    )->fetchColumn();

    // Bank balances: ONE line for all bank accounts (P1: all treated as YER)
    $daily_bank_balance = (float)$db->query(
        "SELECT COALESCE(SUM(current_balance), 0) FROM bank_accounts"
    )->fetchColumn();

    // Purchase cards with a positive balance
    $prepaid_cards_balance = (float)$db->query(
        "SELECT COALESCE(SUM(balance), 0) FROM purchase_cards WHERE balance > 0"
    )->fetchColumn();

    // Customer receivables: positive remaining amounts (unchanged rule)
    $customer_outstanding_balance = (float)$db->query(
        "SELECT COALESCE(SUM(final_amount - paid_amount), 0) FROM customer_orders WHERE (final_amount - paid_amount) > 0.01"
    )->fetchColumn();

    $total_assets = $daily_cash_balance + $daily_bank_balance + $prepaid_cards_balance + $customer_outstanding_balance;

    // ---- LIABILITIES ----
    // Every active liability account of the chart: credit - debit from journal lines
    $liab_rows = $db->query("
        SELECT a.id, a.code, a.name,
               COALESCE(SUM(CASE WHEN jei.type = 'credit' THEN jei.amount ELSE -jei.amount END), 0) AS balance
        FROM accounts a
        LEFT JOIN journal_entry_items jei ON jei.account_id = a.id
        WHERE a.type = 'liability' AND a.is_active = 1
        GROUP BY a.id, a.code, a.name
        ORDER BY a.code")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($liab_rows as &$lr) {
        $lr['code'] = normalizeArabicDigits($lr['code']);
        $journal_liabilities += (float)$lr['balance'];
    }
    unset($lr);

    // Due to customers: negative remaining on non-cancelled orders (shown as a positive amount)
    $placeholders = implode(',', array_fill(0, count($excluded_due_statuses), '?'));
    $due_stmt = $db->prepare("
        SELECT o.id, o.order_number, o.invoice_number, o.status, o.order_date,
               o.final_amount, o.paid_amount,
               (o.paid_amount - o.final_amount) AS due_to_customer,
               c.name AS customer_name
        FROM customer_orders o
        LEFT JOIN customers c ON c.id = o.customer_id
        WHERE (o.final_amount - o.paid_amount) < -0.01
          AND o.status NOT IN ($placeholders)
        ORDER BY due_to_customer DESC");
    $due_stmt->execute($excluded_due_statuses);
    $due_rows = $due_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($due_rows as $d) {
        $due_to_customers += (float)$d['due_to_customer'];
    }

    $total_liabilities = $journal_liabilities + $due_to_customers;

    // ---- EQUITY ----
    // Every active equity account of the chart: credit - debit from journal lines
    $eq_rows = $db->query("
        SELECT a.id, a.code, a.name,
               COALESCE(SUM(CASE WHEN jei.type = 'credit' THEN jei.amount ELSE -jei.amount END), 0) AS balance
        FROM accounts a
        LEFT JOIN journal_entry_items jei ON jei.account_id = a.id
        WHERE a.type = 'equity' AND a.is_active = 1
        GROUP BY a.id, a.code, a.name
        ORDER BY a.code")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($eq_rows as &$er) {
        $er['code'] = normalizeArabicDigits($er['code']);
        $capital += (float)$er['balance'];
    }
    unset($er);

    // Retained earnings = net profit from the start of the system to today (P4 definition, after basket cost)
    $pl = getProfitReport($db, '2000-01-01', $today_date);
    $retained_earnings = (float)$pl['net_after_basket_cost'];

    $total_equity = $capital + $retained_earnings;

    // ---- CHECK: assets must equal liabilities + equity. Never forced: the difference is shown.
    $difference = $total_assets - ($total_liabilities + $total_equity);
    $balanced = abs($difference) < 0.01;

} catch (PDOException $e) {
    $error_message = "حدث خطأ في استعلام قاعدة البيانات: " . $e->getMessage();
}

include '../../includes/header.php';
?>

<style>
    .balance-sheet-container {
        display: grid;
        grid-template-columns: 1fr;
        gap: 2rem;
    }
    @media (min-width: 1024px) {
        .balance-sheet-container {
            grid-template-columns: 1fr 1fr;
        }
    }
    .bs-section {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        display: flex;
        flex-direction: column;
    }
    .bs-header {
        padding: 1rem 1.5rem;
        border-bottom: 2px solid #f3f4f6;
    }
    .bs-header h2 {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1f2937;
    }
    .bs-content {
        padding: 1.5rem;
        flex: 1;
    }
    .bs-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1rem 0;
        border-bottom: 1px solid #e5e7eb;
    }
    .bs-item:last-child {
        border-bottom: none;
    }
    .bs-item-label {
        font-weight: 600;
        color: #4b5563;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .bs-item-value {
        font-weight: 700;
        font-family: monospace;
        font-size: 1rem;
        color: #111827;
    }
    .bs-total {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 1.25rem 1.5rem;
        margin-top: auto;
        border-top: 3px solid #e5e7eb;
        background-color: #f9fafb;
        border-radius: 0 0 12px 12px;
    }
    .bs-total-label {
        font-size: 1.1rem;
        font-weight: 800;
    }
    .bs-total-value {
        font-size: 1.25rem;
        font-weight: 800;
    }
    /* Highlight matching totals */
    .bs-total.assets-total {
        background-color: #f0fdf4;
        border-top-color: #16a34a;
    }
    .bs-total.equity-total {
        background-color: #f0fdf4;
        border-top-color: #16a34a;
    }
    .bs-how {
        display: block;
        font-size: 0.8rem;
        font-weight: 400;
        color: #6b7280;
        line-height: 1.6;
        margin-top: 0.25rem;
    }
    .note-box {
        background-color: #fffbeb;
        border: 1px solid #fde68a;
        color: #b45309;
        padding: 1rem;
        border-radius: 8px;
        margin-top: 1rem;
        font-size: 0.9rem;
        line-height: 1.6;
    }
    .date-badge {
        background: rgba(255,255,255,0.2);
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.9rem;
        margin-right: 10px;
    }
    .balance-check-badge {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.5rem 1rem;
        border-radius: 9999px;
        font-weight: 700;
        font-size: 0.9rem;
        margin-top: 1rem;
    }
    /* ===== Phone layout ===== */
    .bs-section, .bs-item-label, .bs-item-value { min-width: 0; }
    .bs-item-value { direction: ltr; text-align: right; overflow-wrap: anywhere; }
    @media (max-width: 640px) {
        .bs-page-head { flex-direction: column; align-items: stretch !important; gap: 1rem; padding: 1.25rem !important; }
        .bs-page-title { font-size: 1.35rem !important; line-height: 1.4; flex-wrap: wrap; }
        .bs-page-back { width: 100%; justify-content: center; }
        .balance-sheet-container { gap: 1.25rem; }
        .bs-header { padding: 0.9rem 1rem; }
        .bs-header h2 { font-size: 1.05rem; line-height: 1.4; }
        .bs-content { padding: 0.75rem 1rem; }
        .bs-item { flex-direction: column; align-items: flex-start; gap: 0.35rem; padding: 0.85rem 0; }
        .bs-item-label { font-size: 0.95rem; line-height: 1.5; }
        .bs-item-value { font-size: 1rem; align-self: flex-end; }
        .bs-how { font-size: 0.75rem; }
        .bs-total { flex-direction: column; align-items: flex-start; gap: 0.5rem; padding: 1rem; }
        .bs-total-label { font-size: 1rem; }
        .bs-total-value { font-size: 1.1rem; align-self: flex-end; direction: ltr; overflow-wrap: anywhere; }
        .note-box { font-size: 0.85rem; padding: 0.85rem; }
        .note-box ul { padding-right: 1rem; }
        .balance-check-badge { font-size: 0.8rem; padding: 0.45rem 0.8rem; }
        .date-badge { margin-right: 0; margin-top: 0.4rem; display: inline-block; }
    }
</style>

<div class="min-h-screen bg-gray-50 py-6" dir="rtl">
    <div class="max-w-7xl mx-auto px-4">
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-purple-600 to-indigo-700 shadow-xl rounded-2xl mb-8 p-6">
            <div class="bs-page-head flex justify-between items-center gap-4">
                <div>
                    <h1 class="bs-page-title text-3xl font-bold text-white flex items-center flex-wrap">
                        <i class="fas fa-chart-line ml-3"></i>
                        <?php echo $page_title; ?>
                        <span class="date-badge"><?php echo date('Y-m-d'); ?></span>
                    </h1>
                    <p class="text-purple-100 mt-2">عرض التدفقات النقدية والأصول والالتزامات</p>
                </div>
                <a href="index.php" class="bs-page-back px-6 py-3 bg-white text-purple-600 rounded-xl hover:bg-purple-50 font-semibold transition text-center">
                    <i class="fas fa-arrow-right ml-2"></i>
                    العودة للرئيسية
                </a>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p class="font-bold">خطأ!</p>
                <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
        <?php endif; ?>

        <div class="balance-sheet-container">

            <!-- ====== LEFT: ASSETS ====== -->
            <div class="bs-section">
                <div class="bs-header">
                    <h2><i class="fas fa-plus-circle text-green-500 ml-2"></i>الأصول</h2>
                </div>
                <div class="bs-content">
                    <div class="bs-item">
                        <span class="bs-item-label">
                            <i class="fas fa-money-bill-wave text-green-500"></i>
                            إجمالي المقبوضات النقدية
                            <small class="bs-how">مجموع الدفعات النقدية من جدول دفعات العملاء منذ بداية النظام.</small>
                        </span>
                        <span class="bs-item-value text-green-700"><?php echo number_format($daily_cash_balance, 2); ?> ي.ر</span>
                    </div>
                    <div class="bs-item">
                        <span class="bs-item-label">
                            <i class="fas fa-university text-blue-500"></i>
                            أرصدة الحسابات البنكية
                            <small class="bs-how">مجموع الرصيد الحالي لكل الحسابات البنكية. كلها تُعامل كريال يمني.</small>
                        </span>
                        <span class="bs-item-value text-blue-700"><?php echo number_format($daily_bank_balance, 2); ?> ي.ر</span>
                    </div>
                    <div class="bs-item">
                        <span class="bs-item-label">
                            <i class="fas fa-credit-card text-indigo-500"></i>
                            أرصدة بطاقات الشراء
                            <small class="bs-how">مجموع الأرصدة المتبقية في بطاقات الشراء المدفوعة مسبقاً (رصيدها أكبر من صفر).</small>
                        </span>
                        <span class="bs-item-value text-indigo-700"><?php echo number_format($prepaid_cards_balance, 2); ?> ي.ر</span>
                    </div>
                    <div class="bs-item">
                        <span class="bs-item-label">
                            <i class="fas fa-user-clock text-orange-500"></i>
                            ذمم العملاء (مبالغ مستحقة لنا)
                            <small class="bs-how">الفرق بين المبلغ النهائي لكل طلب وما دفعه العميل، للطلبات التي بقي عليها أكثر من 0.01.</small>
                        </span>
                        <span class="bs-item-value text-orange-700"><?php echo number_format($customer_outstanding_balance, 2); ?> ي.ر</span>
                    </div>
                    <div class="bs-item">
                        <span class="bs-item-label">
                            <i class="fas fa-exchange-alt text-gray-400"></i>
                            مدفوعات بالتحويل (للمعلومية فقط)
                            <small class="bs-how">مجموع الدفعات بالتحويل. داخلة أصلاً في أرصدة البنوك، ولا تُضاف مرة ثانية.</small>
                        </span>
                        <span class="bs-item-value text-gray-500"><?php echo number_format($transfer_payments, 2); ?> ي.ر</span>
                    </div>
                </div>
                <div class="bs-total assets-total">
                    <span class="bs-total-label text-green-800"><i class="fas fa-equals ml-1"></i> إجمالي الأصول</span>
                    <span class="bs-total-value text-green-800"><?php echo number_format($total_assets, 2); ?> ي.ر</span>
                </div>
            </div>

            <!-- ====== RIGHT: LIABILITIES AND EQUITY ====== -->
            <div class="bs-section">
                <div class="bs-header">
                    <h2><i class="fas fa-balance-scale text-purple-500 ml-2"></i>الالتزامات وحقوق الملكية</h2>
                </div>
                <div class="bs-content">

                    <p class="font-bold text-red-700 mb-2">الالتزامات</p>
                    <?php foreach ($liab_rows as $lr): ?>
                    <div class="bs-item">
                        <span class="bs-item-label"><?php echo htmlspecialchars($lr['name']); ?> <small class="text-gray-400">(<?php echo htmlspecialchars($lr['code']); ?>)</small></span>
                        <span class="bs-item-value text-red-700"><?php echo number_format((float)$lr['balance'], 2); ?> ي.ر</span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($liab_rows)): ?>
                    <div class="bs-item"><span class="bs-item-label text-gray-500">لا توجد حسابات التزامات في الدليل.</span></div>
                    <?php endif; ?>
                    <div class="bs-item">
                        <span class="bs-item-label">
                            <i class="fas fa-user-tag text-red-500"></i>
                            مبالغ مستحقة للعملاء
                            <small class="bs-how">مجموع المبالغ المتبقية السالبة على الطلبات غير الملغاة (الشركة مدينة للعميل بها). اضغط على التفاصيل.</small>
                        </span>
                        <span class="bs-item-value text-red-700"><?php echo number_format($due_to_customers, 2); ?> ي.ر</span>
                    </div>
                    <?php if (!empty($due_rows)): ?>
                    <details class="mb-3 text-sm">
                        <summary class="cursor-pointer text-blue-700 font-semibold">تفاصيل المبالغ المستحقة للعملاء (<?php echo count($due_rows); ?> طلب)</summary>
                        <div class="overflow-x-auto mt-2">
                            <table class="w-full text-xs">
                                <thead><tr class="bg-gray-50"><th class="text-right p-1">الطلب</th><th class="text-right p-1">الفاتورة</th><th class="text-right p-1">العميل</th><th class="text-right p-1">الحالة</th><th class="text-right p-1">النهائي</th><th class="text-right p-1">المدفوع</th><th class="text-right p-1">المستحق للعميل</th></tr></thead>
                                <tbody>
                                <?php foreach ($due_rows as $d): ?>
                                    <tr class="border-b">
                                        <td class="p-1"><?php echo htmlspecialchars($d['order_number']); ?></td>
                                        <td class="p-1"><?php echo htmlspecialchars((string)$d['invoice_number']); ?></td>
                                        <td class="p-1"><?php echo htmlspecialchars((string)$d['customer_name']); ?></td>
                                        <td class="p-1"><?php echo htmlspecialchars((string)$d['status']); ?></td>
                                        <td class="p-1" dir="ltr"><?php echo number_format((float)$d['final_amount'], 2); ?></td>
                                        <td class="p-1" dir="ltr"><?php echo number_format((float)$d['paid_amount'], 2); ?></td>
                                        <td class="p-1 font-bold text-red-700" dir="ltr"><?php echo number_format((float)$d['due_to_customer'], 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </details>
                    <?php endif; ?>

                    <div class="bs-total mb-4">
                        <span class="bs-total-label text-red-800">إجمالي الالتزامات</span>
                        <span class="bs-total-value text-red-800"><?php echo number_format($total_liabilities, 2); ?> ي.ر</span>
                    </div>

                    <p class="font-bold text-purple-700 mb-2">حقوق الملكية</p>
                    <?php foreach ($eq_rows as $er): ?>
                    <div class="bs-item">
                        <span class="bs-item-label"><?php echo htmlspecialchars($er['name']); ?> <small class="text-gray-400">(<?php echo htmlspecialchars($er['code']); ?>)</small></span>
                        <span class="bs-item-value text-purple-700"><?php echo number_format((float)$er['balance'], 2); ?> ي.ر</span>
                    </div>
                    <?php endforeach; ?>
                    <?php if (empty($eq_rows)): ?>
                    <div class="bs-item"><span class="bs-item-label text-gray-500">لا يوجد حساب رأس مال في الدليل بعد.</span></div>
                    <?php endif; ?>
                    <div class="bs-item">
                        <span class="bs-item-label">
                            <i class="fas fa-chart-line text-green-500"></i>
                            الأرباح المحتجزة (صافي الربح منذ بداية النظام)
                            <small class="bs-how">الإيرادات المدفوعة − المصروفات المعتمدة − تكلفة السلال، من بداية النظام حتى اليوم.</small>
                        </span>
                        <span class="bs-item-value text-green-700"><?php echo number_format($retained_earnings, 2); ?> ي.ر</span>
                    </div>
                    <div class="bs-total">
                        <span class="bs-total-label text-purple-800">إجمالي حقوق الملكية</span>
                        <span class="bs-total-value text-purple-800"><?php echo number_format($total_equity, 2); ?> ي.ر</span>
                    </div>
                </div>
                <div class="bs-total assets-total">
                    <span class="bs-total-label text-green-800">الالتزامات + حقوق الملكية</span>
                    <span class="bs-total-value text-green-800"><?php echo number_format($total_liabilities + $total_equity, 2); ?> ي.ر</span>
                </div>
            </div>

        </div>

        <!-- ====== CHECK ====== -->
        <div class="mt-6 p-4 rounded-xl border-2 <?php echo $balanced ? 'border-green-400 bg-green-50' : 'border-red-400 bg-red-50'; ?>">
            <?php if ($balanced): ?>
                <p class="font-bold text-green-800"><i class="fas fa-check-circle"></i> الأصول = الالتزامات + حقوق الملكية (متوازن)</p>
            <?php else: ?>
                <p class="font-bold text-red-800"><i class="fas fa-exclamation-triangle"></i> فرق غير مفسّر: <span dir="ltr"><?php echo number_format($difference, 2); ?></span> ي.ر</p>
                <p class="text-sm text-red-700 mt-1">الأصول <span dir="ltr"><?php echo number_format($total_assets, 2); ?></span> لا تساوي الالتزامات + حقوق الملكية. لم يتم تعديل الأرقام لإخفاء الفرق.</p>
            <?php endif; ?>
        </div>

        <div class="note-box mt-6">
            <p><i class="fas fa-info-circle"></i> <strong>ملاحظات:</strong></p>
            <ul class="list-disc pr-5 mt-2 space-y-3">
                <li><strong>الأصول:</strong> النقد من المدفوعات النقدية، وكل أرصدة الحسابات البنكية (سطر واحد، بالريال اليمني)، وبطاقات الشراء، وذمم العملاء المستحقة لنا.</li>
                <li><strong>الالتزامات:</strong> أرصدة حسابات الالتزامات من الدليل (من القيود المحاسبية)، مضافاً إليها المبالغ المستحقة للعملاء: مجموع المتبقي السالب على الطلبات غير الملغاة، وتُعرض كمبلغ موجب.</li>
                <li><strong>حقوق الملكية:</strong> رأس المال من حسابات حقوق الملكية في الدليل، والأرباح المحتجزة = صافي الربح منذ بداية النظام.</li>
                <li><strong>التحقق:</strong> إذا لم تتساوَ الأصول مع الالتزامات + حقوق الملكية يظهر الفرق بوضوح ولا يُخفى.</li>
                <li><strong>كيف تتحقق من الأرقام:</strong> الأرقام تُحسب لحظة فتح الصفحة من جداول المدفوعات والحسابات البنكية وبطاقات الشراء والطلبات والقيود المحاسبية.</li>
            </ul>
        </div>

    </div>
</div>

<?php include '../../includes/footer.php'; ?>
