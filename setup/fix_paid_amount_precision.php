<?php
/**
 * Fix paid_amount column precision to DECIMAL(10,3)
 * Senior PHP/MySQL Engineer Implementation
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>";
echo "<html dir='rtl' lang='ar'>";
echo "<head>";
echo "<meta charset='UTF-8'>";
echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
echo "<title>Fix Paid Amount Precision</title>";
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
echo "table { width: 100%; border-collapse: collapse; margin: 10px 0; }";
echo "td, th { padding: 10px; border: 1px solid #ddd; text-align: right; }";
echo "th { background: #f8f9fa; font-weight: bold; }";
echo ".highlight { background: #fff3cd; font-weight: bold; }";
echo "</style>";
echo "</head>";
echo "<body>";
echo "<div class='container'>";

echo "<h1>🔧 Fix Paid Amount Column Precision</h1>";

try {
    // Check if database connection is successful
    if (!$db) {
        throw new Exception("فشل الاتصال بقاعدة البيانات");
    }
    
    echo "<div class='info'>";
    echo "<strong>✅ الاتصال بقاعدة البيانات ناجح</strong>";
    echo "</div>";
    
    // Check current column definition
    echo "<h2>🔍 فحص التعريف الحالي للعمود</h2>";
    
    $columnInfo = $db->query("SHOW COLUMNS FROM customer_orders WHERE Field = 'paid_amount'")->fetch(PDO::FETCH_ASSOC);
    
    if (!$columnInfo) {
        echo "<div class='error'>";
        echo "<strong>❌ خطأ:</strong> عمود paid_amount غير موجود في الجدول";
        echo "</div>";
        echo "<div class='info'>";
        echo "يرجى تشغيل <a href='add_paid_amount_column.php'>add_paid_amount_column.php</a> أولاً";
        echo "</div>";
        exit;
    }
    
    echo "<div class='info'>";
    echo "<strong>التعريف الحالي:</strong><br>";
    echo "<table>";
    echo "<tr><th>Field</th><td>{$columnInfo['Field']}</td></tr>";
    echo "<tr><th>Type</th><td class='highlight'>{$columnInfo['Type']}</td></tr>";
    echo "<tr><th>Null</th><td>{$columnInfo['Null']}</td></tr>";
    echo "<tr><th>Default</th><td>" . ($columnInfo['Default'] ?? 'NULL') . "</td></tr>";
    echo "</table>";
    echo "</div>";
    
    // Check if precision needs to be fixed
    $currentType = strtolower($columnInfo['Type']);
    $needsFix = !preg_match('/decimal\(10,3\)/i', $currentType);
    
    if ($needsFix) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ يحتاج إلى تصحيح:</strong> الدقة الحالية هي {$columnInfo['Type']} ويجب أن تكون DECIMAL(10,3)";
        echo "</div>";
        
        echo "<h2>🔧 تصحيح دقة العمود</h2>";
        
        // Modify column to correct precision
        $sql = "ALTER TABLE customer_orders 
                MODIFY COLUMN paid_amount DECIMAL(10,3) DEFAULT 0.000";
        
        $db->exec($sql);
        
        echo "<div class='success'>";
        echo "<strong>✅ تم تصحيح دقة العمود بنجاح</strong>";
        echo "</div>";
        
        echo "<pre>SQL: $sql</pre>";
        
        // Verify the change
        $newColumnInfo = $db->query("SHOW COLUMNS FROM customer_orders WHERE Field = 'paid_amount'")->fetch(PDO::FETCH_ASSOC);
        
        echo "<div class='success'>";
        echo "<strong>التعريف الجديد:</strong><br>";
        echo "<table>";
        echo "<tr><th>Field</th><td>{$newColumnInfo['Field']}</td></tr>";
        echo "<tr><th>Type</th><td class='highlight'>{$newColumnInfo['Type']}</td></tr>";
        echo "<tr><th>Null</th><td>{$newColumnInfo['Null']}</td></tr>";
        echo "<tr><th>Default</th><td>" . ($newColumnInfo['Default'] ?? 'NULL') . "</td></tr>";
        echo "</table>";
        echo "</div>";
        
    } else {
        echo "<div class='success'>";
        echo "<strong>✅ العمود صحيح:</strong> الدقة الحالية DECIMAL(10,3) صحيحة ولا تحتاج إلى تعديل";
        echo "</div>";
    }
    
    // Show sample data
    echo "<h2>📊 عينة من البيانات</h2>";
    
    $sampleData = $db->query("
        SELECT id, order_number, final_amount, paid_amount,
               (final_amount - paid_amount) as remaining_amount
        FROM customer_orders 
        ORDER BY id DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($sampleData)) {
        echo "<div class='info'>";
        echo "<table>";
        echo "<tr>";
        echo "<th>رقم الطلب</th>";
        echo "<th>المبلغ النهائي</th>";
        echo "<th>المبلغ المدفوع</th>";
        echo "<th>المتبقي</th>";
        echo "</tr>";
        
        foreach ($sampleData as $row) {
            echo "<tr>";
            echo "<td>{$row['order_number']}</td>";
            echo "<td>" . number_format($row['final_amount'], 3) . " ريال</td>";
            echo "<td class='highlight'>" . number_format($row['paid_amount'], 3) . " ريال</td>";
            echo "<td>" . number_format($row['remaining_amount'], 3) . " ريال</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        echo "</div>";
    }
    
    echo "<div class='success' style='margin-top: 30px; font-size: 18px;'>";
    echo "<strong>🎉 تم إكمال التصحيح بنجاح!</strong><br><br>";
    echo "يمكنك الآن استخدام نظام الطلبات بشكل صحيح.";
    echo "</div>";
    
    echo "<div style='text-align: center; margin-top: 30px;'>";
    echo "<a href='../modules/orders/index.php' class='btn'>📋 عرض قائمة الطلبات</a>";
    echo "<a href='../modules/orders/create.php' class='btn' style='background: #28a745;'>➕ إنشاء طلب جديد</a>";
    echo "<a href='../index.php' class='btn' style='background: #6c757d;'>🏠 الصفحة الرئيسية</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<strong>❌ خطأ في قاعدة البيانات:</strong><br>";
    echo $e->getMessage();
    echo "</div>";
    
    echo "<pre style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "Error Code: " . $e->getCode() . "\n";
    echo "Error Message: " . $e->getMessage() . "\n";
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
