<?php
/**
 * Verify and fix order amounts to ensure they match with discount calculations
 */

require_once 'config/database.php';

echo "<h2>تحقق من صحة مبالغ الطلبات</h2>\n\n";

try {
    // Get all orders
    $stmt = $db->query("
        SELECT 
            id,
            order_number,
            subtotal_amount,
            discount_amount,
            automatic_discount_amount,
            additional_discount,
            shipping_cost,
            total_amount,
            final_amount,
            created_at
        FROM customer_orders
        ORDER BY id DESC
        LIMIT 50
    ");
    
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; width: 100%;'>\n";
    echo "<tr style='background: #f0f0f0;'>";
    echo "<th>رقم الطلب</th>";
    echo "<th>المجموع الفرعي</th>";
    echo "<th>الخصم التلقائي</th>";
    echo "<th>خصم إضافي</th>";
    echo "<th>الشحن</th>";
    echo "<th>المجموع المحسوب</th>";
    echo "<th>المجموع المحفوظ</th>";
    echo "<th>الحالة</th>";
    echo "</tr>\n";
    
    $fixed_count = 0;
    
    foreach ($orders as $order) {
        $subtotal = floatval($order['subtotal_amount']);
        $auto_discount = floatval($order['automatic_discount_amount']);
        $additional_discount = floatval($order['additional_discount']);
        $shipping = floatval($order['shipping_cost']);
        
        // Calculate what final_amount SHOULD be
        $calculated_final = $subtotal - $auto_discount - $additional_discount + $shipping;
        $stored_final = floatval($order['final_amount']);
        
        $is_correct = abs($calculated_final - $stored_final) < 0.01;
        $status_color = $is_correct ? '#d4edda' : '#f8d7da';
        $status_text = $is_correct ? '✓ صحيح' : '✗ خطأ';
        
        echo "<tr style='background: {$status_color};'>";
        echo "<td>{$order['order_number']}</td>";
        echo "<td>" . number_format($subtotal, 0, '', '') . "</td>";
        echo "<td>" . number_format($auto_discount, 0, '', '') . "</td>";
        echo "<td>" . number_format($additional_discount, 0, '', '') . "</td>";
        echo "<td>" . number_format($shipping, 0, '', '') . "</td>";
        echo "<td><strong>" . number_format($calculated_final, 0, '', '') . "</strong></td>";
        echo "<td>" . number_format($stored_final, 0, '', '') . "</td>";
        echo "<td><strong>{$status_text}</strong></td>";
        echo "</tr>\n";
        
        // Fix if incorrect
        if (!$is_correct) {
            $update_stmt = $db->prepare("
                UPDATE customer_orders 
                SET final_amount = ?,
                    total_amount = ?
                WHERE id = ?
            ");
            
            $total_after_discount = $subtotal - $auto_discount - $additional_discount;
            $update_stmt->execute([$calculated_final, $total_after_discount, $order['id']]);
            $fixed_count++;
        }
    }
    
    echo "</table>\n\n";
    
    if ($fixed_count > 0) {
        echo "<div style='background: #d4edda; padding: 15px; margin-top: 20px; border-radius: 5px;'>";
        echo "<strong>✓ تم إصلاح {$fixed_count} طلب</strong>";
        echo "</div>\n";
    } else {
        echo "<div style='background: #d4edda; padding: 15px; margin-top: 20px; border-radius: 5px;'>";
        echo "<strong>✓ جميع الطلبات صحيحة!</strong>";
        echo "</div>\n";
    }
    
    // Show today's statistics
    echo "<h3 style='margin-top: 30px;'>إحصائيات اليوم</h3>\n";
    
    $today_stats = $db->query("
        SELECT 
            COUNT(*) as order_count,
            COALESCE(SUM(subtotal_amount), 0) as total_subtotal,
            COALESCE(SUM(automatic_discount_amount), 0) as total_discount,
            COALESCE(SUM(final_amount), 0) as total_final
        FROM customer_orders
        WHERE DATE(created_at) = CURDATE()
        AND status NOT IN ('cancelled')
    ")->fetch(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>\n";
    echo "<tr><td><strong>عدد الطلبات اليوم:</strong></td><td>{$today_stats['order_count']}</td></tr>\n";
    echo "<tr><td><strong>المجموع الفرعي:</strong></td><td>" . number_format($today_stats['total_subtotal'], 0, '', '') . " ر.ي</td></tr>\n";
    echo "<tr><td><strong>إجمالي الخصم:</strong></td><td>" . number_format($today_stats['total_discount'], 0, '', '') . " ر.ي</td></tr>\n";
    echo "<tr><td><strong>المبيعات النهائية:</strong></td><td><strong style='color: green; font-size: 1.2em;'>" . number_format($today_stats['total_final'], 0, '', '') . " ر.ي</strong></td></tr>\n";
    echo "</table>\n";
    
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo "<strong>خطأ:</strong> " . $e->getMessage();
    echo "</div>\n";
}
?>

<style>
body {
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    direction: rtl;
    padding: 20px;
    background: #f5f5f5;
}
table {
    background: white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}
th {
    background: #4CAF50;
    color: white;
    font-weight: bold;
}
</style>
