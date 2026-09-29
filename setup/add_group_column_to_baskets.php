<?php
/**
 * Add purchase_group_id column to purchase_baskets table
 * إضافة عمود المجموعة لجدول السلال
 */

require_once '../config/database.php';

echo "<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <title>إضافة عمود المجموعة</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 10px 0; }
    </style>
</head>
<body>";

echo "<h1>إضافة عمود purchase_group_id لجدول السلال</h1>";

try {
    // Check if purchase_baskets table exists
    $tables = $db->query("SHOW TABLES LIKE 'purchase_baskets'")->fetchAll();
    
    if (empty($tables)) {
        echo "<div class='error'>❌ جدول purchase_baskets غير موجود</div>";
        exit();
    }
    
    echo "<div class='info'>✅ جدول purchase_baskets موجود</div>";
    
    // Check if column exists
    $columns = $db->query("SHOW COLUMNS FROM purchase_baskets LIKE 'purchase_group_id'")->fetchAll();
    
    if (!empty($columns)) {
        echo "<div class='info'>✅ عمود purchase_group_id موجود بالفعل</div>";
    } else {
        echo "<div class='info'>جاري إضافة عمود purchase_group_id...</div>";
        
        $db->exec("
            ALTER TABLE purchase_baskets 
            ADD COLUMN purchase_group_id INT NULL AFTER id,
            ADD INDEX idx_purchase_group_id (purchase_group_id)
        ");
        
        echo "<div class='success'>✅ تم إضافة عمود purchase_group_id بنجاح</div>";
    }
    
    // Check if foreign key exists
    $fks = $db->query("
        SELECT CONSTRAINT_NAME 
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'purchase_baskets' 
        AND CONSTRAINT_NAME LIKE '%purchase_group%'
    ")->fetchAll();
    
    if (empty($fks)) {
        echo "<div class='info'>جاري إضافة Foreign Key...</div>";
        
        try {
            $db->exec("
                ALTER TABLE purchase_baskets
                ADD CONSTRAINT fk_basket_purchase_group
                FOREIGN KEY (purchase_group_id) 
                REFERENCES purchase_groups(id) 
                ON DELETE SET NULL
            ");
            echo "<div class='success'>✅ تم إضافة Foreign Key بنجاح</div>";
        } catch (PDOException $e) {
            echo "<div class='info'>⚠️ Foreign Key: " . $e->getMessage() . "</div>";
        }
    } else {
        echo "<div class='info'>✅ Foreign Key موجود بالفعل</div>";
    }
    
    echo "<div class='success'><strong>✅ اكتمل الإعداد بنجاح!</strong></div>";
    echo "<p><a href='create_groups_for_existing_baskets.php' style='display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px;'>التالي: إنشاء مجموعات للسلال الموجودة</a></p>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

echo "</body></html>";
?>
