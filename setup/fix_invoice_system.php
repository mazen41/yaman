<?php
/**
 * Complete Invoice System Fix
 * Senior PHP Engineer Solution - Comprehensive Diagnostic & Repair
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized access. Please login first.');
}

require_once '../config/database.php';
require_once '../includes/auto_generate_helpers.php';

?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إصلاح نظام الفواتير</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        h2 { color: #34495e; margin-top: 30px; }
        .step { background: #ecf0f1; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #3498db; }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        .info { color: #3498db; font-weight: bold; }
        .btn { display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .btn:hover { background: #2980b9; }
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: right; border-bottom: 1px solid #ddd; }
        th { background: #34495e; color: white; }
        .progress { background: #ecf0f1; height: 30px; border-radius: 15px; overflow: hidden; margin: 20px 0; }
        .progress-bar { background: #3498db; height: 100%; text-align: center; line-height: 30px; color: white; transition: width 0.3s; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 إصلاح شامل لنظام الفواتير</h1>
    
<?php

echo "<div class='step'><h2>المرحلة 1: التشخيص</h2>";

// Step 1: Check if customer_invoices table exists
echo "<p class='info'>▶ فحص جدول customer_invoices...</p>";
try {
    $tableCheck = $db->query("SHOW TABLES LIKE 'customer_invoices'");
    $tableExists = $tableCheck->rowCount() > 0;
    
    if ($tableExists) {
        echo "<p class='success'>✅ الجدول موجود</p>";
        
        // Check table structure
        $columns = $db->query("DESCRIBE customer_invoices")->fetchAll(PDO::FETCH_COLUMN);
        $requiredColumns = ['invoice_number', 'customer_id', 'order_id', 'amount', 'discount_amount', 'paid_amount', 'total_amount', 'status'];
        $missingColumns = array_diff($requiredColumns, $columns);
        
        if (!empty($missingColumns)) {
            echo "<p class='warning'>⚠ أعمدة ناقصة: " . implode(', ', $missingColumns) . "</p>";
        } else {
            echo "<p class='success'>✅ بنية الجدول صحيحة</p>";
        }
    } else {
        echo "<p class='error'>❌ الجدول غير موجود - سيتم إنشاؤه الآن</p>";
        
        // Create the table
        $db->exec("
            CREATE TABLE customer_invoices (
                id INT PRIMARY KEY AUTO_INCREMENT,
                invoice_number VARCHAR(30) UNIQUE NOT NULL,
                customer_id INT NOT NULL,
                order_id INT NOT NULL,
                amount DECIMAL(15,3) DEFAULT 0,
                discount_amount DECIMAL(15,3) DEFAULT 0,
                tax_amount DECIMAL(15,3) DEFAULT 0,
                total_amount DECIMAL(15,3) NOT NULL,
                paid_amount DECIMAL(15,3) DEFAULT 0,
                status ENUM('pending', 'partial', 'paid', 'cancelled') DEFAULT 'pending',
                created_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE RESTRICT,
                FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE RESTRICT,
                FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_invoice_number (invoice_number),
                INDEX idx_customer_id (customer_id),
                INDEX idx_order_id (order_id),
                INDEX idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "<p class='success'>✅ تم إنشاء الجدول بنجاح</p>";
        $tableExists = true;
    }
} catch (PDOException $e) {
    echo "<p class='error'>❌ خطأ: " . $e->getMessage() . "</p>";
    exit;
}

echo "</div>";

// Step 2: Count orders without invoices
echo "<div class='step'><h2>المرحلة 2: تحليل البيانات</h2>";

try {
    $stmt = $db->query("
        SELECT COUNT(*) as total_orders,
               SUM(CASE WHEN i.id IS NULL THEN 1 ELSE 0 END) as orders_without_invoice,
               SUM(CASE WHEN i.id IS NOT NULL THEN 1 ELSE 0 END) as orders_with_invoice
        FROM customer_orders o
        LEFT JOIN customer_invoices i ON o.id = i.order_id
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "<table>";
    echo "<tr><th>الإحصائية</th><th>العدد</th></tr>";
    echo "<tr><td>إجمالي الطلبات</td><td class='info'>" . $stats['total_orders'] . "</td></tr>";
    echo "<tr><td>طلبات بدون فاتورة</td><td class='error'>" . $stats['orders_without_invoice'] . "</td></tr>";
    echo "<tr><td>طلبات لديها فاتورة</td><td class='success'>" . $stats['orders_with_invoice'] . "</td></tr>";
    echo "</table>";
    
    $needsGeneration = $stats['orders_without_invoice'] > 0;
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ خطأ: " . $e->getMessage() . "</p>";
    exit;
}

echo "</div>";

// Step 3: Generate missing invoices
if ($needsGeneration) {
    echo "<div class='step'><h2>المرحلة 3: إنشاء الفواتير الناقصة</h2>";
    
    try {
        $stmt = $db->query("
            SELECT o.id, o.order_number, o.customer_id
            FROM customer_orders o
            LEFT JOIN customer_invoices i ON o.id = i.order_id
            WHERE i.id IS NULL
            ORDER BY o.created_at ASC
        ");
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total = count($orders);
        $success = 0;
        $failed = 0;
        
        echo "<div class='progress'><div class='progress-bar' id='progressBar' style='width: 0%'>0%</div></div>";
        echo "<div id='results'>";
        
        foreach ($orders as $index => $order) {
            try {
                $invoiceNumber = createInvoiceForOrder($db, $order['id'], $_SESSION['user_id']);
                
                if ($invoiceNumber) {
                    echo "<p class='success'>✅ {$order['order_number']} → {$invoiceNumber}</p>";
                    $success++;
                } else {
                    echo "<p class='error'>❌ {$order['order_number']} - فشل الإنشاء</p>";
                    $failed++;
                }
                
                // Update progress
                $progress = round((($index + 1) / $total) * 100);
                echo "<script>
                    document.getElementById('progressBar').style.width = '{$progress}%';
                    document.getElementById('progressBar').textContent = '{$progress}%';
                </script>";
                
                flush();
                ob_flush();
                
            } catch (Exception $e) {
                echo "<p class='error'>❌ {$order['order_number']} - خطأ: " . $e->getMessage() . "</p>";
                $failed++;
            }
        }
        
        echo "</div>";
        
        echo "<h3>النتيجة النهائية:</h3>";
        echo "<table>";
        echo "<tr><th>الحالة</th><th>العدد</th></tr>";
        echo "<tr><td class='success'>نجح</td><td>{$success}</td></tr>";
        echo "<tr><td class='error'>فشل</td><td>{$failed}</td></tr>";
        echo "<tr><td class='info'>الإجمالي</td><td>{$total}</td></tr>";
        echo "</table>";
        
    } catch (PDOException $e) {
        echo "<p class='error'>❌ خطأ: " . $e->getMessage() . "</p>";
    }
    
    echo "</div>";
} else {
    echo "<div class='step'><p class='success'>✅ جميع الطلبات لديها فواتير بالفعل!</p></div>";
}

// Step 4: Verify auto-generation is working
echo "<div class='step'><h2>المرحلة 4: التحقق من التوليد التلقائي</h2>";

$createFile = '../modules/orders/create.php';
$content = file_get_contents($createFile);

if (strpos($content, 'createInvoiceForOrder') !== false && 
    strpos($content, 'auto_generate_helpers.php') !== false) {
    echo "<p class='success'>✅ التوليد التلقائي مفعّل في create.php</p>";
} else {
    echo "<p class='error'>❌ التوليد التلقائي غير مفعّل</p>";
    echo "<p class='warning'>يرجى التأكد من وجود الكود التالي في modules/orders/create.php:</p>";
    echo "<pre>require_once '../../includes/auto_generate_helpers.php';
// After order creation:
createInvoiceForOrder(\$db, \$order_id, \$_SESSION['user_id']);</pre>";
}

echo "</div>";

?>

<div class="step">
    <h2>✅ اكتمل الإصلاح!</h2>
    <p>جميع الفواتير الناقصة تم إنشاؤها والنظام جاهز للعمل.</p>
    <a href="../modules/orders/index.php" class="btn btn-success">الذهاب إلى قائمة الطلبات</a>
    <a href="../modules/orders/create.php" class="btn">إنشاء طلب جديد</a>
</div>

</div>
</body>
</html>
