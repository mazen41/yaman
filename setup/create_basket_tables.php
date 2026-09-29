<?php
/**
 * Create Purchase Basket Tables
 * إنشاء جداول سلة الشراء المتكاملة
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    die('يجب تسجيل الدخول أولاً');
}

require_once '../config/database.php';

?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إعداد جداول سلة الشراء</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .step { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #667eea; }
    </style>
</head>
<body>
<div class="container">
    <h1><i class="fas fa-shopping-basket"></i> إعداد نظام سلة الشراء المتكامل</h1>

<?php

try {
    // Don't use transaction for DDL statements (CREATE TABLE, ALTER TABLE)
    // $db->beginTransaction();
    
    // 1. Purchase Baskets Table
    echo "<div class='step'>";
    echo "<h2>1. إنشاء جدول سلال الشراء</h2>";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS purchase_baskets (
            id INT PRIMARY KEY AUTO_INCREMENT,
            basket_code VARCHAR(50) UNIQUE NOT NULL,
            basket_name VARCHAR(255) NOT NULL,
            supplier_id INT NULL,
            purchase_group_id INT NULL,
            purchase_date DATE NOT NULL,
            expected_delivery_date DATE NULL,
            notes TEXT NULL,
            shipping_cost DECIMAL(10,3) DEFAULT 0,
            tax_rate DECIMAL(5,2) DEFAULT 0,
            tax_included TINYINT(1) DEFAULT 0,
            subtotal_amount DECIMAL(10,3) DEFAULT 0,
            discount_amount DECIMAL(10,3) DEFAULT 0,
            tax_amount DECIMAL(10,3) DEFAULT 0,
            final_amount DECIMAL(10,3) DEFAULT 0,
            total_items INT DEFAULT 0,
            status ENUM('draft', 'locked', 'completed', 'cancelled') DEFAULT 'draft',
            created_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            locked_at TIMESTAMP NULL,
            locked_by INT NULL,
            FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
            FOREIGN KEY (locked_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_basket_code (basket_code),
            INDEX idx_status (status),
            INDEX idx_purchase_date (purchase_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "<p class='success'>✅ تم إنشاء جدول purchase_baskets</p>";
    echo "</div>";
    
    // 2. Basket Items Table
    echo "<div class='step'>";
    echo "<h2>2. إنشاء جدول عناصر السلة</h2>";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS basket_items (
            id INT PRIMARY KEY AUTO_INCREMENT,
            basket_id INT NOT NULL,
            order_id INT NOT NULL,
            quantity INT DEFAULT 1,
            unit_price DECIMAL(10,3) NOT NULL,
            total_price DECIMAL(10,3) NOT NULL,
            notes TEXT NULL,
            added_by INT NOT NULL,
            added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (basket_id) REFERENCES purchase_baskets(id) ON DELETE CASCADE,
            FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE,
            FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE RESTRICT,
            UNIQUE KEY unique_basket_order (basket_id, order_id),
            INDEX idx_basket_id (basket_id),
            INDEX idx_order_id (order_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "<p class='success'>✅ تم إنشاء جدول basket_items</p>";
    echo "</div>";
    
    // 3. Basket Activity Log Table
    echo "<div class='step'>";
    echo "<h2>3. إنشاء جدول سجل النشاط</h2>";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS basket_activity_log (
            id INT PRIMARY KEY AUTO_INCREMENT,
            basket_id INT NOT NULL,
            action_type ENUM('create', 'add_order', 'remove_order', 'update', 'lock', 'unlock', 'complete', 'cancel') NOT NULL,
            description TEXT NOT NULL,
            user_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (basket_id) REFERENCES purchase_baskets(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
            INDEX idx_basket_id (basket_id),
            INDEX idx_action_type (action_type),
            INDEX idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "<p class='success'>✅ تم إنشاء جدول basket_activity_log</p>";
    echo "</div>";
    
    // 4. Purchase Groups Table
    echo "<div class='step'>";
    echo "<h2>4. إنشاء جدول مجموعات الشراء</h2>";
    
    // Check if table exists
    $tables = $db->query("SHOW TABLES LIKE 'purchase_groups'")->fetchAll();
    
    if (empty($tables)) {
        $db->exec("
            CREATE TABLE IF NOT EXISTS purchase_groups (
                id INT PRIMARY KEY AUTO_INCREMENT,
                group_code VARCHAR(50) UNIQUE NOT NULL,
                group_name VARCHAR(255) NOT NULL,
                description TEXT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_by INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
                INDEX idx_group_code (group_code),
                INDEX idx_is_active (is_active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "<p class='success'>✅ تم إنشاء جدول purchase_groups</p>";
    } else {
        // Table exists, check structure
        $columns = $db->query("SHOW COLUMNS FROM purchase_groups")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p class='success'>✅ جدول purchase_groups موجود</p>";
        echo "<p>الأعمدة الموجودة: " . implode(', ', $columns) . "</p>";
        
        // Add missing columns
        if (!in_array('group_code', $columns)) {
            try {
                $db->exec("ALTER TABLE purchase_groups ADD COLUMN group_code VARCHAR(50) UNIQUE NOT NULL AFTER id");
                echo "<p class='success'>✅ تم إضافة عمود group_code</p>";
            } catch (PDOException $e) {
                echo "<p class='warning'>⚠️ عمود group_code: " . $e->getMessage() . "</p>";
            }
        }
        
        if (!in_array('group_name', $columns)) {
            try {
                $db->exec("ALTER TABLE purchase_groups ADD COLUMN group_name VARCHAR(255) NOT NULL AFTER group_code");
                echo "<p class='success'>✅ تم إضافة عمود group_name</p>";
            } catch (PDOException $e) {
                echo "<p class='warning'>⚠️ عمود group_name: " . $e->getMessage() . "</p>";
            }
        }
        
        if (!in_array('description', $columns)) {
            try {
                $db->exec("ALTER TABLE purchase_groups ADD COLUMN description TEXT NULL");
                echo "<p class='success'>✅ تم إضافة عمود description</p>";
            } catch (PDOException $e) {
                echo "<p class='warning'>⚠️ عمود description: " . $e->getMessage() . "</p>";
            }
        }
    }
    
    echo "</div>";
    
    // 5. Update customer_orders table
    echo "<div class='step'>";
    echo "<h2>5. تحديث جدول الطلبات</h2>";
    
    // Check if basket_id column exists
    $columns = $db->query("SHOW COLUMNS FROM customer_orders LIKE 'basket_id'")->fetchAll();
    
    if (empty($columns)) {
        $db->exec("
            ALTER TABLE customer_orders 
            ADD COLUMN basket_id INT NULL AFTER status,
            ADD FOREIGN KEY (basket_id) REFERENCES purchase_baskets(id) ON DELETE SET NULL,
            ADD INDEX idx_basket_id (basket_id)
        ");
        echo "<p class='success'>✅ تم إضافة عمود basket_id إلى جدول customer_orders</p>";
    } else {
        echo "<p class='success'>✅ عمود basket_id موجود بالفعل</p>";
    }
    
    echo "</div>";
    
    // 6. Add foreign key to purchase_baskets for purchase_group_id
    echo "<div class='step'>";
    echo "<h2>6. ربط السلال بمجموعات الشراء</h2>";
    
    try {
        // Check if foreign key already exists
        $fks = $db->query("
            SELECT CONSTRAINT_NAME 
            FROM information_schema.KEY_COLUMN_USAGE 
            WHERE TABLE_SCHEMA = DATABASE() 
            AND TABLE_NAME = 'purchase_baskets' 
            AND COLUMN_NAME = 'purchase_group_id'
            AND REFERENCED_TABLE_NAME IS NOT NULL
        ")->fetchAll();
        
        if (empty($fks)) {
            $db->exec("
                ALTER TABLE purchase_baskets 
                ADD CONSTRAINT fk_basket_purchase_group
                FOREIGN KEY (purchase_group_id) REFERENCES purchase_groups(id) ON DELETE SET NULL
            ");
            echo "<p class='success'>✅ تم ربط purchase_baskets بـ purchase_groups</p>";
        } else {
            echo "<p class='success'>✅ الربط موجود بالفعل</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='success'>⚠️ تخطي الربط: " . $e->getMessage() . "</p>";
    }
    
    echo "</div>";
    
    // 7. Insert sample purchase groups
    echo "<div class='step'>";
    echo "<h2>7. إضافة مجموعات شراء افتراضية</h2>";
    
    // Check if purchase_groups table exists first
    $tables = $db->query("SHOW TABLES LIKE 'purchase_groups'")->fetchAll();
    
    if (!empty($tables)) {
        // Verify all required columns exist
        $columns = $db->query("SHOW COLUMNS FROM purchase_groups")->fetchAll(PDO::FETCH_COLUMN);
        $required_columns = ['group_code', 'group_name', 'description', 'created_by'];
        $missing_columns = array_diff($required_columns, $columns);
        
        if (!empty($missing_columns)) {
            echo "<p class='error'>❌ أعمدة ناقصة في جدول purchase_groups: " . implode(', ', $missing_columns) . "</p>";
            echo "<p class='warning'>⚠️ يرجى حذف الجدول وإعادة تشغيل السكريبت، أو تشغيل:</p>";
            echo "<pre style='background: #f8f9fa; padding: 10px; border-radius: 5px;'>DROP TABLE IF EXISTS purchase_groups;</pre>";
        } else {
            // All columns exist, proceed with insert
            $groups = [
                ['PG-2025-001', 'مجموعة يناير 2025', 'مجموعة الشراء الشهرية - يناير'],
                ['PG-2025-002', 'مجموعة فبراير 2025', 'مجموعة الشراء الشهرية - فبراير'],
                ['PG-SPECIAL-01', 'مجموعة العروض الخاصة', 'مجموعة للعروض والتخفيضات الخاصة']
            ];
            
            foreach ($groups as $group) {
                try {
                    $stmt = $db->prepare("
                        INSERT INTO purchase_groups (group_code, group_name, description, created_by)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$group[0], $group[1], $group[2], $_SESSION['user_id']]);
                    echo "<p class='success'>✅ تم إضافة مجموعة: {$group[1]}</p>";
                } catch (PDOException $e) {
                    if (strpos($e->getMessage(), 'Duplicate') !== false) {
                        echo "<p class='success'>✅ المجموعة موجودة: {$group[1]}</p>";
                    } else {
                        echo "<p class='warning'>⚠️ خطأ في إضافة المجموعة: " . $e->getMessage() . "</p>";
                    }
                }
            }
        }
    } else {
        echo "<p class='error'>❌ جدول purchase_groups غير موجود. يرجى التحقق من الخطوة 4.</p>";
    }
    
    echo "</div>";
    
    // $db->commit(); // No transaction was started
    
    echo "<div class='step'>";
    echo "<h2 class='success'>✅ اكتمل الإعداد بنجاح!</h2>";
    echo "<p>تم إنشاء جميع الجداول والعلاقات بنجاح.</p>";
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='../modules/purchases/basket_complete.php' style='display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;'>";
    echo "<i class='fas fa-shopping-basket'></i> الذهاب إلى سلة الشراء";
    echo "</a>";
    echo "</div>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ خطأ: " . $e->getMessage() . "</p>";
    echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;'>" . $e->getTraceAsString() . "</pre>";
}

?>

</div>
</body>
</html>
