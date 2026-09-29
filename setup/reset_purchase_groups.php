<?php
/**
 * Reset Purchase Groups Table
 * حذف وإعادة إنشاء جدول مجموعات الشراء
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
    <title>إعادة تعيين جدول مجموعات الشراء</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #e74c3c; padding-bottom: 10px; }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        .step { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #e74c3c; }
        .btn { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; margin-top: 20px; }
    </style>
</head>
<body>
<div class="container">
    <h1>⚠️ إعادة تعيين جدول مجموعات الشراء</h1>

<?php

try {
    echo "<div class='step'>";
    echo "<h2>1. حذف الجدول القديم</h2>";
    
    // Disable foreign key checks temporarily
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    echo "<p class='success'>✅ تم تعطيل فحص Foreign Keys مؤقتاً</p>";
    
    // Drop the table
    $db->exec("DROP TABLE IF EXISTS purchase_groups");
    echo "<p class='success'>✅ تم حذف الجدول القديم</p>";
    
    // Re-enable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    echo "<p class='success'>✅ تم تفعيل فحص Foreign Keys</p>";
    
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>2. إنشاء الجدول الجديد</h2>";
    
    // Create new table with correct structure
    $db->exec("
        CREATE TABLE purchase_groups (
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
    
    echo "<p class='success'>✅ تم إنشاء الجدول الجديد بالهيكل الصحيح</p>";
    
    // Show table structure
    $columns = $db->query("SHOW COLUMNS FROM purchase_groups")->fetchAll(PDO::FETCH_ASSOC);
    echo "<p><strong>هيكل الجدول:</strong></p>";
    echo "<table style='width: 100%; border-collapse: collapse; margin-top: 10px;'>";
    echo "<tr style='background: #f8f9fa;'><th style='padding: 8px; border: 1px solid #ddd; text-align: right;'>العمود</th><th style='padding: 8px; border: 1px solid #ddd; text-align: right;'>النوع</th></tr>";
    foreach ($columns as $col) {
        echo "<tr><td style='padding: 8px; border: 1px solid #ddd;'>{$col['Field']}</td><td style='padding: 8px; border: 1px solid #ddd;'>{$col['Type']}</td></tr>";
    }
    echo "</table>";
    
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>3. إعادة ربط purchase_baskets</h2>";
    
    // Check if purchase_baskets table exists
    $tables = $db->query("SHOW TABLES LIKE 'purchase_baskets'")->fetchAll();
    
    if (!empty($tables)) {
        // First, remove any existing foreign keys
        try {
            $fks = $db->query("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.KEY_COLUMN_USAGE 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = 'purchase_baskets' 
                AND COLUMN_NAME = 'purchase_group_id'
                AND REFERENCED_TABLE_NAME IS NOT NULL
            ")->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($fks as $fk_name) {
                $db->exec("ALTER TABLE purchase_baskets DROP FOREIGN KEY $fk_name");
                echo "<p class='success'>✅ تم إزالة Foreign Key القديم: $fk_name</p>";
            }
        } catch (PDOException $e) {
            echo "<p class='warning'>⚠️ لا توجد Foreign Keys قديمة</p>";
        }
        
        // Set existing values to NULL
        $db->exec("UPDATE purchase_baskets SET purchase_group_id = NULL WHERE purchase_group_id IS NOT NULL");
        echo "<p class='success'>✅ تم تحديث purchase_baskets</p>";
        
        // Re-add foreign key
        try {
            $db->exec("
                ALTER TABLE purchase_baskets 
                ADD CONSTRAINT fk_basket_purchase_group
                FOREIGN KEY (purchase_group_id) REFERENCES purchase_groups(id) ON DELETE SET NULL
            ");
            echo "<p class='success'>✅ تم إعادة ربط purchase_baskets بـ purchase_groups</p>";
        } catch (PDOException $e) {
            echo "<p class='warning'>⚠️ Foreign Key: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p class='warning'>⚠️ جدول purchase_baskets غير موجود</p>";
    }
    
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2>4. إضافة البيانات الافتراضية</h2>";
    
    $groups = [
        ['PG-2025-001', 'مجموعة يناير 2025', 'مجموعة الشراء الشهرية - يناير'],
        ['PG-2025-002', 'مجموعة فبراير 2025', 'مجموعة الشراء الشهرية - فبراير'],
        ['PG-SPECIAL-01', 'مجموعة العروض الخاصة', 'مجموعة للعروض والتخفيضات الخاصة']
    ];
    
    foreach ($groups as $group) {
        $stmt = $db->prepare("
            INSERT INTO purchase_groups (group_code, group_name, description, created_by)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$group[0], $group[1], $group[2], $_SESSION['user_id']]);
        echo "<p class='success'>✅ تم إضافة: {$group[1]} ({$group[0]})</p>";
    }
    
    echo "</div>";
    
    echo "<div class='step'>";
    echo "<h2 class='success'>✅ اكتمل بنجاح!</h2>";
    echo "<p>تم إعادة إنشاء جدول purchase_groups بنجاح مع البيانات الافتراضية.</p>";
    echo "<a href='create_basket_tables.php' class='btn'>🔄 العودة لإكمال الإعداد</a>";
    echo "<a href='../modules/purchases/basket_complete.php' class='btn' style='background: #C7A46D; margin-right: 10px;'>🛒 الذهاب إلى سلة الشراء</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ خطأ: " . $e->getMessage() . "</p>";
    echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;'>" . $e->getTraceAsString() . "</pre>";
}

?>

</div>
</body>
</html>
