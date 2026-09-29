<?php
/**
 * Expenses Management System - Database Setup
 * نظام إدارة المصروفات
 */

require_once '../config/database.php';

?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء جداول نظام المصروفات</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px;
            margin: 0;
        }
        .container {
            max-width: 1000px;
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
        .info {
            background: #d1ecf1;
            border: 2px solid #17a2b8;
            color: #0c5460;
            padding: 15px;
            border-radius: 10px;
            margin: 15px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: right;
        }
        th {
            background: #f8f9fa;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>💰 إنشاء جداول نظام المصروفات</h1>

<?php
try {
    echo "<div class='info'><strong>📋 جاري إنشاء الجداول...</strong></div>";

    // ============================================
    // Table 1: expense_categories
    // ============================================
    echo "<h3>1️⃣ جدول فئات المصروفات (expense_categories)</h3>";
    
    $sql = "CREATE TABLE IF NOT EXISTS expense_categories (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        category_name VARCHAR(255) NOT NULL,
        category_code VARCHAR(50) UNIQUE,
        description TEXT,
        parent_id INT(11) NULL COMMENT 'للفئات الفرعية',
        icon VARCHAR(50) DEFAULT 'fa-folder',
        color VARCHAR(20) DEFAULT '#6c757d',
        is_active TINYINT(1) DEFAULT 1,
        created_by INT(11),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category_code (category_code),
        INDEX idx_parent_id (parent_id),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "<div class='success'><strong>✅ نجح:</strong> تم إنشاء جدول expense_categories</div>";

    // ============================================
    // Table 2: expense_items
    // ============================================
    echo "<h3>2️⃣ جدول بنود المصروفات (expense_items)</h3>";
    
    $sql = "CREATE TABLE IF NOT EXISTS expense_items (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        item_name VARCHAR(255) NOT NULL,
        item_code VARCHAR(50) UNIQUE,
        category_id INT(11),
        description TEXT,
        unit VARCHAR(50) COMMENT 'وحدة القياس',
        default_price DECIMAL(10,3) DEFAULT 0.000,
        is_active TINYINT(1) DEFAULT 1,
        created_by INT(11),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_item_code (item_code),
        INDEX idx_category_id (category_id),
        INDEX idx_is_active (is_active),
        FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "<div class='success'><strong>✅ نجح:</strong> تم إنشاء جدول expense_items</div>";

    // ============================================
    // Table 3: expenses
    // ============================================
    echo "<h3>3️⃣ جدول المصروفات (expenses)</h3>";
    
    $sql = "CREATE TABLE IF NOT EXISTS expenses (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        expense_number VARCHAR(50) UNIQUE NOT NULL,
        expense_date DATE NOT NULL,
        category_id INT(11),
        item_id INT(11),
        description TEXT NOT NULL,
        amount DECIMAL(10,3) NOT NULL,
        quantity DECIMAL(10,3) DEFAULT 1.000,
        unit_price DECIMAL(10,3),
        payment_method ENUM('cash', 'bank_transfer', 'check', 'credit_card', 'other') DEFAULT 'cash',
        payment_status ENUM('pending', 'paid', 'partial') DEFAULT 'paid',
        paid_amount DECIMAL(10,3) DEFAULT 0.000,
        remaining_amount DECIMAL(10,3) DEFAULT 0.000,
        vendor_name VARCHAR(255) COMMENT 'اسم المورد/البائع',
        vendor_phone VARCHAR(20),
        invoice_number VARCHAR(100) COMMENT 'رقم الفاتورة',
        invoice_image VARCHAR(255) COMMENT 'صورة الفاتورة',
        receipt_number VARCHAR(100) COMMENT 'رقم الإيصال',
        check_number VARCHAR(100) COMMENT 'رقم الشيك',
        bank_name VARCHAR(100),
        reference_number VARCHAR(100) COMMENT 'رقم مرجعي',
        notes TEXT,
        approved_by INT(11) COMMENT 'من وافق على الصرف',
        approved_at DATETIME,
        status ENUM('pending', 'approved', 'rejected', 'paid') DEFAULT 'pending',
        created_by INT(11),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_expense_number (expense_number),
        INDEX idx_expense_date (expense_date),
        INDEX idx_category_id (category_id),
        INDEX idx_item_id (item_id),
        INDEX idx_status (status),
        INDEX idx_payment_status (payment_status),
        FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL,
        FOREIGN KEY (item_id) REFERENCES expense_items(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "<div class='success'><strong>✅ نجح:</strong> تم إنشاء جدول expenses</div>";

    // ============================================
    // Table 4: expense_attachments
    // ============================================
    echo "<h3>4️⃣ جدول مرفقات المصروفات (expense_attachments)</h3>";
    
    $sql = "CREATE TABLE IF NOT EXISTS expense_attachments (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        expense_id INT(11) NOT NULL,
        file_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_type VARCHAR(50),
        file_size INT(11),
        description TEXT,
        uploaded_by INT(11),
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_expense_id (expense_id),
        FOREIGN KEY (expense_id) REFERENCES expenses(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "<div class='success'><strong>✅ نجح:</strong> تم إنشاء جدول expense_attachments</div>";

    // ============================================
    // Table 5: expense_recurring
    // ============================================
    echo "<h3>5️⃣ جدول المصروفات المتكررة (expense_recurring)</h3>";
    
    $sql = "CREATE TABLE IF NOT EXISTS expense_recurring (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        recurring_name VARCHAR(255) NOT NULL,
        category_id INT(11),
        item_id INT(11),
        description TEXT,
        amount DECIMAL(10,3) NOT NULL,
        frequency ENUM('daily', 'weekly', 'monthly', 'quarterly', 'yearly') NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE,
        next_date DATE NOT NULL,
        last_generated_date DATE,
        payment_method VARCHAR(50),
        vendor_name VARCHAR(255),
        is_active TINYINT(1) DEFAULT 1,
        auto_generate TINYINT(1) DEFAULT 1 COMMENT 'توليد تلقائي',
        created_by INT(11),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_next_date (next_date),
        INDEX idx_is_active (is_active),
        FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE SET NULL,
        FOREIGN KEY (item_id) REFERENCES expense_items(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "<div class='success'><strong>✅ نجح:</strong> تم إنشاء جدول expense_recurring</div>";

    // ============================================
    // Table 6: expense_budgets
    // ============================================
    echo "<h3>6️⃣ جدول ميزانيات المصروفات (expense_budgets)</h3>";
    
    $sql = "CREATE TABLE IF NOT EXISTS expense_budgets (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        budget_name VARCHAR(255) NOT NULL,
        category_id INT(11),
        budget_amount DECIMAL(10,3) NOT NULL,
        spent_amount DECIMAL(10,3) DEFAULT 0.000,
        remaining_amount DECIMAL(10,3),
        period_type ENUM('monthly', 'quarterly', 'yearly') NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        alert_percentage INT(11) DEFAULT 80 COMMENT 'نسبة التنبيه',
        is_active TINYINT(1) DEFAULT 1,
        created_by INT(11),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_category_id (category_id),
        INDEX idx_period (start_date, end_date),
        FOREIGN KEY (category_id) REFERENCES expense_categories(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "<div class='success'><strong>✅ نجح:</strong> تم إنشاء جدول expense_budgets</div>";

    // ============================================
    // Insert Sample Data
    // ============================================
    echo "<h3>📝 إضافة بيانات تجريبية...</h3>";

    // Sample Categories
    $categories = [
        ['رواتب وأجور', 'SALARY', 'الرواتب والأجور الشهرية', 'fa-users', '#28a745'],
        ['إيجارات', 'RENT', 'إيجار المكاتب والمحلات', 'fa-building', '#17a2b8'],
        ['مرافق', 'UTILITIES', 'كهرباء، ماء، إنترنت', 'fa-bolt', '#ffc107'],
        ['صيانة', 'MAINTENANCE', 'صيانة المعدات والمباني', 'fa-tools', '#dc3545'],
        ['تسويق', 'MARKETING', 'الحملات الإعلانية والتسويق', 'fa-bullhorn', '#6f42c1'],
        ['مواصلات', 'TRANSPORT', 'وقود ومواصلات', 'fa-car', '#fd7e14'],
        ['قرطاسية', 'STATIONERY', 'أدوات مكتبية وقرطاسية', 'fa-paperclip', '#20c997'],
        ['أخرى', 'OTHER', 'مصروفات متنوعة', 'fa-ellipsis-h', '#6c757d']
    ];

    $stmt = $db->prepare("
        INSERT INTO expense_categories 
        (category_name, category_code, description, icon, color, created_by) 
        VALUES (?, ?, ?, ?, ?, 1)
    ");

    foreach ($categories as $cat) {
        try {
            $stmt->execute($cat);
            echo "<div class='success'><strong>✅</strong> تمت إضافة فئة: {$cat[0]}</div>";
        } catch (PDOException $e) {
            // Already exists
        }
    }

    // Sample Items
    $items = [
        ['راتب موظف', 'SAL-001', 1, 'راتب شهري للموظف', 'شهر', 5000.000],
        ['إيجار مكتب', 'RENT-001', 2, 'إيجار شهري للمكتب', 'شهر', 3000.000],
        ['فاتورة كهرباء', 'UTIL-001', 3, 'فاتورة كهرباء شهرية', 'شهر', 500.000],
        ['صيانة طابعة', 'MAINT-001', 4, 'صيانة الطابعات', 'مرة', 200.000],
        ['إعلان فيسبوك', 'MARK-001', 5, 'حملة إعلانية', 'حملة', 1000.000]
    ];

    $stmt = $db->prepare("
        INSERT INTO expense_items 
        (item_name, item_code, category_id, description, unit, default_price, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, 1)
    ");

    foreach ($items as $item) {
        try {
            $stmt->execute($item);
            echo "<div class='success'><strong>✅</strong> تمت إضافة بند: {$item[0]}</div>";
        } catch (PDOException $e) {
            // Already exists
        }
    }

    echo "<div class='success'>";
    echo "<h3>🎉 اكتملت عملية الإنشاء بنجاح!</h3>";
    echo "<p>تم إنشاء 6 جداول لنظام المصروفات</p>";
    echo "<p>تمت إضافة 8 فئات و 5 بنود تجريبية</p>";
    echo "</div>";

    // Display table structures
    echo "<div class='info'>";
    echo "<h3>📋 ملخص الجداول المنشأة:</h3>";
    
    $tables = [
        'expense_categories' => 'فئات المصروفات',
        'expense_items' => 'بنود المصروفات',
        'expenses' => 'المصروفات',
        'expense_attachments' => 'المرفقات',
        'expense_recurring' => 'المصروفات المتكررة',
        'expense_budgets' => 'الميزانيات'
    ];
    
    echo "<table>";
    echo "<tr><th>اسم الجدول</th><th>الوصف</th><th>عدد الأعمدة</th></tr>";
    
    foreach ($tables as $table => $desc) {
        try {
            $columns = $db->query("DESCRIBE $table")->fetchAll();
            echo "<tr>";
            echo "<td><strong>$table</strong></td>";
            echo "<td>$desc</td>";
            echo "<td>" . count($columns) . "</td>";
            echo "</tr>";
        } catch (PDOException $e) {
            // Table doesn't exist
        }
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
            <a href="../modules/expenses/index.php" class="btn">
                💰 الانتقال إلى إدارة المصروفات
            </a>
            <a href="../index.php" class="btn" style="background: #28a745;">
                🏠 العودة للصفحة الرئيسية
            </a>
        </div>
    </div>
</body>
</html>
