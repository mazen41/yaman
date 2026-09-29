-- Create discount tiers table for customer types
CREATE TABLE IF NOT EXISTS customer_type_discount_tiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_type_id INT NOT NULL,
    tier_number INT NOT NULL COMMENT '1, 2, or 3',
    min_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    max_amount DECIMAL(10,2) NULL COMMENT 'NULL means unlimited',
    discount_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_type_id) REFERENCES customer_types(id) ON DELETE CASCADE,
    UNIQUE KEY unique_tier (customer_type_id, tier_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add index for faster lookups
CREATE INDEX idx_customer_type_amount ON customer_type_discount_tiers(customer_type_id, min_amount, max_amount);
