<?php
/**
 * Complete Rebuild: Drop and recreate purchase_groups table
 */

require_once '../config/database.php';

?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة بناء جدول مجموعات الشراء</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            margin: 0;
        }
        .container {
            max-width: 900px;
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
            font-size: 32px;
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
        .warning {
            background: #fff3cd;
            border: 2px solid #ffc107;
            color: #856404;
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
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
        }
        .btn {
            display: inline-block;
            padding: 12px 30px;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            margin: 10px 5px;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #764ba2;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔄 إعادة بناء جدول مجموعات الشراء</h1>

<?php
try {
    // Check if table exists
    $tableExists = false;
    try {
        $db->query("SELECT 1 FROM purchase_groups LIMIT 1");
        $tableExists = true;
    } catch (PDOException $e) {
        // Table doesn't exist
    }

    if ($tableExists) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ تحذير:</strong> جدول purchase_groups موجود بالفعل<br>";
        echo "سيتم حذف الجدول القديم وإعادة إنشائه من جديد<br>";
        echo "<strong>ملاحظة:</strong> سيتم فقدان جميع البيانات الموجودة!";
        echo "</div>";
        
        // Drop the table
        echo "<div class='info'><strong>🗑️ جاري حذف الجدول القديم...</strong></div>";
        $db->exec("DROP TABLE IF EXISTS purchase_groups");
        echo "<div class='success'><strong>✅ تم:</strong> حذف الجدول القديم</div>";
    }

    // Create new table with all columns
    echo "<div class='info'><strong>🔨 جاري إنشاء الجدول الجديد...</strong></div>";
    
    $sql = "CREATE TABLE purchase_groups (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        group_name VARCHAR(255) NOT NULL,
        group_number VARCHAR(100) UNIQUE,
        description TEXT,
        start_date DATE,
        end_date DATE,
        status ENUM('active', 'inactive', 'completed') DEFAULT 'active',
        total_orders INT(11) DEFAULT 0,
        total_amount DECIMAL(10,3) DEFAULT 0.000,
        notes TEXT,
        is_active TINYINT(1) DEFAULT 1,
        created_by INT(11),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_group_number (group_number),
        INDEX idx_status (status),
        INDEX idx_is_active (is_active),
        INDEX idx_created_by (created_by)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    
    echo "<div class='success'>";
    echo "<strong>✅ نجح:</strong> تم إنشاء جدول purchase_groups بنجاح";
    echo "</div>";
    echo "<pre>$sql</pre>";

    // Insert sample data
    echo "<div class='info'><strong>📝 جاري إضافة بيانات تجريبية...</strong></div>";
    
    $sampleData = [
        [
            'group_name' => 'مجموعة الشراء - يناير 2025',
            'group_number' => 'PG-2025-01',
            'description' => 'مجموعة شراء شهر يناير للمواد الغذائية والمستلزمات',
            'start_date' => '2025-01-01',
            'end_date' => '2025-01-31',
            'status' => 'active'
        ],
        [
            'group_name' => 'مجموعة الشراء - فبراير 2025',
            'group_number' => 'PG-2025-02',
            'description' => 'مجموعة شراء شهر فبراير للمواد الغذائية',
            'start_date' => '2025-02-01',
            'end_date' => '2025-02-28',
            'status' => 'active'
        ],
        [
            'group_name' => 'مجموعة شراء خاصة - عروض الصيف',
            'group_number' => 'PG-2025-SUMMER',
            'description' => 'مجموعة شراء خاصة للاستفادة من عروض الصيف',
            'start_date' => '2025-06-01',
            'end_date' => '2025-08-31',
            'status' => 'active'
        ]
    ];

    $stmt = $db->prepare("
        INSERT INTO purchase_groups 
        (group_name, group_number, description, start_date, end_date, status, created_by, is_active) 
        VALUES (?, ?, ?, ?, ?, ?, 1, 1)
    ");

    foreach ($sampleData as $data) {
        $stmt->execute([
            $data['group_name'],
            $data['group_number'],
            $data['description'],
            $data['start_date'],
            $data['end_date'],
            $data['status']
        ]);
        echo "<div class='success'><strong>✅</strong> تمت إضافة: {$data['group_name']}</div>";
    }

    echo "<div class='success'>";
    echo "<h3>🎉 اكتملت عملية إعادة البناء بنجاح!</h3>";
    echo "<p>تم إنشاء جدول مجموعات الشراء بجميع الأعمدة المطلوبة</p>";
    echo "<p>تمت إضافة 3 مجموعات تجريبية</p>";
    echo "</div>";

    // Display table structure
    echo "<div class='info'>";
    echo "<h3>📋 هيكل الجدول النهائي:</h3>";
    $columns = $db->query("DESCRIBE purchase_groups")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table style='width:100%; border-collapse: collapse;'>";
    echo "<tr style='background:#f8f9fa;'>";
    echo "<th style='border:1px solid #ddd; padding:8px; text-align:right;'>اسم العمود</th>";
    echo "<th style='border:1px solid #ddd; padding:8px; text-align:right;'>النوع</th>";
    echo "<th style='border:1px solid #ddd; padding:8px; text-align:right;'>Null</th>";
    echo "<th style='border:1px solid #ddd; padding:8px; text-align:right;'>Key</th>";
    echo "<th style='border:1px solid #ddd; padding:8px; text-align:right;'>Default</th>";
    echo "</tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td style='border:1px solid #ddd; padding:8px;'><strong>{$col['Field']}</strong></td>";
        echo "<td style='border:1px solid #ddd; padding:8px;'>{$col['Type']}</td>";
        echo "<td style='border:1px solid #ddd; padding:8px;'>{$col['Null']}</td>";
        echo "<td style='border:1px solid #ddd; padding:8px;'>{$col['Key']}</td>";
        echo "<td style='border:1px solid #ddd; padding:8px;'>{$col['Default']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

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
