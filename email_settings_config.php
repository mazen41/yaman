<?php
session_start();
require_once 'config/database.php';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $config_name = $_POST['config_name'] ?? 'custom_config';
        $smtp_host = $_POST['smtp_host'] ?? '';
        $smtp_port = intval($_POST['smtp_port'] ?? 587);
        $smtp_username = $_POST['smtp_username'] ?? '';
        $smtp_password = $_POST['smtp_password'] ?? '';
        $smtp_encryption = $_POST['smtp_encryption'] ?? 'tls';
        $from_email = $_POST['from_email'] ?? '';
        $from_name = $_POST['from_name'] ?? '';
        $test_mode = isset($_POST['test_mode']) ? 1 : 0;
        $debug_mode = isset($_POST['debug_mode']) ? 1 : 0;
        
        // Validate required fields
        if (empty($smtp_host) || empty($from_email) || empty($from_name)) {
            throw new Exception("الحقول المطلوبة: SMTP Host، From Email، From Name");
        }
        
        // Deactivate all existing configurations
        $db->exec("UPDATE email_settings SET is_active = 0");
        
        // Insert or update configuration
        $stmt = $db->prepare("
            INSERT INTO email_settings 
            (config_name, smtp_host, smtp_port, smtp_username, smtp_password, 
             smtp_encryption, from_email, from_name, is_active, test_mode, debug_mode)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)
            ON DUPLICATE KEY UPDATE
            smtp_host = VALUES(smtp_host),
            smtp_port = VALUES(smtp_port),
            smtp_username = VALUES(smtp_username),
            smtp_password = VALUES(smtp_password),
            smtp_encryption = VALUES(smtp_encryption),
            from_email = VALUES(from_email),
            from_name = VALUES(from_name),
            is_active = 1,
            test_mode = VALUES(test_mode),
            debug_mode = VALUES(debug_mode)
        ");
        
        $stmt->execute([
            $config_name, $smtp_host, $smtp_port, $smtp_username, $smtp_password,
            $smtp_encryption, $from_email, $from_name, $test_mode, $debug_mode
        ]);
        
        $success_message = "تم حفظ إعدادات البريد الإلكتروني بنجاح";
        
    } catch (Exception $e) {
        $error_message = "حدث خطأ: " . $e->getMessage();
    }
}

