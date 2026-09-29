<?php
/**
 * System Status Dashboard
 * Shows overall system health including email functionality
 */

session_start();
require_once 'config/database.php';

// Check email system status
function checkEmailSystemStatus($db) {
    try {
        // Check if email_settings table exists and has active config
        $config = $db->query("SELECT * FROM email_settings WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        
        if (!$config) {
            return ['status' => 'warning', 'message' => 'لا توجد إعدادات بريد إلكتروني نشطة'];
        }
        
        // Check if EnterpriseEmailer class exists
        if (!file_exists('includes/EnterpriseEmailer.php')) {
            return ['status' => 'error', 'message' => 'فئة البريد الإلكتروني غير موجودة'];
        }
        
        // Check if email queue table exists
        $queue_check = $db->query("SHOW TABLES LIKE 'email_queue'")->rowCount();
        if ($queue_check == 0) {
            return ['status' => 'warning', 'message' => 'جدول قائمة البريد الإلكتروني غير موجود'];
        }
        
        return ['status' => 'success', 'message' => 'نظام البريد الإلكتروني يعمل بشكل صحيح', 'config' => $config];
        
    } catch (Exception $e) {
        return ['status' => 'error', 'message' => 'خطأ في فحص النظام: ' . $e->getMessage()];
    }
}

$email_status = checkEmailSystemStatus($db);

// Get basic system stats
$stats = [
    'orders' => $db->query("SELECT COUNT(*) FROM customer_orders")->fetchColumn(),
    'customers' => $db->query("SELECT COUNT(*) FROM customers")->fetchColumn(),
    'pending_emails' => 0
];

try {
    $stats['pending_emails'] = $db->query("SELECT COUNT(*) FROM email_queue WHERE status = 'pending'")->fetchColumn();
} catch (Exception $e) {
    // Table might not exist
}
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>حالة النظام - نظام إدارة يمان</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen py-6">
        <div class="max-w-6xl mx-auto px-4">
            <!-- Header -->
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-6 py-4 border-b">
                    <h1 class="text-2xl font-bold text-gray-900">
                        <i class="fas fa-heartbeat mr-2"></i>📊 حالة النظام
                    </h1>
                    <p class="text-gray-600 mt-1">مراقبة شاملة لحالة جميع أنظمة يمان</p>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="bg-blue-50 p-4 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-shopping-cart text-2xl text-blue-600 mr-3"></i>
                                <div>
                                    <p class="text-sm text-gray-600">الطلبات</p>
                                    <p class="text-2xl font-bold text-blue-600"><?php echo number_format($stats['orders']); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-amber-50 p-4 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-users text-2xl text-amber-600 mr-3"></i>
                                <div>
                                    <p class="text-sm text-gray-600">العملاء</p>
                                    <p class="text-2xl font-bold text-amber-600"><?php echo number_format($stats['customers']); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-yellow-50 p-4 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-envelope text-2xl text-yellow-600 mr-3"></i>
                                <div>
                                    <p class="text-sm text-gray-600">رسائل معلقة</p>
                                    <p class="text-2xl font-bold text-yellow-600"><?php echo number_format($stats['pending_emails']); ?></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="bg-purple-50 p-4 rounded-lg">
                            <div class="flex items-center">
                                <i class="fas fa-server text-2xl text-purple-600 mr-3"></i>
                                <div>
                                    <p class="text-sm text-gray-600">حالة النظام</p>
                                    <p class="text-lg font-bold text-purple-600">
                                        <?php echo $email_status['status'] == 'success' ? 'ممتاز' : 'يحتاج مراجعة'; ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Email System Status -->
            <div class="bg-white shadow rounded-lg mb-6">
                <div class="px-6 py-4 border-b">
                    <h2 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-envelope mr-2"></i>حالة نظام البريد الإلكتروني
                    </h2>
                </div>
                
                <div class="p-6">
                    <?php
                    $status_colors = [
                        'success' => 'bg-amber-100 border-amber-400 text-amber-700',
                        'warning' => 'bg-yellow-100 border-yellow-400 text-yellow-700',
                        'error' => 'bg-red-100 border-red-400 text-red-700'
                    ];
                    
                    $status_icons = [
                        'success' => 'fas fa-check-circle',
                        'warning' => 'fas fa-exclamation-triangle',
                        'error' => 'fas fa-times-circle'
                    ];
                    ?>
                    
                    <div class="<?php echo $status_colors[$email_status['status']]; ?> border px-4 py-3 rounded mb-4">
                        <i class="<?php echo $status_icons[$email_status['status']]; ?> mr-2"></i>
                        <?php echo $email_status['message']; ?>
                    </div>
                    
                    <?php if (isset($email_status['config'])): ?>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <h3 class="font-bold mb-3">التكوين الحالي:</h3>
                        <div class="grid grid-cols-2 gap-4 text-sm">
                            <div><strong>الخادم:</strong> <?php echo htmlspecialchars($email_status['config']['smtp_host']); ?></div>
                            <div><strong>المنفذ:</strong> <?php echo htmlspecialchars($email_status['config']['smtp_port']); ?></div>
                            <div><strong>التشفير:</strong> <?php echo htmlspecialchars($email_status['config']['smtp_encryption']); ?></div>
                            <div><strong>المرسل:</strong> <?php echo htmlspecialchars($email_status['config']['from_email']); ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b">
                    <h2 class="text-xl font-bold text-gray-900">
                        <i class="fas fa-tools mr-2"></i>إجراءات سريعة
                    </h2>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <a href="quick_email_test.php" class="bg-amber-500 text-white p-4 rounded-lg text-center hover:bg-amber-600 transition">
                            <i class="fas fa-paper-plane text-2xl mb-2"></i>
                            <div class="text-sm">اختبار سريع للبريد</div>
                        </a>
                        
                        <a href="email_settings_config.php" class="bg-blue-500 text-white p-4 rounded-lg text-center hover:bg-blue-600 transition">
                            <i class="fas fa-cogs text-2xl mb-2"></i>
                            <div class="text-sm">إعدادات البريد</div>
                        </a>
                        
                        <a href="email_queue_manager.php" class="bg-yellow-500 text-white p-4 rounded-lg text-center hover:bg-yellow-600 transition">
                            <i class="fas fa-list text-2xl mb-2"></i>
                            <div class="text-sm">قائمة البريد</div>
                        </a>
                        
                        <a href="modules/orders/index.php" class="bg-purple-500 text-white p-4 rounded-lg text-center hover:bg-purple-600 transition">
                            <i class="fas fa-shopping-cart text-2xl mb-2"></i>
                            <div class="text-sm">إدارة الطلبات</div>
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Navigation -->
            <div class="mt-6 text-center">
                <a href="index.php" class="inline-flex items-center px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                    <i class="fas fa-home mr-2"></i>العودة إلى الصفحة الرئيسية
                </a>
            </div>
        </div>
    </div>
</body>
</html>
