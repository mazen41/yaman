<?php
/**
 * Create Order Status History Table
 * إنشاء جدول سجل حالات الطلبات
 */

require_once '../config/database.php';

echo "<!DOCTYPE html>
<html dir='rtl' lang='ar'>
<head>
    <meta charset='UTF-8'>
    <title>إنشاء جدول سجل الحالات</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .success { color: green; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border: 1px solid #bee5eb; margin: 10px 0; }
    </style>
</head>
<body>";

echo "<h1>إنشاء جدول سجل حالات الطلبات</h1>";

try {
    // Check if table exists
    $tables = $db->query("SHOW TABLES LIKE 'order_status_history'")->fetchAll();
    
    if (empty($tables)) {
        echo "<div class='info'>جدول order_status_history غير موجود. جاري الإنشاء...</div>";
        
        $db->exec("
            CREATE TABLE order_status_history (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                old_status VARCHAR(50),
                new_status VARCHAR(50) NOT NULL,
                notes TEXT,
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_order_id (order_id),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "<div class='success'>✅ تم إنشاء جدول order_status_history بنجاح</div>";
    } else {
        echo "<div class='info'>✅ جدول order_status_history موجود بالفعل</div>";
    }
    
    // Populate with existing order statuses
    echo "<div class='info'>جاري إضافة سجلات للطلبات الموجودة...</div>";
    
    try {
        $orders = $db->query("
            SELECT id, status, created_at 
            FROM customer_orders 
            WHERE id NOT IN (SELECT DISTINCT order_id FROM order_status_history)
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($orders)) {
            $stmt = $db->prepare("
                INSERT INTO order_status_history (order_id, old_status, new_status, notes, created_at)
                VALUES (?, NULL, ?, 'تم إنشاء الطلب', ?)
            ");
            
            $count = 0;
            foreach ($orders as $order) {
                try {
                    $stmt->execute([
                        $order['id'],
                        $order['status'],
                        $order['created_at']
                    ]);
                    $count++;
                } catch (PDOException $e) {
                    // Skip if error
                    continue;
                }
            }
            
            echo "<div class='success'>✅ تم إضافة $count سجل للطلبات الموجودة</div>";
        } else {
            echo "<div class='info'>جميع الطلبات لديها سجلات بالفعل</div>";
        }
    } catch (PDOException $e) {
        echo "<div class='info'>⚠️ تخطي إضافة السجلات: " . $e->getMessage() . "</div>";
    }
    
    echo "<div class='success'><strong>✅ اكتمل الإعداد بنجاح!</strong></div>";
    echo "<p><a href='../modules/orders/index.php'>الذهاب إلى قائمة الطلبات</a></p>";
    
} catch (PDOException $e) {
    echo "<div class='error'>❌ خطأ: " . $e->getMessage() . "</div>";
}

echo "</body></html>";
?>
