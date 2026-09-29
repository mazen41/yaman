<?php
/**
 * Complete Coupons Management System Setup
 * Senior Developer Implementation
 */

require_once '../config/database.php';

echo "<h1>🎫 إعداد نظام إدارة الكوبونات</h1>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; border-radius: 5px;'>";
echo "<pre>";

try {
    echo "=== إعداد نظام إدارة الكوبونات الشامل ===\n\n";
    
    // Step 1: Create coupons table
    echo "STEP 1: إنشاء جدول الكوبونات...\n";
    echo "--------------------------------\n";
    
    $coupons_table = "
        CREATE TABLE IF NOT EXISTS coupons (
            id INT AUTO_INCREMENT PRIMARY KEY,
            coupon_code VARCHAR(50) NOT NULL UNIQUE,
            coupon_name VARCHAR(255) NOT NULL,
            description TEXT DEFAULT NULL,
            discount_type ENUM('percentage', 'fixed_amount') NOT NULL,
            discount_value DECIMAL(10,2) NOT NULL,
            min_order_amount DECIMAL(10,2) DEFAULT 0.00,
            max_discount_amount DECIMAL(10,2) DEFAULT NULL,
            usage_limit INT DEFAULT NULL,
            usage_count INT DEFAULT 0,
            user_usage_limit INT DEFAULT 1,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            is_active TINYINT(1) DEFAULT 1,
            applies_to ENUM('all_products', 'specific_categories', 'specific_products') DEFAULT 'all_products',
            created_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_coupon_code (coupon_code),
            INDEX idx_active_dates (is_active, start_date, end_date),
            INDEX idx_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($coupons_table);
    echo "✅ تم إنشاء جدول coupons\n";
    
    // Step 2: Create coupon usage tracking table
    echo "\nSTEP 2: إنشاء جدول تتبع استخدام الكوبونات...\n";
    echo "--------------------------------------------\n";
    
    $coupon_usage_table = "
        CREATE TABLE IF NOT EXISTS coupon_usage (
            id INT AUTO_INCREMENT PRIMARY KEY,
            coupon_id INT NOT NULL,
            order_id INT NOT NULL,
            customer_id INT DEFAULT NULL,
            discount_amount DECIMAL(10,2) NOT NULL,
            used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
            FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE,
            FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
            INDEX idx_coupon_usage (coupon_id, customer_id),
            INDEX idx_order_coupon (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($coupon_usage_table);
    echo "✅ تم إنشاء جدول coupon_usage\n";
    
    // Step 3: Create coupon categories relation table
    echo "\nSTEP 3: إنشاء جدول ربط الكوبونات بالفئات...\n";
    echo "-------------------------------------------\n";
    
    $coupon_categories_table = "
        CREATE TABLE IF NOT EXISTS coupon_categories (
            id INT AUTO_INCREMENT PRIMARY KEY,
            coupon_id INT NOT NULL,
            category_id INT NOT NULL,
            FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE CASCADE,
            UNIQUE KEY unique_coupon_category (coupon_id, category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    $db->exec($coupon_categories_table);
    echo "✅ تم إنشاء جدول coupon_categories\n";
    
    // Step 4: Add coupon fields to orders table
    echo "\nSTEP 4: إضافة حقول الكوبونات لجدول الطلبات...\n";
    echo "---------------------------------------------\n";
    
    try {
        // Check if columns exist first
        $columns = $db->query("DESCRIBE customer_orders")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('coupon_id', $columns)) {
            $db->exec("ALTER TABLE customer_orders ADD COLUMN coupon_id INT DEFAULT NULL");
            echo "✅ تم إضافة حقل coupon_id\n";
        }
        
        if (!in_array('coupon_code', $columns)) {
            $db->exec("ALTER TABLE customer_orders ADD COLUMN coupon_code VARCHAR(50) DEFAULT NULL");
            echo "✅ تم إضافة حقل coupon_code\n";
        }
        
        if (!in_array('coupon_discount', $columns)) {
            $db->exec("ALTER TABLE customer_orders ADD COLUMN coupon_discount DECIMAL(10,2) DEFAULT 0.00");
            echo "✅ تم إضافة حقل coupon_discount\n";
        }
        
        // Add foreign key constraint
        $db->exec("ALTER TABLE customer_orders ADD FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL");
        echo "✅ تم إضافة قيود المفاتيح الخارجية\n";
        
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "⚠️ الحقول موجودة مسبقاً\n";
        } else {
            echo "⚠️ تحذير: " . $e->getMessage() . "\n";
        }
    }
    
    // Step 5: Insert sample coupons
    echo "\nSTEP 5: إدراج كوبونات تجريبية...\n";
    echo "-------------------------------\n";
    
    $sample_coupons = [
        [
            'coupon_code' => 'WELCOME10',
            'coupon_name' => 'خصم الترحيب',
            'description' => 'خصم 10% للعملاء الجدد',
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'min_order_amount' => 100.00,
            'max_discount_amount' => 50.00,
            'usage_limit' => 100,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+30 days'))
        ],
        [
            'coupon_code' => 'SAVE50',
            'coupon_name' => 'وفر 50 ريال',
            'description' => 'خصم ثابت 50 ريال على الطلبات أكثر من 300 ريال',
            'discount_type' => 'fixed_amount',
            'discount_value' => 50.00,
            'min_order_amount' => 300.00,
            'max_discount_amount' => NULL,
            'usage_limit' => 50,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+60 days'))
        ],
        [
            'coupon_code' => 'RAMADAN2025',
            'coupon_name' => 'عروض رمضان',
            'description' => 'خصم 15% بمناسبة شهر رمضان المبارك',
            'discount_type' => 'percentage',
            'discount_value' => 15.00,
            'min_order_amount' => 200.00,
            'max_discount_amount' => 100.00,
            'usage_limit' => 200,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+90 days'))
        ]
    ];
    
    $insert_coupon = $db->prepare("
        INSERT INTO coupons 
        (coupon_code, coupon_name, description, discount_type, discount_value, 
         min_order_amount, max_discount_amount, usage_limit, start_date, end_date, created_by)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE coupon_name = VALUES(coupon_name)
    ");
    
    foreach ($sample_coupons as $coupon) {
        $insert_coupon->execute(array_values($coupon));
        echo "✅ تم إدراج كوبون: {$coupon['coupon_code']}\n";
    }
    
    // Step 6: Create directory structure
    echo "\nSTEP 6: إنشاء هيكل المجلدات...\n";
    echo "-----------------------------\n";
    
    $coupons_dir = 'modules/coupons';
    if (!is_dir($coupons_dir)) {
        mkdir($coupons_dir, 0755, true);
        echo "✅ تم إنشاء مجلد $coupons_dir\n";
    } else {
        echo "✅ مجلد $coupons_dir موجود\n";
    }
    
    echo "\n=== تم الإعداد بنجاح ===\n";
    echo "✅ جداول قاعدة البيانات جاهزة\n";
    echo "✅ كوبونات تجريبية تم إدراجها\n";
    echo "✅ هيكل المجلدات جاهز\n";
    echo "✅ النظام جاهز لإنشاء الواجهات\n\n";
    
    // Display current coupons
    echo "الكوبونات المتاحة حالياً:\n";
    echo "------------------------\n";
    $current_coupons = $db->query("SELECT coupon_code, coupon_name, discount_type, discount_value, is_active FROM coupons ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($current_coupons as $coupon) {
        $status = $coupon['is_active'] ? '🟢 نشط' : '🔴 معطل';
        $discount = $coupon['discount_type'] == 'percentage' ? $coupon['discount_value'] . '%' : $coupon['discount_value'] . ' ريال';
        echo "• {$coupon['coupon_code']} - {$coupon['coupon_name']} ($discount) $status\n";
    }
    
} catch (PDOException $e) {
    echo "\n❌ خطأ في قاعدة البيانات: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "\n❌ خطأ في النظام: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "</div>";

echo "<div style='margin-top: 20px; text-align: center;'>";
echo "<a href='create_coupons_interface.php' style='background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🎫 إنشاء واجهات الكوبونات</a>";
echo "<a href='update_sidebar.php' style='background: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>📋 تحديث الشريط الجانبي</a>";
echo "<a href='modules/orders/index.php' style='background: #FF9800; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>📦 الطلبات</a>";
echo "</div>";
?>
