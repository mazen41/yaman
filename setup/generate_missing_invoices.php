<?php
/**
 * Generate Missing Invoices for Existing Orders
 * This script will create invoices for any orders that don't have one yet
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized access');
}

require_once '../config/database.php';
require_once '../includes/auto_generate_helpers.php';

echo "<h2>Generating Missing Invoices...</h2>";
echo "<style>
    body { font-family: Arial, sans-serif; padding: 20px; direction: rtl; }
    .success { color: green; }
    .error { color: red; }
    .info { color: blue; }
    .summary { background: #f0f0f0; padding: 15px; margin: 20px 0; border-radius: 5px; }
</style>";

try {
    // Check if customer_invoices table exists
    $tableCheck = $db->query("SHOW TABLES LIKE 'customer_invoices'");
    if ($tableCheck->rowCount() == 0) {
        echo "<p class='error'>❌ جدول customer_invoices غير موجود!</p>";
        echo "<p><a href='create_customer_invoices_table.php'>إنشاء الجدول الآن</a></p>";
        exit;
    }
    
    // Get all orders without invoices
    $stmt = $db->query("
        SELECT o.id, o.order_number, o.customer_id, o.created_at
        FROM customer_orders o
        LEFT JOIN customer_invoices i ON o.id = i.order_id
        WHERE i.id IS NULL
        ORDER BY o.created_at DESC
    ");
    
    $orders_without_invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total_orders = count($orders_without_invoices);
    
    if ($total_orders == 0) {
        echo "<p class='success'>✅ جميع الطلبات لديها فواتير بالفعل!</p>";
        echo "<p><a href='../modules/orders/index.php'>العودة إلى قائمة الطلبات</a></p>";
        exit;
    }
    
    echo "<div class='summary'>";
    echo "<strong>تم العثور على {$total_orders} طلب بدون فاتورة</strong>";
    echo "</div>";
    
    $success_count = 0;
    $error_count = 0;
    
    echo "<h3>جاري إنشاء الفواتير...</h3>";
    echo "<ul>";
    
    foreach ($orders_without_invoices as $order) {
        try {
            $invoiceNumber = createInvoiceForOrder($db, $order['id'], $_SESSION['user_id']);
            
            if ($invoiceNumber) {
                echo "<li class='success'>✅ تم إنشاء فاتورة <strong>{$invoiceNumber}</strong> للطلب <strong>{$order['order_number']}</strong></li>";
                $success_count++;
            } else {
                echo "<li class='error'>❌ فشل إنشاء فاتورة للطلب <strong>{$order['order_number']}</strong></li>";
                $error_count++;
            }
            
            // Small delay to prevent overwhelming the database
            usleep(100000); // 0.1 second
            
        } catch (Exception $e) {
            echo "<li class='error'>❌ خطأ في الطلب <strong>{$order['order_number']}</strong>: " . $e->getMessage() . "</li>";
            $error_count++;
        }
    }
    
    echo "</ul>";
    
    echo "<div class='summary'>";
    echo "<h3>النتيجة النهائية:</h3>";
    echo "<p class='success'>✅ تم إنشاء <strong>{$success_count}</strong> فاتورة بنجاح</p>";
    if ($error_count > 0) {
        echo "<p class='error'>❌ فشل إنشاء <strong>{$error_count}</strong> فاتورة</p>";
    }
    echo "</div>";
    
    echo "<p><a href='../modules/orders/index.php' style='display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>العودة إلى قائمة الطلبات</a></p>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ خطأ في قاعدة البيانات: " . $e->getMessage() . "</p>";
}
?>
