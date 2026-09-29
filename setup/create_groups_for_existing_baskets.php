<?php
/**
 * Create Purchase Groups for Existing Baskets
 * إنشاء مجموعات للسلال الموجودة
 */

require_once '../config/database.php';

echo "<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <title>إنشاء مجموعات للسلال الموجودة</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 10px 0; }
        .warning { color: orange; padding: 10px; background: #fff3cd; border: 1px solid #ffeaa7; margin: 10px 0; }
    </style>
</head>
<body>";

echo "<h1>إنشاء مجموعات شراء للسلال الموجودة</h1>";

try {
    // Check if purchase_groups table exists
    $tables = $db->query("SHOW TABLES LIKE 'purchase_groups'")->fetchAll();
    
    if (empty($tables)) {
        echo "<div class='error'>❌ جدول purchase_groups غير موجود. يرجى تشغيل create_basket_tables.php أولاً</div>";
        exit();
    }
    
    echo "<div class='info'>✅ جدول purchase_groups موجود</div>";
    
    // Get all baskets without groups
    $baskets = $db->query("
        SELECT pb.*, 
               (SELECT COUNT(*) FROM basket_items WHERE basket_id = pb.id) as items_count
        FROM purchase_baskets pb
        WHERE (pb.purchase_group_id IS NULL OR pb.purchase_group_id = 0)
        ORDER BY pb.created_at ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($baskets)) {
        echo "<div class='info'>✅ جميع السلال لديها مجموعات بالفعل</div>";
        echo "<p><a href='../modules/purchases/groups/index.php'>الذهاب إلى المجموعات</a></p>";
        exit();
    }
    
    echo "<div class='info'>وجدنا " . count($baskets) . " سلة بدون مجموعة</div>";
    echo "<div class='info'>جاري إنشاء المجموعات...</div>";
    
    $created_count = 0;
    $skipped_count = 0;
    
    foreach ($baskets as $basket) {
        try {
            // Generate group code and name
            $group_code = 'PG-' . date('Y-m', strtotime($basket['created_at'])) . '-' . str_pad($basket['id'], 4, '0', STR_PAD_LEFT);
            
            // Get basket name/code
            $basket_identifier = '';
            if (!empty($basket['basket_name'])) {
                $basket_identifier = $basket['basket_name'];
            } elseif (!empty($basket['basket_code'])) {
                $basket_identifier = 'سلة ' . $basket['basket_code'];
            } else {
                $basket_identifier = 'سلة #' . $basket['id'];
            }
            
            $group_name = 'مجموعة الشراء - ' . $basket_identifier;
            $group_description = 'تم إنشاؤها تلقائياً من السلة (ID: ' . $basket['id'] . ')';
            
            // Check if group already exists with this code
            $existing = $db->prepare("SELECT id FROM purchase_groups WHERE group_code = ?");
            $existing->execute([$group_code]);
            
            if ($existing->fetch()) {
                echo "<div class='warning'>⚠️ المجموعة $group_code موجودة بالفعل - تخطي السلة #{$basket['id']}</div>";
                $skipped_count++;
                continue;
            }
            
            // Insert purchase group
            $group_stmt = $db->prepare("
                INSERT INTO purchase_groups (group_code, group_name, description, created_by, created_at)
                VALUES (?, ?, ?, 1, ?)
            ");
            $group_stmt->execute([
                $group_code, 
                $group_name, 
                $group_description,
                $basket['created_at']
            ]);
            
            $group_id = $db->lastInsertId();
            
            // Link basket to group
            $update_basket = $db->prepare("
                UPDATE purchase_baskets 
                SET purchase_group_id = ? 
                WHERE id = ?
            ");
            $update_basket->execute([$group_id, $basket['id']]);
            
            echo "<div class='success'>✅ تم إنشاء المجموعة: $group_name (السلة #{$basket['id']}, {$basket['items_count']} طلبات)</div>";
            $created_count++;
            
        } catch (PDOException $e) {
            echo "<div class='error'>❌ خطأ في السلة #{$basket['id']}: " . $e->getMessage() . "</div>";
            $skipped_count++;
        }
    }
    
    echo "<hr>";
    echo "<div class='success'><strong>✅ اكتمل الإعداد!</strong></div>";
    echo "<div class='info'>تم إنشاء: $created_count مجموعة</div>";
    if ($skipped_count > 0) {
        echo "<div class='warning'>تم تخطي: $skipped_count سلة</div>";
    }
    
    echo "<p><a href='../modules/purchases/groups/index.php' style='display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; margin-top: 20px;'>الذهاب إلى المجموعات</a></p>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

echo "</body></html>";
?>
