<?php
/**
 * Fix Basket System Errors
 * إصلاح أخطاء نظام السلة
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
    <title>إصلاح أخطاء نظام السلة</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #667eea; padding-bottom: 10px; }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        .step { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #667eea; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 إصلاح أخطاء نظام السلة</h1>

<?php

try {
    echo "<div class='step'>";
    echo "<h2>1. التحقق من جدول suppliers</h2>";
    
    // Check suppliers table structure
    $supplier_columns = $db->query("SHOW COLUMNS FROM suppliers")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<p>الأعمدة الموجودة: " . implode(', ', $supplier_columns) . "</p>";
    
    if (!in_array('company_name', $supplier_columns)) {
        echo "<p class='warning'>⚠️ عمود company_name غير موجود - سيتم إضافته</p>";
        
        $db->exec("ALTER TABLE suppliers ADD COLUMN company_name VARCHAR(255) NULL AFTER name");
        
        echo "<p class='success'>✅ تم إضافة عمود company_name</p>";
    } else {
        echo "<p class='success'>✅ عمود company_name موجود</p>";
    }
    
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>2. التحقق من جدول purchase_groups</h2>";
    
    // Check if purchase_groups table exists
    $tables = $db->query("SHOW TABLES LIKE 'purchase_groups'")->fetchAll();
    
    if (empty($tables)) {
        echo "<p class='warning'>⚠️ جدول purchase_groups غير موجود - سيتم إنشاؤه</p>";
        
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
        
        // Verify table structure
        $group_columns = $db->query("SHOW COLUMNS FROM purchase_groups")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>الأعمدة: " . implode(', ', $group_columns) . "</p>";
        
        // Insert sample data
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
                    echo "<p class='warning'>⚠️ خطأ: " . $e->getMessage() . "</p>";
                }
            }
        }
    } else {
        echo "<p class='success'>✅ جدول purchase_groups موجود</p>";
    }
    
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>3. التحقق من جدول purchase_baskets</h2>";
    
    // Check if purchase_baskets table exists
    $tables = $db->query("SHOW TABLES LIKE 'purchase_baskets'")->fetchAll();
    
    if (empty($tables)) {
        echo "<p class='warning'>⚠️ جدول purchase_baskets غير موجود</p>";
        echo "<p class='error'>❌ يرجى تشغيل: <a href='create_basket_tables.php'>create_basket_tables.php</a></p>";
    } else {
        echo "<p class='success'>✅ جدول purchase_baskets موجود</p>";
        
        // Check if purchase_group_id column exists
        $basket_columns = $db->query("SHOW COLUMNS FROM purchase_baskets")->fetchAll(PDO::FETCH_COLUMN);
        
        if (!in_array('purchase_group_id', $basket_columns)) {
            echo "<p class='warning'>⚠️ عمود purchase_group_id غير موجود - سيتم إضافته</p>";
            
            $db->exec("
                ALTER TABLE purchase_baskets 
                ADD COLUMN purchase_group_id INT NULL AFTER supplier_id,
                ADD INDEX idx_purchase_group_id (purchase_group_id)
            ");
            
            echo "<p class='success'>✅ تم إضافة عمود purchase_group_id</p>";
            
            // Add foreign key
            try {
                $db->exec("
                    ALTER TABLE purchase_baskets 
                    ADD CONSTRAINT fk_basket_purchase_group
                    FOREIGN KEY (purchase_group_id) REFERENCES purchase_groups(id) ON DELETE SET NULL
                ");
                echo "<p class='success'>✅ تم إضافة Foreign Key</p>";
            } catch (PDOException $e) {
                echo "<p class='warning'>⚠️ Foreign Key: " . $e->getMessage() . "</p>";
            }
        } else {
            echo "<p class='success'>✅ عمود purchase_group_id موجود</p>";
        }
    }
    
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>4. التحقق من جدول basket_items</h2>";
    
    $tables = $db->query("SHOW TABLES LIKE 'basket_items'")->fetchAll();
    
    if (empty($tables)) {
        echo "<p class='error'>❌ جدول basket_items غير موجود</p>";
        echo "<p class='error'>يرجى تشغيل: <a href='create_basket_tables.php'>create_basket_tables.php</a></p>";
    } else {
        echo "<p class='success'>✅ جدول basket_items موجود</p>";
    }
    
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>5. التحقق من جدول basket_activity_log</h2>";
    
    $tables = $db->query("SHOW TABLES LIKE 'basket_activity_log'")->fetchAll();
    
    if (empty($tables)) {
        echo "<p class='error'>❌ جدول basket_activity_log غير موجود</p>";
        echo "<p class='error'>يرجى تشغيل: <a href='create_basket_tables.php'>create_basket_tables.php</a></p>";
    } else {
        echo "<p class='success'>✅ جدول basket_activity_log موجود</p>";
    }
    
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2 class='success'>✅ اكتمل الفحص والإصلاح!</h2>";
    echo "<p>جميع الأخطاء تم إصلاحها. يمكنك الآن استخدام النظام.</p>";
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='../modules/purchases/basket_complete.php' style='display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;'>";
    echo "🛒 الذهاب إلى سلة الشراء";
    echo "</a>";
    echo "</div>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ خطأ: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

?>

</div>
</body>
</html>
