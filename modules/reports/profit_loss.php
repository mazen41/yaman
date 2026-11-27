<?php
/**
 * Profit & Loss Report (P4, one complete page)
 * All numbers come from includes/financial_calc.php, the same functions used by financial/index.php.
 * Read-only.
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/database.php';
require_once '../../includes/check_permissions.php';
require_once '../../includes/financial_calc.php';

$page_title = 'تقرير الأرباح والخسائر';

// Date filters (validated as Y-m-d)
$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
foreach (['date_from' => 'date_from', 'date_to' => 'date_to'] as $k => $_) {
    $d = DateTime::createFromFormat('!Y-m-d', $$k);
    if (!$d || $d->format('Y-m-d') !== $$k) {
        $$k = $k === 'date_from' ? date('Y-m-01') : date('Y-m-d');
    }
}

$error_message = '';
try {
    $pl = getProfitReport($db, $date_from, $date_to);
    $basket_rows = getBasketProfitRows($db, $date_from, $date_to);
    $coupon_rows = getCouponRows($db, $date_from, $date_to);

    // Approved expenses by category (same status rule as the expenses page)
    $cat_stmt = $db->prepare("
        SELECT ec.category_name, COALESCE(SUM(e.amount), 0) AS category_total
        FROM expenses e
        LEFT JOIN expense_categories ec ON e.category_id = ec.id
        WHERE e.expense_date BETWEEN ? AND ? AND e.status = 'approved'
        GROUP BY e.category_id, ec.category_name
        ORDER BY category_total DESC
    ");
    $cat_stmt->execute([$date_from, $date_to]);
    $expenses_by_category = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = 'حدث خطأ: ' . $e->getMessage();
    $pl = ['revenue' => 0, 'expenses' => 0, 'net_profit' => 0, 'margin' => 0,
           'orders_final_total' => 0, 'discounts' => 0, 'coupon_discounts' => 0,
           'shipping' => 0, 'baskets_profit' => 0, 'basket_cost' => 0, 'net_after_basket_cost' => 0];
    $basket_rows = [];
    $coupon_rows = [];
    $expenses_by_category = [];
}

// Reconciliation: each figure is produced by the shared function, so the pages must agree.
$recon_expenses_page = calcApprovedExpenses($db, $date_from, $date_to); // "معتمد" card on expenses page
$recon_fin_revenue = calcOrdersRevenue($db, $date_from, $date_to);      // financial/index.php revenue
$recon_ok = abs($pl['revenue'] - $recon_fin_revenue) < 0.01 && abs($pl['expenses'] - $recon_expenses_page) < 0.01;

function fmt($n) { return number_format((float)$n, 2); }

include '../../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-6" dir="rtl">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

        <!-- Header -->
        <div class="bg-gradient-to-r from-indigo-500 to-purple-600 text-white rounded-2xl shadow-xl px-5 py-6 sm:px-8 sm:py-8">
            <h1 class="text-2xl sm:text-3xl font-bold flex items-center gap-3">
                <i class="fas fa-chart-line"></i>
                <span><?php echo $page_title; ?></span>
            </h1>
            <p class="mt-2 text-sm sm:text-base text-indigo-100">
                من <span class="font-semibold"><?php echo htmlspecialchars($date_from); ?></span>
                إلى <span class="font-semibold"><?php echo htmlspecialchars($date_to); ?></span>
            </p>
        </div>

        <?php if ($error_message): ?>
        <div class="bg-red-100 border-r-4 border-red-500 text-red-700 p-4 rounded-lg"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Filter -->
        <div class="bg-white rounded-2xl shadow-md p-4 sm:p-6">
            <form method="GET" class="grid grid-cols-1 sm:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">من تاريخ</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-1">إلى تاريخ</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="w-full rounded-lg border-gray-300">
                </div>
                <div>
                    <button type="submit" class="w-full px-4 py-2.5 rounded-lg bg-indigo-600 text-white font-semibold hover:bg-indigo-700">
                        <i class="fas fa-search ml-2"></i> عرض التقرير
                    </button>
                </div>
            </form>
        </div>

        <!-- Main figures -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="bg-white rounded-2xl shadow-md p-5 border-r-4 border-emerald-500">
                <div class="text-sm font-semibold text-gray-600">إجمالي الإيرادات (المدفوع)</div>
                <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo fmt($pl['revenue']); ?> ر.ي</div>
                <p class="text-xs text-gray-500 mt-2">مجموع المبالغ المدفوعة من كل الطلبات في الفترة، بأي حالة.</p>
            </div>
            <div class="bg-white rounded-2xl shadow-md p-5 border-r-4 border-red-500">
                <div class="text-sm font-semibold text-gray-600">إجمالي المصروفات (المعتمدة)</div>
                <div class="text-2xl font-bold text-gray-900 mt-1"><?php echo fmt($pl['expenses']); ?> ر.ي</div>
                <p class="text-xs text-gray-500 mt-2">المصروفات بحالة "معتمد" فقط.</p>
            </div>
            <div class="bg-white rounded-2xl shadow-md p-5 border-r-4 border-blue-500">
                <div class="text-sm font-semibold text-gray-600">صافي الربح / الخسارة</div>
                <div class="text-2xl font-bold mt-1 <?php echo $pl['net_profit'] >= 0 ? 'text-emerald-600' : 'text-red-600'; ?>"><?php echo fmt($pl['net_profit']); ?> ر.ي</div>
                <p class="text-xs text-gray-500 mt-2">= الإيرادات (المدفوع) − المصروفات المعتمدة</p>
                <div class="mt-3 pt-3 border-t">
                    <div class="text-xs font-semibold text-gray-600">الربح بعد تكلفة شراء السلال</div>
                    <div class="text-lg font-bold mt-1 <?php echo $pl['net_after_basket_cost'] >= 0 ? 'text-emerald-600' : 'text-red-600'; ?>"><?php echo fmt($pl['net_after_basket_cost']); ?> ر.ي</div>
                    <p class="text-xs text-gray-500">= صافي الربح − تكلفة شراء السلال (<?php echo fmt($pl['basket_cost']); ?>)</p>
                </div>
            </div>
            <div class="bg-white rounded-2xl shadow-md p-5 border-r-4 border-amber-500">
                <div class="text-sm font-semibold text-gray-600">هامش الربح</div>
                <div class="text-2xl font-bold mt-1 <?php echo $pl['margin'] >= 0 ? 'text-emerald-600' : 'text-red-600'; ?>"><?php echo number_format($pl['margin'], 2); ?>%</div>
                <p class="text-xs text-gray-500 mt-2">صافي الربح ÷ الإيرادات</p>
            </div>
        </div>

        <!-- Info lines (not added to profit) -->
        <div class="bg-white rounded-2xl shadow-md p-5">
            <h3 class="font-bold text-gray-800 mb-3"><i class="fas fa-info-circle text-gray-500 ml-1"></i> معلومات إضافية (لا تُضاف إلى الربح)</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                <div class="flex justify-between border-b py-2"><span>إجمالي الطلبات بعد الخصم</span><span class="font-semibold"><?php echo fmt($pl['orders_final_total']); ?> ر.ي</span></div>
                <div class="flex justify-between border-b py-2"><span>الخصومات (يدوي + إضافي)</span><span class="font-semibold"><?php echo fmt($pl['discounts']); ?> ر.ي</span></div>
                <div class="flex justify-between border-b py-2"><span>خصم الكوبونات المعطى</span><span class="font-semibold"><?php echo fmt($pl['coupon_discounts']); ?> ر.ي</span></div>
                <div class="flex justify-between border-b py-2"><span>إيرادات الشحن (إن وُجدت)</span><span class="font-semibold"><?php echo fmt($pl['shipping']); ?> ر.ي</span></div>
            </div>
        </div>

        <!-- Expenses by category (approved only) -->
        <?php if (!empty($expenses_by_category)): ?>
        <div class="bg-white rounded-2xl shadow-md p-5">
            <h3 class="font-bold text-gray-800 mb-3">المصروفات المعتمدة حسب الفئة</h3>
            <div class="divide-y divide-gray-200">
                <?php foreach ($expenses_by_category as $category): ?>
                <div class="flex justify-between py-2 text-sm">
                    <span class="font-semibold"><?php echo htmlspecialchars($category['category_name'] ?: 'غير مصنف'); ?></span>
                    <span class="font-bold text-red-600"><?php echo fmt($category['category_total']); ?> ر.ي</span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Purchase baskets: sale value minus purchase cost -->
        <div class="bg-white rounded-2xl shadow-md p-5 overflow-x-auto">
            <h3 class="font-bold text-gray-800 mb-1">ربح سلال الشراء</h3>
            <p class="text-xs text-gray-500 mb-3">الربح = قيمة البيع (مجموع الطلبات المرتبطة بالسلة) − تكلفة الشراء</p>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-right">رقم السلة</th>
                        <th class="px-3 py-2 text-right">التاريخ</th>
                        <th class="px-3 py-2 text-right">الحالة</th>
                        <th class="px-3 py-2 text-right">قيمة البيع</th>
                        <th class="px-3 py-2 text-right">تكلفة الشراء</th>
                        <th class="px-3 py-2 text-right">الربح</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($basket_rows)): ?>
                    <tr><td colspan="6" class="px-3 py-4 text-center text-gray-500">لا توجد سلال في هذه الفترة.</td></tr>
                    <?php endif; ?>
                    <?php
                    $tot_sale = 0; $tot_cost = 0; $tot_profit = 0;
                    foreach ($basket_rows as $b):
                        $tot_sale += $b['sale_value']; $tot_cost += $b['purchase_cost']; $tot_profit += $b['profit'];
                    ?>
                    <tr>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($b['basket_code'] ?: ('#' . $b['id'])); ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($b['basket_date']); ?></td>
                        <td class="px-3 py-2"><?php echo htmlspecialchars($b['status']); ?></td>
                        <td class="px-3 py-2"><?php echo fmt($b['sale_value']); ?></td>
                        <td class="px-3 py-2"><?php echo fmt($b['purchase_cost']); ?></td>
                        <td class="px-3 py-2 font-bold <?php echo $b['profit'] >= 0 ? 'text-emerald-600' : 'text-red-600'; ?>"><?php echo fmt($b['profit']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <?php if (!empty($basket_rows)): ?>
                <tfoot class="bg-gray-50 font-bold">
                    <tr>
                        <td colspan="3" class="px-3 py-2">الإجمالي</td>
                        <td class="px-3 py-2"><?php echo fmt($tot_sale); ?></td>
                        <td class="px-3 py-2"><?php echo fmt($tot_cost); ?></td>
                        <td class="px-3 py-2"><?php echo fmt($tot_profit); ?></td>
                    </tr>
                </tfoot>
                <?php endif; ?>
            </table>
        </div>

        <!-- Coupons: discount given and the result after coupons -->
        <div class="bg-white rounded-2xl shadow-md p-5 overflow-x-auto">
            <h3 class="font-bold text-gray-800 mb-1">الكوبونات</h3>
            <p class="text-xs text-gray-500 mb-3">الإيرادات محسوبة بعد الخصم، لذلك صافي الربح يعكس أثر الكوبونات.</p>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50 text-gray-600">
                    <tr>
                        <th class="px-3 py-2 text-right">الكوبون</th>
                        <th class="px-3 py-2 text-right">عدد الطلبات</th>
                        <th class="px-3 py-2 text-right">الخصم المعطى</th>
                        <th class="px-3 py-2 text-right">قيمة الطلبات بعد الكوبون</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php if (empty($coupon_rows)): ?>
                    <tr><td colspan="4" class="px-3 py-4 text-center text-gray-500">لا توجد كوبونات مستخدمة في هذه الفترة.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($coupon_rows as $c): ?>
                    <tr>
                        <td class="px-3 py-2 font-mono"><?php echo htmlspecialchars($c['coupon_code']); ?></td>
                        <td class="px-3 py-2"><?php echo (int)$c['orders_affected']; ?></td>
                        <td class="px-3 py-2"><?php echo fmt($c['discount_given']); ?></td>
                        <td class="px-3 py-2"><?php echo fmt($c['final_after_coupon']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p class="mt-3 text-sm font-semibold">صافي الربح بعد الكوبونات: <span class="<?php echo $pl['net_profit'] >= 0 ? 'text-emerald-600' : 'text-red-600'; ?>"><?php echo fmt($pl['net_profit']); ?> ر.ي</span></p>
        </div>

        <!-- Reconciliation -->
        <div class="bg-white rounded-2xl shadow-md p-5 border-r-4 <?php echo $recon_ok ? 'border-emerald-500' : 'border-red-500'; ?>">
            <h3 class="font-bold text-gray-800 mb-3">
                <?php if ($recon_ok): ?>
                    <i class="fas fa-check-circle text-emerald-600 ml-1"></i> المطابقة: الأرقام متطابقة
                <?php else: ?>
                    <i class="fas fa-times-circle text-red-600 ml-1"></i> المطابقة: يوجد فرق
                <?php endif; ?>
            </h3>
            <table class="min-w-full text-sm">
                <thead class="text-gray-600"><tr><th class="py-1 text-right">البند</th><th class="py-1 text-right">هذه الصفحة</th><th class="py-1 text-right">المصدر الآخر</th><th class="py-1 text-right">الفرق</th></tr></thead>
                <tbody>
                    <tr>
                        <td class="py-1">الإيرادات (المدفوع) ← المالية</td>
                        <td class="py-1"><?php echo fmt($pl['revenue']); ?></td>
                        <td class="py-1"><?php echo fmt($recon_fin_revenue); ?></td>
                        <td class="py-1"><?php echo fmt($pl['revenue'] - $recon_fin_revenue); ?></td>
                    </tr>
                    <tr>
                        <td class="py-1">المصروفات المعتمدة ← صفحة المصروفات</td>
                        <td class="py-1"><?php echo fmt($pl['expenses']); ?></td>
                        <td class="py-1"><?php echo fmt($recon_expenses_page); ?></td>
                        <td class="py-1"><?php echo fmt($pl['expenses'] - $recon_expenses_page); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="pt-2 text-center">
            <a href="index.php" class="inline-flex items-center px-5 py-2.5 rounded-lg bg-gray-700 text-white text-sm font-semibold hover:bg-gray-800">
                <i class="fas fa-arrow-right ml-2"></i> العودة للتقارير
            </a>
        </div>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>
