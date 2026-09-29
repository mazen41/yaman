<?php
/**
 * Add Order Images Table
 * Senior Engineer Solution - Complete Image Upload System
 */

require_once '../config/database.php';

?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إضافة جدول صور الطلبات</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; }
        h1 { color: #2c3e50; }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .info { color: #3498db; }
        .btn { display: inline-block; padding: 12px 24px; background: #3498db; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>
<div class="container">
    <h1>🖼️ إضافة نظام صور الطلبات</h1>

<?php

try {
    echo "<h2>المرحلة 1: إنشاء جدول order_images</h2>";
    
    // Check if table exists
    $check = $db->query("SHOW TABLES LIKE 'order_images'");
    
    if ($check->rowCount() > 0) {
        echo "<p class='info'>✓ الجدول موجود بالفعل</p>";
    } else {
        echo "<p>إنشاء جدول order_images...</p>";
        
        $db->exec("
            CREATE TABLE order_images (
                id INT PRIMARY KEY AUTO_INCREMENT,
                order_id INT NOT NULL,
                image_path VARCHAR(255) NOT NULL,
                image_name VARCHAR(255) NOT NULL,
                image_type VARCHAR(50),
                image_size INT,
                display_order INT DEFAULT 0,
                uploaded_by INT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (order_id) REFERENCES customer_orders(id) ON DELETE CASCADE,
                FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
                INDEX idx_order_id (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "<p class='success'>✅ تم إنشاء جدول order_images</p>";
    }
    
    echo "<h2>المرحلة 2: إنشاء مجلد uploads</h2>";
    
    // Create uploads directory structure
    $upload_dirs = [
        '../uploads',
        '../uploads/orders',
        '../uploads/orders/images'
    ];
    
    foreach ($upload_dirs as $dir) {
        if (!file_exists($dir)) {
            if (mkdir($dir, 0755, true)) {
                echo "<p class='success'>✅ تم إنشاء المجلد: $dir</p>";
            } else {
                echo "<p class='error'>❌ فشل إنشاء المجلد: $dir</p>";
            }
        } else {
            echo "<p class='info'>✓ المجلد موجود: $dir</p>";
        }
    }
    
    // Create .htaccess for security
    $htaccess_content = "# Prevent PHP execution in uploads folder
<FilesMatch \"\\.php$\">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Allow image files
<FilesMatch \"\\.(jpg|jpeg|png|gif|webp)$\">
    Order Allow,Deny
    Allow from all
</FilesMatch>";
    
    file_put_contents('../uploads/orders/.htaccess', $htaccess_content);
    echo "<p class='success'>✅ تم إنشاء ملف الحماية .htaccess</p>";
    
    echo "<h2>المرحلة 3: التحقق من بنية الجدول</h2>";
    
    $columns = $db->query("SHOW COLUMNS FROM order_images")->fetchAll(PDO::FETCH_ASSOC);
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
    
    echo "<h2 class='success'>✅ اكتمل الإعداد بنجاح!</h2>";
    echo "<p>الآن يمكن رفع صور متعددة لكل طلب</p>";
    
    echo "<h3>الميزات المضافة:</h3>";
    echo "<ul>";
    echo "<li>✅ رفع صور متعددة لكل طلب</li>";
    echo "<li>✅ حفظ معلومات الصورة (الاسم، النوع، الحجم)</li>";
    echo "<li>✅ ترتيب الصور</li>";
    echo "<li>✅ حماية أمنية للمجلد</li>";
    echo "<li>✅ ربط الصور بالطلب والمستخدم</li>";
    echo "</ul>";
    
    echo "<a href='../modules/orders/create.php' class='btn'>إنشاء طلب جديد</a>";
    echo "<a href='../modules/orders/index.php' class='btn'>قائمة الطلبات</a>";
    
} catch (PDOException $e) {
    echo "<p class='error'>❌ خطأ: " . $e->getMessage() . "</p>";
}

?>

</div>
</body>
</html>
