-- Create order_notes table if it doesn't exist
CREATE TABLE IF NOT EXISTS `order_notes` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `order_id` INT NOT NULL,
    `note` TEXT NOT NULL,
    `is_internal` TINYINT(1) DEFAULT 1 COMMENT 'internal note',
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `customer_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX idx_notes_order_id (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create order_status_history table if it doesn't exist
CREATE TABLE IF NOT EXISTS `order_status_history` (
    `id` INT PRIMARY KEY AUTO_INCREMENT,
    `order_id` INT NOT NULL,
    `status` VARCHAR(50) NOT NULL,
    `notes` TEXT,
    `created_by` INT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`order_id`) REFERENCES `customer_orders`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX idx_history_order_id (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