// Get current configuration
$current_config = $db->query("SELECT * FROM email_settings WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعدادات البريد الإلكتروني - نظام يمان</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen py-6">
        <div class="max-w-4xl mx-auto px-4">
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h1 class="text-2xl font-bold text-gray-900">🔧 إعدادات البريد الإلكتروني المتقدمة</h1>
                    <p class="text-gray-600 mt-1">تكوين نظام البريد الإلكتروني مع آليات الاحتياطية المتعددة</p>
                </div>

                <?php if ($success_message): ?>
                <div class="bg-amber-100 border border-amber-400 text-amber-700 px-4 py-3 m-6 rounded">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo $success_message; ?>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 m-6 rounded">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <?php echo $error_message; ?>
                </div>
                <?php endif; ?>

                <form method="POST" class="p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-tag mr-2"></i>اسم التكوين
                            </label>
                            <input type="text" name="config_name" 
                                   value="<?php echo htmlspecialchars($current_config['config_name'] ?? 'custom_config'); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-server mr-2"></i>SMTP Host *
                            </label>
                            <input type="text" name="smtp_host" 
                                   value="<?php echo htmlspecialchars($current_config['smtp_host'] ?? 'smtp.gmail.com'); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                            <p class="text-xs text-gray-500 mt-1">Gmail: smtp.gmail.com | Outlook: smtp.office365.com</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-network-wired mr-2"></i>SMTP Port
                            </label>
                            <select name="smtp_port" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="587" <?php echo ($current_config['smtp_port'] ?? 587) == 587 ? 'selected' : ''; ?>>587 (TLS)</option>
                                <option value="465" <?php echo ($current_config['smtp_port'] ?? 587) == 465 ? 'selected' : ''; ?>>465 (SSL)</option>
                                <option value="25" <?php echo ($current_config['smtp_port'] ?? 587) == 25 ? 'selected' : ''; ?>>25 (Plain)</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-shield-alt mr-2"></i>التشفير
                            </label>
                            <select name="smtp_encryption" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                                <option value="tls" <?php echo ($current_config['smtp_encryption'] ?? 'tls') == 'tls' ? 'selected' : ''; ?>>TLS (موصى به)</option>
                                <option value="ssl" <?php echo ($current_config['smtp_encryption'] ?? 'tls') == 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="none" <?php echo ($current_config['smtp_encryption'] ?? 'tls') == 'none' ? 'selected' : ''; ?>>بدون تشفير</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-user mr-2"></i>اسم المستخدم SMTP
                            </label>
                            <input type="email" name="smtp_username" 
                                   value="<?php echo htmlspecialchars($current_config['smtp_username'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-lock mr-2"></i>كلمة مرور SMTP
                            </label>
                            <input type="password" name="smtp_password" 
                                   value="<?php echo htmlspecialchars($current_config['smtp_password'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <p class="text-xs text-gray-500 mt-1">للجيميل: استخدم App Password وليس كلمة المرور العادية</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-envelope mr-2"></i>البريد المرسل منه *
                            </label>
                            <input type="email" name="from_email" 
                                   value="<?php echo htmlspecialchars($current_config['from_email'] ?? ''); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-signature mr-2"></i>اسم المرسل *
                            </label>
                            <input type="text" name="from_name" 
                                   value="<?php echo htmlspecialchars($current_config['from_name'] ?? 'نظام إدارة يمان'); ?>"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                        </div>
                    </div>
                    
                    <div class="border-t pt-6">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">
                            <i class="fas fa-cogs mr-2"></i>خيارات متقدمة
                        </h3>
                        
                        <div class="space-y-3">
                            <label class="flex items-center">
                                <input type="checkbox" name="test_mode" value="1" 
                                       <?php echo ($current_config['test_mode'] ?? 0) ? 'checked' : ''; ?>
                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="mr-2 text-sm text-gray-700">
                                    <i class="fas fa-flask mr-1"></i>وضع الاختبار (لن يتم إرسال رسائل فعلية)
                                </span>
                            </label>
                            
                            <label class="flex items-center">
                                <input type="checkbox" name="debug_mode" value="1" 
                                       <?php echo ($current_config['debug_mode'] ?? 0) ? 'checked' : ''; ?>
                                       class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50">
                                <span class="mr-2 text-sm text-gray-700">
                                    <i class="fas fa-bug mr-1"></i>وضع التصحيح (عرض تفاصيل الأخطاء)
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center pt-6">
                        <a href="modules/orders/index.php" class="text-gray-600 hover:text-gray-800">
                            <i class="fas fa-arrow-right mr-2"></i>العودة إلى الطلبات
                        </a>
                        
                        <div class="space-x-4 space-x-reverse">
                            <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition">
                                <i class="fas fa-save mr-2"></i>حفظ الإعدادات
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="mt-6 bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b">
                    <h2 class="text-lg font-bold">🧪 اختبار النظام</h2>
                </div>
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <a href="test_enterprise_email.php" class="bg-amber-500 text-white p-4 rounded-lg text-center hover:bg-amber-600 transition">
                            <i class="fas fa-paper-plane text-2xl mb-2"></i>
                            <div>اختبار الإرسال</div>
                        </a>
                        
                        <a href="email_queue_manager.php" class="bg-blue-500 text-white p-4 rounded-lg text-center hover:bg-blue-600 transition">
                            <i class="fas fa-list text-2xl mb-2"></i>
                            <div>إدارة القائمة</div>
                        </a>
                        
                        <a href="email_system_fix.php" class="bg-purple-500 text-white p-4 rounded-lg text-center hover:bg-purple-600 transition">
                            <i class="fas fa-tools text-2xl mb-2"></i>
                            <div>تشخيص النظام</div>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="mt-6 bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <h3 class="font-bold text-yellow-800 mb-2">📝 تعليمات الإعداد:</h3>
                <ul class="text-sm text-yellow-700 space-y-1">
                    <li>• <strong>Gmail:</strong> استخدم smtp.gmail.com مع المنفذ 587 وتشفير TLS</li>
                    <li>• <strong>App Password:</strong> قم بإنشاء كلمة مرور خاصة بالتطبيق من إعدادات جوجل</li>
                    <li>• <strong>المحلي:</strong> استخدم localhost مع المنفذ 25 بدون تشفير للاختبار المحلي</li>
                    <li>• <strong>Outlook:</strong> استخدم smtp.office365.com مع المنفذ 587 وتشفير TLS</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>
