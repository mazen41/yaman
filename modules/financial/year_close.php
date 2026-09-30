<?php
/**
 * Financial Year Closing
 * Shows the yearly summary and records the year as closed (with a snapshot of the totals).
 */
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/database.php';
require_once '../../includes/check_permissions.php';

$user_id = $_SESSION['user_id'];

if (!hasPermission($user_id, 'financial', 'view')) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية للوصول إلى هذه الصفحة';
    header('Location: ../../index.php');
    exit();
}

$page_title = 'إقفال السنة المالية';
$current_year = (int)date('Y');
$selected_year = (int)($_GET['year'] ?? $current_year);
if ($selected_year < 2000 || $selected_year > $current_year) {
    $selected_year = $current_year;
}
$message = null;
$currency_symbol = 'ر.ي';

// Exchange rates - keep in sync with modules/financial/index.php
$usd_to_yer_rate = 535;
$sar_to_yer_rate = 142;
$base_currency   = 'YER';

// Only admins may actually close a year (the action cannot be undone from this page)
$is_admin = false;
try {
    $admin_stmt = $db->prepare("SELECT is_admin FROM users WHERE id = ?");
    $admin_stmt->execute([$user_id]);
    $is_admin = ((int)$admin_stmt->fetchColumn() === 1);
} catch (PDOException $e) {
    error_log('year_close admin check: ' . $e->getMessage());
}

