<?php
/**
 * Update All Phone Numbers to Yemen Format (+967)
 * Senior Engineer Solution
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized access. Please login first.');
}

require_once '../config/database.php';
require_once '../includes/phone_utils.php';

?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>تحديث أرقام الهواتف إلى الصيغة اليمنية</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        h2 { color: #34495e; margin-top: 30px; background: #ecf0f1; padding: 15px; border-radius: 5px; }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .info { color: #3498db; font-weight: bold; }
        .warning { color: #f39c12; font-weight: bold; }
        .step { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #3498db; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: right; border-bottom: 1px solid #ddd; }
        th { background: #34495e; color: white; }
        .btn { display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .btn-success { background: #27ae60; }
        .phone-example { background: #fff3cd; padding: 15px; border-radius: 8px; margin: 15px 0; border: 2px solid #ffc107; }
    </style>
</head>
<body>
<div class="container">
    <h1>🇾🇪 تحديث أرقام الهواتف إلى الصيغة اليمنية</h1>
    
    <div class="phone-example">
        <h3>📱 الصيغة الجديدة:</h3>
        <ul style="font-size: 18px; line-height: 2;">
            <li><strong>الجوال:</strong> +967 777 123 456 (9 أرقام)</li>
            <li><strong>الأرضي:</strong> +967 1 234 567 (7 أرقام)</li>
            <li><strong>رمز الدولة:</strong> +967 (اليمن)</li>
        </ul>
    </div>

<?php

try {
    $db->beginTransaction();
    
    $updated_count = 0;
    $tables_updated = [];
    
    echo "<h2>المرحلة 1: تحديث جدول العملاء (Customers)</h2>";
    echo "<div class='step'>";
    
    // Update customers table
    $stmt = $db->query("SELECT id, mobile_number, whatsapp_number, phone FROM customers");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<p class='info'>تم العثور على " . count($customers) . " عميل</p>";
    
    foreach ($customers as $customer) {
        $updates = [];
        $params = [];
        
        if (!empty($customer['mobile_number'])) {
            $formatted = formatYemenPhone($customer['mobile_number']);
            $updates[] = "mobile_number = ?";
            $params[] = $formatted;
        }
        
        if (!empty($customer['whatsapp_number'])) {
            $formatted = formatYemenPhone($customer['whatsapp_number']);
            $updates[] = "whatsapp_number = ?";
            $params[] = $formatted;
        }
        
        if (!empty($customer['phone'])) {
            $formatted = formatYemenPhone($customer['phone']);
            $updates[] = "phone = ?";
            $params[] = $formatted;
        }
        
        if (!empty($updates)) {
            $params[] = $customer['id'];
            $sql = "UPDATE customers SET " . implode(', ', $updates) . " WHERE id = ?";
            $update_stmt = $db->prepare($sql);
            $update_stmt->execute($params);
            $updated_count++;
        }
    }
    
    echo "<p class='success'>✅ تم تحديث $updated_count عميل</p>";
    $tables_updated['customers'] = $updated_count;
    echo "</div>";
    
    // Update suppliers table
    echo "<h2>المرحلة 2: تحديث جدول الموردين (Suppliers)</h2>";
    echo "<div class='step'>";
    
    try {
        $stmt = $db->query("SELECT id, phone, mobile FROM suppliers");
        $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $supplier_count = 0;
        
        echo "<p class='info'>تم العثور على " . count($suppliers) . " مورد</p>";
        
        foreach ($suppliers as $supplier) {
            $updates = [];
            $params = [];
            
            if (!empty($supplier['phone'])) {
                $formatted = formatYemenPhone($supplier['phone']);
                $updates[] = "phone = ?";
                $params[] = $formatted;
            }
            
            if (!empty($supplier['mobile'])) {
                $formatted = formatYemenPhone($supplier['mobile']);
                $updates[] = "mobile = ?";
                $params[] = $formatted;
            }
            
            if (!empty($updates)) {
                $params[] = $supplier['id'];
                $sql = "UPDATE suppliers SET " . implode(', ', $updates) . " WHERE id = ?";
                $update_stmt = $db->prepare($sql);
                $update_stmt->execute($params);
                $supplier_count++;
            }
        }
        
        echo "<p class='success'>✅ تم تحديث $supplier_count مورد</p>";
        $tables_updated['suppliers'] = $supplier_count;
    } catch (PDOException $e) {
        echo "<p class='warning'>⚠ جدول الموردين غير موجود أو لا يحتوي على أرقام هواتف</p>";
    }
    echo "</div>";
    
    // Update users table
    echo "<h2>المرحلة 3: تحديث جدول المستخدمين (Users)</h2>";
    echo "<div class='step'>";
    
    try {
        $stmt = $db->query("SELECT id, phone FROM users WHERE phone IS NOT NULL AND phone != ''");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $user_count = 0;
        
        echo "<p class='info'>تم العثور على " . count($users) . " مستخدم لديه رقم هاتف</p>";
        
        foreach ($users as $user) {
            if (!empty($user['phone'])) {
                $formatted = formatYemenPhone($user['phone']);
                $update_stmt = $db->prepare("UPDATE users SET phone = ? WHERE id = ?");
                $update_stmt->execute([$formatted, $user['id']]);
                $user_count++;
            }
        }
        
        echo "<p class='success'>✅ تم تحديث $user_count مستخدم</p>";
        $tables_updated['users'] = $user_count;
    } catch (PDOException $e) {
        echo "<p class='warning'>⚠ جدول المستخدمين لا يحتوي على حقل phone</p>";
    }
    echo "</div>";
    
    // Update shipping companies
    echo "<h2>المرحلة 4: تحديث جدول شركات الشحن (Shipping Companies)</h2>";
    echo "<div class='step'>";
    
    try {
        $stmt = $db->query("SELECT id, phone, mobile FROM shipping_companies");
        $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $company_count = 0;
        
        echo "<p class='info'>تم العثور على " . count($companies) . " شركة شحن</p>";
        
        foreach ($companies as $company) {
            $updates = [];
            $params = [];
            
            if (!empty($company['phone'])) {
                $formatted = formatYemenPhone($company['phone']);
                $updates[] = "phone = ?";
                $params[] = $formatted;
            }
            
            if (!empty($company['mobile'])) {
                $formatted = formatYemenPhone($company['mobile']);
                $updates[] = "mobile = ?";
                $params[] = $formatted;
            }
            
            if (!empty($updates)) {
                $params[] = $company['id'];
                $sql = "UPDATE shipping_companies SET " . implode(', ', $updates) . " WHERE id = ?";
                $update_stmt = $db->prepare($sql);
                $update_stmt->execute($params);
                $company_count++;
            }
        }
        
        echo "<p class='success'>✅ تم تحديث $company_count شركة شحن</p>";
        $tables_updated['shipping_companies'] = $company_count;
    } catch (PDOException $e) {
        echo "<p class='warning'>⚠ جدول شركات الشحن غير موجود</p>";
    }
    echo "</div>";
    
    $db->commit();
    
    // Summary
    echo "<h2 class='success'>✅ اكتمل التحديث بنجاح!</h2>";
    
    echo "<div class='step'>";
    echo "<h3>ملخص التحديثات:</h3>";
    echo "<table>";
    echo "<tr><th>الجدول</th><th>عدد السجلات المحدثة</th></tr>";
    
    $total = 0;
    foreach ($tables_updated as $table => $count) {
        echo "<tr><td>$table</td><td class='success'>$count</td></tr>";
        $total += $count;
    }
    
    echo "<tr style='background: #27ae60; color: white; font-weight: bold;'>";
    echo "<td>الإجمالي</td><td>$total</td></tr>";
    echo "</table>";
    echo "</div>";
    
    echo "<div class='phone-example'>";
    echo "<h3>✅ تم تطبيق الصيغة اليمنية على جميع الأرقام:</h3>";
    echo "<ul style='font-size: 16px; line-height: 2;'>";
    echo "<li>رمز الدولة: <strong>+967</strong></li>";
    echo "<li>تنسيق الجوال: <strong>+967 XXX XXX XXX</strong></li>";
    echo "<li>تنسيق الأرضي: <strong>+967 X XXX XXX</strong></li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<div style='text-align: center; margin-top: 30px;'>";
    echo "<a href='../index.php' class='btn btn-success'>الذهاب إلى الصفحة الرئيسية</a>";
    echo "<a href='../modules/customers/index.php' class='btn'>عرض العملاء</a>";
    echo "</div>";
    
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<p class='error'>❌ خطأ: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}

?>

</div>
</body>
</html>
