-- P3: link each bank account to a chart account (nullable, additive).
-- NOT RUN. Run it yourself later. Code falls back to بنك 1202 while this column is missing or NULL.
ALTER TABLE bank_accounts ADD COLUMN IF NOT EXISTS chart_account_id INT NULL DEFAULT NULL;
