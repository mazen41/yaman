<?php
/**
 * Advanced Purchases System Database Enhancement
 * Senior PHP/MySQL Engineer Implementation
 * Based on detailed requirements from uploaded images
 */

require_once '../config/database.php';

echo "<h1>🚀 إنشاء النظام المتقدم لإدارة المشتريات</h1>";
echo "<div style='font-family: monospace; background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<pre>";

try {
    echo "=== Advanced Purchases System Enhancement ===\n";
    echo "=============================================\n\n";
    
    // 1. Create purchase_baskets table for bulk ordering
    echo "STEP 1: إنشاء جدول سلة المشتريات...\n";
    echo "--------------------------------\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS purchase_baskets (
            id INT AUTO_INCREMENT PRIMARY KEY,
            basket_name VARCHAR(255) NOT NULL,
            description TEXT,
            created_by INT NOT NULL,
            status ENUM('active', 'ordered', 'cancelled') DEFAULT 'active',
            total_items INT DEFAULT 0,
            estimated_total DECIMAL(15,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_created_by (created_by),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول سلة المشتريات\n";
    
    // 2. Create purchase_basket_items table
    echo "\nSTEP 2: إنشاء جدول عناصر سلة المشتريات...\n";
    echo "--------------------------------------\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS purchase_basket_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            basket_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL,
            estimated_price DECIMAL(15,2) NOT NULL,
            notes TEXT,
            priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (basket_id) REFERENCES purchase_baskets(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
            INDEX idx_basket_id (basket_id),
            INDEX idx_product_id (product_id),
            INDEX idx_priority (priority)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول عناصر سلة المشتريات\n";
    
    // 3. Create purchase_approvals table for workflow
    echo "\nSTEP 3: إنشاء جدول موافقات الشراء...\n";
    echo "--------------------------------\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS purchase_approvals (
            id INT AUTO_INCREMENT PRIMARY KEY,
            purchase_order_id INT NOT NULL,
            approver_id INT NOT NULL,
            approval_level INT NOT NULL,
            status ENUM('pending', 'approved', 'rejected', 'cancelled') DEFAULT 'pending',
            comments TEXT,
            approved_amount DECIMAL(15,2),
            approved_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
            INDEX idx_purchase_order_id (purchase_order_id),
            INDEX idx_approver_id (approver_id),
            INDEX idx_status (status),
            INDEX idx_approval_level (approval_level)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول موافقات الشراء\n";
    
    // 4. Create purchase_modifications table
    echo "\nSTEP 4: إنشاء جدول تعديلات الطلبات...\n";
    echo "--------------------------------\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS purchase_modifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            purchase_order_id INT NOT NULL,
            modification_type ENUM('quantity_change', 'price_change', 'item_addition', 'item_removal', 'supplier_change', 'date_change') NOT NULL,
            old_value TEXT,
            new_value TEXT,
            reason TEXT,
            modified_by INT NOT NULL,
            approved_by INT NULL,
            status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            approved_at TIMESTAMP NULL,
            FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
            INDEX idx_purchase_order_id (purchase_order_id),
            INDEX idx_modification_type (modification_type),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول تعديلات الطلبات\n";
    
    // 5. Create purchase_delivery_tracking table
    echo "\nSTEP 5: إنشاء جدول تتبع التوصيل...\n";
    echo "------------------------------\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS purchase_delivery_tracking (
            id INT AUTO_INCREMENT PRIMARY KEY,
            purchase_order_id INT NOT NULL,
            tracking_number VARCHAR(100),
            carrier_name VARCHAR(255),
            shipping_method VARCHAR(100),
            estimated_delivery DATE,
            actual_delivery DATE NULL,
            delivery_status ENUM('preparing', 'shipped', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'returned') DEFAULT 'preparing',
            delivery_address TEXT,
            recipient_name VARCHAR(255),
            recipient_phone VARCHAR(20),
            tracking_notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
            INDEX idx_purchase_order_id (purchase_order_id),
            INDEX idx_tracking_number (tracking_number),
            INDEX idx_delivery_status (delivery_status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول تتبع التوصيل\n";
    
    // 6. Create purchase_analytics table
    echo "\nSTEP 6: إنشاء جدول تحليلات المشتريات...\n";
    echo "-----------------------------------\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS purchase_analytics (
            id INT AUTO_INCREMENT PRIMARY KEY,
            supplier_id INT NOT NULL,
            period_month INT NOT NULL,
            period_year INT NOT NULL,
            total_orders INT DEFAULT 0,
            total_amount DECIMAL(15,2) DEFAULT 0.00,
            average_order_value DECIMAL(15,2) DEFAULT 0.00,
            on_time_deliveries INT DEFAULT 0,
            late_deliveries INT DEFAULT 0,
            cancelled_orders INT DEFAULT 0,
            quality_rating DECIMAL(3,2) DEFAULT 0.00,
            performance_score DECIMAL(5,2) DEFAULT 0.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE,
            UNIQUE KEY unique_supplier_period (supplier_id, period_month, period_year),
            INDEX idx_period (period_year, period_month),
            INDEX idx_performance_score (performance_score)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول تحليلات المشتريات\n";
    
    // 7. Add new columns to existing tables
    echo "\nSTEP 7: تحديث الجداول الموجودة...\n";
    echo "-----------------------------\n";
    
    // Add columns to purchase_orders if they don't exist
    $columns_to_add = [
        'priority' => "ALTER TABLE purchase_orders ADD COLUMN priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium' AFTER status",
        'approval_status' => "ALTER TABLE purchase_orders ADD COLUMN approval_status ENUM('not_required', 'pending', 'approved', 'rejected') DEFAULT 'not_required' AFTER priority",
        'modification_count' => "ALTER TABLE purchase_orders ADD COLUMN modification_count INT DEFAULT 0 AFTER approval_status",
        'last_modified_at' => "ALTER TABLE purchase_orders ADD COLUMN last_modified_at TIMESTAMP NULL AFTER modification_count"
    ];
    
    foreach ($columns_to_add as $column => $sql) {
        try {
            $check = $db->query("SHOW COLUMNS FROM purchase_orders LIKE '$column'")->fetch();
            if (!$check) {
                $db->exec($sql);
                echo "✅ تم إضافة العمود $column إلى جدول purchase_orders\n";
            } else {
                echo "⚠️  العمود $column موجود مسبقاً\n";
            }
        } catch (Exception $e) {
            echo "❌ خطأ في إضافة العمود $column: " . $e->getMessage() . "\n";
        }
    }
    
    // 8. Create sample data for advanced features
    echo "\nSTEP 8: إضافة بيانات تجريبية متقدمة...\n";
    echo "-----------------------------------\n";
    
    // Sample purchase baskets
    $sample_baskets = [
        ['مشتريات مكتبية شهرية', 'مشتريات المكتب للشهر الحالي', 1],
        ['مستلزمات تقنية', 'أجهزة ومعدات تقنية للقسم', 1],
        ['مواد تنظيف', 'مواد تنظيف وصيانة المكتب', 1]
    ];
    
    $basket_stmt = $db->prepare("
        INSERT INTO purchase_baskets (basket_name, description, created_by) 
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE description = VALUES(description)
    ");
    
    foreach ($sample_baskets as $basket) {
        $basket_stmt->execute($basket);
        echo "✅ تم إضافة سلة المشتريات: {$basket[0]}\n";
    }
    
    // Update some purchase orders with advanced fields
    $db->exec("
        UPDATE purchase_orders 
        SET priority = 'high', approval_status = 'approved' 
        WHERE id <= 2
    ");
    
    echo "\nSTEP 9: إنشاء الفهارس المحسنة...\n";
    echo "-----------------------------\n";
    
    // Create composite indexes for better performance
    $indexes = [
        "CREATE INDEX IF NOT EXISTS idx_orders_status_priority ON purchase_orders(status, priority)",
        "CREATE INDEX IF NOT EXISTS idx_orders_supplier_date ON purchase_orders(supplier_id, order_date)",
        "CREATE INDEX IF NOT EXISTS idx_approvals_status_level ON purchase_approvals(status, approval_level)",
        "CREATE INDEX IF NOT EXISTS idx_delivery_status_date ON purchase_delivery_tracking(delivery_status, estimated_delivery)"
    ];
    
    foreach ($indexes as $index_sql) {
        try {
            $db->exec($index_sql);
            echo "✅ تم إنشاء فهرس محسن\n";
        } catch (Exception $e) {
            echo "⚠️  الفهرس موجود مسبقاً أو خطأ: " . $e->getMessage() . "\n";
        }
    }
    
    echo "\nSTEP 10: إحصائيات النظام المتقدم...\n";
    echo "--------------------------------\n";
    
    $advanced_stats = $db->query("
        SELECT 
            (SELECT COUNT(*) FROM purchase_baskets) as total_baskets,
            (SELECT COUNT(*) FROM purchase_approvals) as total_approvals,
            (SELECT COUNT(*) FROM purchase_modifications) as total_modifications,
            (SELECT COUNT(*) FROM purchase_delivery_tracking) as total_deliveries,
            (SELECT COUNT(*) FROM suppliers WHERE is_active = 1) as active_suppliers,
            (SELECT COUNT(*) FROM purchase_orders WHERE priority = 'high') as high_priority_orders
    ")->fetch();
    
    echo "📊 سلال المشتريات: " . $advanced_stats['total_baskets'] . "\n";
    echo "✅ الموافقات: " . $advanced_stats['total_approvals'] . "\n";
    echo "📝 التعديلات: " . $advanced_stats['total_modifications'] . "\n";
    echo "🚚 تتبع التوصيل: " . $advanced_stats['total_deliveries'] . "\n";
    echo "🏢 الموردين النشطين: " . $advanced_stats['active_suppliers'] . "\n";
    echo "⚡ الطلبات عالية الأولوية: " . $advanced_stats['high_priority_orders'] . "\n";
    
    echo "\n🎉 تم إنشاء النظام المتقدم لإدارة المشتريات بنجاح!\n";
    echo "================================================\n";
    echo "✅ 6 جداول جديدة للميزات المتقدمة\n";
    echo "✅ تحديث الجداول الموجودة بحقول إضافية\n";
    echo "✅ فهارس محسنة للأداء العالي\n";
    echo "✅ بيانات تجريبية للاختبار\n";
    echo "✅ نظام موافقات متدرج\n";
    echo "✅ تتبع التعديلات والتغييرات\n";
    echo "✅ إدارة التوصيل والشحن\n";
    echo "✅ تحليلات الأداء والإحصائيات\n";
    
} catch (PDOException $e) {
    echo "\n❌ Database Error: " . $e->getMessage() . "\n";
    echo "🔧 تأكد من اتصال قاعدة البيانات والصلاحيات\n";
} catch (Exception $e) {
    echo "\n❌ System Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "</div>";

echo "<div style='text-align: center; margin: 20px;'>";
echo "<a href='modules/purchases/basket.php' style='background: #28a745; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>🛒 سلة المشتريات</a>";
echo "<a href='modules/purchases/approvals.php' style='background: #007bff; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>✅ الموافقات</a>";
echo "<a href='modules/purchases/tracking.php' style='background: #6f42c1; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>🚚 التتبع</a>";
echo "<a href='modules/purchases/analytics.php' style='background: #fd7e14; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>📊 التحليلات</a>";
echo "<a href='modules/purchases/index.php' style='background: #17a2b8; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>🛒 المشتريات</a>";
echo "<a href='index.php' style='background: #6c757d; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>🏠 الرئيسية</a>";
echo "</div>";
?>
