# Purchase Order System - Database Schema

## SQL Script to Create All Tables

```sql
-- ============================================
-- نظام إنشاء طلبات الشراء - Purchase Order System
-- Database Tables Creation Script
-- ============================================

-- 1. جدول مجموعات الشراء - Purchase Groups
CREATE TABLE IF NOT EXISTS `purchase_groups` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `group_name` VARCHAR(100) NOT NULL COMMENT 'اسم المجموعة',
  `description` TEXT DEFAULT NULL COMMENT 'وصف المجموعة',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT 'نشط/غير نشط',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='مجموعات الشراء';

-- 2. جدول طلبات الشراء الرئيسية - Main Purchase Orders
CREATE TABLE IF NOT EXISTS `purchase_orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(50) NOT NULL UNIQUE COMMENT 'رقم سلة الشراء',
  `serial_number` VARCHAR(50) NOT NULL UNIQUE COMMENT 'الرقم التسلسلي',
  `purchase_date` DATE NOT NULL COMMENT 'تاريخ الشراء',
  `purchase_group_id` INT(11) DEFAULT NULL COMMENT 'اختيار مجموعة الشراء',
  `total_items` INT(11) DEFAULT 0 COMMENT 'إجمالي عدد القطع',
  `total_discount_before` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'خصم النقطة',
  `total_discount_after` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'خصم النادي',
  `total_price_before_discount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'سعر السلة قبل الخصم',
  `total_price_after_discount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'سعر السلة بعد الخصم (قابل للتعديل)',
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
  INDEX `idx_created_by` (`created_by`),
  FOREIGN KEY (`purchase_group_id`) REFERENCES `purchase_groups`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='طلبات الشراء الرئيسية';

-- 3. جدول عناصر طلبات الشراء - Purchase Order Items (Customer Orders)
CREATE TABLE IF NOT EXISTS `purchase_order_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `purchase_order_id` INT(11) NOT NULL COMMENT 'معرف طلب الشراء',
  `item_number` INT(11) NOT NULL COMMENT 'رقم العنصر في الطلب (م)',
  `client_name` VARCHAR(200) NOT NULL COMMENT 'اسم العميل',
  `client_order_number` VARCHAR(50) NOT NULL COMMENT 'رقم الطلب',
  `client_phone` VARCHAR(20) NOT NULL COMMENT 'رقم جوال العميل',
  `item_quantity` INT(11) NOT NULL DEFAULT 1 COMMENT 'عدد القطع',
  `item_price` DECIMAL(10,2) NOT NULL COMMENT 'قيمة الطلب',
  `notes` TEXT DEFAULT NULL COMMENT 'ملاحظات',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_purchase_order` (`purchase_order_id`),
  INDEX `idx_client_phone` (`client_phone`),
  INDEX `idx_client_order_number` (`client_order_number`),
  FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='عناصر طلبات الشراء (طلبات العملاء)';

-- 4. جدول تتبع حالة الطلبات - Purchase Order Tracking
CREATE TABLE IF NOT EXISTS `purchase_order_tracking` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `purchase_order_id` INT(11) NOT NULL COMMENT 'معرف طلب الشراء',
  `tracking_type` ENUM('tracking_code_1', 'tracking_code_2', 'additional') NOT NULL COMMENT 'نوع رمز التتبع',
  `tracking_code` VARCHAR(100) NOT NULL COMMENT 'رمز التتبع',
  `status` VARCHAR(50) DEFAULT NULL COMMENT 'حالة الشحنة',
  `location` VARCHAR(200) DEFAULT NULL COMMENT 'الموقع الحالي',
  `last_update` DATETIME DEFAULT NULL COMMENT 'آخر تحديث',
  `notes` TEXT DEFAULT NULL COMMENT 'ملاحظات',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_purchase_order` (`purchase_order_id`),
  INDEX `idx_tracking_code` (`tracking_code`),
  INDEX `idx_tracking_type` (`tracking_type`),
  FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='تتبع حالة طلبات الشراء';

-- 5. جدول سجل تغييرات حالة الطلب - Purchase Order Status Log
CREATE TABLE IF NOT EXISTS `purchase_order_status_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `purchase_order_id` INT(11) NOT NULL COMMENT 'معرف طلب الشراء',
  `old_status` VARCHAR(50) DEFAULT NULL COMMENT 'الحالة القديمة',
  `new_status` VARCHAR(50) NOT NULL COMMENT 'الحالة الجديدة',
  `changed_by` INT(11) NOT NULL COMMENT 'المستخدم الذي قام بالتغيير',
  `notes` TEXT DEFAULT NULL COMMENT 'ملاحظات',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_purchase_order` (`purchase_order_id`),
  INDEX `idx_changed_by` (`changed_by`),
  FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='سجل تغييرات حالة طلبات الشراء';

-- 6. جدول رموز التتبع الإضافية - Additional Tracking Codes
CREATE TABLE IF NOT EXISTS `purchase_order_additional_codes` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `purchase_order_id` INT(11) NOT NULL COMMENT 'معرف طلب الشراء',
  `code` VARCHAR(100) NOT NULL COMMENT 'رمز التتبع الإضافي',
  `description` TEXT DEFAULT NULL COMMENT 'وصف الرمز',
  `code_value` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'قيمة الرمز (إن وجدت)',
  `is_used` TINYINT(1) DEFAULT 0 COMMENT 'تم استخدامه',
  `used_at` DATETIME DEFAULT NULL COMMENT 'تاريخ الاستخدام',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_purchase_order` (`purchase_order_id`),
  INDEX `idx_code` (`code`),
  INDEX `idx_is_used` (`is_used`),
  FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='رموز التتبع الإضافية';

