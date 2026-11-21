-- ============================================================
-- Migration: bank_account_transactions (deposit / withdrawal ledger)
-- Safe to run more than once (uses IF NOT EXISTS).
-- ============================================================

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Verify (should return 1 row with the table name)
SELECT table_name FROM information_schema.tables
WHERE table_schema = DATABASE() AND table_name = 'bank_account_transactions';
