<?php
/**
 * Create Purchases Database Structure
 * Senior PHP/MySQL Engineer Implementation
 * Based on the requirements from the uploaded images
 */

require_once '../config/database.php';

echo "<h1>🔧 إنشاء قاعدة بيانات نظام المشتريات</h1>";
echo "<div style='font-family: monospace; background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<pre>";

try {
    echo "=== Creating Purchases Database Structure ===\n";
    echo "============================================\n\n";
    
    // 1. Create suppliers table
    echo "STEP 1: إنشاء جدول الموردين...\n";
    echo "----------------------------\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS suppliers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            supplier_code VARCHAR(50) UNIQUE NOT NULL,
            name VARCHAR(255) NOT NULL,
            contact_person VARCHAR(255),
            phone VARCHAR(20),
            email VARCHAR(255),
            address TEXT,
            city VARCHAR(100),
            country VARCHAR(100),
            tax_number VARCHAR(50),
            payment_terms VARCHAR(100),
            credit_limit DECIMAL(15,2) DEFAULT 0.00,
            current_balance DECIMAL(15,2) DEFAULT 0.00,
            is_active TINYINT(1) DEFAULT 1,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_supplier_code (supplier_code),
            INDEX idx_name (name),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول الموردين\n";
    
    // 2. Create purchase_groups table
    echo "\nSTEP 2: إنشاء جدول مجموعات الشراء...\n";
    echo "-----------------------------------\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS purchase_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_number VARCHAR(50) UNIQUE NOT NULL,
            group_name VARCHAR(255) NOT NULL,
            description TEXT,
            status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
            total_amount DECIMAL(15,2) DEFAULT 0.00,
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_group_number (group_number),
            INDEX idx_status (status),
            INDEX idx_created_by (created_by)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول مجموعات الشراء\n";
    
    // 3. Create purchase_orders table
    echo "\nSTEP 3: إنشاء جدول طلبات الشراء...\n";
    echo "--------------------------------\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS purchase_orders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(50) UNIQUE NOT NULL,
            supplier_id INT NOT NULL,
            purchase_group_id INT NULL,
            order_date DATE NOT NULL,
            expected_delivery_date DATE,
            status ENUM('draft', 'pending', 'approved', 'ordered', 'partial_received', 'received', 'cancelled') DEFAULT 'draft',
            subtotal DECIMAL(15,2) DEFAULT 0.00,
            tax_amount DECIMAL(15,2) DEFAULT 0.00,
            discount_amount DECIMAL(15,2) DEFAULT 0.00,
            shipping_cost DECIMAL(15,2) DEFAULT 0.00,
            total_amount DECIMAL(15,2) DEFAULT 0.00,
            payment_terms VARCHAR(100),
            delivery_address TEXT,
            notes TEXT,
            created_by INT NOT NULL,
            approved_by INT NULL,
            approved_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
            FOREIGN KEY (purchase_group_id) REFERENCES purchase_groups(id) ON DELETE SET NULL,
            INDEX idx_order_number (order_number),
            INDEX idx_supplier_id (supplier_id),
            INDEX idx_purchase_group_id (purchase_group_id),
            INDEX idx_status (status),
            INDEX idx_order_date (order_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول طلبات الشراء\n";
    
    // 4. Create purchase_order_items table
    echo "\nSTEP 4: إنشاء جدول عناصر طلبات الشراء...\n";
    echo "--------------------------------------\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS purchase_order_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            purchase_order_id INT NOT NULL,
            product_id INT NOT NULL,
            quantity INT NOT NULL,
            unit_price DECIMAL(15,2) NOT NULL,
            total_price DECIMAL(15,2) NOT NULL,
            received_quantity INT DEFAULT 0,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE CASCADE,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
            INDEX idx_purchase_order_id (purchase_order_id),
            INDEX idx_product_id (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول عناصر طلبات الشراء\n";
    
    // 5. Create purchase_receipts table
    echo "\nSTEP 5: إنشاء جدول استلام المشتريات...\n";
    echo "-----------------------------------\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS purchase_receipts (
            id INT AUTO_INCREMENT PRIMARY KEY,
            receipt_number VARCHAR(50) UNIQUE NOT NULL,
            purchase_order_id INT NOT NULL,
            supplier_id INT NOT NULL,
            receipt_date DATE NOT NULL,
            total_received_amount DECIMAL(15,2) DEFAULT 0.00,
            status ENUM('partial', 'complete') DEFAULT 'partial',
            notes TEXT,
            received_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (purchase_order_id) REFERENCES purchase_orders(id) ON DELETE RESTRICT,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE RESTRICT,
            INDEX idx_receipt_number (receipt_number),
            INDEX idx_purchase_order_id (purchase_order_id),
            INDEX idx_supplier_id (supplier_id),
            INDEX idx_receipt_date (receipt_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول استلام المشتريات\n";
    
    // 6. Create purchase_receipt_items table
    echo "\nSTEP 6: إنشاء جدول عناصر استلام المشتريات...\n";
    echo "----------------------------------------\n";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS purchase_receipt_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            purchase_receipt_id INT NOT NULL,
            purchase_order_item_id INT NOT NULL,
            product_id INT NOT NULL,
            ordered_quantity INT NOT NULL,
            received_quantity INT NOT NULL,
            unit_price DECIMAL(15,2) NOT NULL,
            total_amount DECIMAL(15,2) NOT NULL,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (purchase_receipt_id) REFERENCES purchase_receipts(id) ON DELETE CASCADE,
            FOREIGN KEY (purchase_order_item_id) REFERENCES purchase_order_items(id) ON DELETE RESTRICT,
            FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE RESTRICT,
            INDEX idx_purchase_receipt_id (purchase_receipt_id),
            INDEX idx_purchase_order_item_id (purchase_order_item_id),
            INDEX idx_product_id (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✅ تم إنشاء جدول عناصر استلام المشتريات\n";
    
    // 7. Add sample suppliers
    echo "\nSTEP 7: إضافة موردين تجريبيين...\n";
    echo "------------------------------\n";
    
    $sample_suppliers = [
        ['SUP-001', 'شركة التقنية المتقدمة', 'أحمد محمد', '0501234567', 'info@techadvanced.com', 'الرياض، المملكة العربية السعودية', 'الرياض', 'السعودية', '123456789', 'نقد عند التسليم'],
        ['SUP-002', 'مؤسسة الإلكترونيات الحديثة', 'سارة أحمد', '0509876543', 'sales@modernelec.com', 'جدة، المملكة العربية السعودية', 'جدة', 'السعودية', '987654321', '30 يوم'],
        ['SUP-003', 'شركة المكتبيات الشاملة', 'محمد علي', '0551122334', 'orders@officecomplete.com', 'الدمام، المملكة العربية السعودية', 'الدمام', 'السعودية', '456789123', '15 يوم'],
        ['SUP-004', 'مجموعة الأثاث العصري', 'فاطمة حسن', '0556677889', 'info@modernfurniture.com', 'الخبر، المملكة العربية السعودية', 'الخبر', 'السعودية', '789123456', 'نقد عند التسليم'],
        ['SUP-005', 'شركة المواد الغذائية الطازجة', 'عبدالله سالم', '0544455667', 'supply@freshfoods.com', 'مكة، المملكة العربية السعودية', 'مكة', 'السعودية', '321654987', '7 أيام']
    ];
    
    $insert_supplier = $db->prepare("
        INSERT INTO suppliers (supplier_code, name, contact_person, phone, email, address, city, country, tax_number, payment_terms, is_active) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE 
        name = VALUES(name),
        contact_person = VALUES(contact_person),
        phone = VALUES(phone),
        email = VALUES(email)
    ");
    
    foreach ($sample_suppliers as $supplier) {
        $insert_supplier->execute($supplier);
        echo "✅ تم إضافة المورد: {$supplier[1]}\n";
    }
    
    echo "\nSTEP 8: إحصائيات النظام...\n";
    echo "------------------------\n";
    
    $stats = $db->query("
        SELECT 
            (SELECT COUNT(*) FROM suppliers WHERE is_active = 1) as active_suppliers,
            (SELECT COUNT(*) FROM purchase_orders) as total_orders,
            (SELECT COUNT(*) FROM purchase_groups) as total_groups,
            (SELECT COUNT(*) FROM products WHERE is_active = 1) as available_products
    ")->fetch();
    
    echo "📊 الموردين النشطين: " . $stats['active_suppliers'] . "\n";
    echo "📋 طلبات الشراء: " . $stats['total_orders'] . "\n";
    echo "👥 مجموعات الشراء: " . $stats['total_groups'] . "\n";
    echo "📦 المنتجات المتاحة: " . $stats['available_products'] . "\n";
    
    echo "\n🎉 تم إنشاء قاعدة بيانات نظام المشتريات بنجاح!\n";
    echo "===============================================\n";
    echo "✅ 6 جداول رئيسية\n";
    echo "✅ " . count($sample_suppliers) . " موردين تجريبيين\n";
    echo "✅ علاقات قاعدة البيانات محددة\n";
    echo "✅ فهارس محسنة للأداء\n";
    echo "✅ دعم كامل للغة العربية\n";
    
} catch (PDOException $e) {
    echo "\n❌ Database Error: " . $e->getMessage() . "\n";
    echo "🔧 تأكد من اتصال قاعدة البيانات والصلاحيات\n";
} catch (Exception $e) {
    echo "\n❌ System Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "</div>";

echo "<div style='text-align: center; margin: 20px;'>";
echo "<a href='modules/purchases/index.php' style='background: #007bff; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>🛒 إدارة المشتريات</a>";
echo "<a href='modules/purchases/suppliers.php' style='background: #28a745; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>🏢 الموردين</a>";
echo "<a href='modules/purchases/groups.php' style='background: #6f42c1; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>👥 مجموعات الشراء</a>";
echo "<a href='index.php' style='background: #17a2b8; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>🏠 الرئيسية</a>";
echo "</div>";
?>
