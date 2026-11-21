<?php
/**
 * Migration: create the bank_account_transactions table
 * (deposit / withdrawal ledger used by modules/payments/bank_accounts.php).
 *
 * Safe to run more than once.
 *
 * Run on the server from the project folder:
 *   php database/migrations/create_bank_account_transactions.php
 * Or open it in a browser while logged in to the system.
 */

if (php_sapi_name() !== 'cli') {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        exit('Please log in first.');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

require_once __DIR__ . '/../../config/database.php';

try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS bank_account_transactions (
            id INT(11) NOT NULL AUTO_INCREMENT,
            account_id INT(11) NOT NULL,
            transaction_type ENUM('deposit','withdrawal','transfer_out','transfer_in','correction','opening_balance') NOT NULL,
            amount DECIMAL(15,3) NOT NULL,
            balance_before DECIMAL(15,3) NOT NULL,
            balance_after DECIMAL(15,3) NOT NULL,
            description TEXT NULL,
            related_transaction_id INT(11) NULL,
            created_by INT(11) NULL,
            created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_bat_account (account_id),
            KEY idx_bat_created (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    $exists = (int)$db->query("
        SELECT COUNT(*) FROM information_schema.tables
        WHERE table_schema = DATABASE() AND table_name = 'bank_account_transactions'
    ")->fetchColumn();

    $count = $exists ? (int)$db->query("SELECT COUNT(*) FROM bank_account_transactions")->fetchColumn() : 0;

    echo $exists
        ? "OK: table bank_account_transactions exists. Current rows: {$count}\n"
        : "ERROR: table was not created.\n";
} catch (PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
