<?php
/**
 * Accounts report (P5): account statement (كشف حساب) and trial balance (ميزان مراجعة).
 * Source: journal_entry_items / journal_entries only. Read-only.
 * Balance direction: assets and expenses = debit - credit; everything else = credit - debit.
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/database.php';
require_once '../../includes/account_helpers.php';

$page_title = 'تقرير الحسابات';

// ---------- helpers ----------
function ar_descendants(array $children, int $id): array
{
    $out = [$id];
    $stack = [$id];
    $seen = [$id => true];
    while ($stack) {
        $cur = array_pop($stack);
        foreach ($children[$cur] ?? [] as $c) {
            if (!isset($seen[$c])) {
                $seen[$c] = true;
                $out[] = $c;
                $stack[] = $c;
            }
        }
    }
    return $out;
}

function ar_balance(string $type, float $dr, float $cr): float
{
    return in_array($type, ['asset', 'expense'], true) ? $dr - $cr : $cr - $dr;
}

function ar_nf($n): string
{
    return number_format((float)$n, 2);
}

// ---------- filters ----------
$is_date = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
$date_from = $is_date($_GET['date_from'] ?? '') ? $_GET['date_from'] : '';
$date_to = $is_date($_GET['date_to'] ?? '') ? $_GET['date_to'] : '';
$view = ($_GET['view'] ?? 'statement') === 'trial' ? 'trial' : 'statement';
$account_id = (int)($_GET['account_id'] ?? 0);
$parent_id = (int)($_GET['parent_id'] ?? 0);
$include_children = isset($_GET['include_children']);
$type_list = ['asset', 'liability', 'equity', 'revenue', 'expense', 'contra_revenue', 'contra_asset'];
$type_filter = in_array($_GET['type'] ?? '', $type_list, true) ? $_GET['type'] : '';
$search = trim($_GET['q'] ?? '');
$side_raw = $_GET['side'] ?? 'all';
$side = in_array($side_raw, ['all', 'debit', 'credit'], true) ? $side_raw : 'all';
$min_amount = is_numeric($_GET['min_amount'] ?? null) ? (float)$_GET['min_amount'] : 0.0;
$user_filter = (int)($_GET['user_id'] ?? 0);
$want_csv = ($_GET['export'] ?? '') === 'csv';
$error_message = '';

// ---------- data ----------
$accounts = [];
$children = [];
$users = [];
$rows = [];           // statement lines or trial rows
$opening = 0.0;
$closing = 0.0;
$totals = ['dr' => 0.0, 'cr' => 0.0];
$trial_check = null;  // true when total debit = total credit (all accounts)
$root = null;
$root_type = '';
$by_id = [];

try {
    $accounts = $db->query("SELECT id, code, name, type, parent_id, is_active FROM accounts ORDER BY code ASC")->fetchAll(PDO::FETCH_ASSOC);
    $by_id = [];
    foreach ($accounts as &$a) {
        $a['code'] = normalizeArabicDigits($a['code']);
        $a['id'] = (int)$a['id'];
        $by_id[$a['id']] = $a;
        if ($a['parent_id']) {
            $children[(int)$a['parent_id']][] = $a['id'];
        }
    }
    unset($a);
    $users = $db->query("SELECT id, full_name FROM users ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

    // Date condition on journal entries
    $date_cond = function (string $from, string $to): array {
        $c = [];
        $p = [];
        if ($from !== '') { $c[] = 'je.entry_date >= ?'; $p[] = $from; }
        if ($to !== '') { $c[] = 'je.entry_date <= ?'; $p[] = $to; }
        return [$c, $p];
    };

    if ($view === 'statement') {
        // Root account: a single account, or a main account (optionally with all its sub-accounts)
        $root = $account_id ?: $parent_id;
        if ($root && isset($by_id[$root])) {
            $root_type = $by_id[$root]['type'];
            $ids = $include_children ? ar_descendants($children, $root) : [$root];
            if ($type_filter !== '') {
                $ids = array_values(array_filter($ids, fn($i) => $by_id[$i]['type'] === $type_filter));
            }
            if (!empty($ids)) {
                $ph = implode(',', array_fill(0, count($ids), '?'));

                // Opening balance: everything before date_from (no other filter applies to the balance)
                $dr0 = 0.0; $cr0 = 0.0;
                if ($date_from !== '') {
                    $st = $db->prepare("
                        SELECT jei.type, COALESCE(SUM(jei.amount), 0) AS s
                        FROM journal_entry_items jei
                        JOIN journal_entries je ON je.id = jei.entry_id
                        WHERE jei.account_id IN ($ph) AND je.entry_date < ?
                        GROUP BY jei.type");
                    $st->execute(array_merge($ids, [$date_from]));
                    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                        if ($r['type'] === 'debit') $dr0 = (float)$r['s'];
                        if ($r['type'] === 'credit') $cr0 = (float)$r['s'];
                    }
                }
                $opening = ar_balance($root_type, $dr0, $cr0);

                // Lines in the period, with the view filters
                [$dc, $dp] = $date_cond($date_from, $date_to);
                $conds = array_merge(["jei.account_id IN ($ph)"], $dc);
                $params = array_merge($ids, $dp);
                if ($side !== 'all') { $conds[] = 'jei.type = ?'; $params[] = $side; }
                if ($min_amount > 0) { $conds[] = 'jei.amount >= ?'; $params[] = $min_amount; }
                if ($search !== '') {
                    $conds[] = '(je.description LIKE ? OR je.transaction_number LIKE ?)';
                    $params[] = "%$search%";
                    $params[] = "%$search%";
                }
                if ($user_filter > 0) { $conds[] = 'je.created_by = ?'; $params[] = $user_filter; }

                $ls = $db->prepare("
                    SELECT je.id AS entry_id, je.entry_date, je.transaction_number, je.description,
                           u.full_name, a.code, a.name AS account_name, jei.type, jei.amount
                    FROM journal_entry_items jei
                    JOIN journal_entries je ON je.id = jei.entry_id
                    JOIN accounts a ON a.id = jei.account_id
                    LEFT JOIN users u ON u.id = je.created_by
                    WHERE " . implode(' AND ', $conds) . "
                    ORDER BY je.entry_date ASC, je.id ASC, jei.id ASC");
                $ls->execute($params);
                $lines = $ls->fetchAll(PDO::FETCH_ASSOC);

                $running = $opening;
                foreach ($lines as $l) {
                    $amt = (float)$l['amount'];
                    $dr = $l['type'] === 'debit' ? $amt : 0.0;
                    $cr = $l['type'] === 'credit' ? $amt : 0.0;
                    $totals['dr'] += $dr;
                    $totals['cr'] += $cr;
                    $running += ar_balance($root_type, $dr, $cr);
                    $l['dr'] = $dr;
                    $l['cr'] = $cr;
                    $l['running'] = $running;
                    $rows[] = $l;
                }
                $closing = $running;
            }
        }
    } else {
        // Trial balance over all accounts (filters: dates, type, main account)
        $opening_sums = [];
        $period_sums = [];

        $st = $db->prepare("
            SELECT jei.account_id, jei.type, COALESCE(SUM(jei.amount), 0) AS s
            FROM journal_entry_items jei
            JOIN journal_entries je ON je.id = jei.entry_id
            WHERE je.entry_date < ?
            GROUP BY jei.account_id, jei.type");
        if ($date_from !== '') {
            $st->execute([$date_from]);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $opening_sums[(int)$r['account_id']][$r['type']] = (float)$r['s'];
            }
        }

        [$dc, $dp] = $date_cond($date_from, $date_to);
        $where = $dc ? 'WHERE ' . implode(' AND ', $dc) : '';
        $ps = $db->prepare("
            SELECT jei.account_id, jei.type, COALESCE(SUM(jei.amount), 0) AS s
            FROM journal_entry_items jei
            JOIN journal_entries je ON je.id = jei.entry_id
            $where
            GROUP BY jei.account_id, jei.type");
        $ps->execute($dp);
        foreach ($ps->fetchAll(PDO::FETCH_ASSOC) as $r) {
            $period_sums[(int)$r['account_id']][$r['type']] = (float)$r['s'];
        }

        // Per-account own balances, then roll up to parents (parent = own + children)
        $own_close = [];
        $own_dr = [];
        $own_cr = [];
        $all_dr = 0.0;
        $all_cr = 0.0;
        foreach ($by_id as $id => $a) {
            $od = $opening_sums[$id]['debit'] ?? 0.0;
            $oc = $opening_sums[$id]['credit'] ?? 0.0;
            $pd = $period_sums[$id]['debit'] ?? 0.0;
            $pc = $period_sums[$id]['credit'] ?? 0.0;
            $own_dr[$id] = $pd;
            $own_cr[$id] = $pc;
            $own_open[$id] = ar_balance($a['type'], $od, $oc);
            $own_close[$id] = ar_balance($a['type'], $od + $pd, $oc + $pc);
            $all_dr += $pd;
            $all_cr += $pc;
        }
        $trial_check = abs($all_dr - $all_cr) < 0.01;

        $rolled = function (int $id) use (&$rolled, &$own_close, $children): float {
            $sum = $own_close[$id] ?? 0.0;
            foreach ($children[$id] ?? [] as $c) {
                $sum += $rolled($c);
            }
            return $sum;
        };

        // Which accounts to show
        $scope = null;
        if ($parent_id && isset($by_id[$parent_id])) {
            $scope = $include_children ? ar_descendants($children, $parent_id) : [$parent_id];
        }
        foreach ($by_id as $id => $a) {
            if ($type_filter !== '' && $a['type'] !== $type_filter) continue;
            if ($scope !== null && !in_array($id, $scope, true)) continue;
            if (!$a['is_active'] && ($own_dr[$id] + $own_cr[$id]) == 0 && abs($own_open[$id] ?? 0) < 0.01) continue;
            $rows[] = [
                'id' => $id,
                'code' => $a['code'],
                'name' => $a['name'],
                'type' => $a['type'],
                'is_parent' => !empty($children[$id]),
                'opening' => $own_open[$id] ?? 0.0,
                'dr' => $own_dr[$id],
                'cr' => $own_cr[$id],
                'closing' => $own_close[$id],
                'rolled' => $rolled($id),
            ];
            $totals['dr'] += $own_dr[$id];
            $totals['cr'] += $own_cr[$id];
        }
    }

    // CSV export (Excel opens it)
    if ($want_csv && $error_message === '') {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="accounts_report_' . $view . '_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        if ($view === 'statement') {
            fputcsv($out, ['التاريخ', 'رقم القيد', 'الوصف', 'الرمز', 'الحساب', 'مدين', 'دائن', 'الرصيد']);
            fputcsv($out, ['', '', 'رصيد أول المدة', '', '', '', '', $opening]);
            foreach ($rows as $r) {
                fputcsv($out, [$r['entry_date'], $r['transaction_number'], $r['description'], $r['code'], $r['account_name'], $r['dr'], $r['cr'], $r['running']]);
            }
            fputcsv($out, ['', '', 'رصيد الإقفال', '', '', $totals['dr'], $totals['cr'], $closing]);
        } else {
            fputcsv($out, ['الرمز', 'الاسم', 'النوع', 'رصيد أول المدة', 'مدين الفترة', 'دائن الفترة', 'رصيد الإقفال', 'مجمع (يشمل الفرعية)']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['code'], $r['name'], $r['type'], $r['opening'], $r['dr'], $r['cr'], $r['closing'], $r['rolled']]);
            }
        }
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
    .ar-table th { background:#f9fafb; font-weight:700; color:#374151; font-size:.85rem; white-space:nowrap; }
    .ar-table td, .ar-table th { padding:.6rem .7rem; border-bottom:1px solid #e5e7eb; font-size:.88rem; vertical-align:top; }
    .ar-num { font-family: monospace; white-space:nowrap; direction:ltr; text-align:left; }
    .ar-tot td { font-weight:700; background:#f3f4f6; }
    @media print { .no-print { display:none !important; } .ar-table td, .ar-table th { font-size:.75rem; } }
</style>

<div class="min-h-screen bg-gray-50 py-6" dir="rtl">
    <div class="max-w-7xl mx-auto px-4">

        <div class="bg-gradient-to-r from-blue-600 to-indigo-700 shadow-xl rounded-2xl mb-6 p-6 no-print">
            <div class="flex flex-col md:flex-row md:justify-between md:items-center gap-4">
                <div>
                    <h1 class="text-2xl md:text-3xl font-bold text-white flex items-center">
                        <i class="fas fa-book ml-3"></i> <?php echo htmlspecialchars($page_title); ?>
                    </h1>
                    <p class="text-blue-100 mt-2 text-sm md:text-base">كشف حساب لكل حساب من دليل الحسابات، وميزان مراجعة لكل الحسابات. كل الأرقام من قيود اليومية.</p>
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
                <label class="block text-sm font-medium text-gray-700 mb-1">نوع التقرير</label>
                <select name="view" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="statement" <?php echo $view === 'statement' ? 'selected' : ''; ?>>كشف حساب</option>
                    <option value="trial" <?php echo $view === 'trial' ? 'selected' : ''; ?>>ميزان مراجعة</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">من تاريخ</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">إلى تاريخ</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">نوع الحساب</label>
                <select name="type" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">كل الأنواع</option>
                    <?php foreach ($type_list as $t): ?>
                        <option value="<?php echo $t; ?>" <?php echo $type_filter === $t ? 'selected' : ''; ?>><?php echo $t; ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">حساب محدد (كشف الحساب)</label>
                <select name="account_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">-- لا يوجد --</option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?php echo $a['id']; ?>" <?php echo $account_id === $a['id'] ? 'selected' : ''; ?>>[<?php echo htmlspecialchars($a['code']); ?>] <?php echo htmlspecialchars($a['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الحساب الرئيسي</label>
                <select name="parent_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">-- لا يوجد --</option>
                    <?php foreach ($accounts as $a): ?>
                        <option value="<?php echo $a['id']; ?>" <?php echo $parent_id === $a['id'] ? 'selected' : ''; ?>>[<?php echo htmlspecialchars($a['code']); ?>] <?php echo htmlspecialchars($a['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="flex items-center gap-2 mt-2 text-sm text-gray-700">
                    <input type="checkbox" name="include_children" value="1" <?php echo $include_children ? 'checked' : ''; ?>> تضمين الحسابات الفرعية (متكرر)
                </label>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">بحث في الوصف / رقم القيد</label>
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الجانب</label>
                <select name="side" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="all" <?php echo $side === 'all' ? 'selected' : ''; ?>>الكل</option>
                    <option value="debit" <?php echo $side === 'debit' ? 'selected' : ''; ?>>مدين فقط</option>
                    <option value="credit" <?php echo $side === 'credit' ? 'selected' : ''; ?>>دائن فقط</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">أقل مبلغ</label>
                <input type="number" step="0.01" min="0" name="min_amount" value="<?php echo $min_amount ? htmlspecialchars($min_amount) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">المستخدم الذي أنشأ القيد</label>
                <select name="user_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">الكل</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $user_filter === (int)$u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['full_name'] ?? ('#' . $u['id'])); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold"><i class="fas fa-search ml-1"></i>عرض</button>
            </div>
        </form>

        <?php if ($view === 'statement'): ?>
            <?php if (!$root): ?>
                <div class="bg-white rounded-2xl shadow p-6 text-gray-600">اختر حسابًا من "حساب محدد" أو "الحساب الرئيسي" لعرض كشف الحساب.</div>
            <?php else: ?>
            <div class="bg-white rounded-2xl shadow-lg p-6 overflow-x-auto">
                <h2 class="font-bold text-gray-800 mb-1">كشف الحساب: [<?php echo htmlspecialchars($by_id[$root]['code']); ?>] <?php echo htmlspecialchars($by_id[$root]['name']); ?></h2>
                <p class="text-xs text-gray-500 mb-4">
                    رصيد أول المدة = كل القيود قبل "من تاريخ" (بدون فلاتر البحث). إذا طبّقت فلاتر على الجانب أو المبلغ أو البحث، فالرصيد الختامي = أول المدة + السطور المعروضة فقط.
                </p>
                <table class="ar-table min-w-full">
                    <thead>
                        <tr>
                            <th>التاريخ</th><th>رقم القيد</th><th>الوصف</th><th>الحساب</th>
                            <th>مدين</th><th>دائن</th><th>الرصيد</th><th>المستخدم</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="ar-tot">
                            <td colspan="6">رصيد أول المدة</td>
                            <td class="ar-num"><?php echo ar_nf($opening); ?></td><td></td>
                        </tr>
                        <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="text-center text-gray-500 py-4">لا توجد قيود في هذه الفترة.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><?php echo htmlspecialchars((string)$r['entry_date']); ?></td>
                            <td class="ar-num"><?php echo htmlspecialchars((string)$r['transaction_number']); ?></td>
                            <td><?php echo htmlspecialchars($r['description'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($r['code'] . ' - ' . $r['account_name']); ?></td>
                            <td class="ar-num"><?php echo $r['dr'] ? ar_nf($r['dr']) : ''; ?></td>
                            <td class="ar-num"><?php echo $r['cr'] ? ar_nf($r['cr']) : ''; ?></td>
                            <td class="ar-num"><?php echo ar_nf($r['running']); ?></td>
                            <td><?php echo htmlspecialchars($r['full_name'] ?? ''); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="ar-tot">
                            <td colspan="4">إجمالي الفترة / رصيد الإقفال</td>
                            <td class="ar-num"><?php echo ar_nf($totals['dr']); ?></td>
                            <td class="ar-num"><?php echo ar_nf($totals['cr']); ?></td>
                            <td class="ar-num"><?php echo ar_nf($closing); ?></td><td></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="bg-white rounded-2xl shadow-lg p-6 overflow-x-auto">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="font-bold text-gray-800">ميزان المراجعة</h2>
                    <?php if ($trial_check === true): ?>
                        <span class="text-sm font-semibold text-emerald-700"><i class="fas fa-check-circle"></i> إجمالي المدين = إجمالي الدائن</span>
                    <?php elseif ($trial_check === false): ?>
                        <span class="text-sm font-semibold text-red-700"><i class="fas fa-exclamation-triangle"></i> تحذير: إجمالي المدين لا يساوي إجمالي الدائن</span>
                    <?php endif; ?>
                </div>
                <p class="text-xs text-gray-500 mb-4">"مجمع" للحسابات الرئيسية = رصيدها + أرصدة كل الفرعية. إجمالي الأعمدة يساوي مجموع الحسابات نفسها بدون تكرار.</p>
                <table class="ar-table min-w-full">
                    <thead>
                        <tr>
                            <th>الرمز</th><th>الاسم</th><th>النوع</th><th>رصيد أول المدة</th>
                            <th>مدين الفترة</th><th>دائن الفترة</th><th>رصيد الإقفال</th><th>مجمع (يشمل الفرعية)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                        <tr><td colspan="8" class="text-center text-gray-500 py-4">لا توجد حسابات مطابقة.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $r): ?>
                        <tr class="<?php echo $r['is_parent'] ? 'font-semibold bg-indigo-50/40' : ''; ?>">
                            <td class="ar-num"><?php echo htmlspecialchars($r['code']); ?></td>
                            <td><?php echo htmlspecialchars($r['name']); ?></td>
                            <td><?php echo htmlspecialchars($r['type']); ?></td>
                            <td class="ar-num"><?php echo ar_nf($r['opening']); ?></td>
                            <td class="ar-num"><?php echo ar_nf($r['dr']); ?></td>
                            <td class="ar-num"><?php echo ar_nf($r['cr']); ?></td>
                            <td class="ar-num"><?php echo ar_nf($r['closing']); ?></td>
                            <td class="ar-num"><?php echo $r['is_parent'] ? ar_nf($r['rolled']) : ''; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr class="ar-tot">
                            <td colspan="4">الإجمالي (حسابات معروضة)</td>
                            <td class="ar-num"><?php echo ar_nf($totals['dr']); ?></td>
                            <td class="ar-num"><?php echo ar_nf($totals['cr']); ?></td>
                            <td colspan="2"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php include '../../includes/footer.php'; ?>
