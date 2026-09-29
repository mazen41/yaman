<?php
require_once '../config/database.php';

echo "<h1>Fixing Database Tables and Structure</h1>";
echo "<pre>";

try {
    echo "Step 1: Checking and creating required tables...\n\n";
    
    // 1. Check and create customers table
    $customers_check = $db->query("SHOW TABLES LIKE 'customers'");
    if ($customers_check->rowCount() == 0) {
        echo "Creating customers table...\n";
        $db->exec("CREATE TABLE customers (
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
        )");
        echo "✅ customers table created\n";
    } else {
        echo "✅ customers table exists\n";
    }
    
    // 2. Check and create customer_orders table
    $orders_check = $db->query("SHOW TABLES LIKE 'customer_orders'");
    if ($orders_check->rowCount() == 0) {
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
            created_by INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        echo "✅ customer_orders table created\n";
    } else {
        echo "✅ customer_orders table exists\n";
        
        // Check for missing columns
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
    
    // 3. Check and create order_items table
    $items_check = $db->query("SHOW TABLES LIKE 'order_items'");
    if ($items_check->rowCount() == 0) {
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
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        echo "✅ order_items table created\n";
    } else {
        echo "✅ order_items table exists\n";
    }
    
    // 4. Check and create order_status_history table
    $history_check = $db->query("SHOW TABLES LIKE 'order_status_history'");
    if ($history_check->rowCount() == 0) {
        echo "Creating order_status_history table...\n";
        $db->exec("CREATE TABLE order_status_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL,
            status VARCHAR(50) NOT NULL,
            notes TEXT DEFAULT NULL,
            created_by INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        echo "✅ order_status_history table created\n";
    } else {
        echo "✅ order_status_history table exists\n";
    }
    
    echo "\nStep 2: Creating sample data if needed...\n";
    
    // Check if we have customers
    $customer_count = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    if ($customer_count == 0) {
        echo "Creating sample customers...\n";
        
        $customers = [
            ['محمد أحمد', 'CUST001', '0501234567', '966501234567', 'mohammed@example.com', 'الرياض'],
            ['فاطمة علي', 'CUST002', '0507654321', '966507654321', 'fatima@example.com', 'جدة'],
            ['عبدالله سالم', 'CUST003', '0509876543', '966509876543', 'abdullah@example.com', 'الدمام']
        ];
        
        $customer_insert = $db->prepare("
            INSERT INTO customers (name, customer_code, mobile_number, whatsapp_number, email, city_name, is_active)
            VALUES (?, ?, ?, ?, ?, ?, 1)
        ");
        
        foreach ($customers as $customer) {
            $customer_insert->execute($customer);
        }
        
        echo "✅ Created " . count($customers) . " sample customers\n";
    }
    
    // Check if we have orders
    $order_count = $db->query("SELECT COUNT(*) FROM customer_orders")->fetchColumn();
    if ($order_count == 0) {
        echo "Creating sample orders...\n";
        
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
                $subtotal = rand(200, 1200);
                
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
                $shipping_cost = rand(15, 50);
                $final_amount = $total_amount + $shipping_cost;
                
                $statuses = ['new', 'processing', 'completed'];
                $status = $statuses[array_rand($statuses)];
                
                // Insert order
                $order_insert->execute([
                    $order_num, $customer['id'], $subtotal, $total_amount, $final_amount,
                    $discount_type, $discount_value, $discount_amount,
                    $status, 'cash', 'delivery', $shipping_cost,
                    "طلب تجريبي للعميل " . $customer['name'], $requires_approval
                ]);
                
                $order_id = $db->lastInsertId();
                
                // Add sample items
                $products = [
                    ['منتج تجريبي A', 2, 75],
                    ['منتج تجريبي B', 1, 150],
                    ['منتج تجريبي C', 3, 50]
                ];
                
                foreach ($products as $product) {
                    $item_insert->execute([
                        $order_id, $product[0], $product[1], $product[2], $product[1] * $product[2]
                    ]);
                }
                
                echo "✅ Created order $order_num for " . $customer['name'] . "\n";
                $order_number++;
            }
        }
    }
    
    echo "\nStep 3: Final verification...\n";
    
    $final_customers = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    $final_orders = $db->query("SELECT COUNT(*) FROM customer_orders")->fetchColumn();
    $final_items = $db->query("SELECT COUNT(*) FROM order_items")->fetchColumn();
    
    echo "Final counts:\n";
    echo "- Customers: $final_customers\n";
    echo "- Orders: $final_orders\n";
    echo "- Order Items: $final_items\n";
    
    echo "\n✅ Database setup completed successfully!\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<h2>Test Links</h2>";
echo "<ul>";
echo "<li><a href='modules/orders/index.php'>📋 Orders List</a></li>";
echo "<li><a href='modules/orders/view.php?id=1'>👁️ View Order #1</a></li>";
echo "<li><a href='modules/orders/create.php'>➕ Create New Order</a></li>";
echo "</ul>";
?>
