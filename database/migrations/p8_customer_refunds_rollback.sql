-- ROLLBACK for p8_customer_refunds.sql
-- Drops only the two P8 tables. Account 2200 is NOT deleted (journal lines may use it).
DROP TABLE IF EXISTS customer_refund_allocations;
DROP TABLE IF EXISTS customer_refunds;
