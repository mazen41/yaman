-- Suppliers Table: Stores information about product suppliers.
CREATE TABLE IF NOT EXISTS `suppliers` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(255) NOT NULL COMMENT 'Supplier name',
  `contact_person` VARCHAR(255) NULL COMMENT 'Main contact person at the supplier',
  `email` VARCHAR(255) NULL UNIQUE COMMENT 'Supplier email for communication',
  `phone` VARCHAR(50) NULL COMMENT 'Supplier phone number',
  `address` TEXT NULL COMMENT 'Physical address of the supplier',
  `is_active` BOOLEAN NOT NULL DEFAULT TRUE COMMENT 'Flag to enable/disable the supplier',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchases Table: Stores the main details for each purchase order.
CREATE TABLE IF NOT EXISTS `purchases` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `supplier_id` INT NOT NULL COMMENT 'Foreign key linking to the suppliers table',
  `purchase_date` DATE NOT NULL COMMENT 'The date the purchase was made',
  `reference_no` VARCHAR(50) NULL UNIQUE COMMENT 'A unique reference number for the purchase',
  `status` ENUM('received', 'pending', 'ordered', 'cancelled') NOT NULL DEFAULT 'pending' COMMENT 'The current status of the purchase order',
  `total_amount` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'The total cost of the purchase',
  `notes` TEXT NULL COMMENT 'Any additional notes about the purchase',
  `created_by` INT NULL COMMENT 'Foreign key linking to the users table',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`supplier_id`) REFERENCES `suppliers`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Purchase Items Table: Stores individual items for each purchase order.
CREATE TABLE IF NOT EXISTS `purchase_items` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `purchase_id` INT NOT NULL COMMENT 'Foreign key linking to the purchases table',
  `product_id` INT NULL COMMENT 'Foreign key linking to a products table (if it exists)',
  `product_name` VARCHAR(255) NOT NULL COMMENT 'The name of the purchased product',
  `quantity` INT NOT NULL DEFAULT 1,
  `unit_cost` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'The cost per unit of the product',
  `subtotal` DECIMAL(15, 2) NOT NULL DEFAULT 0.00 COMMENT 'Total cost for this item (quantity * unit_cost)',
  FOREIGN KEY (`purchase_id`) REFERENCES `purchases`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
