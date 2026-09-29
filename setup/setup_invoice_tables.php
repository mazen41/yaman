<?php
require_once '../config/database.php';

echo "<h2>Setting up Invoice and Payment Tables</h2>";

try {
    // Create customer_invoices table
    $db->exec("CREATE TABLE IF NOT EXISTS `customer_invoices` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    echo "<p>✓ Customer invoices table created successfully</p>";
    
    // Create customer_payments table
    $db->exec("CREATE TABLE IF NOT EXISTS `customer_payments` (
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
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    
    echo "<p>✓ Customer payments table created successfully</p>";
    
    // Generate invoices for existing orders
    echo "<h3>Generating invoices for existing orders</h3>";
    
    // First, check if there are existing orders without invoices
    $orders_stmt = $db->query("SELECT co.* FROM customer_orders co 
                              LEFT JOIN customer_invoices ci ON co.id = ci.order_id 
                              WHERE ci.id IS NULL");
    $orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($orders) > 0) {
        $invoice_stmt = $db->prepare("INSERT INTO customer_invoices 
                                     (invoice_number, customer_id, order_id, amount, tax_amount, total_amount, status, due_date, created_at, updated_at) 
                                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        
        foreach ($orders as $order) {
            // Generate invoice number
            $invoice_number = 'INV-' . date('Ymd') . '-' . sprintf('%04d', $order['id']);
            
            // Calculate tax (assuming 15% VAT)
            $amount = $order['total_amount'] ?? 0;
            $tax_amount = $amount * 0.15;
            $total_amount = $amount + $tax_amount;
            
            // Set due date (15 days from order date)
            $due_date = date('Y-m-d', strtotime($order['created_at'] . ' + 15 days'));
            
            // Set status based on order status
            $status = 'pending';
            if ($order['status'] == 'completed') {
                $status = 'paid';
            } elseif ($order['status'] == 'cancelled') {
                $status = 'cancelled';
            }
            
            // Insert invoice
            $invoice_stmt->execute([
                $invoice_number,
                $order['customer_id'],
                $order['id'],
                $amount,
                $tax_amount,
                $total_amount,
                $status,
                $due_date
            ]);
            
            $invoice_id = $db->lastInsertId();
            
            echo "<p>Created invoice #$invoice_number for order #{$order['order_number']} - Amount: $total_amount</p>";
            
            // If order is completed, also create a payment record
            if ($order['status'] == 'completed') {
                $payment_number = 'PAY-' . date('Ymd') . '-' . sprintf('%04d', $order['id']);
                
                $payment_stmt = $db->prepare("INSERT INTO customer_payments 
                                           (payment_number, customer_id, invoice_id, amount, payment_method, payment_date, created_at, updated_at) 
                                           VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())");
                
                $payment_stmt->execute([
                    $payment_number,
                    $order['customer_id'],
                    $invoice_id,
                    $total_amount,
                    $order['payment_method'] ?? 'cash',
                    date('Y-m-d', strtotime($order['updated_at']))
                ]);
                
                echo "<p>Created payment #$payment_number for invoice #$invoice_number - Amount: $total_amount</p>";
            }
        }
        
        echo "<p>✓ Generated " . count($orders) . " invoices for existing orders</p>";
    } else {
        echo "<p>No orders found without invoices</p>";
    }
    
    // Check if we have sample orders but no invoices yet
    $count_orders = $db->query("SELECT COUNT(*) FROM customer_orders")->fetchColumn();
    $count_invoices = $db->query("SELECT COUNT(*) FROM customer_invoices")->fetchColumn();
    
    if ($count_orders > 0 && $count_invoices == 0) {
        echo "<p>Creating sample invoices and payments for demonstration...</p>";
        
        // Create sample invoices and payments
        $sample_orders = $db->query("SELECT * FROM customer_orders LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($sample_orders as $order) {
            // Generate invoice
            $invoice_number = 'INV-' . date('Ymd') . '-' . sprintf('%04d', $order['id']);
            $amount = $order['final_amount'] ?? 1000; // Use order amount or default to 1000
            $tax_amount = $amount * 0.15;
            $total_amount = $amount + $tax_amount;
            
            $db->exec("INSERT INTO customer_invoices 
                      (invoice_number, customer_id, order_id, amount, tax_amount, total_amount, status, due_date, created_at) 
                      VALUES ('$invoice_number', {$order['customer_id']}, {$order['id']}, $amount, $tax_amount, $total_amount, 'paid', 
                      DATE_ADD('{$order['created_at']}', INTERVAL 15 DAY), '{$order['created_at']}')");
            
            $invoice_id = $db->lastInsertId();
            
            // Generate payment
            $payment_number = 'PAY-' . date('Ymd') . '-' . sprintf('%04d', $order['id']);
            
            $db->exec("INSERT INTO customer_payments 
                      (payment_number, customer_id, invoice_id, amount, payment_method, payment_date, created_at) 
                      VALUES ('$payment_number', {$order['customer_id']}, $invoice_id, $total_amount, 'cash', 
                      '{$order['created_at']}', '{$order['created_at']}')");
            
            echo "<p>Created sample invoice and payment for order #{$order['order_number']}</p>";
        }
    }
    
    // Show current stats
    $invoices_count = $db->query("SELECT COUNT(*) FROM customer_invoices")->fetchColumn();
    $payments_count = $db->query("SELECT COUNT(*) FROM customer_payments")->fetchColumn();
    
    echo "<h3>Current Statistics:</h3>";
    echo "<ul>";
    echo "<li>Total Invoices: $invoices_count</li>";
    echo "<li>Total Payments: $payments_count</li>";
    echo "</ul>";
    
    echo "<p><a href='modules/customers/view_enhanced.php?id=1&tab=invoices'>View Customer Invoices</a></p>";
    
} catch (PDOException $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}
?>