-- ============================================
-- Insert Sample Purchase Groups
-- ============================================

INSERT INTO `purchase_groups` (`group_name`, `description`, `is_active`) VALUES
('مجموعة الشراء 1', 'مجموعة الشراء الأولى', 1),
('مجموعة الشراء 2', 'مجموعة الشراء الثانية', 1),
('مجموعة الشراء 3', 'مجموعة الشراء الثالثة', 1),
('مجموعة VIP', 'مجموعة العملاء المميزين', 1),
('مجموعة الجملة', 'مجموعة تجار الجملة', 1);

-- ============================================
-- Create Views for Reporting
-- ============================================

-- عرض ملخص طلبات الشراء
CREATE OR REPLACE VIEW `v_purchase_orders_summary` AS
SELECT 
    po.id,
    po.order_number,
    po.serial_number,
    po.purchase_date,
    pg.group_name,
    po.total_items,
    po.total_price_before_discount,
    po.total_price_after_discount,
    po.total_discount_before + po.total_discount_after AS total_discount,
    po.status,
    COUNT(DISTINCT poi.id) AS customer_count,
    u.username AS created_by_name,
    po.created_at
FROM purchase_orders po
LEFT JOIN purchase_groups pg ON po.purchase_group_id = pg.id
LEFT JOIN purchase_order_items poi ON po.id = poi.purchase_order_id
LEFT JOIN users u ON po.created_by = u.id
GROUP BY po.id;

-- عرض تفاصيل عناصر الطلبات
CREATE OR REPLACE VIEW `v_purchase_order_items_details` AS
SELECT 
    poi.*,
    po.order_number,
    po.serial_number,
    po.purchase_date,
    pg.group_name
FROM purchase_order_items poi
INNER JOIN purchase_orders po ON poi.purchase_order_id = po.id
LEFT JOIN purchase_groups pg ON po.purchase_group_id = pg.id;

-- عرض تتبع الشحنات
CREATE OR REPLACE VIEW `v_purchase_tracking_status` AS
SELECT 
    pot.*,
    po.order_number,
    po.serial_number,
    po.purchase_date
FROM purchase_order_tracking pot
INNER JOIN purchase_orders po ON pot.purchase_order_id = po.id;

-- ============================================
-- Create Stored Procedures
-- ============================================

DELIMITER //

-- إجراء لحساب إجماليات الطلب
CREATE PROCEDURE `sp_calculate_purchase_order_totals`(
    IN p_purchase_order_id INT
)
BEGIN
    DECLARE v_total_items INT DEFAULT 0;
    DECLARE v_total_price DECIMAL(10,2) DEFAULT 0.00;
    
    -- حساب إجمالي القطع والسعر
    SELECT 
        COALESCE(SUM(item_quantity), 0),
        COALESCE(SUM(item_price), 0.00)
    INTO v_total_items, v_total_price
    FROM purchase_order_items
    WHERE purchase_order_id = p_purchase_order_id;
    
    -- تحديث الطلب
    UPDATE purchase_orders
    SET 
        total_items = v_total_items,
        total_price_before_discount = v_total_price,
        total_price_after_discount = v_total_price - total_discount_before - total_discount_after
    WHERE id = p_purchase_order_id;
END //

-- إجراء لتحديث حالة الطلب
CREATE PROCEDURE `sp_update_purchase_order_status`(
    IN p_purchase_order_id INT,
    IN p_new_status VARCHAR(50),
    IN p_changed_by INT,
    IN p_notes TEXT
)
BEGIN
    DECLARE v_old_status VARCHAR(50);
    
    -- الحصول على الحالة القديمة
    SELECT status INTO v_old_status
    FROM purchase_orders
    WHERE id = p_purchase_order_id;
    
    -- تحديث الحالة
    UPDATE purchase_orders
    SET status = p_new_status
    WHERE id = p_purchase_order_id;
    
    -- تسجيل التغيير
    INSERT INTO purchase_order_status_log (
        purchase_order_id, old_status, new_status, changed_by, notes
    ) VALUES (
        p_purchase_order_id, v_old_status, p_new_status, p_changed_by, p_notes
    );
