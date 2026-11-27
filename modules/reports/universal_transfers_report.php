<?php
/**
 * Universal transfers report (P6): every operation saved by modules/financial/universal_transfer.php.
 * Source: money_transfers (one row per transfer) + journal_entries (source_module = 'universal_transfer', source_id = transfer id).
 * Read-only. Amounts are in Yemeni Rial (ي.ر).
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/database.php';
require_once '../../includes/account_helpers.php';
require_once '../../includes/transfer_names.php';

$page_title = 'تقرير التحويل المباشر بين الحسابات';
$currency = 'ي.ر';

// ---------- helpers ----------
function ut_nf($n): string
{
    return number_format((float)$n, 2);
}

function ut_type_label(string $t): string
{
    return $t === 'bank' ? 'بنك' : 'حساب من الدليل';
}

// ---------- filters ----------
$is_date = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d);
$date_from = $is_date($_GET['date_from'] ?? '') ? $_GET['date_from'] : '';
$date_to   = $is_date($_GET['date_to'] ?? '') ? $_GET['date_to'] : '';
$src_type  = in_array($_GET['from_type'] ?? '', ['bank', 'account'], true) ? $_GET['from_type'] : '';
$src_id    = (int)($_GET['from_id'] ?? 0);
$dst_type  = in_array($_GET['to_type'] ?? '', ['bank', 'account'], true) ? $_GET['to_type'] : '';
$dst_id    = (int)($_GET['to_id'] ?? 0);
$min_amount = is_numeric($_GET['min_amount'] ?? null) ? (float)$_GET['min_amount'] : null;
$max_amount = is_numeric($_GET['max_amount'] ?? null) ? (float)$_GET['max_amount'] : null;
$search    = trim($_GET['q'] ?? '');
$user_id   = (int)($_GET['user_id'] ?? 0);
$want_csv  = ($_GET['export'] ?? '') === 'csv';
$cm_from   = date('Y-m-01');
$cm_to     = date('Y-m-d');

// ---------- dropdown data ----------
$bank_list = [];
$chart_list = [];
$users = [];
$error_message = '';

try {
    $bank_list  = $db->query("SELECT id, bank_name, account_name, account_number FROM bank_accounts ORDER BY bank_name, account_name")->fetchAll(PDO::FETCH_ASSOC);
    $chart_list = $db->query("SELECT id, code, name FROM accounts WHERE is_active = 1 ORDER BY code ASC")->fetchAll(PDO::FETCH_ASSOC);
    $users      = $db->query("SELECT id, full_name FROM users ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = 'تعذر تحميل القوائم: ' . $e->getMessage();
}

// ---------- main query (all filters, prepared statements) ----------
$rows = [];
$total_amount = 0.0;
$per_account = [];

try {
    $conds = [];
    $params = [];
    if ($date_from !== '') { $conds[] = 'mt.transfer_date >= ?'; $params[] = $date_from; }
    if ($date_to !== '')   { $conds[] = 'mt.transfer_date <= ?'; $params[] = $date_to; }
    if ($src_type !== '')  { $conds[] = 'mt.from_type = ?';      $params[] = $src_type; }
    if ($src_id > 0)       { $conds[] = 'mt.from_id = ?';        $params[] = $src_id; }
    if ($dst_type !== '')  { $conds[] = 'mt.to_type = ?';        $params[] = $dst_type; }
    if ($dst_id > 0)       { $conds[] = 'mt.to_id = ?';          $params[] = $dst_id; }
    if ($min_amount !== null) { $conds[] = 'mt.amount >= ?';     $params[] = $min_amount; }
    if ($max_amount !== null) { $conds[] = 'mt.amount <= ?';     $params[] = $max_amount; }
    if ($search !== '')    { $conds[] = 'mt.description LIKE ?'; $params[] = "%$search%"; }
    if ($user_id > 0)      { $conds[] = 'mt.created_by = ?';    $params[] = $user_id; }
    $where = $conds ? 'WHERE ' . implode(' AND ', $conds) : '';

    $stmt = $db->prepare("
        SELECT mt.id, mt.transfer_date, mt.amount, mt.from_type, mt.from_id, mt.from_name_snapshot,
               mt.to_type, mt.to_id, mt.to_name_snapshot, mt.description, mt.created_at,
               u.full_name, u.username,
               (SELECT je.id FROM journal_entries je
                 WHERE je.source_module = 'universal_transfer' AND je.source_id = mt.id
                 ORDER BY je.id LIMIT 1) AS journal_id
        FROM money_transfers mt
        LEFT JOIN users u ON u.id = mt.created_by
        $where
        ORDER BY mt.transfer_date DESC, mt.id DESC");
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Show names once even when they were saved twice (display only, stored data unchanged)
    foreach ($rows as &$rr) {
        $rr['from_name_snapshot'] = dedupeDisplayName((string)$rr['from_name_snapshot']);
        $rr['to_name_snapshot'] = dedupeDisplayName((string)$rr['to_name_snapshot']);
    }
    unset($rr);

    // Per-account summary: total moved out (as source), in (as target), and net. Follows the same filters.
    foreach ($rows as $r) {
        $amt = (float)$r['amount'];
        $total_amount += $amt;

        $out_key = $r['from_type'] . ':' . $r['from_id'];
        $in_key  = $r['to_type'] . ':' . $r['to_id'];
        if (!isset($per_account[$out_key])) {
            $per_account[$out_key] = ['label' => $r['from_name_snapshot'] ?: ('#' . $r['from_id']), 'type' => $r['from_type'], 'in' => 0.0, 'out' => 0.0];
        }
        if (!isset($per_account[$in_key])) {
            $per_account[$in_key] = ['label' => $r['to_name_snapshot'] ?: ('#' . $r['to_id']), 'type' => $r['to_type'], 'in' => 0.0, 'out' => 0.0];
        }
        $per_account[$out_key]['out'] += $amt;
        $per_account[$in_key]['in']   += $amt;
    }
    uasort($per_account, fn($a, $b) => strcmp($a['label'], $b['label']));
} catch (PDOException $e) {
    $error_message = 'تعذر تحميل العمليات: ' . $e->getMessage();
}

// ---------- CSV export (same filters) ----------
if ($want_csv && $error_message === '') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="universal_transfers_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['رقم العملية', 'التاريخ', 'من (النوع)', 'من (الاسم)', 'إلى (النوع)', 'إلى (الاسم)', 'المبلغ', 'الوصف', 'المنفذ', 'رقم القيد']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['transfer_date'],
            ut_type_label($r['from_type']), $r['from_name_snapshot'],
            ut_type_label($r['to_type']), $r['to_name_snapshot'],
            number_format((float)$r['amount'], 2, '.', ''),
            $r['description'], $r['full_name'] ?: $r['username'], $r['journal_id'],
        ]);
    }
    fputcsv($out, ['', '', '', '', '', 'الإجمالي', number_format($total_amount, 2, '.', ''), '', '', '']);
    fclose($out);
    exit();
}

include '../../includes/header.php';
?>

<style>
    .ut-stat { background:#fff; border-radius:12px; padding:1.25rem 1.5rem; box-shadow:0 2px 8px rgba(0,0,0,.08); border-right:4px solid; }
    .ut-table th { background:#f9fafb; font-weight:700; color:#374151; font-size:.85rem; white-space:nowrap; }
    .ut-table td, .ut-table th { padding:.7rem .75rem; border-bottom:1px solid #e5e7eb; font-size:.88rem; vertical-align:top; }
    .ut-num { font-family: monospace; white-space:nowrap; direction:ltr; text-align:left; }
    @media print {
        .no-print { display:none !important; }
        .ut-table td, .ut-table th { font-size:.75rem; }
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
                    <p class="text-blue-100 mt-2 text-sm md:text-base">كل العمليات المسجلة من صفحة التحويل المباشر بين الحسابات، مع الحساب المصدر والحساب الهدف والمبلغ والمنفذ</p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="index.php" class="px-5 py-3 bg-white/20 text-white rounded-xl hover:bg-white/30 font-semibold transition"><i class="fas fa-arrow-right ml-2"></i>التقارير</a>
                    <a href="?<?= htmlspecialchars(http_build_query(array_merge($_GET, ['export' => 'csv']))) ?>" class="px-5 py-3 bg-white/20 text-white rounded-xl hover:bg-white/30 font-semibold transition"><i class="fas fa-file-csv ml-2"></i>CSV</a>
                    <button onclick="window.print()" class="px-5 py-3 bg-white text-blue-600 rounded-xl hover:bg-blue-50 font-semibold transition"><i class="fas fa-print ml-2"></i>طباعة</button>
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
                <label class="block text-sm font-medium text-gray-700 mb-1">نوع المصدر</label>
                <select name="from_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">الكل</option>
                    <option value="bank" <?php echo $src_type === 'bank' ? 'selected' : ''; ?>>بنك</option>
                    <option value="account" <?php echo $src_type === 'account' ? 'selected' : ''; ?>>حساب من الدليل</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الحساب المصدر</label>
                <select name="from_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="0">الكل</option>
                    <optgroup label="البنوك">
                        <?php foreach ($bank_list as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo ($src_type === 'bank' && $src_id === (int)$b['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['bank_name'] . ' - ' . $b['account_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="الدليل">
                        <?php foreach ($chart_list as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($src_type === 'account' && $src_id === (int)$c['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(normalizeArabicDigits($c['code']) . ' ' . $c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">نوع الهدف</label>
                <select name="to_type" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">الكل</option>
                    <option value="bank" <?php echo $dst_type === 'bank' ? 'selected' : ''; ?>>بنك</option>
                    <option value="account" <?php echo $dst_type === 'account' ? 'selected' : ''; ?>>حساب من الدليل</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">الحساب الهدف</label>
                <select name="to_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="0">الكل</option>
                    <optgroup label="البنوك">
                        <?php foreach ($bank_list as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo ($dst_type === 'bank' && $dst_id === (int)$b['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($b['bank_name'] . ' - ' . $b['account_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                    <optgroup label="الدليل">
                        <?php foreach ($chart_list as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo ($dst_type === 'account' && $dst_id === (int)$c['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(normalizeArabicDigits($c['code']) . ' ' . $c['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </optgroup>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">أقل مبلغ</label>
                <input type="number" step="0.01" name="min_amount" value="<?php echo $min_amount !== null ? htmlspecialchars((string)$min_amount) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg" dir="ltr">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">أكبر مبلغ</label>
                <input type="number" step="0.01" name="max_amount" value="<?php echo $max_amount !== null ? htmlspecialchars((string)$max_amount) : ''; ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg" dir="ltr">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">بحث في الوصف</label>
                <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">المنفذ</label>
                <select name="user_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="0">الكل</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $user_id === (int)$u['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($u['full_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="md:col-span-2 flex flex-wrap items-end gap-2">
                <button type="submit" class="px-5 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-semibold"><i class="fas fa-search ml-1"></i>بحث</button>
                <a href="?date_from=<?= $cm_from ?>&date_to=<?= $cm_to ?>" class="px-5 py-2 bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200 font-semibold">الشهر الحالي</a>
                <a href="universal_transfers_report.php" class="px-5 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 font-semibold">إعادة</a>
            </div>
        </form>

        <!-- Summary -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div class="ut-stat" style="border-right-color:#2563eb;">
                <p class="text-gray-600 text-sm">عدد العمليات</p>
                <p class="text-2xl font-bold text-blue-700 mt-1 ut-num"><?php echo count($rows); ?></p>
            </div>
            <div class="ut-stat" style="border-right-color:#16a34a;">
                <p class="text-gray-600 text-sm">إجمالي المبالغ المحولة</p>
                <p class="text-2xl font-bold text-green-700 mt-1 ut-num"><?php echo ut_nf($total_amount); ?> <?php echo $currency; ?></p>
            </div>
        </div>

        <!-- Operations table -->
        <div class="bg-white shadow-lg rounded-2xl overflow-hidden mb-6">
            <div class="overflow-x-auto">
                <table class="w-full ut-table" style="min-width:1000px;">
                    <thead>
                        <tr>
                            <th class="text-right">رقم العملية</th>
                            <th class="text-right">التاريخ</th>
                            <th class="text-right">من</th>
                            <th class="text-right">إلى</th>
                            <th class="text-right">المبلغ</th>
                            <th class="text-right">الوصف</th>
                            <th class="text-right">المنفذ</th>
                            <th class="text-right">رقم القيد</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                            <tr><td colspan="8" class="text-center text-gray-500 py-10">لا توجد عمليات تحويل ضمن الفلتر المحدد.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $r): ?>
                        <tr>
                            <td class="ut-num"><?php echo (int)$r['id']; ?></td>
                            <td class="ut-num"><?php echo htmlspecialchars($r['transfer_date']); ?></td>
                            <td>
                                <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($r['from_name_snapshot'] ?: ('#' . $r['from_id'])); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars(ut_type_label($r['from_type'])); ?></div>
                            </td>
                            <td>
                                <div class="font-semibold text-gray-800"><?php echo htmlspecialchars($r['to_name_snapshot'] ?: ('#' . $r['to_id'])); ?></div>
                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars(ut_type_label($r['to_type'])); ?></div>
                            </td>
                            <td class="ut-num font-bold text-gray-800"><?php echo ut_nf($r['amount']); ?> <?php echo $currency; ?></td>
                            <td class="text-gray-700"><?php echo htmlspecialchars($r['description'] ?? ''); ?></td>
                            <td class="text-gray-700"><?php echo htmlspecialchars($r['full_name'] ?: ($r['username'] ?: '-')); ?></td>
                            <td class="ut-num"><?php echo $r['journal_id'] ? (int)$r['journal_id'] : '-'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if (!empty($rows)): ?>
                    <tfoot>
                        <tr class="bg-gray-50 font-bold">
                            <td colspan="4" class="text-left">الإجمالي</td>
                            <td class="ut-num"><?php echo ut_nf($total_amount); ?> <?php echo $currency; ?></td>
                            <td colspan="3"></td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </div>

        <!-- Per-account summary (same filters) -->
        <?php if (!empty($per_account)): ?>
        <div class="bg-white shadow-lg rounded-2xl overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200 font-bold text-gray-800">ملخص حسب الحساب (نفس الفلتر)</div>
            <div class="overflow-x-auto">
                <table class="w-full ut-table" style="min-width:600px;">
                    <thead>
                        <tr>
                            <th class="text-right">الحساب</th>
                            <th class="text-right">النوع</th>
                            <th class="text-right">إجمالي الوارد</th>
                            <th class="text-right">إجمالي الصادر</th>
                            <th class="text-right">الصافي</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($per_account as $acc): $net = $acc['in'] - $acc['out']; ?>
                        <tr>
                            <td class="font-semibold text-gray-800"><?php echo htmlspecialchars($acc['label']); ?></td>
                            <td class="text-gray-600"><?php echo htmlspecialchars(ut_type_label($acc['type'])); ?></td>
                            <td class="ut-num text-green-700"><?php echo ut_nf($acc['in']); ?></td>
                            <td class="ut-num text-red-700"><?php echo ut_nf($acc['out']); ?></td>
                            <td class="ut-num font-bold <?php echo $net >= 0 ? 'text-blue-700' : 'text-red-700'; ?>"><?php echo ut_nf($net); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <p class="text-xs text-gray-500 mt-4">المبالغ بالريال اليمني (ي.ر). رقم القيد هو رقم القيد المحاسبي المسجل لكل عملية تحويل في دفتر اليومية.</p>

    </div>
</div>

<?php include '../../includes/footer.php'; ?>
