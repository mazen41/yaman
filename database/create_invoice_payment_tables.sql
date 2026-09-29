-- Invoices table
CREATE TABLE IF NOT EXISTS `customer_invoices` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `invoice_number` VARCHAR(50) NOT NULL UNIQUE,
  `customer_id` INT NOT NULL,
  `order_id` INT NULL,
  `amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `tax_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `total_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `status` ENUM('pending', 'paid', 'partially_paid', 'cancelled', 'overdue') NOT NULL DEFAULT 'pending',
  `due_date` DATE NULL,
  `notes` TEXT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`order_id`) REFERENCES `customer_orders`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Payments table
CREATE TABLE IF NOT EXISTS `customer_payments` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `payment_number` VARCHAR(50) NOT NULL UNIQUE,
  `customer_id` INT NOT NULL,
  `invoice_id` INT NULL,
  `amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00,
  `payment_method` ENUM('cash', 'transfer', 'credit_card', 'check', 'other') NOT NULL DEFAULT 'cash',
  `payment_date` DATE NOT NULL,
  `reference_number` VARCHAR(100) NULL,
  `notes` TEXT NULL,
  `created_by` INT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`customer_id`) REFERENCES `customers`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`invoice_id`) REFERENCES `customer_invoices`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
