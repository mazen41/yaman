-- ============================================================
-- P8: payouts to customers we owe (Option A)
-- Safe to run more than once (IF NOT EXISTS / WHERE NOT EXISTS).
-- Adds two new tables and the liability account 2200 if missing.
-- Does not change any existing row.
-- ============================================================

CREATE TABLE IF NOT EXISTS customer_refunds (
  id INT(11) NOT NULL AUTO_INCREMENT,
  refund_number VARCHAR(50) NOT NULL,
  customer_id INT(11) NOT NULL,
  bank_account_id INT(11) NOT NULL,
  amount DECIMAL(15,3) NOT NULL,
  refund_date DATE NOT NULL,
  note TEXT NULL,
  bank_transaction_id INT(11) NULL,
  journal_entry_id INT(11) NULL,
  created_by INT(11) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cr_number (refund_number),
  KEY idx_cr_customer (customer_id),
  KEY idx_cr_bank (bank_account_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_refund_allocations (
  id INT(11) NOT NULL AUTO_INCREMENT,
  refund_id INT(11) NOT NULL,
  order_id INT(11) NOT NULL,
  amount DECIMAL(15,3) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_cra_refund (refund_id),
  KEY idx_cra_order (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO accounts (code, name, type, parent_id, currency, initial_balance, current_balance, description, is_active, created_by)
SELECT '2200', 'مبالغ مستحقة للعملاء', 'liability', NULL, 'YER', 0.00, 0.00, 'مبالغ مستحقة للعملاء - قيود الدفع للعملاء (P8)', 1, NULL
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM accounts WHERE code = '2200');
