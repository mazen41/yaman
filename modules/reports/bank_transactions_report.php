<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/database.php';
$page_title = 'تقرير الإيداعات والسحوبات البنكية';

// --- Filters ---
$is_date = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
$date_from  = $is_date($_GET['date_from'] ?? '') ? $_GET['date_from'] : '';
$date_to    = $is_date($_GET['date_to'] ?? '')   ? $_GET['date_to']   : '';
$account_id = isset($_GET['account_id']) && $_GET['account_id'] !== '' ? intval($_GET['account_id']) : 0;
$type_raw   = $_GET['type'] ?? 'all';
$type       = in_array($type_raw, ['all', 'deposit', 'withdrawal'], true) ? $type_raw : 'all';

$where  = [];
$params = [];
if ($type === 'all') {
    $where[] = "t.transaction_type IN ('deposit','withdrawal')";
} else {
    $where[]  = "t.transaction_type = ?";
    $params[] = $type;
}
if ($date_from !== '') { $where[] = "DATE(t.created_at) >= ?"; $params[] = $date_from; }
if ($date_to !== '')   { $where[] = "DATE(t.created_at) <= ?"; $params[] = $date_to; }
if ($account_id > 0)   { $where[] = "t.account_id = ?";        $params[] = $account_id; }
$where_sql = implode(' AND ', $where);

$rows = [];
$totals = ['deposits' => 0, 'withdrawals' => 0, 'deposit_count' => 0, 'withdrawal_count' => 0];
$accounts = [];
$error_message = '';

