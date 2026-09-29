-- Create customer_otps table for OTP authentication
CREATE TABLE IF NOT EXISTS `customer_otps` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `customer_id` INT NOT NULL,
    `otp_code` VARCHAR(6) NOT NULL,
    `expires_at` DATETIME NOT NULL,
    `used` TINYINT(1) DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE CASCADE,
    INDEX idx_customer_otp (`customer_id`, `otp_code`),
    INDEX idx_expires (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add is_active column to customers if not exists
ALTER TABLE `customers` 
ADD COLUMN IF NOT EXISTS `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Customer account status';
