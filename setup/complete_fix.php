<?php
require_once 'config/database.php';

echo "<h1>Complete System Fix</h1>";
echo "<pre>";

try {
    echo "Step 1: Fixing database structure...\n";
    
    // Check if customer_orders table exists
    $tables_stmt = $db->query("SHOW TABLES LIKE 'customer_orders'");
    if ($tables_stmt->rowCount() == 0) {
        echo "Creating customer_orders table...\n";
        
        $create_table_sql = "CREATE TABLE customer_orders (
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
            created_by INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $db->exec($create_table_sql);
        echo "customer_orders table created successfully!\n";
    } else {
        echo "customer_orders table exists, checking columns...\n";
        
        // Get existing columns
        $columns_stmt = $db->query("DESCRIBE customer_orders");
        $existing_columns = $columns_stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $required_columns = [
            'subtotal_amount' => 'DECIMAL(10,2) DEFAULT 0.00',
            'discount_type' => 'ENUM("percentage", "fixed") DEFAULT NULL',
            'discount_value' => 'DECIMAL(10,2) DEFAULT 0.00',
            'discount_amount' => 'DECIMAL(10,2) DEFAULT 0.00',
            'requires_approval' => 'TINYINT(1) DEFAULT 0',
            'approved_by' => 'INT DEFAULT NULL',
            'approved_at' => 'DATETIME DEFAULT NULL',
            'order_link' => 'VARCHAR(255) DEFAULT NULL'
        ];
        
        foreach ($required_columns as $column => $definition) {
            if (!in_array($column, $existing_columns)) {
                echo "Adding missing column: $column\n";
                $db->exec("ALTER TABLE customer_orders ADD COLUMN $column $definition");
            }
        }
    }
    
    // Check order_items table
    $items_tables_stmt = $db->query("SHOW TABLES LIKE 'order_items'");
    if ($items_tables_stmt->rowCount() == 0) {
        echo "Creating order_items table...\n";
        
        $create_items_sql = "CREATE TABLE order_items (
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
        )";
        
        $db->exec($create_items_sql);
        echo "order_items table created successfully!\n";
    }
    
    // Check customers table
    $customers_stmt = $db->query("SHOW TABLES LIKE 'customers'");
    if ($customers_stmt->rowCount() == 0) {
        echo "Creating customers table...\n";
        
        $create_customers_sql = "CREATE TABLE customers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            customer_code VARCHAR(50) UNIQUE NOT NULL,
            mobile_number VARCHAR(20) DEFAULT NULL,
            whatsapp_number VARCHAR(20) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            city_name VARCHAR(100) DEFAULT NULL,
            address TEXT DEFAULT NULL,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )";
        
        $db->exec($create_customers_sql);
        echo "customers table created successfully!\n";
    }
    
    echo "\nStep 2: Creating sample data...\n";
    
    // Check if we have customers
    $customer_count_stmt = $db->query("SELECT COUNT(*) FROM customers");
    $customer_count = $customer_count_stmt->fetchColumn();
    
    if ($customer_count == 0) {
        echo "Creating sample customers...\n";
        
        $customers_data = [
            ['محمد أحمد', 'CUST001', '0501234567', '966501234567', 'mohammed@example.com', 'الرياض'],
            ['فاطمة علي', 'CUST002', '0507654321', '966507654321', 'fatima@example.com', 'جدة'],
            ['عبدالله سالم', 'CUST003', '0509876543', '966509876543', 'abdullah@example.com', 'الدمام']
        ];
        
        $customer_insert = $db->prepare("
            INSERT INTO customers (name, customer_code, mobile_number, whatsapp_number, email, city_name, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        
        foreach ($customers_data as $customer) {
            $customer_insert->execute($customer);
        }
        
        echo "Created " . count($customers_data) . " sample customers\n";
    }
    
    // Check if we have orders
    $order_count_stmt = $db->query("SELECT COUNT(*) FROM customer_orders");
    $order_count = $order_count_stmt->fetchColumn();
    
    if ($order_count == 0) {
        echo "Creating sample orders...\n";
        
        // Get customers
        $customers_stmt = $db->query("SELECT id, name FROM customers LIMIT 3");
        $customers = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $order_insert = $db->prepare("
            INSERT INTO customer_orders (
                order_number, customer_id, subtotal_amount, total_amount, final_amount,
                discount_type, discount_value, discount_amount,
                status, payment_method, shipping_method, shipping_cost,
                notes, requires_approval, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $item_insert = $db->prepare("
            INSERT INTO order_items (order_id, product_name, quantity, unit_price, total_price)
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $order_number = 1;
        
        foreach ($customers as $customer) {
            for ($i = 1; $i <= 2; $i++) {
                $order_num = 'ORD-' . str_pad($order_number, 6, '0', STR_PAD_LEFT);
                $subtotal = rand(150, 1500);
                
                // Calculate discount
                $discount_type = null;
                $discount_value = 0;
                $discount_amount = 0;
                $requires_approval = 0;
                
                if ($subtotal >= 100 && $subtotal < 500) {
                    $discount_type = 'percentage';
                    $discount_value = 10;
                    $discount_amount = round($subtotal * 0.10, 2);
                } elseif ($subtotal >= 500 && $subtotal < 1000) {
                    $discount_type = 'percentage';
                    $discount_value = 11;
                    $discount_amount = round($subtotal * 0.11, 2);
                } elseif ($subtotal >= 1000) {
                    $discount_type = 'percentage';
                    $discount_value = 12;
                    $discount_amount = round($subtotal * 0.12, 2);
                    $requires_approval = 1;
                }
                
                $total_amount = $subtotal - $discount_amount;
                $shipping_cost = rand(10, 50);
                $final_amount = $total_amount + $shipping_cost;
                
                $statuses = ['new', 'processing', 'completed'];
                $status = $statuses[array_rand($statuses)];
                
                // Insert order
                $order_insert->execute([
                    $order_num,
                    $customer['id'],
                    $subtotal,
                    $total_amount,
                    $final_amount,
                    $discount_type,
                    $discount_value,
                    $discount_amount,
                    $status,
                    'cash',
                    'delivery',
                    $shipping_cost,
                    "طلب تجريبي للعميل " . $customer['name'],
                    $requires_approval
                ]);
                
                $order_id = $db->lastInsertId();
                
                // Add sample items
                $products = [
                    ['منتج A', 2, 50],
                    ['منتج B', 1, 100],
                    ['منتج C', 3, 75]
                ];
                
                foreach ($products as $product) {
                    $item_insert->execute([
                        $order_id,
                        $product[0],
                        $product[1],
                        $product[2],
                        $product[1] * $product[2]
                    ]);
                }
                
                echo "Created order $order_num for customer " . $customer['name'] . "\n";
                $order_number++;
            }
        }
    }
    
    echo "\nStep 3: Final verification...\n";
    
    $final_order_count = $db->query("SELECT COUNT(*) FROM customer_orders")->fetchColumn();
    $final_customer_count = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    $final_item_count = $db->query("SELECT COUNT(*) FROM order_items")->fetchColumn();
    
    echo "Final counts:\n";
    echo "- Customers: $final_customer_count\n";
    echo "- Orders: $final_order_count\n";
    echo "- Order Items: $final_item_count\n";
    
    echo "\n✅ System fix completed successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ul>";
echo "<li><a href='modules/orders/index.php'>View Orders List</a></li>";
echo "<li><a href='modules/orders/view.php?id=1'>View Order #1</a></li>";
echo "<li><a href='modules/orders/create.php'>Create New Order</a></li>";
echo "</ul>";
?>