END //

-- إجراء لإضافة رمز تتبع
CREATE PROCEDURE `sp_add_tracking_code`(
    IN p_purchase_order_id INT,
    IN p_tracking_type VARCHAR(20),
    IN p_tracking_code VARCHAR(100),
    IN p_status VARCHAR(50),
    IN p_location VARCHAR(200)
)
BEGIN
    -- إضافة رمز التتبع
    INSERT INTO purchase_order_tracking (
        purchase_order_id, tracking_type, tracking_code, 
        status, location, last_update
    ) VALUES (
        p_purchase_order_id, p_tracking_type, p_tracking_code,
        p_status, p_location, NOW()
    );
    
    -- تحديث الطلب الرئيسي
    IF p_tracking_type = 'tracking_code_1' THEN
        UPDATE purchase_orders
        SET tracking_code_1 = p_tracking_code
        WHERE id = p_purchase_order_id;
    ELSEIF p_tracking_type = 'tracking_code_2' THEN
        UPDATE purchase_orders
        SET tracking_code_2 = p_tracking_code
        WHERE id = p_purchase_order_id;
    END IF;
END //

DELIMITER ;

-- ============================================
-- Create Triggers
-- ============================================

DELIMITER //

-- Trigger لحساب الإجماليات عند إضافة عنصر
CREATE TRIGGER `trg_after_insert_purchase_item`
AFTER INSERT ON `purchase_order_items`
FOR EACH ROW
BEGIN
    CALL sp_calculate_purchase_order_totals(NEW.purchase_order_id);
END //

-- Trigger لحساب الإجماليات عند تحديث عنصر
CREATE TRIGGER `trg_after_update_purchase_item`
AFTER UPDATE ON `purchase_order_items`
FOR EACH ROW
BEGIN
    CALL sp_calculate_purchase_order_totals(NEW.purchase_order_id);
END //

-- Trigger لحساب الإجماليات عند حذف عنصر
CREATE TRIGGER `trg_after_delete_purchase_item`
AFTER DELETE ON `purchase_order_items`
FOR EACH ROW
BEGIN
    CALL sp_calculate_purchase_order_totals(OLD.purchase_order_id);
END //

DELIMITER ;

-- ============================================
-- Grant Permissions (Optional)
-- ============================================

-- GRANT SELECT, INSERT, UPDATE, DELETE ON yassin_admin_system.purchase_* TO 'your_user'@'localhost';
-- GRANT EXECUTE ON PROCEDURE yassin_admin_system.sp_* TO 'your_user'@'localhost';

-- ============================================
-- Verification Queries
-- ============================================

-- التحقق من الجداول
SELECT 
    TABLE_NAME, 
    TABLE_ROWS, 
    CREATE_TIME 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'yassin_admin_system' 
  AND TABLE_NAME LIKE 'purchase%';

-- التحقق من الـ Views
SELECT 
    TABLE_NAME 
FROM information_schema.VIEWS 
WHERE TABLE_SCHEMA = 'yassin_admin_system' 
  AND TABLE_NAME LIKE 'v_purchase%';

-- التحقق من الـ Procedures
SELECT 
    ROUTINE_NAME, 
    ROUTINE_TYPE 
FROM information_schema.ROUTINES 
WHERE ROUTINE_SCHEMA = 'yassin_admin_system' 
  AND ROUTINE_NAME LIKE 'sp_%';

-- ============================================
-- END OF SCRIPT
-- ============================================
```

## How to Run This Script

### Method 1: Using phpMyAdmin
1. Open phpMyAdmin
2. Select database `yassin_admin_system`
3. Go to SQL tab
4. Copy and paste the entire script
5. Click "Go"

### Method 2: Using MySQL Command Line
```bash
mysql -u root -p yassin_admin_system < create_purchase_order_tables.sql
```

### Method 3: Using PHP Script
Create a file `install_purchase_order_system.php`:

```php
<?php
require_once 'config/database.php';

try {
    $sql = file_get_contents('PURCHASE_ORDER_DATABASE.md');
    // Extract SQL from markdown
    preg_match('/```sql(.*?)```/s', $sql, $matches);
    $sql_script = $matches[1];
    
    $pdo->exec($sql_script);
    echo "✅ Database tables created successfully!";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
```

## Verification

After running the script, verify the tables:

```sql
SHOW TABLES LIKE 'purchase%';
DESCRIBE purchase_orders;
DESCRIBE purchase_order_items;
SELECT * FROM purchase_groups;
```
