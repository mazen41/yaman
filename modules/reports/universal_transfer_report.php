<?php
/**
 * Universal transfer operations report (P6).
 * Source: money_transfers (one row per transfer) + journal_entries where source_module = 'universal_transfer'.
 * Read-only. Filters: dates, account, side, account kind, user, amount range, search.
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/database.php';
require_once '../../includes/account_helpers.php';

$page_title = 'تقرير التحويلات المباشرة بين الحسابات';

// ---------- helpers ----------
function tr_nf($n): string
{
    return number_format((float)$n, 2);
}

function tr_kind_label(string $t): string
{
    return $t === 'bank' ? 'حساب بنكي' : 'حساب مالي';
}

// ---------- filters ----------
$is_date = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
$date_from = $is_date($_GET['date_from'] ?? '') ? $_GET['date_from'] : '';
$date_to = $is_date($_GET['date_to'] ?? '') ? $_GET['date_to'] : '';

// account value looks like "bank:12" or "account:34"
$acc_type = '';
$acc_id = 0;
if (preg_match('/^(bank|account):(\d+)$/', (string)($_GET['account'] ?? ''), $m)) {
    $acc_type = $m[1];
    $acc_id = (int)$m[2];
}
$acc_side = in_array($_GET['side'] ?? 'all', ['all', 'from', 'to'], true) ? $_GET['side'] : 'all';
$kind = in_array($_GET['kind'] ?? '', ['bank', 'account'], true) ? $_GET['kind'] : '';
$user_filter = (int)($_GET['user_id'] ?? 0);
$min_amount = is_numeric($_GET['min_amount'] ?? null) ? (float)$_GET['min_amount'] : 0.0;
$max_amount = is_numeric($_GET['max_amount'] ?? null) ? (float)$_GET['max_amount'] : 0.0;
$search = trim($_GET['q'] ?? '');
$want_csv = ($_GET['export'] ?? '') === 'csv';
$error_message = '';

// ---------- data ----------
$rows = [];
$summary = [];        // per account: sent (out) / received (in)
$totals = ['count' => 0, 'amount' => 0.0];
$bank_list = [];
$chart_list = [];
$users = [];

try {
    $bank_list = $db->query("SELECT id, bank_name, account_name FROM bank_accounts ORDER BY bank_name, account_name")->fetchAll(PDO::FETCH_ASSOC);
    $chart_list = $db->query("SELECT id, code, name FROM accounts ORDER BY code ASC")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($chart_list as &$c) {
        $c['code'] = normalizeArabicDigits($c['code']);
    }
    unset($c);
    $users = $db->query("SELECT id, full_name FROM users ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    $conds = [];
    $params = [];
    if ($date_from !== '') { $conds[] = 'mt.transfer_date >= ?'; $params[] = $date_from; }
    if ($date_to !== '') { $conds[] = 'mt.transfer_date <= ?'; $params[] = $date_to; }
    if ($acc_type !== '' && $acc_id > 0) {
        if ($acc_side === 'from') {
            $conds[] = '(mt.from_type = ? AND mt.from_id = ?)';
            array_push($params, $acc_type, $acc_id);
        } elseif ($acc_side === 'to') {
            $conds[] = '(mt.to_type = ? AND mt.to_id = ?)';
            array_push($params, $acc_type, $acc_id);
        } else {
            $conds[] = '((mt.from_type = ? AND mt.from_id = ?) OR (mt.to_type = ? AND mt.to_id = ?))';
            array_push($params, $acc_type, $acc_id, $acc_type, $acc_id);
        }
    }
    if ($kind !== '') {
        $conds[] = '(mt.from_type = ? OR mt.to_type = ?)';
        array_push($params, $kind, $kind);
    }
    if ($user_filter > 0) { $conds[] = 'mt.created_by = ?'; $params[] = $user_filter; }
    if ($min_amount > 0) { $conds[] = 'mt.amount >= ?'; $params[] = $min_amount; }
    if ($max_amount > 0) { $conds[] = 'mt.amount <= ?'; $params[] = $max_amount; }
    if ($search !== '') {
        $conds[] = '(mt.description LIKE ? OR mt.from_name_snapshot LIKE ? OR mt.to_name_snapshot LIKE ? OR je.transaction_number LIKE ?)';
        array_push($params, "%$search%", "%$search%", "%$search%", "%$search%");
    }
    $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

    $st = $db->prepare("
        SELECT mt.id, mt.transfer_date, mt.amount, mt.from_type, mt.from_id, mt.from_name_snapshot,
               mt.to_type, mt.to_id, mt.to_name_snapshot, mt.description, mt.created_by,
               u.full_name, je.id AS entry_id, je.transaction_number
        FROM money_transfers mt
        LEFT JOIN users u ON u.id = mt.created_by
        LEFT JOIN journal_entries je ON je.source_module = 'universal_transfer' AND je.source_id = mt.id
        $where
        ORDER BY mt.transfer_date ASC, mt.id ASC");
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
        $amt = (float)$r['amount'];
        $totals['count']++;
        $totals['amount'] += $amt;

        $out_key = $r['from_type'] . ':' . (int)$r['from_id'];
        $in_key = $r['to_type'] . ':' . (int)$r['to_id'];
        if (!isset($summary[$out_key])) {
            $summary[$out_key] = ['name' => $r['from_name_snapshot'], 'kind' => $r['from_type'], 'out' => 0.0, 'in' => 0.0, 'count' => 0];
        }
        if (!isset($summary[$in_key])) {
            $summary[$in_key] = ['name' => $r['to_name_snapshot'], 'kind' => $r['to_type'], 'out' => 0.0, 'in' => 0.0, 'count' => 0];
        }
        $summary[$out_key]['out'] += $amt;
        $summary[$out_key]['count']++;
        $summary[$in_key]['in'] += $amt;
        $summary[$in_key]['count']++;
    }
    uasort($summary, fn($a, $b) => strcmp((string)$a['name'], (string)$b['name']));

    // CSV export (Excel opens it)
    if ($want_csv) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="universal_transfers_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['التاريخ', 'رقم القيد', 'الوصف', 'من', 'نوع المصدر', 'إلى', 'نوع الهدف', 'المبلغ', 'المستخدم', 'رقم العملية']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['transfer_date'], $r['transaction_number'] ?? '', $r['description'] ?? '',
                $r['from_name_snapshot'], tr_kind_label($r['from_type']),
                $r['to_name_snapshot'], tr_kind_label($r['to_type']),
                $r['amount'], $r['full_name'] ?? '', $r['id'],
            ]);
        }
        fputcsv($out, ['', '', 'الإجمالي', '', '', '', '', $totals['amount'], '', $totals['count'] . ' عملية']);
        fclose($out);
        exit();
    }
} catch (PDOException $e) {
    $error_message = 'تعذر تحميل بيانات التقرير: ' . $e->getMessage();
}

// Query string for CSV link
$qs = $_GET;
$qs['export'] = 'csv';
$csv_url = '?' . http_build_query($qs);

include '../../includes/header.php';
?>

<style>
    .ut-table th { background:#f9fafb; font-weight:700; color:#374151; font-size:.85rem; white-space:nowrap; }
    .ut-table td, .ut-table th { padding:.6rem .7rem; border-bottom:1px solid #e5e7eb; font-size:.88rem; vertical-align:top; }
    .ut-num { font-family: monospace; white-space:nowrap; direction:ltr; text-align:left; }
    .ut-tot td { font-weight:700; background:#f3f4f6; }
    @media print { .no-print { display:none !important; } .ut-table td, .ut-table th { font-size:.75rem; } }
</style>

<div class="min-h-screen bg-gray-50 py-6" dir="rtl">
    <div class="max-w-7xl mx-auto px-4">

        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 shadow-xl rounded-2xl mb-6 p-6 no-print">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-white flex items-center">
                        <i class="fas fa-exchange-alt ml-3"></i> <?php echo htmlspecialchars($page_title); ?>
                    </h1>
                    <p class="text-blue-100 mt-2 text-sm md:text-base">كل عملية تحويل بين الحسابات البنكية والحسابات المالية، مع الإجماليات وملخص لكل حساب.</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="index.php" class="px-5 py-3 bg-white/20 text-white rounded-xl hover:bg-white/30 font-semibold"><i class="fas fa-arrow-right ml-2"></i>التقارير</a>
                    <button onclick="window.print()" class="px-5 py-3 bg-white text-blue-600 rounded-xl hover:bg-blue-50 font-semibold"><i class="fas fa-print ml-2"></i>طباعة / PDF</button>
                    <a href="<?php echo htmlspecialchars($csv_url); ?>" class="px-5 py-3 bg-green-600 text-white rounded-xl hover:bg-green-700 font-semibold"><i class="fas fa-file-excel ml-2"></i>Excel (CSV)</a>
                </div>
            </div>
        </div>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Filters -->
        <form method="GET" action="" class="bg-white shadow-lg rounded-2xl mb-6 p-6 grid grid-cols-1 md:grid-cols-4 gap-4 no-print">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">من تاريخ</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">إلى تاريخ</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الحساب</label>
                <select name="account" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">كل الحسابات</option>
                    <optgroup label="حسابات بنكية">
                        <?php foreach ($bank_list as $b): $v = 'bank:' . (int)$b['id']; ?>
                            <option value="<?php echo $v; ?>" <?php echo ($acc_type === 'bank' && $acc_id === (int)$b['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($b['bank_name'] . ' - ' . $b['account_name']); ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="حسابات مالية (الدليل)">
                        <?php foreach ($chart_list as $a): $v = 'account:' . (int)$a['id']; ?>
                            <option value="<?php echo $v; ?>" <?php echo ($acc_type === 'account' && $acc_id === (int)$a['id']) ? 'selected' : ''; ?>>[<?php echo htmlspecialchars($a['code']); ?>] <?php echo htmlspecialchars($a['name']); ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">جانب الحساب</label>
                <select name="side" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="all" <?php echo $acc_side === 'all' ? 'selected' : ''; ?>>مصدر أو هدف</option>
                    <option value="from" <?php echo $acc_side === 'from' ? 'selected' : ''; ?>>المصدر فقط (خرج منه)</option>
                    <option value="to" <?php echo $acc_side === 'to' ? 'selected' : ''; ?>>الهدف فقط (دخل إليه)</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">نوع الحساب (أي طرف)</label>
                <select name="kind" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">الكل</option>
                    <option value="bank" <?php echo $kind === 'bank' ? 'selected' : ''; ?>>حساب بنكي</option>
                    <option value="account" <?php echo $kind === 'account' ? 'selected' : ''; ?>>حساب مالي</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">المستخدم</label>
                <select name="user_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">الكل</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $user_filter === (int)$u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['full_name'] ?? ('#' . $u['id'])); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">أقل مبلغ</label>
                <input type="number" step="0.01" min="0" name="min_amount" value="<?php echo $min_amount ? htmlspecialchars($min_amount) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">أكبر مبلغ</label>
                <input type="number" step="0.01" min="0" name="max_amount" value="<?php echo $max_amount ? htmlspecialchars($max_amount) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">بحث (الوصف، الاسم، رقم القيد)</label>
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold"><i class="fas fa-search ml-1"></i>عرض</button>
            </div>
        </form>

        <!-- Totals -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="bg-white rounded-2xl shadow p-5 border-r-4 border-blue-500">
                <p class="text-gray-600 text-sm">عدد العمليات</p>
                <p class="text-2xl font-bold text-gray-900"><?php echo number_format($totals['count']); ?></p>
            </div>
            <div class="bg-white rounded-2xl shadow p-5 border-r-4 border-emerald-500">
                <p class="text-gray-600 text-sm">إجمالي المبالغ المحوّلة</p>
                <p class="text-2xl font-bold text-gray-900 ut-num"><?php echo tr_nf($totals['amount']); ?></p>
            </div>
        </div>

        <!-- Transactions -->
        <div class="bg-white rounded-2xl shadow-lg p-6 overflow-x-auto mb-6">
            <h2 class="font-bold text-gray-800 mb-4">العمليات</h2>
            <table class="ut-table min-w-full">
                <thead>
                    <tr>
                        <th>التاريخ</th><th>رقم القيد</th><th>الوصف</th>
                        <th>من (المصدر)</th><th>إلى (الهدف)</th><th>المبلغ</th><th>المستخدم</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($rows)): ?>
                    <tr><td colspan="7" class="text-center text-gray-500 py-4">لا توجد عمليات تحويل مطابقة للفلاتر.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($r['transfer_date']); ?></td>
                        <td class="ut-num"><?php echo htmlspecialchars($r['transaction_number'] ?? '-'); ?></td>
                        <td><?php echo htmlspecialchars($r['description'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($r['from_name_snapshot']); ?> <span class="text-xs text-gray-500">(<?php echo tr_kind_label($r['from_type']); ?>)</span></td>
                        <td><?php echo htmlspecialchars($r['to_name_snapshot']); ?> <span class="text-xs text-gray-500">(<?php echo tr_kind_label($r['to_type']); ?>)</span></td>
                        <td class="ut-num"><?php echo tr_nf($r['amount']); ?></td>
                        <td><?php echo htmlspecialchars($r['full_name'] ?? ''); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (!empty($rows)): ?>
                    <tr class="ut-tot">
                        <td colspan="5">الإجمالي (<?php echo $totals['count']; ?> عملية)</td>
                        <td class="ut-num"><?php echo tr_nf($totals['amount']); ?></td>
                        <td></td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Per-account summary -->
        <div class="bg-white rounded-2xl shadow-lg p-6 overflow-x-auto">
            <h2 class="font-bold text-gray-800 mb-1">ملخص لكل حساب</h2>
            <p class="text-xs text-gray-500 mb-4">"مرسل" = مجموع ما خرج من الحساب في العمليات المعروضة. "مستلم" = مجموع ما دخل إليه. "الصافي" = مستلم − مرسل.</p>
            <table class="ut-table min-w-full">
                <thead>
                    <tr><th>الحساب</th><th>النوع</th><th>عدد العمليات</th><th>مرسل (خرج)</th><th>مستلم (دخل)</th><th>الصافي</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($summary)): ?>
                    <tr><td colspan="6" class="text-center text-gray-500 py-4">لا توجد بيانات.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($summary as $s): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['name']); ?></td>
                        <td><?php echo tr_kind_label($s['kind']); ?></td>
                        <td class="ut-num"><?php echo (int)$s['count']; ?></td>
                        <td class="ut-num"><?php echo tr_nf($s['out']); ?></td>
                        <td class="ut-num"><?php echo tr_nf($s['in']); ?></td>
                        <td class="ut-num"><?php echo tr_nf($s['in'] - $s['out']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>
</div>

<?php include '../../includes/footer.php'; ?>