try {
    $stmt = $db->prepare("
        SELECT t.id, t.account_id, t.transaction_type, t.amount, t.balance_before, t.balance_after,
               t.description, t.created_at, t.created_by,
               a.bank_name, a.account_name, a.account_number,
               u.full_name, u.username
        FROM bank_account_transactions t
        LEFT JOIN bank_accounts a ON a.id = t.account_id
        LEFT JOIN users u ON u.id = t.created_by
        WHERE $where_sql
        ORDER BY t.created_at DESC, t.id DESC
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        if ($r['transaction_type'] === 'deposit') {
            $totals['deposits'] += (float)$r['amount'];
            $totals['deposit_count']++;
        } else {
            $totals['withdrawals'] += (float)$r['amount'];
            $totals['withdrawal_count']++;
        }
    }

    $accounts = $db->query("SELECT id, bank_name, account_name, account_number FROM bank_accounts ORDER BY bank_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = 'تعذر تحميل بيانات الإيداعات والسحوبات: ' . $e->getMessage();
}

$net = $totals['deposits'] - $totals['withdrawals'];

include '../../includes/header.php';
?>

<style>
    .br-stat { background:#fff; border-radius:12px; padding:1.25rem 1.5rem; box-shadow:0 2px 8px rgba(0,0,0,.08); border-right:4px solid; }
    .br-table th { background:#f9fafb; font-weight:700; color:#374151; font-size:.85rem; white-space:nowrap; }
    .br-table td, .br-table th { padding:.7rem .75rem; border-bottom:1px solid #e5e7eb; font-size:.88rem; vertical-align:top; }
    .br-badge { display:inline-block; padding:.2rem .6rem; border-radius:999px; font-size:.78rem; font-weight:700; }
    .br-badge.deposit { background:#dcfce7; color:#166534; }
    .br-badge.withdrawal { background:#fee2e2; color:#991b1b; }
    .br-num { font-family: monospace; white-space:nowrap; direction:ltr; text-align:left; }
    @media print {
        .no-print { display:none !important; }
        .br-table td, .br-table th { font-size:.75rem; }
    }
</style>

<div class="min-h-screen bg-gray-50 py-6" dir="rtl">
    <div class="max-w-7xl mx-auto px-4">

        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 shadow-xl rounded-2xl mb-6 p-6 no-print">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-white flex items-center">
                        <i class="fas fa-exchange-alt ml-3"></i>
                        <?php echo htmlspecialchars($page_title); ?>
                    </h1>
                    <p class="text-blue-100 mt-2 text-sm md:text-base">كل عملية إيداع أو سحب من الحسابات البنكية مع تفاصيلها ورصيد الحساب قبل وبعد العملية</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="index.php" class="px-5 py-3 bg-white/20 text-white rounded-xl hover:bg-white/30 font-semibold transition"><i class="fas fa-arrow-right ml-2"></i>التقارير</a>
                    <button onclick="window.print()" class="px-5 py-3 bg-white text-blue-600 rounded-xl hover:bg-blue-50 font-semibold transition"><i class="fas fa-print ml-2"></i>طباعة</button>
                </div>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Filters -->
        <form method="GET" action="" class="bg-white shadow-lg rounded-2xl mb-6 p-6 grid grid-cols-1 md:grid-cols-5 gap-4 no-print">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">من التاريخ</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">إلى التاريخ</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الحساب البنكي</label>
                <select name="account_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">كل الحسابات</option>
                    <?php foreach ($accounts as $acc): ?>
                        <option value="<?php echo $acc['id']; ?>" <?php echo $account_id === (int)$acc['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($acc['bank_name'] . ' - ' . $acc['account_name'] . ' (' . $acc['account_number'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">نوع العملية</label>
                <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="all" <?php echo $type === 'all' ? 'selected' : ''; ?>>الكل (إيداع وسحب)</option>
                    <option value="deposit" <?php echo $type === 'deposit' ? 'selected' : ''; ?>>إيداع فقط</option>
                    <option value="withdrawal" <?php echo $type === 'withdrawal' ? 'selected' : ''; ?>>سحب فقط</option>
                </select>
            </div>
            <div class="flex items-end gap-2">
                <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold"><i class="fas fa-search ml-1"></i>بحث</button>
                <a href="bank_transactions_report.php" class="px-5 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-semibold">إعادة</a>
            </div>
        </form>

        <!-- Summary -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
            <div class="br-stat" style="border-right-color:#16a34a;">
                <p class="text-gray-600 text-sm">إجمالي الإيداعات</p>
                <p class="text-2xl font-bold text-green-700 mt-1 br-num"><?php echo number_format($totals['deposits'], 2); ?> ر.ي</p>
                <p class="text-xs text-gray-500 mt-1"><?php echo $totals['deposit_count']; ?> عملية</p>
            </div>
            <div class="br-stat" style="border-right-color:#dc2626;">
                <p class="text-gray-600 text-sm">إجمالي السحوبات</p>
                <p class="text-2xl font-bold text-red-700 mt-1 br-num"><?php echo number_format($totals['withdrawals'], 2); ?> ر.ي</p>
                <p class="text-xs text-gray-500 mt-1"><?php echo $totals['withdrawal_count']; ?> عملية</p>
            </div>
            <div class="br-stat" style="border-right-color:#2563eb;">
                <p class="text-gray-600 text-sm">صافي الحركة (إيداع − سحب)</p>
                <p class="text-2xl font-bold <?php echo $net >= 0 ? 'text-blue-700' : 'text-red-700'; ?> mt-1 br-num"><?php echo number_format($net, 2); ?> ر.ي</p>
                <p class="text-xs text-gray-500 mt-1"><?php echo count($rows); ?> عملية حسب الفلتر</p>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white shadow-lg rounded-2xl overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full br-table" style="min-width:900px;">
                    <thead>
                        <tr>
                            <th class="text-right">#</th>
                            <th class="text-right">التاريخ والوقت</th>
                            <th class="text-right">الحساب البنكي</th>
                            <th class="text-right">النوع</th>
                            <th class="text-right">المبلغ</th>
                            <th class="text-right">الرصيد قبل</th>
                            <th class="text-right">الرصيد بعد</th>
                            <th class="text-right">الملاحظة</th>
                            <th class="text-right">بواسطة</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="9" class="text-center text-gray-500 py-10">لا توجد عمليات إيداع أو سحب ضمن الفلتر المحدد.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $r):
                            $is_dep = $r['transaction_type'] === 'deposit';
                            $who = $r['full_name'] ?: ($r['username'] ?: 'مستخدم #' . $r['created_by']);
                        ?>
                        <tr>
                            <td class="br-num"><?php echo $r['id']; ?></td>
                            <td class="br-num"><?php echo date('Y-m-d H:i', strtotime($r['created_at'])); ?></td>
                            <td>
                                <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($r['bank_name'] ?? '—'); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars(($r['account_name'] ?? '') . ' · ' . ($r['account_number'] ?? '')); ?></div>
                            </td>
                            <td><span class="br-badge <?php echo $is_dep ? 'deposit' : 'withdrawal'; ?>"><?php echo $is_dep ? 'إيداع' : 'سحب'; ?></span></td>
                            <td class="br-num font-bold <?php echo $is_dep ? 'text-green-700' : 'text-red-700'; ?>"><?php echo ($is_dep ? '+' : '−') . number_format((float)$r['amount'], 2); ?></td>
                            <td class="br-num"><?php echo number_format((float)$r['balance_before'], 2); ?></td>
                            <td class="br-num"><?php echo number_format((float)$r['balance_after'], 2); ?></td>
                            <td class="text-gray-700"><?php echo htmlspecialchars($r['description'] ?? ''); ?></td>
                            <td class="text-gray-700"><?php echo htmlspecialchars($who); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <p class="text-xs text-gray-500 mt-4">المبالغ بالريال اليمني (ر.ي). الرصيد قبل وبعد يُسجَّل لحظة كل عملية، ويمكن مطابقته مع رصيد الحساب الحالي في صفحة الحسابات البنكية.</p>

    </div>
</div>

<?php include '../../includes/footer.php'; ?>
