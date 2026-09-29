<?php
/**
 * Update purchase_orders table to add new required fields
 * Senior PHP/MySQL Engineer Implementation
 */

require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تحديث جدول طلبات الشراء</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; border-radius: 5px; margin: 10px 0; }
        h1 { color: #333; border-bottom: 3px solid #28a745; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 25px; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        td, th { padding: 10px; border: 1px solid #ddd; text-align: right; }
        th { background: #f8f9fa; font-weight: bold; }
        .btn { display: inline-block; padding: 12px 24px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .btn:hover { background: #218838; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 تحديث جدول طلبات الشراء</h1>
    
    <?php
    try {
        // Check if table exists
        $tableCheck = $db->query("SHOW TABLES LIKE 'purchase_orders'")->fetch();
        
        if (!$tableCheck) {
            echo "<div class='error'>";
            echo "<strong>❌ خطأ:</strong> جدول purchase_orders غير موجود";
            echo "</div>";
            exit;
        }
        
        echo "<div class='success'>";
        echo "<strong>✅ جدول purchase_orders موجود</strong>";
        echo "</div>";
        
        // Get current columns
        $columns = $db->query("DESCRIBE purchase_orders")->fetchAll(PDO::FETCH_ASSOC);
        $existingColumns = array_column($columns, 'Field');
        
        echo "<h2>📋 الأعمدة الحالية</h2>";
        echo "<div class='info'>";
        echo "عدد الأعمدة: " . count($existingColumns);
        echo "</div>";
        
        // Define new columns to add in correct order
        // First, columns that depend on existing columns
        $newColumns = [
            // Add after supplier_id (should exist)
            'purchase_group_id' => [
                'definition' => "INT(11) NULL",
                'after' => 'supplier_id',
                'priority' => 1
            ],
            // Add after order_number (should exist)
            'purchase_basket_number' => [
                'definition' => "VARCHAR(100) NULL",
                'after' => 'order_number',
                'priority' => 1
            ],
            // Add order_date if missing
            'order_date' => [
                'definition' => "DATE NULL",
                'after' => 'purchase_group_id',
                'priority' => 2
            ],
            // Add after order_date
            'purchase_date' => [
                'definition' => "DATE NULL",
                'after' => 'order_date',
                'priority' => 3
            ],
            // Add after purchase_date
            'account_number' => [
                'definition' => "VARCHAR(100) NULL",
                'after' => 'purchase_date',
                'priority' => 4
            ],
            // Add after account_number
            'expected_delivery_date' => [
                'definition' => "DATE NULL",
                'after' => 'account_number',
                'priority' => 5
            ],
            // Add after expected_delivery_date
            'payment_terms' => [
                'definition' => "TEXT NULL",
                'after' => 'expected_delivery_date',
                'priority' => 6
            ],
            // Add after payment_terms
            'delivery_address' => [
                'definition' => "TEXT NULL",
                'after' => 'payment_terms',
                'priority' => 7
            ],
            // Add after total_amount (should exist)
            'paid_amount' => [
                'definition' => "DECIMAL(10,3) DEFAULT 0.000",
                'after' => 'total_amount',
                'priority' => 1
            ],
            // Add after paid_amount
            'remaining_amount' => [
                'definition' => "DECIMAL(10,3) DEFAULT 0.000",
                'after' => 'paid_amount',
                'priority' => 8
            ],
            // Add after remaining_amount
            'status' => [
                'definition' => "VARCHAR(50) DEFAULT 'pending'",
                'after' => 'remaining_amount',
                'priority' => 9
            ]
        ];
        
        // Sort by priority to add in correct order
        uasort($newColumns, function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
        
        echo "<h2>🔨 إضافة الأعمدة الجديدة</h2>";
        
        $addedColumns = [];
        $skippedColumns = [];
        
        foreach ($newColumns as $columnName => $columnInfo) {
            // Refresh existing columns list after each addition
            $existingColumns = $db->query("DESCRIBE purchase_orders")->fetchAll(PDO::FETCH_COLUMN);
            
            if (in_array($columnName, $existingColumns)) {
                $skippedColumns[] = $columnName;
                echo "<div class='warning'>";
                echo "<strong>⚠️ تخطي:</strong> العمود <code>$columnName</code> موجود بالفعل";
                echo "</div>";
            } else {
                try {
                    $afterColumn = $columnInfo['after'];
                    $definition = $columnInfo['definition'];
                    
                    // Check if the 'after' column exists
                    if (in_array($afterColumn, $existingColumns)) {
                        $sql = "ALTER TABLE purchase_orders ADD COLUMN $columnName $definition AFTER $afterColumn";
                    } else {
                        // If 'after' column doesn't exist, just add at the end
                        $sql = "ALTER TABLE purchase_orders ADD COLUMN $columnName $definition";
                        echo "<div class='info'>";
                        echo "<strong>ℹ️ ملاحظة:</strong> العمود <code>$afterColumn</code> غير موجود، سيتم إضافة <code>$columnName</code> في النهاية";
                        echo "</div>";
                    }
                    
                    $db->exec($sql);
                    $addedColumns[] = $columnName;
                    
                    echo "<div class='success'>";
                    echo "<strong>✅ تمت الإضافة:</strong> العمود <code>$columnName</code>";
                    echo "</div>";
                    echo "<pre>SQL: $sql</pre>";
                } catch (PDOException $e) {
                    echo "<div class='error'>";
                    echo "<strong>❌ خطأ في إضافة العمود $columnName:</strong> " . $e->getMessage();
                    echo "</div>";
                }
            }
        }
        
        // Update purchase_order_items table to support product_name
        echo "<h2>🔨 تحديث جدول purchase_order_items</h2>";
        
        $itemsTableCheck = $db->query("SHOW TABLES LIKE 'purchase_order_items'")->fetch();
        
        if ($itemsTableCheck) {
            $itemColumns = $db->query("DESCRIBE purchase_order_items")->fetchAll(PDO::FETCH_ASSOC);
            $itemExistingColumns = array_column($itemColumns, 'Field');
            
            // Add product_name column
            if (!in_array('product_name', $itemExistingColumns)) {
                try {
                    $sql = "ALTER TABLE purchase_order_items ADD COLUMN product_name VARCHAR(255) NULL AFTER purchase_order_id";
                    $db->exec($sql);
                    
                    echo "<div class='success'>";
                    echo "<strong>✅ تمت الإضافة:</strong> العمود <code>product_name</code> في جدول purchase_order_items";
                    echo "</div>";
                    echo "<pre>SQL: $sql</pre>";
                } catch (PDOException $e) {
                    echo "<div class='error'>";
                    echo "<strong>❌ خطأ:</strong> " . $e->getMessage();
                    echo "</div>";
                }
            } else {
                echo "<div class='warning'>";
                echo "<strong>⚠️ تخطي:</strong> العمود <code>product_name</code> موجود بالفعل";
                echo "</div>";
            }
            
            // Make product_id nullable (optional)
            if (in_array('product_id', $itemExistingColumns)) {
                try {
                    $sql = "ALTER TABLE purchase_order_items MODIFY COLUMN product_id INT(11) NULL";
                    $db->exec($sql);
                    
                    echo "<div class='success'>";
                    echo "<strong>✅ تم التحديث:</strong> العمود <code>product_id</code> أصبح اختياري";
                    echo "</div>";
                    echo "<pre>SQL: $sql</pre>";
                } catch (PDOException $e) {
                    echo "<div class='warning'>";
                    echo "<strong>⚠️ تحذير:</strong> " . $e->getMessage();
                    echo "</div>";
                }
            }
        } else {
            echo "<div class='error'>";
            echo "<strong>❌ خطأ:</strong> جدول purchase_order_items غير موجود";
            echo "</div>";
        }
        
        // Verify final structure
        echo "<h2>🔍 التحقق من الهيكل النهائي</h2>";
        
        $finalColumns = $db->query("DESCRIBE purchase_orders")->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<div class='info'>";
        echo "<strong>أعمدة جدول purchase_orders:</strong><br><br>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
        
        foreach ($finalColumns as $col) {
            $highlight = in_array($col['Field'], array_keys($newColumns)) ? 'style="background: #d4edda;"' : '';
            echo "<tr $highlight>";
            echo "<td>{$col['Field']}</td>";
            echo "<td>{$col['Type']}</td>";
            echo "<td>{$col['Null']}</td>";
            echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        echo "</div>";
        
        // Summary
        echo "<div class='success' style='margin-top: 30px; font-size: 18px;'>";
        echo "<strong>🎉 تم إكمال التحديث بنجاح!</strong><br><br>";
        echo "الأعمدة المضافة: " . count($addedColumns) . "<br>";
        echo "الأعمدة المتخطاة: " . count($skippedColumns) . "<br>";
        echo "</div>";
        
        echo "<div style='text-align: center; margin-top: 30px;'>";
        echo "<a href='../modules/purchases/add.php' class='btn'>➕ إضافة طلب شراء جديد</a>";
        echo "<a href='../modules/purchases/index.php' class='btn' style='background: #007bff;'>📋 قائمة المشتريات</a>";
        echo "</div>";
        
    } catch (PDOException $e) {
        echo "<div class='error'>";
        echo "<strong>❌ خطأ في قاعدة البيانات:</strong><br>";
        echo $e->getMessage();
        echo "</div>";
    }
    ?>
</div>
</body>
</html>
