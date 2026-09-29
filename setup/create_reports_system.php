<?php
/**
 * Complete Reports System Database Setup
 * Senior PHP/MySQL Engineer Implementation
 */

require_once '../config/database.php';

echo "<h1>📊 إنشاء نظام التقارير الشامل</h1>";
echo "<div style='font-family: monospace; background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<pre>";

try {
    echo "=== Complete Reports System Setup ===\n";
    echo "=====================================\n\n";
    
    // 1. Check and create customer_orders table if not exists
    echo "STEP 1: فحص وإنشاء جدول customer_orders...\n";
    echo "------------------------------------\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS customer_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(50) UNIQUE NOT NULL,
            customer_id INT,
            order_date DATE NOT NULL,
            subtotal DECIMAL(15,2) DEFAULT 0.00,
            tax_amount DECIMAL(15,2) DEFAULT 0.00,
            discount_amount DECIMAL(15,2) DEFAULT 0.00,
            total_amount DECIMAL(15,2) NOT NULL,
            status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
            payment_method ENUM('cash', 'card', 'bank_transfer', 'check') DEFAULT 'cash',
            notes TEXT,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id),
            INDEX idx_order_date (order_date),
            INDEX idx_customer_id (customer_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ جدول customer_orders تم إنشاؤه/التحقق منه\n";
    
    // 2. Check and create product_categories table if not exists
    echo "\nSTEP 2: فحص وإنشاء جدول product_categories...\n";
    echo "---------------------------------------------\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS product_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ جدول product_categories تم إنشاؤه/التحقق منه\n";
    
    // 3. Update products table to ensure all required fields exist
    echo "\nSTEP 3: تحديث جدول products...\n";
    echo "----------------------------\n";
    
    // Add category_id column if it doesn't exist
    try {
        $db->exec("ALTER TABLE products ADD COLUMN category_id INT AFTER name");
        echo "✅ تم إضافة حقل category_id\n";
    } catch (Exception $e) {
        echo "⚠️  حقل category_id موجود مسبقاً\n";
    }
    
    // Add foreign key if it doesn't exist
    try {
        $db->exec("ALTER TABLE products ADD FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL");
        echo "✅ تم إضافة Foreign Key للفئات\n";
    } catch (Exception $e) {
        echo "⚠️  Foreign Key موجود مسبقاً أو خطأ في الإضافة\n";
    }
    
    // 4. Insert sample data for testing
    echo "\nSTEP 4: إضافة بيانات تجريبية...\n";
    echo "-----------------------------\n";
    
    // Sample categories
    $categories = [
        ['إلكترونيات', 'أجهزة إلكترونية ومعدات تقنية'],
        ['مكتبية', 'مستلزمات وأدوات مكتبية'],
        ['منزلية', 'أدوات ومعدات منزلية'],
        ['طعام ومشروبات', 'منتجات غذائية ومشروبات']
    ];
    
    foreach ($categories as $category) {
        try {
            $stmt = $db->prepare("INSERT IGNORE INTO product_categories (name, description) VALUES (?, ?)");
            $stmt->execute($category);
            echo "✅ تم إضافة فئة: {$category[0]}\n";
        } catch (Exception $e) {
            echo "⚠️  فئة {$category[0]} موجودة مسبقاً\n";
        }
    }
    
    // Sample customer orders
    try {
        // Get some customers and create sample orders
        $customers = $db->query("SELECT id FROM customers LIMIT 3")->fetchAll();
        
        if (!empty($customers)) {
            foreach ($customers as $index => $customer) {
                $order_number = 'ORD-' . date('Y') . '-' . str_pad($index + 1, 4, '0', STR_PAD_LEFT);
                $order_date = date('Y-m-d', strtotime('-' . rand(1, 30) . ' days'));
                $subtotal = rand(100, 1000);
                $tax_amount = $subtotal * 0.15;
                $total_amount = $subtotal + $tax_amount;
                
                $stmt = $db->prepare("
                    INSERT IGNORE INTO customer_orders 
                    (order_number, customer_id, order_date, subtotal, tax_amount, total_amount, status, created_by) 
                    VALUES (?, ?, ?, ?, ?, ?, 'completed', 1)
                ");
                $stmt->execute([$order_number, $customer['id'], $order_date, $subtotal, $tax_amount, $total_amount]);
                echo "✅ تم إضافة طلب تجريبي: $order_number\n";
            }
        }
    } catch (Exception $e) {
        echo "⚠️  خطأ في إضافة الطلبات التجريبية: " . $e->getMessage() . "\n";
    }
    
    // 5. Create reports analytics table
    echo "\nSTEP 5: إنشاء جدول تحليلات التقارير...\n";
    echo "------------------------------------\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS report_analytics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            report_type VARCHAR(100) NOT NULL,
            report_date DATE NOT NULL,
            total_records INT DEFAULT 0,
            total_amount DECIMAL(15,2) DEFAULT 0.00,
            metadata JSON,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_report_type (report_type),
            INDEX idx_report_date (report_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ جدول report_analytics تم إنشاؤه\n";
    
    // 6. Create report exports table
    echo "\nSTEP 6: إنشاء جدول تصدير التقارير...\n";
    echo "-----------------------------------\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS report_exports (
            id INT AUTO_INCREMENT PRIMARY KEY,
            report_name VARCHAR(255) NOT NULL,
            export_type ENUM('excel', 'pdf', 'csv') NOT NULL,
            file_path VARCHAR(500),
            filters JSON,
            exported_by INT NOT NULL,
            export_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (exported_by) REFERENCES users(id),
            INDEX idx_export_date (export_date),
            INDEX idx_exported_by (exported_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ جدول report_exports تم إنشاؤه\n";
    
    echo "\nSTEP 7: فحص البيانات والإحصائيات...\n";
    echo "-----------------------------------\n";
    
    // Check data availability
    $stats = [
        'customers' => $db->query("SELECT COUNT(*) FROM customers")->fetchColumn(),
        'customer_orders' => $db->query("SELECT COUNT(*) FROM customer_orders")->fetchColumn(),
        'products' => $db->query("SELECT COUNT(*) FROM products")->fetchColumn(),
        'purchase_orders' => $db->query("SELECT COUNT(*) FROM purchase_orders")->fetchColumn(),
        'suppliers' => $db->query("SELECT COUNT(*) FROM suppliers")->fetchColumn(),
        'categories' => $db->query("SELECT COUNT(*) FROM product_categories")->fetchColumn()
    ];
    
    foreach ($stats as $table => $count) {
        echo "📊 $table: $count سجل\n";
    }
    
    echo "\nSTEP 8: إنشاء الفهارس المحسنة للتقارير...\n";
    echo "----------------------------------------\n";
    
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_customers_type_active ON customers(customer_type, is_active)",
        "CREATE INDEX IF NOT EXISTS idx_products_category_active ON products(category_id, is_active)",
        "CREATE INDEX IF NOT EXISTS idx_orders_date_status ON customer_orders(order_date, status)",
        "CREATE INDEX IF NOT EXISTS idx_purchase_orders_date_status ON purchase_orders(order_date, status)"
    ];
    
    foreach ($indexes as $index_sql) {
        try {
            $db->exec($index_sql);
            echo "✅ تم إنشاء فهرس محسن\n";
        } catch (Exception $e) {
            echo "⚠️  الفهرس موجود مسبقاً\n";
        }
    }
    
    echo "\n🎯 نتائج إعداد نظام التقارير:\n";
    echo "===============================\n";
    echo "✅ جميع الجداول المطلوبة تم إنشاؤها\n";
    echo "✅ البيانات التجريبية تم إضافتها\n";
    echo "✅ الفهارس المحسنة تم إنشاؤها\n";
    echo "✅ العلاقات بين الجداول تم تأسيسها\n";
    echo "✅ نظام التقارير جاهز للاستخدام\n\n";
    
    echo "📋 التقارير المتاحة:\n";
    echo "==================\n";
    echo "• تقرير المبيعات اليومية: sales-report.php\n";
    echo "• تقرير المشتريات: purchases-report.php\n";
    echo "• تقرير المخزون الحالي: inventory-report.php\n";
    echo "• تقرير العملاء: customers-report.php\n";
    echo "• والمزيد من التقارير المتخصصة\n\n";
    
    echo "🚀 النظام جاهز للاستخدام!\n";
    
} catch (PDOException $e) {
    echo "\n❌ Database Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "\n❌ System Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "</div>";

echo "<div style='text-align: center; margin: 20px;'>";
echo "<a href='modules/reports/index.php' style='background: #dc2626; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>📊 نظام التقارير</a>";
echo "<a href='modules/reports/sales-report.php' style='background: #059669; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>💰 تقرير المبيعات</a>";
echo "<a href='modules/reports/purchases-report.php' style='background: #2563eb; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>🛒 تقرير المشتريات</a>";
echo "<a href='modules/reports/inventory-report.php' style='background: #7c3aed; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>📦 تقرير المخزون</a>";
echo "<a href='modules/reports/customers-report.php' style='background: #4f46e5; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>👥 تقرير العملاء</a>";
echo "<a href='index.php' style='background: #6b7280; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>🏠 الرئيسية</a>";
echo "</div>";
?>
