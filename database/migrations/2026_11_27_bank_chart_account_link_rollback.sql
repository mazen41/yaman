-- Rollback for 2026_11_27_bank_chart_account_link.sql
-- WARNING: drops the saved bank-to-chart links. Journal entries already posted are NOT changed.
ALTER TABLE bank_accounts DROP COLUMN IF EXISTS chart_account_id;
