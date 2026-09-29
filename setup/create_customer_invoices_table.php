<?php
/**
 * Create customer_invoices table if it doesn't exist
 */

require_once '../config/database.php';

try {
    echo "<h2>Creating customer_invoices Table...</h2>";
    
    // Check if table exists
    $check = $db->query("SHOW TABLES LIKE 'customer_invoices'");
    
    if ($check->rowCount() > 0) {
        echo "<p style='color: blue;'>✓ Table 'customer_invoices' already exists</p>";
    } else {
        echo "<p>Creating 'customer_invoices' table...</p>";
        
        $db->exec("
            CREATE TABLE customer_invoices (
                id INT PRIMARY KEY AUTO_INCREMENT,
                invoice_number VARCHAR(30) UNIQUE NOT NULL,
                customer_id INT NOT NULL,
                order_id INT NOT NULL,
                amount DECIMAL(15,3) DEFAULT 0,
                discount_amount DECIMAL(15,3) DEFAULT 0,
                tax_amount DECIMAL(15,3) DEFAULT 0,
                total_amount DECIMAL(15,3) NOT NULL,
                paid_amount DECIMAL(15,3) DEFAULT 0,
                status ENUM('pending', 'partial', 'paid', 'cancelled') DEFAULT 'pending',
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
                FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE RESTRICT,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_invoice_number (invoice_number),
                INDEX idx_customer_id (customer_id),
                INDEX idx_order_id (order_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "<p style='color: green;'>✅ Table 'customer_invoices' created successfully!</p>";
    }
    
    echo "<h3 style='color: green;'>✅ Setup Complete!</h3>";
    echo "<p><a href='generate_missing_invoices.php'>Generate Missing Invoices</a></p>";
    echo "<p><a href='../modules/orders/index.php'>Go to Orders</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}
?>
