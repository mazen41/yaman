<?php
require_once 'config/database.php';

// Function to check if a column exists in a table
function columnExists($db, $table, $column) {
    try {
        $stmt = $db->prepare("SHOW COLUMNS FROM $table LIKE ?");
        $stmt->execute([$column]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Function to check if a table exists
function tableExists($db, $table) {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Start output
echo "<h1>Updating Order System Database Structure</h1>";
echo "<pre>";

try {
    $db->beginTransaction();
    
    // 1. Update customer_orders table
    if (tableExists($db, 'customer_orders')) {
        echo "Updating customer_orders table...\n";
        
        // Add discount fields if they don't exist
        if (!columnExists($db, 'customer_orders', 'discount_type')) {
            $db->exec("ALTER TABLE customer_orders ADD COLUMN discount_type ENUM('percentage', 'fixed') DEFAULT NULL AFTER final_amount");
            echo "- Added discount_type column\n";
        }
        
        if (!columnExists($db, 'customer_orders', 'discount_value')) {
            $db->exec("ALTER TABLE customer_orders ADD COLUMN discount_value DECIMAL(10,2) DEFAULT 0.00 AFTER discount_type");
            echo "- Added discount_value column\n";
        }
        
        if (!columnExists($db, 'customer_orders', 'discount_amount')) {
            $db->exec("ALTER TABLE customer_orders ADD COLUMN discount_amount DECIMAL(10,2) DEFAULT 0.00 AFTER discount_value");
            echo "- Added discount_amount column\n";
        }
        
        if (!columnExists($db, 'customer_orders', 'subtotal_amount')) {
            $db->exec("ALTER TABLE customer_orders ADD COLUMN subtotal_amount DECIMAL(10,2) DEFAULT 0.00 AFTER total_amount");
            echo "- Added subtotal_amount column\n";
        }
        
        if (!columnExists($db, 'customer_orders', 'requires_approval')) {
            $db->exec("ALTER TABLE customer_orders ADD COLUMN requires_approval TINYINT(1) DEFAULT 0 AFTER notes");
            echo "- Added requires_approval column\n";
        }
        
        if (!columnExists($db, 'customer_orders', 'approved_by')) {
            $db->exec("ALTER TABLE customer_orders ADD COLUMN approved_by INT DEFAULT NULL AFTER requires_approval");
            echo "- Added approved_by column\n";
        }
        
        if (!columnExists($db, 'customer_orders', 'approved_at')) {
            $db->exec("ALTER TABLE customer_orders ADD COLUMN approved_at DATETIME DEFAULT NULL AFTER approved_by");
            echo "- Added approved_at column\n";
        }
        
        if (!columnExists($db, 'customer_orders', 'order_link')) {
            $db->exec("ALTER TABLE customer_orders ADD COLUMN order_link VARCHAR(255) DEFAULT NULL AFTER notes");
            echo "- Added order_link column\n";
        }
        
        echo "customer_orders table updated successfully.\n\n";
    } else {
        echo "Creating customer_orders table...\n";
        
        $db->exec("CREATE TABLE customer_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(20) NOT NULL,
            customer_id INT NOT NULL,
            total_amount DECIMAL(10,2) DEFAULT 0.00,
            subtotal_amount DECIMAL(10,2) DEFAULT 0.00,
            discount_type ENUM('percentage', 'fixed') DEFAULT NULL,
            discount_value DECIMAL(10,2) DEFAULT 0.00,
            discount_amount DECIMAL(10,2) DEFAULT 0.00,
            final_amount DECIMAL(10,2) DEFAULT 0.00,
            status ENUM('new', 'processing', 'completed', 'cancelled', 'modified') DEFAULT 'new',
            payment_method VARCHAR(50) DEFAULT NULL,
            shipping_method VARCHAR(50) DEFAULT NULL,
            shipping_cost DECIMAL(10,2) DEFAULT 0.00,
            expected_delivery_date DATE DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            order_link VARCHAR(255) DEFAULT NULL,
            requires_approval TINYINT(1) DEFAULT 0,
            approved_by INT DEFAULT NULL,
            approved_at DATETIME DEFAULT NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
        )");
        
        echo "customer_orders table created successfully.\n\n";
    }
    
    // 2. Update order_items table
    if (tableExists($db, 'order_items')) {
        echo "Updating order_items table...\n";
        
        if (!columnExists($db, 'order_items', 'product_link')) {
            $db->exec("ALTER TABLE order_items ADD COLUMN product_link VARCHAR(255) DEFAULT NULL AFTER notes");
            echo "- Added product_link column\n";
        }
        
        if (!columnExists($db, 'order_items', 'product_status')) {
            $db->exec("ALTER TABLE order_items ADD COLUMN product_status ENUM('available', 'out_of_stock', 'discontinued') DEFAULT 'available' AFTER product_link");
            echo "- Added product_status column\n";
        }
        
        echo "order_items table updated successfully.\n\n";
    } else {
        echo "Creating order_items table...\n";
        
        $db->exec("CREATE TABLE order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            product_id INT DEFAULT NULL,
            product_name VARCHAR(255) NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            notes TEXT DEFAULT NULL,
            product_link VARCHAR(255) DEFAULT NULL,
            product_status ENUM('available', 'out_of_stock', 'discontinued') DEFAULT 'available',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE
        )");
        
        echo "order_items table created successfully.\n\n";
    }
    
    // 3. Update order_status_history table
    if (!tableExists($db, 'order_status_history')) {
        echo "Creating order_status_history table...\n";
        
        $db->exec("CREATE TABLE order_status_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            status VARCHAR(50) NOT NULL,
            notes TEXT DEFAULT NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE
        )");
        
        echo "order_status_history table created successfully.\n\n";
    }
    
    // 4. Create discount_rules table if it doesn't exist
    if (!tableExists($db, 'discount_rules')) {
        echo "Creating discount_rules table...\n";
        
        $db->exec("CREATE TABLE discount_rules (
            id INT AUTO_INCREMENT PRIMARY KEY,
            min_amount DECIMAL(10,2) NOT NULL,
            max_amount DECIMAL(10,2) DEFAULT NULL,
            discount_type ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
            discount_value DECIMAL(10,2) NOT NULL,
            requires_approval TINYINT(1) DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // Insert default discount rules
        $db->exec("INSERT INTO discount_rules (min_amount, max_amount, discount_type, discount_value, requires_approval) VALUES 
            (0, 99.99, 'percentage', 0, 0),
            (100, 499.99, 'percentage', 10, 0),
            (500, 999.99, 'percentage', 11, 0),
            (1000, NULL, 'percentage', 12, 1)
        ");
        
        echo "discount_rules table created successfully.\n\n";
    }
    
    // 5. Create order_payments table if it doesn't exist
    if (!tableExists($db, 'order_payments')) {
        echo "Creating order_payments table...\n";
        
        $db->exec("CREATE TABLE order_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            payment_number VARCHAR(20) NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50) NOT NULL,
            payment_date DATE NOT NULL,
            receipt_image VARCHAR(255) DEFAULT NULL,
            notes TEXT DEFAULT NULL,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE
        )");
        
        echo "order_payments table created successfully.\n\n";
    }
    
    $db->commit();
    echo "All database updates completed successfully!";
    
} catch (PDOException $e) {
    $db->rollBack();
    echo "Error: " . $e->getMessage();
}

echo "</pre>";
?>
