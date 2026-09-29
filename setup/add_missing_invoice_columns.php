<?php
/**
 * Add Missing Columns to customer_invoices Table
 */

require_once '../config/database.php';

?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إضافة الأعمدة الناقصة</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        h1 { color: #2c3e50; }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .btn { display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 إضافة الأعمدة الناقصة</h1>

<?php

try {
    echo "<h2>جاري إضافة الأعمدة...</h2>";
    
    // Check and add discount_amount
    $check = $db->query("SHOW COLUMNS FROM customer_invoices LIKE 'discount_amount'");
    if ($check->rowCount() == 0) {
        echo "<p>إضافة عمود discount_amount...</p>";
        $db->exec("ALTER TABLE customer_invoices ADD COLUMN discount_amount DECIMAL(15,3) DEFAULT 0 AFTER amount");
        echo "<p class='success'>✅ تم إضافة discount_amount</p>";
    } else {
        echo "<p class='success'>✅ discount_amount موجود بالفعل</p>";
    }
    
    // Check and add paid_amount
    $check = $db->query("SHOW COLUMNS FROM customer_invoices LIKE 'paid_amount'");
    if ($check->rowCount() == 0) {
        echo "<p>إضافة عمود paid_amount...</p>";
        $db->exec("ALTER TABLE customer_invoices ADD COLUMN paid_amount DECIMAL(15,3) DEFAULT 0 AFTER total_amount");
        echo "<p class='success'>✅ تم إضافة paid_amount</p>";
    } else {
        echo "<p class='success'>✅ paid_amount موجود بالفعل</p>";
    }
    
    // Verify all required columns exist
    echo "<h2>التحقق من الأعمدة:</h2>";
    $columns = $db->query("SHOW COLUMNS FROM customer_invoices")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='width:100%; border-collapse: collapse;'>";
    echo "<tr><th>اسم العمود</th><th>النوع</th><th>القيمة الافتراضية</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h2 class='success'>✅ تم إضافة جميع الأعمدة بنجاح!</h2>";
    echo "<p>الآن يمكنك تشغيل fix_invoice_system.php مرة أخرى</p>";
    echo "<a href='fix_invoice_system.php' class='btn'>تشغيل إصلاح النظام</a>";
    echo "<a href='../modules/orders/index.php' class='btn'>الذهاب للطلبات</a>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ خطأ: " . $e->getMessage() . "</p>";
}

?>

</div>
</body>
</html>
