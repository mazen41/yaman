<?php
// Shared helpers for chart-of-accounts dropdowns (P2).

/**
 * Convert Arabic-Indic (٠-٩) and Persian (۰-۹) digits to Western 0-9.
 * Used on save and on display so codes sort and match consistently.
 */
function normalizeArabicDigits($value)
{
    return strtr((string)$value, [
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
    ]);
}

/**
 * Every active chart account, of every type, ordered by normalized code.
 * Single-currency system: an empty currency is treated as YER.
 */
function getAccountsForSelect(PDO $db)
{
    // Balance comes from journal lines (P3), not the stored current_balance column.
    $stmt = $db->query("
        SELECT a.id, a.code, a.name, a.type, a.currency,
               COALESCE(SUM(CASE WHEN jei.type = 'debit' THEN jei.amount ELSE 0 END), 0) AS total_debit,
               COALESCE(SUM(CASE WHEN jei.type = 'credit' THEN jei.amount ELSE 0 END), 0) AS total_credit
        FROM accounts a
        LEFT JOIN journal_entry_items jei ON jei.account_id = a.id
        WHERE a.is_active = 1
        GROUP BY a.id, a.code, a.name, a.type, a.currency
    ");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$row) {
        $row['code'] = normalizeArabicDigits($row['code']);
        if (trim((string)($row['currency'] ?? '')) === '') {
            $row['currency'] = 'YER';
        }
        $row['current_balance'] = journalBalanceByNature($row['type'], (float)$row['total_debit'], (float)$row['total_credit']);
    }
    unset($row);

    usort($rows, function ($a, $b) {
        return strnatcmp($a['code'], $b['code']);
    });

    return $rows;
}

/**
 * Balance in the account's normal direction (same rule as modules/financial/accounts.php):
 * asset and expense = debit - credit; everything else = credit - debit.
 */
function journalBalanceByNature($type, $total_debit, $total_credit)
{
    return in_array($type, ['asset', 'expense'], true)
        ? (float)$total_debit - (float)$total_credit
        : (float)$total_credit - (float)$total_debit;
}

/**
 * Current balance of one chart account, from its journal lines.
 */
function getChartAccountBalance(PDO $db, int $account_id, string $type): float
{
    $stmt = $db->prepare("
        SELECT COALESCE(SUM(CASE WHEN type = 'debit' THEN amount ELSE 0 END), 0) AS total_debit,
               COALESCE(SUM(CASE WHEN type = 'credit' THEN amount ELSE 0 END), 0) AS total_credit
        FROM journal_entry_items WHERE account_id = ?
    ");
    $stmt->execute([$account_id]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    return journalBalanceByNature($type, $r['total_debit'], $r['total_credit']);
}

/**
 * True once the nullable bank_accounts.chart_account_id column exists (P3 migration run).
 * Until then the code behaves as if no bank is linked.
 */
function bankChartLinkColumnExists(PDO $db): bool
{
    static $exists = null;
    if ($exists === null) {
        $stmt = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'bank_accounts' AND COLUMN_NAME = 'chart_account_id'");
        $exists = (int)$stmt->fetchColumn() > 0;
    }
    return $exists;
}

/**
 * Id of the chart account for code 1202 (بنك), the fallback for unlinked banks.
 */
function fallbackBankChartAccountId(PDO $db): int
{
    $stmt = $db->prepare("SELECT id FROM accounts WHERE code = ? LIMIT 1");
    $stmt->execute(['1202']);
    $id = $stmt->fetchColumn();
    if (!$id) {
        throw new Exception('حساب بنك 1202 غير موجود في دليل الحسابات.');
    }
    return (int)$id;
}

/**
 * Chart account a bank side posts to: its linked account, else 1202.
 */
function resolveBankChartAccountId(PDO $db, int $bank_id): int
{
    if (bankChartLinkColumnExists($db)) {
        $stmt = $db->prepare("SELECT chart_account_id FROM bank_accounts WHERE id = ?");
        $stmt->execute([$bank_id]);
        $linked = $stmt->fetchColumn();
        if ($linked) {
            return (int)$linked;
        }
    }
    return fallbackBankChartAccountId($db);
}
