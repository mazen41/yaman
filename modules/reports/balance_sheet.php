<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/database.php';
$page_title = 'التقرير المالي اليومي (الميزانية)';

// --- DATA FETCHING AND CALCULATION ---

try {
    $today_date = date('Y-m-d');

    // --- 1. إجمالي المدفوعات النقدية (كل الوقت) ---
    // جميع المبالغ المستلمة نقداً من العملاء
    $customer_cash = (float)$db->query(
        "SELECT COALESCE(SUM(amount), 0) FROM customer_payments WHERE payment_method = 'cash'"
    )->fetchColumn();
    $transfers_in_to_cash = 0; // لا يوجد جدول cash_transactions مستقل
    $daily_cash_balance = $customer_cash;

    // إجمالي المدفوعات بالتحويل البنكي (تُضاف للحسابات البنكية، ليس كاش)
    $transfer_payments = (float)$db->query(
        "SELECT COALESCE(SUM(amount), 0) FROM customer_payments WHERE payment_method = 'transfer'"
    )->fetchColumn();

    // طرق دفع أخرى (شيك، بطاقة ائتمان ...)
    $daily_other_balance = (float)$db->query(
        "SELECT COALESCE(SUM(amount), 0) FROM customer_payments WHERE payment_method NOT IN ('cash', 'transfer')"
    )->fetchColumn();

    // --- 2. أرصدة الحسابات البنكية الحالية (YER) ---
    $daily_bank_balance = (float)$db->query(
        "SELECT COALESCE(SUM(current_balance), 0) FROM bank_accounts WHERE currency = 'YER'"
    )->fetchColumn();

    // أرصدة الحسابات البنكية بالريال السعودي (نعرضها منفصلة)
    $bank_balance_sar = (float)$db->query(
        "SELECT COALESCE(SUM(current_balance), 0) FROM bank_accounts WHERE currency = 'SAR'"
    )->fetchColumn();

    // --- 3. بطاقات الشراء ---
    $prepaid_cards_balance = (float)$db->query(
        "SELECT COALESCE(SUM(balance), 0) FROM purchase_cards WHERE balance > 0"
    )->fetchColumn();

    // --- 4. ذمم العملاء (أصل — مبالغ ستدخل للشركة) ---
    $customer_outstanding_balance = (float)$db->query(
        "SELECT COALESCE(SUM(final_amount - paid_amount), 0) FROM customer_orders WHERE (final_amount - paid_amount) > 0.01"
    )->fetchColumn();

    // --- إجمالي الأصول ---
    $total_assets = $daily_cash_balance + $daily_bank_balance + $daily_other_balance
                  + $prepaid_cards_balance + $customer_outstanding_balance + $bank_balance_sar;

    // --- 5. الالتزامات ---
    $accounts_payable = 0.00;
    $total_liabilities = $accounts_payable;

    // --- 6. حقوق الملكية ---
    $total_equity = $total_assets - $total_liabilities;
    $total_liabilities_and_equity = $total_liabilities + $total_equity;

} catch (PDOException $e) {
    $error_message = "حدث خطأ في استعلام قاعدة البيانات: " . $e->getMessage();
    // Set all financial figures to 0 in case of an error to prevent displaying partial/incorrect data
    $daily_cash_balance = $customer_cash = $transfers_in_to_cash = $daily_bank_balance = $daily_other_balance = $prepaid_cards_balance = 0;
    $total_assets = $accounts_payable = $customer_outstanding_balance = $total_liabilities = 0;
    $total_equity = $total_liabilities_and_equity = 0;
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
</style>

<div class="min-h-screen bg-gray-50 py-6" dir="rtl">
    <div class="max-w-7xl mx-auto px-4">
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-purple-600 to-indigo-700 shadow-xl rounded-2xl mb-8 p-6">
            <div class="flex justify-between items-center">
                <div>
                    <h1 class="text-3xl font-bold text-white flex items-center">
                        <i class="fas fa-chart-line ml-3"></i>
                        <?php echo $page_title; ?>
                        <span class="date-badge"><?php echo date('Y-m-d'); ?></span>
                    </h1>
                    <p class="text-purple-100 mt-2">عرض التدفقات النقدية والأصول والالتزامات</p>
                </div>
                <a href="index.php" class="px-6 py-3 bg-white text-purple-600 rounded-xl hover:bg-purple-50 font-semibold transition">
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

            <!-- ====== LEFT: ASSETS (المقبوضات والأصول) ====== -->
            <div class="bs-section">
                <div class="bs-header">
                    <h2><i class="fas fa-plus-circle text-green-500 ml-2"></i>المقبوضات والأصول</h2>
                </div>
                <div class="bs-content">
                    <div class="bs-item">
                        <span class="bs-item-label">
                            <i class="fas fa-money-bill-wave text-green-500"></i>
                            إجمالي المقبوضات النقدية
                            <small class="bs-how">مجموع كل الدفعات النقدية من جدول دفعات العملاء (طريقة الدفع = نقد) منذ بداية النظام.</small>
                        </span>
                        <span class="bs-item-value text-green-700">
                            <?php echo number_format($daily_cash_balance, 2); ?> ر.ي
                        </span>
                    </div>
                    <div class="bs-item">
                        <span class="bs-item-label">
                            <i class="fas fa-exchange-alt text-teal-500"></i>
                            مدفوعات بالتحويل البنكي
                            <small class="bs-how">مجموع الدفعات بطريقة تحويل من جدول دفعات العملاء. معروض للمعلومية فقط، لأنه داخل أرصدة البنوك أصلاً ولا يُضاف مرة ثانية.</small>
                        </span>
                        <span class="bs-item-value text-teal-700">
                            <?php echo number_format($transfer_payments, 2); ?> ر.ي
                        </span>
                    </div>
                    <div class="bs-item">
                        <span class="bs-item-label">
                            <i class="fas fa-university text-blue-500"></i>
                            أرصدة الحسابات البنكية (ر.ي)
                            <small class="bs-how">الرصيد الحالي للحسابات المسجلة بالريال اليمني (عمود الرصيد الحالي في جدول الحسابات البنكية). حالياً لا يوجد حساب بالريال اليمني، لذلك يظهر صفراً.</small>
                        </span>
                        <span class="bs-item-value text-blue-700">
                            <?php echo number_format($daily_bank_balance, 2); ?> ر.ي
                        </span>
                    </div>
                    <?php if ($bank_balance_sar > 0): ?>
                    <div class="bs-item">
                        <span class="bs-item-label">
                            <i class="fas fa-university text-yellow-500"></i>
                            أرصدة الحسابات البنكية (ر.ي)
                            <small class="bs-how">مجموع الرصيد الحالي لكل الحسابات المسجلة بعملة ر.س في النظام. تُعامل كريال يمني دون تحويل، وتُضاف إلى إجمالي الأصول.</small>
                        </span>
                        <span class="bs-item-value text-yellow-700">
                            <?php echo number_format($bank_balance_sar, 2); ?> ر.ي
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="bs-item">
                        <span class="bs-item-label">
                            <i class="fas fa-wallet text-gray-500"></i>
                            طرق دفع أخرى (شيك، بطاقة...)
                            <small class="bs-how">الدفعات من العملاء بطرق غير النقد والتحويل (شيك، بطاقة، وغيرها)، وأي دفعة لم تُحدَّد لها طريقة دفع.</small>
                        </span>
                        <span class="bs-item-value text-gray-700">
                            <?php echo number_format($daily_other_balance, 2); ?> ر.ي
                        </span>
                    </div>
                    <div class="bs-item">
                        <span class="bs-item-label">
                            <i class="fas fa-credit-card text-indigo-500"></i>
                            أرصدة بطاقات الشراء
                            <small class="bs-how">مجموع الأرصدة المتبقية في بطاقات الشراء المدفوعة مسبقاً، للبطاقات التي رصيدها أكبر من صفر (جدول بطاقات الشراء، عمود الرصيد).</small>
                        </span>
                        <span class="bs-item-value text-indigo-700">
                            <?php echo number_format($prepaid_cards_balance, 2); ?> ر.ي
                        </span>
                    </div>
                    <div class="bs-item">
                        <span class="bs-item-label">
                            <i class="fas fa-user-clock text-orange-500"></i>
                            ذمم العملاء (مبالغ متبقية)
                            <small class="bs-how">الفرق بين المبلغ النهائي لكل طلب وما دفعه العميل عليه (جدول الطلبات)، للطلبات التي بقي عليها أكثر من 0.01. مستحقة للشركة وستدخل عند تحصيلها.</small>
                        </span>
                        <span class="bs-item-value text-orange-700">
                            <?php echo number_format($customer_outstanding_balance, 2); ?> ر.ي
                        </span>
                    </div>
                </div>
                <div class="bs-total assets-total">
                    <span class="bs-total-label text-green-800">
                        <i class="fas fa-equals ml-1"></i> إجمالي الأصول
                    </span>
                    <span class="bs-total-value text-green-800">
                        <?php echo number_format($total_assets, 2); ?> ر.ي
                    </span>
                </div>
            </div>

            <!-- ====== RIGHT: SUMMARY ====== -->
            <div class="bs-section">
                <div class="bs-header">
                    <h2><i class="fas fa-chart-pie text-purple-500 ml-2"></i>ملخص الوضع المالي</h2>
                </div>
                <div class="bs-content">

                    <div class="bs-item">
                        <span class="bs-item-label"><i class="fas fa-money-bill-wave text-green-500"></i>إجمالي المقبوضات النقدية<small class="bs-how">من جدول دفعات العملاء، طريقة الدفع = نقد، منذ بداية النظام.</small></span>
                        <span class="bs-item-value text-green-700"><?php echo number_format($daily_cash_balance, 2); ?> ر.ي</span>
                    </div>
                    <div class="bs-item">
                        <span class="bs-item-label"><i class="fas fa-exchange-alt text-teal-500"></i>مقبوضات التحويل البنكي<small class="bs-how">مجموع الدفعات بطريقة تحويل من جدول دفعات العملاء. داخلة أصلاً في أرصدة البنوك، لذلك لا تُضاف مرة ثانية.</small></span>
                        <span class="bs-item-value text-teal-700"><?php echo number_format($transfer_payments, 2); ?> ر.ي</span>
                    </div>
                    <div class="bs-item">
                        <span class="bs-item-label"><i class="fas fa-university text-blue-500"></i>أرصدة البنوك (ر.ي)</span>
                        <span class="bs-item-value text-blue-700"><?php echo number_format($daily_bank_balance, 2); ?> ر.ي</span>
                    </div>
                    <?php if ($bank_balance_sar > 0): ?>
                    <div class="bs-item">
                        <span class="bs-item-label"><i class="fas fa-university text-yellow-500"></i>أرصدة البنوك (ر.ي)<small class="bs-how">مجموع أرصدة الحسابات المسجلة بعملة ر.س، تُعامل كريال يمني وتُضاف إلى إجمالي الأصول.</small></span>
                        <span class="bs-item-value text-yellow-700"><?php echo number_format($bank_balance_sar, 2); ?> ر.ي</span>
                    </div>
                    <?php endif; ?>
                    <div class="bs-item">
                        <span class="bs-item-label"><i class="fas fa-credit-card text-indigo-500"></i>بطاقات الشراء<small class="bs-how">مجموع الأرصدة المتبقية في بطاقات الشراء المدفوعة مسبقاً (رصيدها أكبر من صفر).</small></span>
                        <span class="bs-item-value text-indigo-700"><?php echo number_format($prepaid_cards_balance, 2); ?> ر.ي</span>
                    </div>
                    <div class="bs-item">
                        <span class="bs-item-label"><i class="fas fa-user-clock text-orange-500"></i>ذمم العملاء (متبقي عليهم)<small class="bs-how">الفرق بين المبلغ النهائي لكل طلب وما دفعه العميل، للطلبات التي بقي عليها مبلغ. مستحقة للشركة.</small></span>
                        <span class="bs-item-value text-orange-700"><?php echo number_format($customer_outstanding_balance, 2); ?> ر.ي</span>
                    </div>
                    <?php if ($daily_other_balance > 0): ?>
                    <div class="bs-item">
                        <span class="bs-item-label"><i class="fas fa-wallet text-gray-500"></i>طرق دفع أخرى<small class="bs-how">الدفعات من العملاء بطرق غير النقد والتحويل (شيك، بطاقة، وغيرها).</small></span>
                        <span class="bs-item-value text-gray-700"><?php echo number_format($daily_other_balance, 2); ?> ر.ي</span>
                    </div>
                    <?php endif; ?>

                </div>
                <div class="bs-total assets-total">
                    <span class="bs-total-label text-green-800"><i class="fas fa-equals ml-1"></i> الإجمالي الكلي</span>
                    <span class="bs-total-value text-green-800"><?php echo number_format($total_assets, 2); ?> ر.ي</span>
                </div>
            </div>

        </div>



        <div class="note-box mt-6">
            <p><i class="fas fa-info-circle"></i> <strong>ملاحظات:</strong></p>
            <ul class="list-disc pr-5 mt-2 space-y-3">
                <li><strong>إجمالي المقبوضات النقدية:</strong> مجموع كل الدفعات النقدية التي استلمناها من العملاء منذ بداية النظام. المصدر: جدول دفعات العملاء (customer_payments) والدفعات التي طريقة دفعها "نقد"، ويشمل الدفعات التي ما زالت بانتظار المراجعة. هذا إجمالي ما دخل نقداً، وليس رصيد الصندوق الحالي، لأنه لا يُخصم منه المصروفات.</li>
                <li><strong>مقبوضات التحويل البنكي:</strong> مجموع الدفعات التي طريقة دفعها "تحويل" من جدول دفعات العملاء. هذه المبالغ دخلت إلى الحسابات البنكية، لذلك هي موجودة أصلاً ضمن أرصدة البنوك، ولا تُضاف مرة ثانية إلى الأصول. يُعرض هذا الرقم للمعلومية فقط.</li>
                <li><strong>أرصدة البنوك (ر.ي):</strong> الرصيد الحالي لكل حساب بنكي كما هو مسجل في جدول الحسابات البنكية (عمود الرصيد الحالي). تُعامل جميع الأرصدة كريال يمني، ويُضاف مجموعها إلى إجمالي الأصول.</li>
                <li><strong>طرق دفع أخرى (شيك، بطاقة...):</strong> مجموع الدفعات من العملاء بأي طريقة غير النقد والتحويل، بالإضافة إلى أي دفعة لم يُحدَّد لها طريقة دفع.</li>
                <li><strong>أرصدة بطاقات الشراء:</strong> مجموع الأرصدة المتبقية في بطاقات الشراء المدفوعة مسبقاً، للبطاقات التي رصيدها أكبر من صفر (جدول بطاقات الشراء، عمود الرصيد). تُعتبر أصلاً لأنها مال دُفع ولم يُستخدم بعد.</li>
                <li><strong>ذمم العملاء:</strong> مجموع المبالغ المتبقية على العملاء من الطلبات، أي الفرق بين المبلغ النهائي لكل طلب وما دفعه العميل عليه (جدول الطلبات: المبلغ النهائي ناقص المبلغ المدفوع)، للطلبات التي بقي عليها أكثر من 0.01. هذه مبالغ مستحقة لنا، وستدخل للشركة عند تحصيلها.</li>
                <li><strong>إجمالي الأصول:</strong> مجموع: النقد + طرق الدفع الأخرى + أرصدة بطاقات الشراء + ذمم العملاء + أرصدة البنوك (تُعامل كريال يمني دون تحويل).</li>
                <li><strong>الالتزامات وحقوق الملكية:</strong> الالتزامات صفر حالياً، لأن النظام لا يسجل ديوناً على الشركة. لذلك تساوي حقوق الملكية إجمالي الأصول.</li>
                <li><strong>كيف تتحقق من الأرقام:</strong> افتح صفحة دفعات العملاء وفلتر حسب طريقة الدفع (نقد) واجمع المبالغ، وصفحة الحسابات البنكية لمجموع الأرصدة، وصفحة بطاقات الشراء لمجموع الأرصدة الموجبة، وصفحة الطلبات لمجموع المبالغ المتبقية. الأرقام تُحسب لحظة فتح هذه الصفحة.</li>
            </ul>
        </div>

    </div>
</div>

<?php include '../../includes/footer.php'; ?>