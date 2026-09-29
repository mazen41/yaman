<?php
/**
 * Create WhatsApp Templates Tables
 * Senior Engineer Solution
 */

session_start();

if (!isset($_SESSION['user_id'])) {
    die('Unauthorized access. Please login first.');
}

require_once '../config/database.php';

?>
<!DOCTYPE html>
<html dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إعداد جداول الواتساب</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; padding: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2c3e50; border-bottom: 3px solid #25d366; padding-bottom: 10px; }
        .success { color: #27ae60; font-weight: bold; }
        .error { color: #e74c3c; font-weight: bold; }
        .step { background: #f8f9fa; padding: 15px; margin: 15px 0; border-radius: 5px; border-left: 4px solid #25d366; }
    </style>
</head>
<body>
<div class="container">
    <h1><i class="fab fa-whatsapp"></i> إعداد نظام قوالب الواتساب</h1>

<?php

try {
    $db->beginTransaction();
    
    echo "<div class='step'>";
    echo "<h2>إنشاء جدول قوالب الواتساب</h2>";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS whatsapp_templates (
            id INT PRIMARY KEY AUTO_INCREMENT,
            template_name VARCHAR(255) NOT NULL,
            template_content TEXT NOT NULL,
            category ENUM('order', 'payment', 'shipping', 'general') DEFAULT 'general',
            variables TEXT,
            is_active TINYINT(1) DEFAULT 1,
            created_by INT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            INDEX idx_category (category),
            INDEX idx_is_active (is_active)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    echo "<p class='success'>✅ تم إنشاء جدول whatsapp_templates</p>";
    echo "</div>";
    
    // Insert default templates
    echo "<div class='step'>";
    echo "<h2>إضافة القوالب الافتراضية</h2>";
    
    $defaultTemplates = [
        [
            'name' => 'تأكيد استلام الطلب',
            'content' => "مرحباً {customer_name} 👋\n\nتم استلام طلبك رقم {order_number} بنجاح ✅\n\nالمبلغ الإجمالي: {order_total}\n\nسيتم التواصل معك قريباً لتأكيد التفاصيل.\n\nشكراً لثقتك بنا 🌟",
            'category' => 'order',
            'variables' => '{customer_name}, {order_number}, {order_total}'
        ],
        [
            'name' => 'تحديث حالة الطلب',
            'content' => "عزيزي {customer_name} 👋\n\nطلبك رقم {order_number} الآن قيد التجهيز 📦\n\nسيتم الشحن خلال 24-48 ساعة.\n\nشكراً لانتظارك 🙏",
            'category' => 'order',
            'variables' => '{customer_name}, {order_number}'
        ],
        [
            'name' => 'تأكيد الشحن',
            'content' => "مرحباً {customer_name} 🚚\n\nتم شحن طلبك رقم {order_number}\n\nرقم التتبع: {tracking_number}\n\nسيصلك الطلب خلال 2-3 أيام عمل.\n\nنتمنى لك تجربة رائعة! 🌟",
            'category' => 'shipping',
            'variables' => '{customer_name}, {order_number}, {tracking_number}'
        ],
        [
            'name' => 'تأكيد استلام الدفعة',
            'content' => "عزيزي {customer_name} 💰\n\nتم استلام دفعتك بنجاح ✅\n\nالمبلغ المدفوع: {payment_amount}\n\nالمبلغ المتبقي: {remaining_amount}\n\nشكراً لك 🙏",
            'category' => 'payment',
            'variables' => '{customer_name}, {payment_amount}, {remaining_amount}'
        ],
        [
            'name' => 'تذكير بالدفع',
            'content' => "مرحباً {customer_name} 👋\n\nنذكرك بوجود مبلغ متبقي على طلبك رقم {order_number}\n\nالمبلغ المتبقي: {remaining_amount}\n\nيرجى التواصل معنا لترتيب الدفع.\n\nشكراً لتعاونك 🙏",
            'category' => 'payment',
            'variables' => '{customer_name}, {order_number}, {remaining_amount}'
        ],
        [
            'name' => 'رسالة ترحيبية',
            'content' => "أهلاً وسهلاً {customer_name} 🌟\n\nنشكرك على التواصل مع {company_name}\n\nنحن هنا لخدمتك على مدار الساعة.\n\nكيف يمكننا مساعدتك اليوم؟ 😊",
            'category' => 'general',
            'variables' => '{customer_name}, {company_name}'
        ],
        [
            'name' => 'شكر بعد التسليم',
            'content' => "عزيزي {customer_name} 🎉\n\nنأمل أن تكون قد استلمت طلبك بحالة ممتازة!\n\nرأيك يهمنا، يرجى تقييم تجربتك معنا.\n\nنتطلع لخدمتك مجدداً 🌟",
            'category' => 'general',
            'variables' => '{customer_name}'
        ]
    ];
    
    foreach ($defaultTemplates as $template) {
        $stmt = $db->prepare("
            INSERT INTO whatsapp_templates (template_name, template_content, category, variables, created_by)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $template['name'],
            $template['content'],
            $template['category'],
            $template['variables'],
            $_SESSION['user_id']
        ]);
        echo "<p class='success'>✅ تم إضافة قالب: {$template['name']}</p>";
    }
    
    echo "</div>";
    
    $db->commit();
    
    echo "<div class='step'>";
    echo "<h2 class='success'>✅ اكتمل الإعداد بنجاح!</h2>";
    echo "<p>تم إنشاء جدول قوالب الواتساب وإضافة 7 قوالب افتراضية.</p>";
    echo "<div style='margin-top: 20px;'>";
    echo "<a href='../modules/whatsapp/templates.php' style='display: inline-block; padding: 12px 24px; background: #25d366; color: white; text-decoration: none; border-radius: 8px; font-weight: bold;'>";
    echo "<i class='fab fa-whatsapp'></i> الذهاب إلى القوالب";
    echo "</a>";
    echo "<a href='../modules/whatsapp/send.php' style='display: inline-block; padding: 12px 24px; background: #128c7e; color: white; text-decoration: none; border-radius: 8px; font-weight: bold; margin-right: 10px;'>";
    echo "<i class='fas fa-paper-plane'></i> إرسال رسالة";
    echo "</a>";
    echo "</div>";
    echo "</div>";
    
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "<p class='error'>❌ خطأ: " . $e->getMessage() . "</p>";
}

?>

</div>
</body>
</html>
