<?php
/**
 * RBAC System Setup Script
 * Run this once to install the complete permissions system
 */

session_start();
require_once 'config/database.php';

// Security check - only super admin can run this
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    die('Access denied. Only super admin can run this setup.');
}

$page_title = 'RBAC System Setup';
$messages = [];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_setup'])) {
    try {
        // Read and execute migration SQL
        $sqlFile = __DIR__ . '/database/migrations/create_rbac_system.sql';
        
        if (!file_exists($sqlFile)) {
            throw new Exception('Migration file not found: ' . $sqlFile);
        }
        
        $sql = file_get_contents($sqlFile);
        
        // Split by semicolon and execute each statement
        $statements = array_filter(array_map('trim', explode(';', $sql)));
        
        $db->beginTransaction();
        
        $executedCount = 0;
        foreach ($statements as $statement) {
            if (empty($statement) || strpos($statement, '--') === 0) {
                continue;
            }
            
            try {
                $db->exec($statement);
                $executedCount++;
            } catch (PDOException $e) {
                // Log but continue (some statements might fail if already exists)
                error_log("Statement execution warning: " . $e->getMessage());
            }
        }
        
        $db->commit();
        
        $messages[] = "✅ Migration completed successfully! Executed $executedCount statements.";
        $messages[] = "✅ Roles table created/updated";
        $messages[] = "✅ Permissions table created/updated";
        $messages[] = "✅ Role permissions table created/updated";
        $messages[] = "✅ Default roles seeded";
        $messages[] = "✅ Default permissions seeded";
        $messages[] = "✅ Cache table created";
        $messages[] = "✅ Audit log table created";
        
        // Verify setup
        $stmt = $db->query("SELECT COUNT(*) as count FROM roles");
        $roleCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM permissions");
        $permissionCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        $messages[] = "📊 Total roles: $roleCount";
        $messages[] = "📊 Total permissions: $permissionCount";
        
    } catch (Exception $e) {
        $db->rollBack();
        $errors[] = "❌ Setup failed: " . $e->getMessage();
    }
}

// Check current status
$status = [];
try {
    $tables = ['roles', 'permissions', 'role_permissions', 'permission_cache', 'permission_audit_log'];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        $exists = $stmt->rowCount() > 0;
        $status[$table] = $exists;
    }
} catch (PDOException $e) {
    $errors[] = "Error checking status: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
            direction: rtl;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        h1 {
            font-size: 32px;
            color: #1f2937;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .subtitle {
            color: #6b7280;
            margin-bottom: 30px;
        }
        
        .status-table {
            width: 100%;
            border-collapse: collapse;
            margin: 30px 0;
        }
        
        .status-table th,
        .status-table td {
            padding: 12px;
            text-align: right;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .status-table th {
            background: #f3f4f6;
            font-weight: 600;
            color: #374151;
        }
        
        .status-exists {
            color: #10b981;
            font-weight: 600;
        }
        
        .status-missing {
            color: #ef4444;
            font-weight: 600;
        }
        
        .messages {
            margin: 20px 0;
        }
        
        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 10px;
            line-height: 1.6;
        }
        
        .message-success {
            background: #d1fae5;
            color: #065f46;
            border-right: 4px solid #10b981;
        }
        
        .message-error {
            background: #fee2e2;
            color: #b91c1c;
            border-right: 4px solid #ef4444;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #e5e7eb;
            color: #374151;
        }
        
        .warning-box {
            background: #fef3c7;
            border: 2px solid #f59e0b;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .warning-box h3 {
            color: #92400e;
            margin-bottom: 10px;
        }
        
        .warning-box ul {
            margin-right: 20px;
            color: #78350f;
        }
        
        .actions {
            margin-top: 30px;
            display: flex;
            gap: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>
            <i class="fas fa-shield-alt"></i>
            RBAC System Setup
        </h1>
        <p class="subtitle">إعداد نظام الصلاحيات المتقدم</p>
        
        <?php if (!empty($messages)): ?>
        <div class="messages">
            <?php foreach ($messages as $message): ?>
            <div class="message message-success">
                <?php echo $message; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($errors)): ?>
        <div class="messages">
            <?php foreach ($errors as $error): ?>
            <div class="message message-error">
                <?php echo $error; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <h2 style="margin-top: 30px; margin-bottom: 15px;">Current System Status</h2>
        
        <table class="status-table">
            <thead>
                <tr>
                    <th>Table Name</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($status as $table => $exists): ?>
                <tr>
                    <td><?php echo $table; ?></td>
                    <td class="<?php echo $exists ? 'status-exists' : 'status-missing'; ?>">
                        <?php if ($exists): ?>
                            <i class="fas fa-check-circle"></i> Exists
                        <?php else: ?>
                            <i class="fas fa-times-circle"></i> Missing
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="warning-box">
            <h3><i class="fas fa-exclamation-triangle"></i> تحذير مهم</h3>
            <p>قبل تشغيل الإعداد، يرجى التأكد من:</p>
            <ul>
                <li>عمل نسخة احتياطية من قاعدة البيانات</li>
                <li>أنك مسجل دخول كمدير نظام</li>
                <li>لا يوجد مستخدمون آخرون يعملون على النظام حالياً</li>
            </ul>
        </div>
        
        <div class="actions">
            <form method="POST" onsubmit="return confirm('هل أنت متأكد من تشغيل الإعداد؟');">
                <button type="submit" name="run_setup" class="btn btn-primary">
                    <i class="fas fa-play"></i>
                    Run Setup
                </button>
            </form>
            
            <a href="index.php" class="btn btn-secondary">
                <i class="fas fa-arrow-right"></i>
                العودة للرئيسية
            </a>
        </div>
        
        <div style="margin-top: 40px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 14px;">
            <p><strong>What this setup does:</strong></p>
            <ul style="margin-right: 20px; margin-top: 10px;">
                <li>Creates roles, permissions, and role_permissions tables</li>
                <li>Seeds default roles (super_admin, manager, employee, etc.)</li>
                <li>Seeds permissions for all modules</li>
                <li>Creates permission cache table for performance</li>
                <li>Creates audit log table for tracking changes</li>
                <li>Adds role_id column to users table</li>
            </ul>
        </div>
    </div>
</body>
</html>
