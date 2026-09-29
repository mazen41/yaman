<?php
/**
 * Add customer_id and order_id columns to expenses table
 * لربط المصروفات بالعملاء (خاصة لدفع التوالف)
 */

require_once '../config/database.php';

echo "<h2>إضافة أعمدة العميل والطلب لجدول المصروفات</h2>";

try {
    // Check if customer_id column exists
    $columns = $db->query("DESCRIBE expenses")->fetchAll(PDO::FETCH_COLUMN);
    
    if (!in_array('customer_id', $columns)) {
        $db->exec("ALTER TABLE expenses ADD COLUMN customer_id INT(11) NULL AFTER vendor_name");
        $db->exec("ALTER TABLE expenses ADD INDEX idx_customer_id (customer_id)");
        echo "<p style='color:green;'>✅ تمت إضافة عمود customer_id</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️ عمود customer_id موجود مسبقاً</p>";
    }
    
    if (!in_array('order_id', $columns)) {
        $db->exec("ALTER TABLE expenses ADD COLUMN order_id INT(11) NULL AFTER customer_id");
        $db->exec("ALTER TABLE expenses ADD INDEX idx_order_id (order_id)");
        echo "<p style='color:green;'>✅ تمت إضافة عمود order_id</p>";
    } else {
        echo "<p style='color:blue;'>ℹ️ عمود order_id موجود مسبقاً</p>";
    }
    
    echo "<h3 style='color:green;'>🎉 تم بنجاح!</h3>";
    echo "<p><a href='../modules/expenses/add.php'>← العودة لإضافة مصروف</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ خطأ: " . $e->getMessage() . "</p>";
}
?>
