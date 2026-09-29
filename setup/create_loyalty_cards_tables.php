<?php
/**
 * Loyalty Cards System - Database Setup
 * نظام بطاقات الهدية والولاء
 */

require_once '../config/database.php';

?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء جداول نظام بطاقات الهدية</title>
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
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            overflow-x: auto;
            font-size: 12px;
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
        <h1>🎁 إنشاء جداول نظام بطاقات الهدية</h1>

<?php
try {
    echo "<div class='info'><strong>📋 جاري إنشاء الجداول...</strong></div>";

    // ============================================
    // Table 1: loyalty_cards
    // ============================================
    echo "<h3>1️⃣ جدول بطاقات الهدية (loyalty_cards)</h3>";
    
    $sql = "CREATE TABLE IF NOT EXISTS loyalty_cards (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        card_number VARCHAR(50) UNIQUE NOT NULL,
        card_password VARCHAR(255) NOT NULL,
        card_type ENUM('gift', 'loyalty', 'promotional') DEFAULT 'gift',
        initial_balance DECIMAL(10,3) DEFAULT 0.000,
        current_balance DECIMAL(10,3) DEFAULT 0.000,
        bonus_balance DECIMAL(10,3) DEFAULT 0.000 COMMENT 'رصيد المكافأة',
        total_spent DECIMAL(10,3) DEFAULT 0.000,
        customer_id INT(11) NULL COMMENT 'ربط بالعميل',
        customer_name VARCHAR(255) NULL,
        customer_phone VARCHAR(20) NULL,
        customer_email VARCHAR(100) NULL,
        status ENUM('active', 'inactive', 'expired', 'blocked') DEFAULT 'active',
        activation_date DATE NULL,
        expiry_date DATE NULL,
        last_used_date DATETIME NULL,
        notes TEXT,
        created_by INT(11),
        is_active TINYINT(1) DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_card_number (card_number),
        INDEX idx_customer_id (customer_id),
        INDEX idx_status (status),
        INDEX idx_card_type (card_type)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "<div class='success'><strong>✅ نجح:</strong> تم إنشاء جدول loyalty_cards</div>";

    // ============================================
    // Table 2: loyalty_card_transactions
    // ============================================
    echo "<h3>2️⃣ جدول معاملات البطاقات (loyalty_card_transactions)</h3>";
    
    $sql = "CREATE TABLE IF NOT EXISTS loyalty_card_transactions (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        card_id INT(11) NOT NULL,
        transaction_type ENUM('purchase', 'refund', 'transfer_in', 'transfer_out', 'bonus', 'adjustment') NOT NULL,
        amount DECIMAL(10,3) NOT NULL,
        balance_before DECIMAL(10,3) NOT NULL,
        balance_after DECIMAL(10,3) NOT NULL,
        order_id INT(11) NULL COMMENT 'ربط بطلب الشراء',
        reference_number VARCHAR(100) NULL,
        description TEXT,
        transfer_to_card_id INT(11) NULL COMMENT 'في حالة التحويل',
        created_by INT(11),
        transaction_date DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_card_id (card_id),
        INDEX idx_transaction_type (transaction_type),
        INDEX idx_order_id (order_id),
        INDEX idx_transaction_date (transaction_date),
        FOREIGN KEY (card_id) REFERENCES loyalty_cards(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "<div class='success'><strong>✅ نجح:</strong> تم إنشاء جدول loyalty_card_transactions</div>";

    // ============================================
    // Table 3: loyalty_card_goals
    // ============================================
    echo "<h3>3️⃣ جدول أهداف البطاقات (loyalty_card_goals)</h3>";
    
    $sql = "CREATE TABLE IF NOT EXISTS loyalty_card_goals (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        card_id INT(11) NOT NULL,
        goal_name VARCHAR(255) NOT NULL,
        goal_description TEXT,
        target_amount DECIMAL(10,3) NOT NULL,
        current_amount DECIMAL(10,3) DEFAULT 0.000,
        goal_status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
        start_date DATE,
        target_date DATE,
        completed_date DATE NULL,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_card_id (card_id),
        INDEX idx_goal_status (goal_status),
        FOREIGN KEY (card_id) REFERENCES loyalty_cards(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "<div class='success'><strong>✅ نجح:</strong> تم إنشاء جدول loyalty_card_goals</div>";

    // ============================================
    // Table 4: loyalty_card_promotions
    // ============================================
    echo "<h3>4️⃣ جدول عروض البطاقات (loyalty_card_promotions)</h3>";
    
    $sql = "CREATE TABLE IF NOT EXISTS loyalty_card_promotions (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        promo_code VARCHAR(50) UNIQUE NOT NULL,
        promo_name VARCHAR(255) NOT NULL,
        promo_description TEXT,
        promo_type ENUM('percentage', 'fixed_amount', 'bonus_balance') NOT NULL,
        promo_value DECIMAL(10,3) NOT NULL,
        min_purchase_amount DECIMAL(10,3) DEFAULT 0.000,
        max_discount_amount DECIMAL(10,3) NULL,
        usage_limit INT(11) DEFAULT 1 COMMENT 'عدد مرات الاستخدام',
        used_count INT(11) DEFAULT 0,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
        applicable_to ENUM('all', 'specific_cards', 'new_cards') DEFAULT 'all',
        created_by INT(11),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_promo_code (promo_code),
        INDEX idx_status (status),
        INDEX idx_dates (start_date, end_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "<div class='success'><strong>✅ نجح:</strong> تم إنشاء جدول loyalty_card_promotions</div>";

    // ============================================
    // Table 5: loyalty_card_promo_usage
    // ============================================
    echo "<h3>5️⃣ جدول استخدام العروض (loyalty_card_promo_usage)</h3>";
    
    $sql = "CREATE TABLE IF NOT EXISTS loyalty_card_promo_usage (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        card_id INT(11) NOT NULL,
        promo_id INT(11) NOT NULL,
        order_id INT(11) NULL,
        discount_amount DECIMAL(10,3) NOT NULL,
        used_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_card_id (card_id),
        INDEX idx_promo_id (promo_id),
        FOREIGN KEY (card_id) REFERENCES loyalty_cards(id) ON DELETE CASCADE,
        FOREIGN KEY (promo_id) REFERENCES loyalty_card_promotions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "<div class='success'><strong>✅ نجح:</strong> تم إنشاء جدول loyalty_card_promo_usage</div>";

    // ============================================
    // Insert Sample Data
    // ============================================
    echo "<h3>📝 إضافة بيانات تجريبية...</h3>";

    // Sample Cards
    $sampleCards = [
        [
            'card_number' => 'GIFT-2025-001',
            'card_password' => password_hash('1234', PASSWORD_DEFAULT),
            'card_type' => 'gift',
            'initial_balance' => 1000.000,
            'current_balance' => 1000.000,
            'customer_name' => 'أحمد محمد',
            'customer_phone' => '0501234567',
            'status' => 'active'
        ],
        [
            'card_number' => 'LOYALTY-2025-001',
            'card_password' => password_hash('5678', PASSWORD_DEFAULT),
            'card_type' => 'loyalty',
            'initial_balance' => 500.000,
            'current_balance' => 500.000,
            'bonus_balance' => 50.000,
            'customer_name' => 'فاطمة علي',
            'customer_phone' => '0559876543',
            'status' => 'active'
        ],
        [
            'card_number' => 'PROMO-2025-SUMMER',
            'card_password' => password_hash('9999', PASSWORD_DEFAULT),
            'card_type' => 'promotional',
            'initial_balance' => 900.000,
            'current_balance' => 900.000,
            'bonus_balance' => 100.000,
            'customer_name' => 'خالد سعيد',
            'customer_phone' => '0551112233',
            'status' => 'active',
            'expiry_date' => '2025-12-31'
        ]
    ];

    $stmt = $db->prepare("
        INSERT INTO loyalty_cards 
        (card_number, card_password, card_type, initial_balance, current_balance, bonus_balance, 
         customer_name, customer_phone, status, expiry_date, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");

    foreach ($sampleCards as $card) {
        $stmt->execute([
            $card['card_number'],
            $card['card_password'],
            $card['card_type'],
            $card['initial_balance'],
            $card['current_balance'],
            $card['bonus_balance'] ?? 0,
            $card['customer_name'],
            $card['customer_phone'],
            $card['status'],
            $card['expiry_date'] ?? null
        ]);
        echo "<div class='success'><strong>✅</strong> تمت إضافة بطاقة: {$card['card_number']}</div>";
    }

    // Sample Promotions
    $samplePromos = [
        [
            'promo_code' => 'SUMMER2025',
            'promo_name' => 'عرض الصيف 2025',
            'promo_description' => 'احصل على 10% خصم على جميع المشتريات',
            'promo_type' => 'percentage',
            'promo_value' => 10.000,
            'min_purchase_amount' => 100.000,
            'start_date' => '2025-06-01',
            'end_date' => '2025-08-31',
            'status' => 'active'
        ],
        [
            'promo_code' => 'BONUS100',
            'promo_name' => 'مكافأة 100 ريال',
            'promo_description' => 'احصل على 100 ريال رصيد إضافي عند الشراء بـ 900 ريال',
            'promo_type' => 'bonus_balance',
            'promo_value' => 100.000,
            'min_purchase_amount' => 900.000,
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'status' => 'active'
        ]
    ];

    $stmt = $db->prepare("
        INSERT INTO loyalty_card_promotions 
        (promo_code, promo_name, promo_description, promo_type, promo_value, 
         min_purchase_amount, start_date, end_date, status, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
    ");

    foreach ($samplePromos as $promo) {
        $stmt->execute([
            $promo['promo_code'],
            $promo['promo_name'],
            $promo['promo_description'],
            $promo['promo_type'],
            $promo['promo_value'],
            $promo['min_purchase_amount'],
            $promo['start_date'],
            $promo['end_date'],
            $promo['status']
        ]);
        echo "<div class='success'><strong>✅</strong> تمت إضافة عرض: {$promo['promo_code']}</div>";
    }

    echo "<div class='success'>";
    echo "<h3>🎉 اكتملت عملية الإنشاء بنجاح!</h3>";
    echo "<p>تم إنشاء 5 جداول لنظام بطاقات الهدية</p>";
    echo "<p>تمت إضافة 3 بطاقات تجريبية و 2 عروض ترويجية</p>";
    echo "</div>";

    // Display table structures
    echo "<div class='info'>";
    echo "<h3>📋 ملخص الجداول المنشأة:</h3>";
    
    $tables = [
        'loyalty_cards' => 'بطاقات الهدية',
        'loyalty_card_transactions' => 'معاملات البطاقات',
        'loyalty_card_goals' => 'أهداف البطاقات',
        'loyalty_card_promotions' => 'العروض الترويجية',
        'loyalty_card_promo_usage' => 'استخدام العروض'
    ];
    
    echo "<table>";
    echo "<tr><th>اسم الجدول</th><th>الوصف</th><th>عدد الأعمدة</th></tr>";
    
    foreach ($tables as $table => $desc) {
        $columns = $db->query("DESCRIBE $table")->fetchAll();
        echo "<tr>";
        echo "<td><strong>$table</strong></td>";
        echo "<td>$desc</td>";
        echo "<td>" . count($columns) . "</td>";
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
            <a href="../modules/loyalty-cards/index.php" class="btn">
                🎁 الانتقال إلى بطاقات الهدية
            </a>
            <a href="../index.php" class="btn" style="background: #28a745;">
                🏠 العودة للصفحة الرئيسية
            </a>
        </div>
    </div>
</body>
</html>
