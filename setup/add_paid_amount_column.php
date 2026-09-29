<?php
/**
 * Database Migration: Add paid_amount column to customer_orders
 * Senior PHP/MySQL Engineer Implementation
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>";
echo "<html dir='rtl' lang='ar'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Database Migration - Add Paid Amount</title>";
echo "<style>";
echo "body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; }";
echo ".container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }";
echo ".success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }";
echo ".error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }";
echo ".info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }";
echo ".warning { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }";
echo "h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }";
echo "h2 { color: #555; margin-top: 25px; }";
echo "pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }";
echo ".btn { display: inline-block; padding: 12px 24px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }";
echo ".btn:hover { background: #0056b3; }";
echo "</style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";

echo "<h1>🔧 Database Migration: Add Paid Amount Column</h1>";

try {
    // Check if database connection is successful
    if (!$db) {
        throw new Exception("فشل الاتصال بقاعدة البيانات");
    }
    
    echo "<div class='info'>";
    echo "<strong>✅ الاتصال بقاعدة البيانات ناجح</strong>";
    echo "</div>";
    
    // Check if customer_orders table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'customer_orders'")->fetch();
    
    if (!$tableCheck) {
        echo "<div class='error'>";
        echo "<strong>❌ خطأ:</strong> جدول customer_orders غير موجود في قاعدة البيانات";
        echo "</div>";
        exit;
    }
    
    echo "<div class='success'>";
    echo "<strong>✅ جدول customer_orders موجود</strong>";
    echo "</div>";
    
    // Check if paid_amount column already exists
    $columnCheck = $db->query("SHOW COLUMNS FROM customer_orders WHERE Field = 'paid_amount'")->fetch(PDO::FETCH_ASSOC);
    
    if ($columnCheck) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ تحذير:</strong> عمود paid_amount موجود بالفعل في الجدول";
        echo "<br>النوع الحالي: {$columnCheck['Type']}";
        echo "</div>";
        
        // Check if precision needs to be fixed
        $currentType = strtolower($columnCheck['Type']);
        $needsFix = (strpos($currentType, 'decimal(10,3)') === false);
        
        if ($needsFix) {
            echo "<h2>🔧 تصحيح دقة العمود</h2>";
            
            echo "<div class='info'>";
            echo "النوع الحالي: <code>{$columnCheck['Type']}</code><br>";
            echo "النوع المطلوب: <code>DECIMAL(10,3)</code>";
            echo "</div>";
            
            try {
                $fixSql = "ALTER TABLE customer_orders 
                          MODIFY COLUMN paid_amount DECIMAL(10,3) DEFAULT 0.000";
                
                $db->exec($fixSql);
                
                echo "<div class='success'>";
                echo "<strong>✅ تم تصحيح دقة العمود من {$columnCheck['Type']} إلى DECIMAL(10,3)</strong>";
                echo "</div>";
                
                echo "<pre>SQL: $fixSql</pre>";
                
                // Verify the fix
                $verifyColumn = $db->query("SHOW COLUMNS FROM customer_orders WHERE Field = 'paid_amount'")->fetch(PDO::FETCH_ASSOC);
                echo "<div class='success'>";
                echo "<strong>التحقق:</strong> النوع الجديد هو <code>{$verifyColumn['Type']}</code>";
                echo "</div>";
                
            } catch (PDOException $e) {
                echo "<div class='error'>";
                echo "<strong>❌ خطأ في التصحيح:</strong> " . $e->getMessage();
                echo "</div>";
            }
        } else {
            echo "<div class='success'>";
            echo "<strong>✅ الدقة صحيحة:</strong> العمود بالفعل بالدقة الصحيحة DECIMAL(10,3)";
            echo "</div>";
        }
    } else {
        echo "<h2>📝 إضافة عمود paid_amount</h2>";
        
        // Add paid_amount column
        $sql = "ALTER TABLE customer_orders 
                ADD COLUMN paid_amount DECIMAL(10,3) DEFAULT 0.000 AFTER final_amount";
        
        $db->exec($sql);
        
        echo "<div class='success'>";
        echo "<strong>✅ تم إضافة عمود paid_amount بنجاح</strong>";
        echo "</div>";
        
        echo "<pre>SQL: $sql</pre>";
    }
    
    // Verify the column was added
    echo "<h2>🔍 التحقق من هيكل الجدول</h2>";
    
    $columns = $db->query("DESCRIBE customer_orders")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='info'>";
    echo "<strong>أعمدة جدول customer_orders:</strong><br><br>";
    echo "<table style='width: 100%; border-collapse: collapse;'>";
    echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
    echo "<td style='padding: 10px; border: 1px solid #ddd;'>Field</td>";
    echo "<td style='padding: 10px; border: 1px solid #ddd;'>Type</td>";
    echo "<td style='padding: 10px; border: 1px solid #ddd;'>Null</td>";
    echo "<td style='padding: 10px; border: 1px solid #ddd;'>Default</td>";
    echo "</tr>";
    
    foreach ($columns as $column) {
        $highlight = ($column['Field'] === 'paid_amount') ? "style='background: #d4edda;'" : "";
        echo "<tr $highlight>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$column['Field']}</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$column['Type']}</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$column['Null']}</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . ($column['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    echo "</div>";
    
    // Update existing orders with paid_amount = 0
    echo "<h2>🔄 تحديث البيانات الموجودة</h2>";
    
    $updateSql = "UPDATE customer_orders 
                  SET paid_amount = 0.000 
                  WHERE paid_amount IS NULL";
    
    $stmt = $db->prepare($updateSql);
    $stmt->execute();
    $updatedRows = $stmt->rowCount();
    
    echo "<div class='success'>";
    echo "<strong>✅ تم تحديث {$updatedRows} سجل بقيمة paid_amount = 0.000</strong>";
    echo "</div>";
    
    // Show sample data
    echo "<h2>📊 عينة من البيانات</h2>";
    
    $sampleData = $db->query("
        SELECT id, order_number, total_amount, discount_amount, final_amount, paid_amount,
               (final_amount - paid_amount) as remaining_amount
        FROM customer_orders 
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($sampleData)) {
        echo "<div class='info'>";
        echo "<table style='width: 100%; border-collapse: collapse; font-size: 12px;'>";
        echo "<tr style='background: #f8f9fa; font-weight: bold;'>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>رقم الطلب</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>الإجمالي</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>الخصم</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>المبلغ النهائي</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>المبلغ المدفوع</td>";
        echo "<td style='padding: 8px; border: 1px solid #ddd;'>المتبقي</td>";
        echo "</tr>";
        
        foreach ($sampleData as $row) {
            echo "<tr>";
            echo "<td style='padding: 8px; border: 1px solid #ddd;'>{$row['order_number']}</td>";
            echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . number_format($row['total_amount'], 3) . "</td>";
            echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . number_format($row['discount_amount'], 3) . "</td>";
            echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . number_format($row['final_amount'], 3) . "</td>";
            echo "<td style='padding: 8px; border: 1px solid #ddd; background: #d4edda;'>" . number_format($row['paid_amount'], 3) . "</td>";
            echo "<td style='padding: 8px; border: 1px solid #ddd;'>" . number_format($row['remaining_amount'], 3) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        echo "</div>";
    }
    
    echo "<div class='success' style='margin-top: 30px; font-size: 18px;'>";
    echo "<strong>🎉 تم إكمال الترحيل بنجاح!</strong><br><br>";
    echo "يمكنك الآن استخدام عمود paid_amount في جميع الاستعلامات والتقارير.";
    echo "</div>";
    
    echo "<div style='text-align: center; margin-top: 30px;'>";
    echo "<a href='../modules/orders/index.php' class='btn'>📋 عرض قائمة الطلبات</a>";
    echo "<a href='../index.php' class='btn' style='background: #28a745;'>🏠 الصفحة الرئيسية</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<strong>❌ خطأ في قاعدة البيانات:</strong><br>";
    echo $e->getMessage();
    echo "</div>";
    
    echo "<pre style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "Error Code: " . $e->getCode() . "\n";
    echo "Error Message: " . $e->getMessage() . "\n";
    echo "Stack Trace:\n" . $e->getTraceAsString();
    echo "</pre>";
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ خطأ:</strong><br>";
    echo $e->getMessage();
    echo "</div>";
}

echo "</div>";
echo "</body>";
echo "</html>";
?>
