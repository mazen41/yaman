<?php
/**
 * Debug Permissions System - Check tables and test saving
 */
session_start();
require_once 'config/database.php';

header('Content-Type: text/html; charset=utf-8');
echo "<div dir='rtl' style='font-family: Arial; padding: 20px;'>";
echo "<h1>🔍 تشخيص نظام الصلاحيات</h1>";

// 1) Check permissions table
echo "<h2>1. جدول permissions</h2>";
try {
    $cols = $db->query("SHOW COLUMNS FROM permissions")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre style='background:#f0f0f0; padding:10px;'>";
    foreach ($cols as $c) {
        echo "- {$c['Field']} ({$c['Type']})\n";
    }
    echo "</pre>";
    
    $count = $db->query("SELECT COUNT(*) FROM permissions")->fetchColumn();
    echo "<p>عدد السجلات: <strong>$count</strong></p>";
    
    if ($count > 0) {
        echo "<p>أمثلة:</p><pre style='background:#e8f5e9; padding:10px;'>";
        $samples = $db->query("SELECT * FROM permissions LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
        print_r($samples);
        echo "</pre>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ خطأ: " . $e->getMessage() . "</p>";
    echo "<p>الجدول غير موجود. سأقوم بإنشائه...</p>";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            permission_key VARCHAR(100) NOT NULL UNIQUE,
            permission_type VARCHAR(50) NOT NULL DEFAULT 'view',
            module_name VARCHAR(100) NOT NULL DEFAULT '',
            description VARCHAR(255) DEFAULT '',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green;'>✓ تم إنشاء جدول permissions</p>";
}

// 2) Check user_permissions table
echo "<h2>2. جدول user_permissions</h2>";
try {
    $cols = $db->query("SHOW COLUMNS FROM user_permissions")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre style='background:#f0f0f0; padding:10px;'>";
    foreach ($cols as $c) {
        echo "- {$c['Field']} ({$c['Type']})\n";
    }
    echo "</pre>";
    
    $count = $db->query("SELECT COUNT(*) FROM user_permissions")->fetchColumn();
    echo "<p>عدد السجلات: <strong>$count</strong></p>";
    
    if ($count > 0) {
        echo "<p>أمثلة:</p><pre style='background:#e8f5e9; padding:10px;'>";
        $samples = $db->query("SELECT up.*, p.permission_key FROM user_permissions up LEFT JOIN permissions p ON up.permission_id = p.id LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
        print_r($samples);
        echo "</pre>";
    }
} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ خطأ: " . $e->getMessage() . "</p>";
    echo "<p>الجدول غير موجود. سأقوم بإنشائه...</p>";
    
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            permission_id INT NOT NULL,
            granted_by INT DEFAULT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_perm (user_id, permission_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    echo "<p style='color:green;'>✓ تم إنشاء جدول user_permissions</p>";
}

// 3) Check users table
echo "<h2>3. جدول users (الموظفين)</h2>";
try {
    $users = $db->query("SELECT id, username, full_name, is_admin FROM users WHERE is_admin = 0 LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre style='background:#fff3e0; padding:10px;'>";
    print_r($users);
    echo "</pre>";
} catch (PDOException $e) {
    echo "<p style='color:red;'>❌ خطأ: " . $e->getMessage() . "</p>";
}

// 4) Test saving a permission
echo "<h2>4. اختبار حفظ صلاحية</h2>";
if (isset($_GET['test_save'])) {
    $test_user_id = intval($_GET['test_user_id'] ?? 1);
    $test_perm = 'customers_view';
    
    try {
        // Ensure permission exists
        $db->exec("INSERT IGNORE INTO permissions (permission_key, permission_type, module_name, description) VALUES ('$test_perm', 'view', 'customers', 'عرض العملاء')");
        echo "<p>✓ تم التأكد من وجود الصلاحية في جدول permissions</p>";
        
        // Get permission ID
        $perm_id = $db->query("SELECT id FROM permissions WHERE permission_key = '$test_perm'")->fetchColumn();
        echo "<p>ID الصلاحية: <strong>$perm_id</strong></p>";
        
        // Insert user permission
        $stmt = $db->prepare("INSERT IGNORE INTO user_permissions (user_id, permission_id, granted_by) VALUES (?, ?, ?)");
        $stmt->execute([$test_user_id, $perm_id, 1]);
        
        echo "<p style='color:green;'>✓ تم حفظ الصلاحية للمستخدم $test_user_id</p>";
        
        // Verify
        $check = $db->prepare("SELECT * FROM user_permissions WHERE user_id = ? AND permission_id = ?");
        $check->execute([$test_user_id, $perm_id]);
        $result = $check->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo "<p style='color:green;'>✓ تم التحقق: الصلاحية محفوظة بنجاح</p>";
            echo "<pre style='background:#e8f5e9; padding:10px;'>";
            print_r($result);
            echo "</pre>";
        } else {
            echo "<p style='color:red;'>❌ فشل التحقق: الصلاحية غير موجودة!</p>";
        }
        
    } catch (PDOException $e) {
        echo "<p style='color:red;'>❌ خطأ: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p><a href='?test_save=1&test_user_id=9' style='background:#2196F3; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>🧪 اختبار حفظ صلاحية (للمستخدم 9)</a></p>";
}

// 5) Populate all permissions
echo "<h2>5. ملء جدول الصلاحيات</h2>";
if (isset($_GET['populate'])) {
    $modules = [
        'dashboard' => ['view'],
        'customers' => ['view', 'edit', 'add'],
        'customer_types' => ['view', 'edit', 'add'],
        'cities' => ['view', 'edit', 'add'],
        'orders' => ['view', 'edit', 'add'],
        'financial_review' => ['view', 'edit'],
        'purchase_groups' => ['view', 'edit', 'add'],
        'baskets' => ['view', 'edit', 'add'],
        'purchase_cards' => ['view', 'edit', 'add'],
        'loyalty_cards' => ['view', 'edit', 'add'],
        'shipping' => ['view', 'edit', 'add'],
        'whatsapp' => ['view', 'add'],
        'financial' => ['view', 'edit', 'add'],
    ];
    
    $labels = ['view' => 'عرض', 'edit' => 'تعديل', 'add' => 'إضافة'];
    $count = 0;
    
    foreach ($modules as $module => $perms) {
        foreach ($perms as $perm) {
            $key = $module . '_' . $perm;
            $desc = $labels[$perm] . ' ' . $module;
            
            $stmt = $db->prepare("INSERT IGNORE INTO permissions (permission_key, permission_type, module_name, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$key, $perm, $module, $desc]);
            
            if ($stmt->rowCount() > 0) {
                echo "<p>+ $key</p>";
                $count++;
            }
        }
    }
    
    echo "<p style='color:green; font-weight:bold;'>✓ تم إضافة $count صلاحية جديدة</p>";
} else {
    echo "<p><a href='?populate=1' style='background:#4CAF50; color:white; padding:10px 20px; text-decoration:none; border-radius:5px;'>📥 ملء جدول الصلاحيات</a></p>";
}

echo "</div>";
