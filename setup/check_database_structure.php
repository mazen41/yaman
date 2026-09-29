<?php
/**
 * Database Structure Analysis
 * Senior PHP/MySQL Engineer Implementation
 */

require_once 'config/database.php';

echo "<h1>🔍 تحليل هيكل قاعدة البيانات</h1>";
echo "<div style='font-family: monospace; background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<pre>";

try {
    echo "=== Database Structure Analysis ===\n";
    echo "Database: yassin_admin_system\n";
    echo "Connection: " . (($db instanceof PDO) ? "✅ Connected" : "❌ Failed") . "\n\n";
    
    // Get all tables
    echo "📋 EXISTING TABLES:\n";
    echo "==================\n";
    
    $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "❌ No tables found in database!\n\n";
        
        echo "🔧 CREATING ESSENTIAL TABLES...\n";
        echo "===============================\n";
        
        // Create customers table
        $db->exec("
            CREATE TABLE IF NOT EXISTS customers (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) UNIQUE,
                phone VARCHAR(20),
                address TEXT,
                customer_type ENUM('individual', 'company') DEFAULT 'individual',
                credit_limit DECIMAL(10,2) DEFAULT 0.00,
                current_balance DECIMAL(10,2) DEFAULT 0.00,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✅ Created: customers\n";
        
        // Create customer_orders table
        $db->exec("
            CREATE TABLE IF NOT EXISTS customer_orders (
                id INT AUTO_INCREMENT PRIMARY KEY,
                customer_id INT NOT NULL,
                order_number VARCHAR(50) UNIQUE NOT NULL,
                status ENUM('pending', 'processing', 'completed', 'cancelled', 'delivered') DEFAULT 'pending',
                total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                discount_amount DECIMAL(10,2) DEFAULT 0.00,
                tax_amount DECIMAL(10,2) DEFAULT 0.00,
                final_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                coupon_id INT NULL,
                coupon_discount DECIMAL(10,2) DEFAULT 0.00,
                payment_status ENUM('pending', 'paid', 'partial', 'refunded') DEFAULT 'pending',
                notes TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✅ Created: customer_orders\n";
        
        // Create products table
        $db->exec("
            CREATE TABLE IF NOT EXISTS products (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                sku VARCHAR(100) UNIQUE,
                description TEXT,
                category_id INT,
                cost_price DECIMAL(10,2) DEFAULT 0.00,
                selling_price DECIMAL(10,2) DEFAULT 0.00,
                stock_quantity INT DEFAULT 0,
                min_stock_level INT DEFAULT 5,
                max_stock_level INT DEFAULT 100,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✅ Created: products\n";
        
        // Create coupons table (if not exists from previous setup)
        $db->exec("
            CREATE TABLE IF NOT EXISTS coupons (
                id INT AUTO_INCREMENT PRIMARY KEY,
                coupon_code VARCHAR(50) UNIQUE NOT NULL,
                coupon_name VARCHAR(255) NOT NULL,
                description TEXT,
                discount_type ENUM('percentage', 'fixed_amount') NOT NULL,
                discount_value DECIMAL(10,2) NOT NULL,
                min_order_amount DECIMAL(10,2) DEFAULT 0.00,
                max_discount_amount DECIMAL(10,2) DEFAULT NULL,
                usage_limit INT DEFAULT NULL,
                user_usage_limit INT DEFAULT 1,
                used_count INT DEFAULT 0,
                start_date DATE NOT NULL,
                end_date DATE NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✅ Created: coupons\n";
        
        // Create coupon_usage table
        $db->exec("
            CREATE TABLE IF NOT EXISTS coupon_usage (
                id INT AUTO_INCREMENT PRIMARY KEY,
                coupon_id INT NOT NULL,
                customer_id INT NOT NULL,
                order_id INT NOT NULL,
                discount_amount DECIMAL(10,2) NOT NULL,
                used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE,
                FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        echo "✅ Created: coupon_usage\n";
        
        // Refresh tables list
        $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    }
    
    foreach ($tables as $table) {
        echo "📁 $table\n";
        
        // Get table structure
        $columns = $db->query("DESCRIBE $table")->fetchAll();
        foreach ($columns as $column) {
            echo "   └── {$column['Field']} ({$column['Type']})\n";
        }
        
        // Get record count
        $count = $db->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        echo "   📊 Records: $count\n\n";
    }
    
    echo "\n🔍 CURRENT DATABASE STATISTICS:\n";
    echo "===============================\n";
    
    $stats_queries = [
        'customers' => "SELECT COUNT(*) FROM customers",
        'customer_orders' => "SELECT COUNT(*) FROM customer_orders", 
        'products' => "SELECT COUNT(*) FROM products",
        'coupons' => "SELECT COUNT(*) FROM coupons WHERE is_active = 1",
        'orders_today' => "SELECT COUNT(*) FROM customer_orders WHERE DATE(created_at) = CURDATE()",
        'sales_today' => "SELECT COALESCE(SUM(final_amount), 0) FROM customer_orders WHERE DATE(created_at) = CURDATE() AND status IN ('completed', 'delivered')",
        'pending_orders' => "SELECT COUNT(*) FROM customer_orders WHERE status = 'pending'",
        'low_stock' => "SELECT COUNT(*) FROM products WHERE stock_quantity <= min_stock_level"
    ];
    
    foreach ($stats_queries as $label => $query) {
        try {
            $result = $db->query($query)->fetchColumn();
            if ($label == 'sales_today') {
                echo "💰 $label: " . number_format($result, 2) . " SAR\n";
            } else {
                echo "📊 $label: " . number_format($result) . "\n";
            }
        } catch (Exception $e) {
            echo "❌ $label: Error - " . $e->getMessage() . "\n";
        }
    }
    
    echo "\n🎯 RECOMMENDATIONS:\n";
    echo "==================\n";
    
    $total_customers = $db->query("SELECT COUNT(*) FROM customers")->fetchColumn();
    $total_orders = $db->query("SELECT COUNT(*) FROM customer_orders")->fetchColumn();
    $total_products = $db->query("SELECT COUNT(*) FROM products")->fetchColumn();
    
    if ($total_customers == 0) {
        echo "⚠️  No customers found - Run add_sample_data.php to add test data\n";
    }
    if ($total_orders == 0) {
        echo "⚠️  No orders found - System needs sample orders for statistics\n";
    }
    if ($total_products == 0) {
        echo "⚠️  No products found - Add products to enable inventory tracking\n";
    }
    
    if ($total_customers > 0 && $total_orders > 0) {
        echo "✅ Database has data - Statistics should work properly\n";
    }
    
} catch (PDOException $e) {
    echo "\n❌ Database Error: " . $e->getMessage() . "\n";
    echo "🔧 Check your database connection and permissions\n";
} catch (Exception $e) {
    echo "\n❌ System Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "</div>";

echo "<div style='text-align: center; margin: 20px;'>";
echo "<a href='add_sample_data.php' style='background: #28a745; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>📊 Add Sample Data</a>";
echo "<a href='index.php' style='background: #007bff; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>🏠 Dashboard</a>";
echo "<a href='modules/customers/index.php' style='background: #6f42c1; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>👥 Customers</a>";
echo "</div>";
?>
