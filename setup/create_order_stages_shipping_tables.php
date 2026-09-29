<?php
/**
 * Order Stages & Shipping Management - Database Setup
 * نظام مراحل الطلبات وإدارة الشحن
 */

require_once '../config/database.php';

?>
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إنشاء جداول مراحل الطلبات والشحن</title>
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
        <h1>📦 إنشاء جداول مراحل الطلبات والشحن</h1>

<?php
try {
    echo "<div class='info'><strong>📋 جاري إنشاء الجداول...</strong></div>";

    // ============================================
    // Check if orders table exists and add columns if needed
    // ============================================
    echo "<h3>1️⃣ تحديث جدول الطلبات</h3>";
    
    // Check which orders table exists
    $orders_table = null;
    $orders_exists = false;
    
    try {
        $db->query("SELECT 1 FROM customer_orders LIMIT 1");
        $orders_table = 'customer_orders';
        $orders_exists = true;
        echo "<div class='info'><strong>ℹ️</strong> تم العثور على جدول customer_orders</div>";
    } catch (PDOException $e) {
        try {
            $db->query("SELECT 1 FROM orders LIMIT 1");
            $orders_table = 'orders';
            $orders_exists = true;
            echo "<div class='info'><strong>ℹ️</strong> تم العثور على جدول orders</div>";
        } catch (PDOException $e2) {
            // Table doesn't exist, create it
            $orders_table = 'orders';
            $sql = "CREATE TABLE IF NOT EXISTS orders (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            order_number VARCHAR(50) UNIQUE NOT NULL,
            customer_id INT(11),
            customer_name VARCHAR(255),
            customer_phone VARCHAR(20),
            customer_email VARCHAR(100),
            total_amount DECIMAL(10,3) DEFAULT 0.000,
            status ENUM('pending', 'processing', 'completed', 'cancelled') DEFAULT 'pending',
            payment_status ENUM('unpaid', 'paid', 'partial') DEFAULT 'unpaid',
            notes TEXT,
            created_by INT(11),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_order_number (order_number),
            INDEX idx_customer_id (customer_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $db->exec($sql);
            echo "<div class='success'><strong>✅ تم:</strong> إنشاء جدول orders</div>";
            $orders_exists = true;
        }
    }

    if ($orders_exists && $orders_table) {
        // Add new columns if they don't exist
        $columns = $db->query("DESCRIBE {$orders_table}")->fetchAll(PDO::FETCH_COLUMN);
        
        $new_columns = [
            'current_stage' => "ALTER TABLE {$orders_table} ADD COLUMN current_stage VARCHAR(50) DEFAULT 'pending' AFTER status",
            'sorting_status' => "ALTER TABLE {$orders_table} ADD COLUMN sorting_status ENUM('not_started', 'in_progress', 'completed') DEFAULT 'not_started' AFTER current_stage",
            'shipping_status' => "ALTER TABLE {$orders_table} ADD COLUMN shipping_status ENUM('not_shipped', 'preparing', 'shipped', 'in_transit', 'delivered', 'returned') DEFAULT 'not_shipped' AFTER sorting_status",
            'shipping_method' => "ALTER TABLE {$orders_table} ADD COLUMN shipping_method VARCHAR(100) AFTER shipping_status",
            'tracking_number' => "ALTER TABLE {$orders_table} ADD COLUMN tracking_number VARCHAR(100) AFTER shipping_method",
            'shipping_company' => "ALTER TABLE {$orders_table} ADD COLUMN shipping_company VARCHAR(100) AFTER tracking_number",
            'shipping_cost' => "ALTER TABLE {$orders_table} ADD COLUMN shipping_cost DECIMAL(10,3) DEFAULT 0.000 AFTER shipping_company",
            'estimated_delivery' => "ALTER TABLE {$orders_table} ADD COLUMN estimated_delivery DATE AFTER shipping_cost",
            'actual_delivery' => "ALTER TABLE {$orders_table} ADD COLUMN actual_delivery DATETIME AFTER estimated_delivery",
            'delivery_notes' => "ALTER TABLE {$orders_table} ADD COLUMN delivery_notes TEXT AFTER actual_delivery"
        ];

        foreach ($new_columns as $col => $sql) {
            if (!in_array($col, $columns)) {
                try {
                    $db->exec($sql);
                    echo "<div class='success'><strong>✅ تم:</strong> إضافة عمود $col إلى جدول {$orders_table}</div>";
                } catch (PDOException $e) {
                    echo "<div class='error'><strong>⚠️</strong> تعذر إضافة عمود $col: " . $e->getMessage() . "</div>";
                }
            }
        }
    }

    // ============================================
    // Table 1: order_stages
    // ============================================
    echo "<h3>2️⃣ جدول مراحل الطلبات (order_stages)</h3>";
    
    $sql = "CREATE TABLE IF NOT EXISTS order_stages (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        order_id INT(11) NOT NULL,
        stage_name VARCHAR(100) NOT NULL COMMENT 'اسم المرحلة',
        stage_type ENUM('sorting', 'packing', 'quality_check', 'shipping', 'delivery', 'custom') NOT NULL,
        stage_status ENUM('pending', 'in_progress', 'completed', 'skipped', 'failed') DEFAULT 'pending',
        assigned_to INT(11) COMMENT 'الموظف المسؤول',
        started_at DATETIME,
        completed_at DATETIME,
        duration_minutes INT(11) COMMENT 'مدة المرحلة بالدقائق',
        notes TEXT,
        stage_order INT(11) DEFAULT 0 COMMENT 'ترتيب المرحلة',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_order_id (order_id),
        INDEX idx_stage_type (stage_type),
        INDEX idx_stage_status (stage_status),
        INDEX idx_assigned_to (assigned_to),
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "<div class='success'><strong>✅ نجح:</strong> تم إنشاء جدول order_stages</div>";

    // ============================================
    // Table 2: order_sorting_details
    // ============================================
    echo "<h3>3️⃣ جدول تفاصيل الفرز (order_sorting_details)</h3>";
    
    $sql = "CREATE TABLE IF NOT EXISTS order_sorting_details (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        order_id INT(11) NOT NULL,
        item_name VARCHAR(255) NOT NULL,
        item_sku VARCHAR(100),
        quantity INT(11) NOT NULL,
        sorted_quantity INT(11) DEFAULT 0,
        location VARCHAR(100) COMMENT 'موقع التخزين',
        bin_number VARCHAR(50) COMMENT 'رقم الصندوق',
        sorted_by INT(11) COMMENT 'من قام بالفرز',
        sorted_at DATETIME,
        notes TEXT,
        is_complete TINYINT(1) DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_order_id (order_id),
        INDEX idx_item_sku (item_sku),
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "<div class='success'><strong>✅ نجح:</strong> تم إنشاء جدول order_sorting_details</div>";

    // ============================================
    // Table 3: shipments
    // ============================================
    echo "<h3>4️⃣ جدول الشحنات (shipments)</h3>";
    
    $sql = "CREATE TABLE IF NOT EXISTS shipments (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        shipment_number VARCHAR(50) UNIQUE NOT NULL,
        order_id INT(11) NOT NULL,
        shipping_company VARCHAR(100) NOT NULL,
        tracking_number VARCHAR(100) UNIQUE,
        shipping_method VARCHAR(100) COMMENT 'طريقة الشحن',
        shipping_cost DECIMAL(10,3) DEFAULT 0.000,
        weight DECIMAL(10,3) COMMENT 'الوزن بالكيلوجرام',
        dimensions VARCHAR(100) COMMENT 'الأبعاد',
        package_count INT(11) DEFAULT 1 COMMENT 'عدد الطرود',
        pickup_address TEXT,
        delivery_address TEXT NOT NULL,
        recipient_name VARCHAR(255) NOT NULL,
        recipient_phone VARCHAR(20) NOT NULL,
        status ENUM('preparing', 'ready_for_pickup', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'failed', 'returned') DEFAULT 'preparing',
        shipped_at DATETIME,
        estimated_delivery DATE,
        actual_delivery DATETIME,
        delivery_signature VARCHAR(255) COMMENT 'توقيع المستلم',
        delivery_photo VARCHAR(255) COMMENT 'صورة التسليم',
        notes TEXT,
        created_by INT(11),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_shipment_number (shipment_number),
        INDEX idx_order_id (order_id),
        INDEX idx_tracking_number (tracking_number),
        INDEX idx_status (status),
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "<div class='success'><strong>✅ نجح:</strong> تم إنشاء جدول shipments</div>";

    // ============================================
    // Table 4: shipment_tracking
    // ============================================
    echo "<h3>5️⃣ جدول تتبع الشحنات (shipment_tracking)</h3>";
    
    $sql = "CREATE TABLE IF NOT EXISTS shipment_tracking (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        shipment_id INT(11) NOT NULL,
        status VARCHAR(100) NOT NULL,
        location VARCHAR(255),
        description TEXT,
        occurred_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_shipment_id (shipment_id),
        INDEX idx_occurred_at (occurred_at),
        FOREIGN KEY (shipment_id) REFERENCES shipments(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "<div class='success'><strong>✅ نجح:</strong> تم إنشاء جدول shipment_tracking</div>";

    // ============================================
    // Table 5: shipping_companies
    // ============================================
    echo "<h3>6️⃣ جدول شركات الشحن (shipping_companies)</h3>";
    
    $sql = "CREATE TABLE IF NOT EXISTS shipping_companies (
        id INT(11) AUTO_INCREMENT PRIMARY KEY,
        company_name VARCHAR(255) NOT NULL,
        company_code VARCHAR(50) UNIQUE,
        contact_person VARCHAR(255),
        phone VARCHAR(20),
        email VARCHAR(100),
        website VARCHAR(255),
        api_key VARCHAR(255) COMMENT 'مفتاح API للتكامل',
        api_endpoint VARCHAR(255),
        default_shipping_cost DECIMAL(10,3) DEFAULT 0.000,
        cost_per_kg DECIMAL(10,3) DEFAULT 0.000,
        is_active TINYINT(1) DEFAULT 1,
        notes TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_company_code (company_code),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "<div class='success'><strong>✅ نجح:</strong> تم إنشاء جدول shipping_companies</div>";

    // ============================================ 
    // Insert Sample Data
    // ============================================
    echo "<h3>📝 إضافة بيانات تجريبية...</h3>";

    // Sample Shipping Companies
    $companies = [
        ['SMSA', 'سمسا إكسبريس', '920003344', 'info@smsa.com'],
        ['ARAMEX', 'أرامكس', '920020505', 'info@aramex.com'],
        ['DHL', 'دي إتش إل', '8001240505', 'info@dhl.com'],
        ['FEDEX', 'فيديكس', '8001234140', 'info@fedex.com']
    ];

    $stmt = $db->prepare("
        INSERT INTO shipping_companies 
        (company_code, company_name, phone, email, default_shipping_cost, cost_per_kg, is_active) 
        VALUES (?, ?, ?, ?, 25.000, 5.000, 1)
    ");

    foreach ($companies as $company) {
        try {
            $stmt->execute($company);
            echo "<div class='success'><strong>✅</strong> تمت إضافة شركة: {$company[1]}</div>";
        } catch (PDOException $e) {
            // Already exists
        }
    }

    echo "<div class='success'>";
    echo "<h3>🎉 اكتملت عملية الإنشاء بنجاح!</h3>";
    echo "<p>تم إنشاء/تحديث جميع الجداول المطلوبة</p>";
    echo "<p>تمت إضافة 4 شركات شحن تجريبية</p>";
    echo "</div>";

    // Display table structures
    echo "<div class='info'>";
    echo "<h3>📋 ملخص الجداول المنشأة:</h3>";
    
    $tables = [
        'orders' => 'الطلبات (محدث)',
        'order_stages' => 'مراحل الطلبات',
        'order_sorting_details' => 'تفاصيل الفرز',
        'shipments' => 'الشحنات',
        'shipment_tracking' => 'تتبع الشحنات',
        'shipping_companies' => 'شركات الشحن'
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
            <a href="../modules/orders/stages.php" class="btn">
                📦 إدارة مراحل الطلبات
            </a>
            <a href="../modules/shipping/index.php" class="btn">
                🚚 إدارة الشحن
            </a>
            <a href="../index.php" class="btn" style="background: #28a745;">
                🏠 العودة للصفحة الرئيسية
            </a>
        </div>
    </div>
</body>
</html>