// Create the table BEFORE any transaction (DDL causes an implicit commit in MySQL)
try {
    $db->exec("CREATE TABLE IF NOT EXISTS fiscal_years (
        id INT AUTO_INCREMENT PRIMARY KEY,
        year INT NOT NULL UNIQUE,
        is_closed TINYINT(1) DEFAULT 0,
        closed_by INT NULL,
        closed_at TIMESTAMP NULL DEFAULT NULL,
        total_orders INT DEFAULT 0,
        total_revenue DECIMAL(15,2) DEFAULT 0,
        total_collected DECIMAL(15,2) DEFAULT 0,
        total_expenses DECIMAL(15,2) DEFAULT 0,
        net_profit DECIMAL(15,2) DEFAULT 0,
        carry_customers INT DEFAULT 0,
        carry_amount DECIMAL(15,2) DEFAULT 0,
        notes TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    error_log('year_close create table: ' . $e->getMessage());
    $message = ['type' => 'error', 'text' => 'تعذر تجهيز جدول السنوات المالية.'];
}

/**
 * Build the summary for one fiscal year.
 * - Orders/revenue/remaining: customer_orders created in the year (cancelled excluded)
 * - Collected: customer_payments received in the year (same basis as the financial page)
 * - Expenses: approved expenses in the year, converted to the base currency
 */
function fy_summary(PDO $db, int $year, float $usd_rate, float $sar_rate, string $base_currency): array
{
    $from = $year . '-01-01';
    $to   = ($year + 1) . '-01-01'; // exclusive upper bound

    $o = $db->prepare("
        SELECT COUNT(*) AS total_orders,
               COALESCE(SUM(final_amount), 0) AS total_revenue,
               COALESCE(SUM(final_amount - paid_amount), 0) AS total_remaining
        FROM customer_orders
        WHERE created_at >= ? AND created_at < ? AND status <> 'cancelled'
    ");
    $o->execute([$from, $to]);
    $orders = $o->fetch(PDO::FETCH_ASSOC);

    $p = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM customer_payments WHERE payment_date >= ? AND payment_date < ?");
    $p->execute([$from, $to]);
    $collected = (float)$p->fetchColumn();

    $e = $db->prepare("
        SELECT COALESCE(SUM(
            CASE
                WHEN currency = :base_currency THEN amount
                WHEN currency = 'USD' THEN amount * :usd_rate
                WHEN currency = 'SAR' THEN amount * :sar_rate
                ELSE amount
            END
        ), 0)
        FROM expenses
        WHERE expense_date >= :from_date AND expense_date < :to_date AND status = 'approved'
    ");
    $e->execute([
        ':base_currency' => $base_currency,
        ':usd_rate'      => $usd_rate,
        ':sar_rate'      => $sar_rate,
        ':from_date'     => $from,
        ':to_date'       => $to,
    ]);
    $expenses = (float)$e->fetchColumn();

    $b = $db->prepare("
        SELECT c.id, c.name, c.phone, SUM(co.final_amount - co.paid_amount) AS remaining
        FROM customers c
        JOIN customer_orders co ON co.customer_id = c.id
        WHERE co.created_at >= ? AND co.created_at < ? AND co.status <> 'cancelled'
        GROUP BY c.id, c.name, c.phone
        HAVING remaining > 0
        ORDER BY remaining DESC
    ");
    $b->execute([$from, $to]);
    $balances = $b->fetchAll(PDO::FETCH_ASSOC);

    return [
        'total_orders'    => (int)$orders['total_orders'],
        'total_revenue'   => (float)$orders['total_revenue'],
        'total_remaining' => (float)$orders['total_remaining'],
        'collected'       => $collected,
        'expenses'        => $expenses,
        'net_profit'      => $collected - $expenses,
        'balances'        => $balances,
        'carry_customers' => count($balances),
        'carry_amount'    => (float)array_sum(array_column($balances, 'remaining')),
    ];
}

// --- Handle POST: close the year ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_close'])) {
    $year_to_close = (int)($_POST['year_to_close'] ?? 0);

    if (!$is_admin) {
        $message = ['type' => 'error', 'text' => 'إقفال السنة المالية متاح للمدير فقط.'];
    } elseif ($year_to_close < 2000 || $year_to_close > $current_year) {
        $message = ['type' => 'error', 'text' => 'السنة المحددة غير صالحة.'];
    } else {
        try {
            $chk = $db->prepare("SELECT COUNT(*) FROM fiscal_years WHERE year = ? AND is_closed = 1");
            $chk->execute([$year_to_close]);
            if ((int)$chk->fetchColumn() > 0) {
                $message = ['type' => 'error', 'text' => 'هذه السنة مغلقة بالفعل.'];
            } else {
                $snap = fy_summary($db, $year_to_close, $usd_to_yer_rate, $sar_to_yer_rate, $base_currency);

                $db->beginTransaction();
                $ins = $db->prepare("
                    INSERT INTO fiscal_years
                        (year, is_closed, closed_by, closed_at, total_orders, total_revenue, total_collected,
                         total_expenses, net_profit, carry_customers, carry_amount)
                    VALUES (?, 1, ?, NOW(), ?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE
                        is_closed = 1, closed_by = VALUES(closed_by), closed_at = NOW(),
                        total_orders = VALUES(total_orders), total_revenue = VALUES(total_revenue),
                        total_collected = VALUES(total_collected), total_expenses = VALUES(total_expenses),
                        net_profit = VALUES(net_profit), carry_customers = VALUES(carry_customers),
                        carry_amount = VALUES(carry_amount)
                ");
                $ins->execute([
                    $year_to_close, $user_id, $snap['total_orders'], $snap['total_revenue'], $snap['collected'],
                    $snap['expenses'], $snap['net_profit'], $snap['carry_customers'], $snap['carry_amount'],
                ]);
                $db->commit();

                $selected_year = $year_to_close;
                $message = ['type' => 'success', 'text' => 'تم إقفال السنة المالية ' . $year_to_close . ' بنجاح. عدد العملاء ذوي الأرصدة المرحّلة: ' . $snap['carry_customers']];
            }
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('year_close failed: ' . $e->getMessage());
            $message = ['type' => 'error', 'text' => 'فشل إقفال السنة المالية. يرجى المحاولة مرة أخرى.'];
        }
    }
}

// --- Data for the selected year ---
$closed_info = false;
$year_data = [
    'total_orders' => 0, 'total_revenue' => 0, 'total_remaining' => 0, 'collected' => 0,
    'expenses' => 0, 'net_profit' => 0, 'balances' => [], 'carry_customers' => 0, 'carry_amount' => 0,
];
try {
    $ci = $db->prepare("SELECT closed_at FROM fiscal_years WHERE year = ? AND is_closed = 1");
    $ci->execute([$selected_year]);
    $closed_info = $ci->fetch(PDO::FETCH_ASSOC);

    $year_data = fy_summary($db, $selected_year, $usd_to_yer_rate, $sar_to_yer_rate, $base_currency);
} catch (PDOException $e) {
    error_log('year_close summary: ' . $e->getMessage());
    $message = ['type' => 'error', 'text' => 'حدث خطأ أثناء تحميل بيانات السنة.'];
}
$already_closed = (bool)$closed_info;
$customers_with_balance = array_slice($year_data['balances'], 0, 20);

include '../../includes/header.php';
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="bg-gradient-to-br from-purple-600 to-purple-700 text-white rounded-xl shadow-lg p-6 mb-6">
        <h1 class="text-2xl sm:text-3xl font-bold flex items-center gap-3 mb-2">
            <i class="fas fa-calendar-check"></i>
            <?php echo $page_title; ?>
        </h1>
        <p class="text-purple-100 text-sm sm:text-base opacity-90">مراجعة ملخص السنة المالية وإقفالها</p>
    </div>

    <?php if ($message): ?>
        <div class="rounded-lg p-4 mb-6 font-bold <?php echo $message['type'] === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
            <?php echo htmlspecialchars($message['text']); ?>
        </div>
    <?php endif; ?>

    <!-- Year Selector -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 mb-6">
        <form method="GET" class="flex items-center gap-3">
            <label class="text-sm font-medium text-gray-700">اختر السنة:</label>
            <select name="year" onchange="this.form.submit()" class="rounded-lg border-gray-300 focus:ring-purple-500 focus:border-purple-500 text-sm">
                <?php for ($y = $current_year; $y >= $current_year - 5; $y--): ?>
                    <option value="<?php echo $y; ?>" <?php echo $y === $selected_year ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </form>
    </div>

    <?php if ($already_closed): ?>
        <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-lg p-4 mb-6 font-bold">
            <i class="fas fa-lock"></i>
            السنة المالية <?php echo $selected_year; ?> مغلقة
            <?php if (!empty($closed_info['closed_at'])): ?>
                <span class="font-normal text-sm">(بتاريخ <?php echo htmlspecialchars($closed_info['closed_at']); ?>)</span>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 border-r-4 border-r-blue-500 p-4">
            <div class="text-2xl font-bold text-blue-700"><?php echo number_format($year_data['total_orders']); ?></div>
            <div class="text-gray-500 text-sm">إجمالي الطلبات</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 border-r-4 border-r-green-500 p-4">
            <div class="text-2xl font-bold text-green-700" style="direction:ltr;text-align:right;"><?php echo number_format($year_data['total_revenue'], 0, ',', '.'); ?> <span class="text-sm text-gray-500"><?php echo $currency_symbol; ?></span></div>
            <div class="text-gray-500 text-sm">إجمالي المبيعات</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 border-r-4 border-r-teal-500 p-4">
            <div class="text-2xl font-bold text-teal-700" style="direction:ltr;text-align:right;"><?php echo number_format($year_data['collected'], 0, ',', '.'); ?> <span class="text-sm text-gray-500"><?php echo $currency_symbol; ?></span></div>
            <div class="text-gray-500 text-sm">المحصّل خلال السنة</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 border-r-4 border-r-yellow-500 p-4">
            <div class="text-2xl font-bold text-yellow-700" style="direction:ltr;text-align:right;"><?php echo number_format($year_data['total_remaining'], 0, ',', '.'); ?> <span class="text-sm text-gray-500"><?php echo $currency_symbol; ?></span></div>
            <div class="text-gray-500 text-sm">إجمالي المتبقي على الطلبات</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 border-r-4 border-r-purple-500 p-4">
            <div class="text-2xl font-bold text-purple-700" style="direction:ltr;text-align:right;"><?php echo number_format($year_data['expenses'], 0, ',', '.'); ?> <span class="text-sm text-gray-500"><?php echo $currency_symbol; ?></span></div>
            <div class="text-gray-500 text-sm">المصروفات المعتمدة</div>
        </div>
        <?php $profit_ok = $year_data['net_profit'] >= 0; ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 border-r-4 <?php echo $profit_ok ? 'border-r-green-500' : 'border-r-red-500'; ?> p-4">
            <div class="text-2xl font-bold <?php echo $profit_ok ? 'text-green-700' : 'text-red-700'; ?>" style="direction:ltr;text-align:right;"><?php echo number_format($year_data['net_profit'], 0, ',', '.'); ?> <span class="text-sm text-gray-500"><?php echo $currency_symbol; ?></span></div>
            <div class="text-gray-500 text-sm">صافي الربح / الخسارة (المحصّل − المصروفات)</div>
        </div>
    </div>

    <!-- Customers with remaining balances -->
    <?php if (!empty($customers_with_balance)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden mb-6">
        <div class="bg-gray-50 px-5 py-3 border-b border-gray-200 font-bold text-gray-800">
            <i class="fas fa-exclamation-circle text-yellow-500"></i>
            عملاء لديهم أرصدة متبقية (<?php echo $year_data['carry_customers']; ?>)
            <span class="text-sm font-normal text-gray-500">— إجمالي: <?php echo number_format($year_data['carry_amount'], 0, ',', '.'); ?> <?php echo $currency_symbol; ?></span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">اسم العميل</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">الهاتف</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">المتبقي</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($customers_with_balance as $cb): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 font-medium text-gray-900"><?php echo htmlspecialchars($cb['name']); ?></td>
                        <td class="px-6 py-3 text-gray-500"><?php echo htmlspecialchars($cb['phone'] ?? '-'); ?></td>
                        <td class="px-6 py-3 font-bold text-red-600"><?php echo number_format((float)$cb['remaining'], 0, ',', '.'); ?> <span class="text-xs font-normal text-gray-500"><?php echo $currency_symbol; ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($year_data['carry_customers'] > count($customers_with_balance)): ?>
            <div class="px-5 py-3 text-xs text-gray-500 border-t border-gray-100">
                يتم عرض أعلى <?php echo count($customers_with_balance); ?> عميل فقط من أصل <?php echo $year_data['carry_customers']; ?>.
            </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Close Year -->
    <?php if (!$already_closed): ?>
    <div class="bg-white border border-red-200 rounded-xl p-5">
        <?php if ($is_admin): ?>
            <p class="font-bold text-red-700 mb-4">
                <i class="fas fa-exclamation-triangle"></i>
                تحذير: إقفال السنة المالية لا يمكن التراجع عنه من هذه الصفحة. راجع جميع البيانات أعلاه قبل المتابعة.
            </p>
            <form method="POST" onsubmit="return confirm('هل أنت متأكد تماماً من إقفال السنة المالية <?php echo $selected_year; ?>؟ هذا الإجراء لا يمكن التراجع عنه.');">
                <input type="hidden" name="confirm_close" value="1">
                <input type="hidden" name="year_to_close" value="<?php echo $selected_year; ?>">
                <button type="submit" class="bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-8 rounded-lg transition-colors duration-200">
                    <i class="fas fa-lock"></i> إقفال السنة المالية <?php echo $selected_year; ?>
                </button>
            </form>
        <?php else: ?>
            <p class="font-bold text-gray-600">
                <i class="fas fa-info-circle"></i> إقفال السنة المالية متاح للمدير فقط.
            </p>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php include '../../includes/footer.php'; ?>
