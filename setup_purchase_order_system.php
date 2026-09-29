<?php
/**
 * Setup Purchase Order System
 * Creates all necessary database tables for the purchase order basket system
 */

// Database connection
$host = 'localhost';
$dbname = 'yassin_admin_system';
$username = 'root';
$password = '';

// Check if reset is requested
$reset = isset($_GET['reset']) && $_GET['reset'] == '1';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<!DOCTYPE html><html dir='rtl'><head><meta charset='utf-8'><title>Setup Purchase Order System</title></head><body style='font-family: Arial, sans-serif; padding: 20px;'>";
    echo "<h2>Setting up Purchase Order System...</h2>";
    
    // Always drop tables in correct order (child tables first)
    echo "<p>Dropping existing tables (if any)...</p>";
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("DROP TABLE IF EXISTS `purchase_order_tracking_codes`");
    $pdo->exec("DROP TABLE IF EXISTS `purchase_order_basket_items`");
    $pdo->exec("DROP TABLE IF EXISTS `purchase_order_baskets`");
    $pdo->exec("DROP TABLE IF EXISTS `purchase_groups`");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<p style='color: green;'>✅ Old tables dropped (if existed)</p>";
    
    // 1. Create purchase_groups table
    echo "<p>Creating purchase_groups table...</p>";
    $pdo->exec("
        CREATE TABLE `purchase_groups` (
          `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `group_name` VARCHAR(100) NOT NULL COMMENT 'اسم المجموعة',
          `description` TEXT DEFAULT NULL COMMENT 'وصف المجموعة',
          `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'نشط/غير نشط',
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX `idx_active` (`is_active`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='مجموعات الشراء'
    ");
    echo "<p style='color: green;'>✅ purchase_groups table created</p>";
    
    // 2. Create purchase_order_baskets table
    echo "<p>Creating purchase_order_baskets table...</p>";
    $pdo->exec("
        CREATE TABLE `purchase_order_baskets` (
          `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `order_number` VARCHAR(50) NOT NULL UNIQUE COMMENT 'رقم سلة الشراء',
          `serial_number` VARCHAR(50) NOT NULL UNIQUE COMMENT 'الرقم التسلسلي',
          `purchase_date` DATE NOT NULL COMMENT 'تاريخ الشراء',
          `purchase_group_id` INT(11) DEFAULT NULL COMMENT 'اختيار مجموعة الشراء',
          `total_items` INT(11) DEFAULT 0 COMMENT 'إجمالي عدد القطع',
          `total_discount_before` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'خصم النقطة',
          `total_discount_after` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'خصم النادي',
          `total_price_before_discount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'سعر السلة قبل الخصم',
          `total_price_after_discount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'سعر السلة بعد الخصم',
          `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending' COMMENT 'حالة الطلب',
          `tracking_code_1` VARCHAR(100) DEFAULT NULL COMMENT 'رمز التتبع 1 - قيد التعليق',
          `tracking_code_2` VARCHAR(100) DEFAULT NULL COMMENT 'رمز التتبع 2 - قيد الشحن',
          `notes` TEXT DEFAULT NULL COMMENT 'ملاحظات',
          `created_by` INT(11) NOT NULL COMMENT 'المستخدم الذي أنشأ الطلب',
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX `idx_order_number` (`order_number`),
          INDEX `idx_serial_number` (`serial_number`),
          INDEX `idx_purchase_date` (`purchase_date`),
          INDEX `idx_purchase_group` (`purchase_group_id`),
          INDEX `idx_status` (`status`),
          INDEX `idx_created_by` (`created_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='طلبات الشراء الرئيسية'
    ");
    
    // 3. Create purchase_order_basket_items table
    echo "<p>Creating purchase_order_basket_items table...</p>";
    $pdo->exec("
        CREATE TABLE `purchase_order_basket_items` (
          `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `basket_id` INT(11) NOT NULL COMMENT 'معرف سلة الشراء',
          `item_number` INT(11) NOT NULL COMMENT 'رقم العنصر (م)',
          `client_name` VARCHAR(200) NOT NULL COMMENT 'اسم العميل',
          `client_order_number` VARCHAR(50) NOT NULL COMMENT 'رقم الطلب',
          `client_phone` VARCHAR(20) NOT NULL COMMENT 'رقم جوال العميل',
          `item_quantity` INT(11) NOT NULL DEFAULT 1 COMMENT 'عدد القطع',
          `item_price` DECIMAL(10,2) NOT NULL COMMENT 'قيمة الطلب',
          `notes` TEXT DEFAULT NULL COMMENT 'ملاحظات',
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX `idx_basket` (`basket_id`),
          INDEX `idx_client_phone` (`client_phone`),
          INDEX `idx_client_order_number` (`client_order_number`),
          FOREIGN KEY (`basket_id`) REFERENCES `purchase_order_baskets`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='عناصر سلة الشراء (طلبات العملاء)'
    ");
    
    // 4. Create purchase_order_tracking_codes table
    echo "<p>Creating purchase_order_tracking_codes table...</p>";
    $pdo->exec("
        CREATE TABLE `purchase_order_tracking_codes` (
          `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
          `basket_id` INT(11) NOT NULL COMMENT 'معرف سلة الشراء',
          `code` VARCHAR(100) NOT NULL COMMENT 'رمز التتبع الإضافي',
          `description` TEXT DEFAULT NULL COMMENT 'وصف الرمز',
          `code_value` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'قيمة الرمز',
          `is_used` TINYINT(1) DEFAULT 0 COMMENT 'تم استخدامه',
          `used_at` DATETIME DEFAULT NULL COMMENT 'تاريخ الاستخدام',
          `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          INDEX `idx_basket` (`basket_id`),
          INDEX `idx_code` (`code`),
          INDEX `idx_is_used` (`is_used`),
          FOREIGN KEY (`basket_id`) REFERENCES `purchase_order_baskets`(`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='رموز التتبع الإضافية'
    ");
    
    // 5. Insert sample purchase groups
    echo "<p>Inserting sample purchase groups...</p>";
    
    // Check if groups already exist
    $check = $pdo->query("SELECT COUNT(*) FROM purchase_groups")->fetchColumn();
    
    if ($check == 0) {
        $stmt = $pdo->prepare("INSERT INTO purchase_groups (group_name, description, is_active) VALUES (?, ?, 1)");
        $groups = [
            ['مجموعة الشراء 1', 'مجموعة الشراء الأولى'],
            ['مجموعة الشراء 2', 'مجموعة الشراء الثانية'],
            ['مجموعة الشراء 3', 'مجموعة الشراء الثالثة'],
            ['مجموعة VIP', 'مجموعة العملاء المميزين'],
            ['مجموعة الجملة', 'مجموعة تجار الجملة']
        ];
        
        foreach ($groups as $group) {
            $stmt->execute($group);
        }
        echo "<p style='color: green;'>✅ Inserted " . count($groups) . " sample groups</p>";
    } else {
        echo "<p style='color: blue;'>ℹ️ Groups already exist, skipping insert</p>";
    }
    
    echo "<h3 style='color: green;'>✅ Setup completed successfully!</h3>";
    echo "<div style='margin: 20px 0;'>";
    echo "<a href='modules/purchases/basket.php' style='display: inline-block; padding: 10px 20px; background: #3b82f6; color: white; text-decoration: none; border-radius: 4px; margin-left: 10px;'>الذهاب إلى نظام سلة الشراء</a>";
    echo "<a href='?reset=1' onclick='return confirm(\"هل أنت متأكد من إعادة تعيين الجداول؟ سيتم حذف جميع البيانات!\")' style='display: inline-block; padding: 10px 20px; background: #ef4444; color: white; text-decoration: none; border-radius: 4px;'>إعادة تعيين الجداول</a>";
    echo "</div>";
    echo "</body></html>";
    
} catch (PDOException $e) {
    echo "<h3 style='color: red;'>❌ Error: " . $e->getMessage() . "</h3>";
    echo "<p>تأكد من:</p>";
    echo "<ul>";
    echo "<li>تشغيل خادم MySQL</li>";
    echo "<li>وجود قاعدة البيانات yassin_admin_system</li>";
    echo "<li>صحة بيانات الاتصال</li>";
    echo "</ul>";
    echo "</body></html>";
}
?>
