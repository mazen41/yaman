<?php
/**
 * Quick Fix: Add missing is_active column to purchase_groups table
 */

require_once '../config/database.php';

?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إصلاح جدول مجموعات الشراء</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            margin: 0;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        h1 {
            color: #667eea;
            text-align: center;
            margin-bottom: 30px;
        }
        .success {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
        }
        .error {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
        }
        .info {
            background: #d1ecf1;
            border: 2px solid #17a2b8;
            color: #0c5460;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            margin-top: 20px;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔧 إصلاح جدول مجموعات الشراء</h1>

<?php
try {
    // Check if table exists
    $tableExists = false;
    try {
        $db->query("SELECT 1 FROM purchase_groups LIMIT 1");
        $tableExists = true;
    } catch (PDOException $e) {
        echo "<div class='error'><strong>❌ خطأ:</strong> جدول purchase_groups غير موجود. يرجى تشغيل create_purchase_groups_table.php أولاً</div>";
        echo "<a href='create_purchase_groups_table.php' class='btn'>إنشاء الجدول</a>";
        exit;
    }

    if ($tableExists) {
        echo "<div class='info'><strong>ℹ️ معلومة:</strong> جدول purchase_groups موجود، جاري التحقق من الأعمدة...</div>";
        
        // Get existing columns
        $columns = $db->query("DESCRIBE purchase_groups")->fetchAll(PDO::FETCH_COLUMN);
        
        // Check and add is_active if missing
        if (!in_array('is_active', $columns)) {
            echo "<div class='info'><strong>🔄 جاري الإصلاح:</strong> إضافة عمود is_active...</div>";
            $db->exec("ALTER TABLE purchase_groups ADD COLUMN is_active TINYINT(1) DEFAULT 1 AFTER notes");
            echo "<div class='success'><strong>✅ تم بنجاح:</strong> تمت إضافة عمود is_active</div>";
        } else {
            echo "<div class='success'><strong>✅ جيد:</strong> عمود is_active موجود بالفعل</div>";
        }
        
        // Update existing records to have is_active = 1
        $db->exec("UPDATE purchase_groups SET is_active = 1 WHERE is_active IS NULL");
        echo "<div class='success'><strong>✅ تم:</strong> تحديث السجلات الموجودة</div>";
        
        echo "<div class='success'>";
        echo "<h3>✅ اكتمل الإصلاح بنجاح!</h3>";
        echo "<p>جدول purchase_groups جاهز للاستخدام الآن</p>";
        echo "</div>";
        
        // Display current structure
        echo "<div class='info'>";
        echo "<h3>📋 هيكل الجدول الحالي:</h3>";
        $columns_info = $db->query("DESCRIBE purchase_groups")->fetchAll(PDO::FETCH_ASSOC);
        echo "<table style='width:100%; border-collapse: collapse;'>";
        echo "<tr style='background:#f8f9fa;'>";
        echo "<th style='border:1px solid #ddd; padding:8px; text-align:right;'>اسم العمود</th>";
        echo "<th style='border:1px solid #ddd; padding:8px; text-align:right;'>النوع</th>";
        echo "<th style='border:1px solid #ddd; padding:8px; text-align:right;'>Null</th>";
        echo "<th style='border:1px solid #ddd; padding:8px; text-align:right;'>Default</th>";
        echo "</tr>";
        foreach ($columns_info as $col) {
            echo "<tr>";
            echo "<td style='border:1px solid #ddd; padding:8px;'><strong>{$col['Field']}</strong></td>";
            echo "<td style='border:1px solid #ddd; padding:8px;'>{$col['Type']}</td>";
            echo "<td style='border:1px solid #ddd; padding:8px;'>{$col['Null']}</td>";
            echo "<td style='border:1px solid #ddd; padding:8px;'>{$col['Default']}</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";
    }

} catch (PDOException $e) {
    echo "<div class='error'>";
    echo "<strong>❌ خطأ:</strong> " . $e->getMessage();
    echo "</div>";
}
?>

        <div style="text-align: center; margin-top: 30px;">
            <a href="../modules/purchases/groups/index.php" class="btn">
                🛒 الانتقال إلى مجموعات الشراء
            </a>
            <a href="../index.php" class="btn" style="background: #28a745;">
                🏠 العودة للصفحة الرئيسية
            </a>
        </div>
    </div>
</body>
</html>
