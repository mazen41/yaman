<?php
// includes/customer_refund_save.php (P8) - the one place that pays a customer.
// Everything happens in ONE transaction. Any error rolls back all of it.

require_once __DIR__ . '/accounting_functions.php';
require_once __DIR__ . '/account_helpers.php';
require_once __DIR__ . '/customer_refund_helpers.php';

/**
 * Pays $amount to customer $customer_id from bank $bank_id.
 * Returns the new refund id. Throws Exception with an Arabic message on refusal.
 */
function crSavePayout(PDO $db, int $bank_id, int $customer_id, float $amount, string $note, int $user_id): int
{
    if ($amount <= 0) {
        throw new Exception('المبلغ يجب أن يكون أكبر من صفر.');
    }
    $amount = round($amount, 3);

    $db->beginTransaction();
    try {
        // 1) Lock the bank row and read its balance.
        $st = $db->prepare("SELECT id, account_name, current_balance FROM bank_accounts WHERE id = ? FOR UPDATE");
        $st->execute([$bank_id]);
        $bank = $st->fetch(PDO::FETCH_ASSOC);
        if (!$bank) {
            throw new Exception('الحساب البنكي غير موجود.');
        }
        $bank_before = (float)$bank['current_balance'];

        // 2) Lock the owed orders of this customer and check the amount.
        $owed = crOwedOrders($db, $customer_id, true);
        $total_owed = 0.0;
        foreach ($owed as $o) $total_owed += (float)$o['owed'];
        $total_owed = round($total_owed, 3);

        if ($total_owed <= CR_THRESHOLD) {
            throw new Exception('لا توجد مبالغ مستحقة لهذا العميل.');
        }
        if ($amount > $total_owed + 0.0005) {
            throw new Exception('المبلغ أكبر من المستحق للعميل (' . number_format($total_owed, 3) . ').');
        }
        if ($amount > $bank_before + 0.0005) {
            throw new Exception('الرصيد البنكي غير كافٍ. الرصيد ' . number_format($bank_before, 3) . '.');
        }

        // 3) Refund header (refund_number is unique, a second save with the same number fails).
        $refund_number = 'CR-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
        $st = $db->prepare("INSERT INTO customer_refunds
            (refund_number, customer_id, bank_account_id, amount, refund_date, note, created_by)
            VALUES (?, ?, ?, ?, CURDATE(), ?, ?)");
        $st->execute([$refund_number, $customer_id, $bank_id, $amount, $note, $user_id]);
        $refund_id = (int)$db->lastInsertId();

        // 4) Allocations, oldest order first.
        $alloc = crAllocate($owed, $amount);
        $sum_alloc = 0.0;
        $st = $db->prepare("INSERT INTO customer_refund_allocations (refund_id, order_id, amount) VALUES (?, ?, ?)");
        foreach ($alloc as $a) {
            $st->execute([$refund_id, $a['order_id'], $a['amount']]);
            $sum_alloc += $a['amount'];
        }
        if (abs($sum_alloc - $amount) > 0.0005) {
            throw new Exception('خطأ في توزيع المبلغ على الطلبات.');
        }

        // 5) Deduct from the bank, only if the balance still covers it.
        $st = $db->prepare("UPDATE bank_accounts SET current_balance = current_balance - ?, updated_at = NOW()
                            WHERE id = ? AND current_balance >= ?");
        $st->execute([$amount, $bank_id, $amount]);
        if ($st->rowCount() !== 1) {
            throw new Exception('الرصيد البنكي تغيّر ولم يعد كافياً. لم يتم الحفظ.');
        }
        $bank_after = round($bank_before - $amount, 3);

        // 6) Bank ledger row.
        $desc = 'دفع مستحقات عميل #' . $refund_id;
        $st = $db->prepare("INSERT INTO bank_account_transactions
            (account_id, transaction_type, amount, balance_before, balance_after, description, created_by)
            VALUES (?, 'withdrawal', ?, ?, ?, ?, ?)");
        $st->execute([$bank_id, $amount, $bank_before, $bank_after, $desc, $user_id]);
        $bank_tx_id = (int)$db->lastInsertId();

        // 7) Balanced journal entry: Dr 2200 (we owe less), Cr bank's chart account.
        $st = $db->prepare("SELECT id FROM accounts WHERE code = '2200' LIMIT 1");
        $st->execute();
        $acc_2200 = (int)$st->fetchColumn();
        if (!$acc_2200) {
            throw new Exception('حساب 2200 (مبالغ مستحقة للعملاء) غير موجود. شغّل الهجرة أولاً.');
        }
        $bank_chart_id = resolveBankChartAccountId($db, $bank_id);
        $je_id = create_journal_entry(
            $db,
            date('Y-m-d'),
            $desc,
            [
                ['account_id' => $acc_2200, 'type' => 'debit', 'amount' => $amount],
                ['account_id' => $bank_chart_id, 'type' => 'credit', 'amount' => $amount],
            ],
            'customer_refund',
            $refund_id,
            $user_id
        );

        // 8) Link the rows together.
        $db->prepare("UPDATE customer_refunds SET bank_transaction_id = ?, journal_entry_id = ? WHERE id = ?")
           ->execute([$bank_tx_id, $je_id, $refund_id]);

        $db->commit();
        return $refund_id;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        throw $e;
    }
}
