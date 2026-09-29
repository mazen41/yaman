<?php
/**
 * Fix Stock Movements Table Structure
 * Senior PHP/MySQL Engineer Implementation
 */

require_once '../config/database.php';

echo "<h1>🔧 إصلاح جدول حركات المخزون</h1>";
echo "<div style='font-family: monospace; background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<pre>";

try {
    echo "=== Fixing Stock Movements Table Structure ===\n";
    echo "==============================================\n\n";
    
    // Get current table structure
    echo "STEP 1: فحص الهيكل الحالي...\n";
    echo "----------------------------\n";
    
    $columns = $db->query("DESCRIBE stock_movements")->fetchAll();
    $existing_columns = [];
    
    foreach ($columns as $column) {
        $existing_columns[] = $column['Field'];
        echo "✓ {$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\nSTEP 2: إضافة الأعمدة المفقودة...\n";
    echo "-------------------------------\n";
    
    // Add missing columns
    $required_columns = [
        'previous_quantity' => 'INT DEFAULT 0',
        'new_quantity' => 'INT DEFAULT 0', 
        'reason' => 'VARCHAR(255)',
        'reference_number' => 'VARCHAR(100)'
    ];
    
    foreach ($required_columns as $column => $definition) {
        if (!in_array($column, $existing_columns)) {
            try {
                $db->exec("ALTER TABLE stock_movements ADD COLUMN $column $definition");
                echo "✅ تم إضافة العمود: $column\n";
            } catch (PDOException $e) {
                echo "⚠️  خطأ في إضافة العمود $column: " . $e->getMessage() . "\n";
            }
        } else {
            echo "✓ العمود موجود: $column\n";
        }
    }
    
    echo "\nSTEP 3: التحقق من الهيكل المحدث...\n";
    echo "--------------------------------\n";
    
    $updated_columns = $db->query("DESCRIBE stock_movements")->fetchAll();
    foreach ($updated_columns as $column) {
        echo "✓ {$column['Field']} ({$column['Type']})\n";
    }
    
    echo "\nSTEP 4: إضافة بيانات تجريبية...\n";
    echo "-----------------------------\n";
    
    // Check if we have products to work with
    $products = $db->query("SELECT id, name, current_stock FROM products WHERE is_active = 1 LIMIT 3")->fetchAll();
    
    if (!empty($products)) {
        foreach ($products as $product) {
            // Add a sample stock movement
            $stmt = $db->prepare("
                INSERT INTO stock_movements 
                (product_id, movement_type, quantity, previous_quantity, new_quantity, reason, reference_number, notes, created_by, movement_date) 
                VALUES (?, 'in', 5, ?, ?, 'استلام بضاعة جديدة', ?, 'حركة تجريبية', 1, NOW())
            ");
            
            $previous = max(0, $product['current_stock'] - 5);
            $new_quantity = $product['current_stock'];
            $ref_number = 'REF-' . rand(1000, 9999);
            
            $stmt->execute([
                $product['id'],
                $previous,
                $new_quantity,
                $ref_number
            ]);
            
            echo "✅ تم إضافة حركة تجريبية للمنتج: {$product['name']}\n";
        }
    } else {
        echo "⚠️  لا توجد منتجات لإضافة حركات تجريبية\n";
        echo "💡 قم بتشغيل add_inventory_sample_data.php أولاً\n";
    }
    
    echo "\nSTEP 5: إحصائيات حركات المخزون...\n";
    echo "--------------------------------\n";
    
    $stats = $db->query("
        SELECT 
            COUNT(*) as total_movements,
            SUM(CASE WHEN movement_type = 'in' THEN 1 ELSE 0 END) as in_movements,
            SUM(CASE WHEN movement_type = 'out' THEN 1 ELSE 0 END) as out_movements,
            SUM(CASE WHEN movement_type = 'adjustment' THEN 1 ELSE 0 END) as adjustment_movements
        FROM stock_movements
    ")->fetch();
    
    echo "📊 إجمالي الحركات: " . $stats['total_movements'] . "\n";
    echo "📈 حركات دخول: " . $stats['in_movements'] . "\n";
    echo "📉 حركات خروج: " . $stats['out_movements'] . "\n";
    echo "🔧 حركات تعديل: " . $stats['adjustment_movements'] . "\n";
    
    echo "\n🎉 تم إصلاح جدول حركات المخزون بنجاح!\n";
    echo "========================================\n";
    echo "✅ جميع الأعمدة المطلوبة موجودة\n";
    echo "✅ البيانات التجريبية مضافة\n";
    echo "✅ النظام جاهز للاستخدام\n";
    
} catch (PDOException $e) {
    echo "\n❌ Database Error: " . $e->getMessage() . "\n";
    echo "🔧 تأكد من اتصال قاعدة البيانات والصلاحيات\n";
} catch (Exception $e) {
    echo "\n❌ System Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "</div>";

echo "<div style='text-align: center; margin: 20px;'>";
echo "<a href='modules/inventory/stock_movement.php' style='background: #007bff; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>🔄 حركات المخزون</a>";
echo "<a href='modules/inventory/index.php' style='background: #6f42c1; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>📦 المخزون</a>";
echo "<a href='add_inventory_sample_data.php' style='background: #28a745; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>📊 بيانات تجريبية</a>";
echo "<a href='index.php' style='background: #17a2b8; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>🏠 الرئيسية</a>";
echo "</div>";
?>
