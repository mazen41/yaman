<?php
/**
 * Quick Email Test - Hostinger SMTP Configuration
 * Senior Developer Solution
 */

session_start();
require_once 'config/database.php';

$test_result = '';
$debug_output = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $test_email = $_POST['test_email'] ?? '';
    
    if (empty($test_email) || !filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
        $test_result = '<div class="bg-red-100 text-red-700 p-4 rounded mb-4">❌ عنوان البريد الإلكتروني غير صالح</div>';
    } else {
        try {
            require_once 'includes/EnterpriseEmailer.php';
            $mailer = new EnterpriseEmailer($db);
            
            $subject = "🧪 اختبار نظام البريد الإلكتروني - " . date('Y-m-d H:i:s');
            $body = '
            <html dir="rtl">
            <head><meta charset="UTF-8"></head>
            <body style="font-family: Arial, sans-serif; direction: rtl; background: #f9f9f9; padding: 20px;">
                <div style="max-width: 600px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
                    <div style="background: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px; margin-bottom: 20px;">
                        <h1>🚀 نظام البريد الإلكتروني يعمل بنجاح!</h1>
                    </div>
                    
                    <div style="padding: 20px;">
                        <h2 style="color: #333;">✅ تم الاختبار بنجاح</h2>
                        <p>مرحباً! هذه رسالة اختبار من نظام إدارة يمان.</p>
                        
                        <div style="background: #e8f5e8; padding: 15px; border-radius: 5px; margin: 20px 0;">
                            <h3>📊 معلومات الاختبار:</h3>
                            <ul>
                                <li><strong>التاريخ والوقت:</strong> ' . date('Y-m-d H:i:s') . '</li>
                                <li><strong>الخادم:</strong> smtp.hostinger.com</li>
                                <li><strong>المنفذ:</strong> 465 (SSL)</li>
                                <li><strong>الحالة:</strong> نشط ✅</li>
                            </ul>
                        </div>
                        
                        <p>إذا وصلتك هذه الرسالة، فهذا يعني أن:</p>
                        <ul style="color: #666;">
                            <li>✅ إعدادات SMTP تعمل بشكل صحيح</li>
                            <li>✅ التشفير SSL يعمل بسلاسة</li>
                            <li>✅ النصوص العربية تظهر صحيحة</li>
                            <li>✅ نظام البريد الإلكتروني جاهز للاستخدام</li>
                        </ul>
                        
                        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin-top: 20px;">
                            <p><strong>🔧 النظام جاهز الآن!</strong></p>
                            <p>يمكنك الآن استخدام نظام البريد الإلكتروني لإرسال إشعارات الطلبات والفواتير للعملاء.</p>
                        </div>
                    </div>
                    
                    <div style="text-align: center; color: #666; font-size: 12px; border-top: 1px solid #eee; padding-top: 20px; margin-top: 20px;">
                        <p>© 2025 نظام إدارة يمان - جميع الحقوق محفوظة</p>
                        <p>تم الإرسال عبر خادم Hostinger SMTP</p>
                    </div>
                </div>
            </body>
            </html>';
            
            $success = $mailer->sendEmail($test_email, $subject, $body, "مستخدم نظام يمان");
            
            if ($success) {
                $test_result = '
                <div class="bg-amber-100 text-amber-700 p-6 rounded-lg mb-4">
                    <div class="flex items-center mb-2">
                        <i class="fas fa-check-circle text-2xl mr-3"></i>
                        <h3 class="text-lg font-bold">🎉 تم الإرسال بنجاح!</h3>
                    </div>
                    <p>تم إرسال رسالة الاختبار بنجاح إلى: <strong>' . htmlspecialchars($test_email) . '</strong></p>
                    <p class="mt-2 text-sm">✅ نظام البريد الإلكتروني يعمل بشكل مثالي مع خادم Hostinger</p>
                </div>';
            } else {
                $error = $mailer->getLastError();
                $test_result = '
                <div class="bg-red-100 text-red-700 p-4 rounded mb-4">
                    <h3 class="font-bold mb-2">❌ فشل في الإرسال</h3>
                    <p>' . htmlspecialchars($error) . '</p>
                </div>';
            }
            
            $debug_output = $mailer->getDebugOutput();
            
        } catch (Exception $e) {
            $test_result = '
            <div class="bg-red-100 text-red-700 p-4 rounded mb-4">
                <h3 class="font-bold mb-2">❌ خطأ في النظام</h3>
                <p>' . htmlspecialchars($e->getMessage()) . '</p>
            </div>';
        }
    }
}

// Get current configuration
$config = $db->query("SELECT * FROM email_settings WHERE is_active = 1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اختبار سريع للبريد الإلكتروني</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen py-6">
        <div class="max-w-2xl mx-auto px-4">
            <div class="bg-white shadow rounded-lg">
                <div class="px-6 py-4 border-b bg-gradient-to-r from-amber-500 to-blue-500 text-white rounded-t-lg">
                    <h1 class="text-2xl font-bold">
                        <i class="fas fa-rocket mr-2"></i>🧪 اختبار سريع - نظام البريد الإلكتروني
                    </h1>
                    <p class="mt-1 opacity-90">اختبار التكوين الحالي مع خادم Hostinger</p>
                </div>

                <?php if ($test_result): ?>
                <div class="p-6 border-b">
                    <?php echo $test_result; ?>
                </div>
                <?php endif; ?>

                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Test Form -->
                        <div>
                            <h2 class="text-lg font-bold mb-4">
                                <i class="fas fa-paper-plane mr-2"></i>إرسال اختبار سريع
                            </h2>
                            
                            <form method="POST" class="space-y-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">
                                        البريد الإلكتروني للاختبار
                                    </label>
                                    <input type="email" name="test_email" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                                           placeholder="your-email@example.com">
                                </div>
                                
                                <button type="submit" class="w-full bg-gradient-to-r from-amber-500 to-blue-500 text-white py-3 px-4 rounded-lg hover:from-amber-600 hover:to-blue-600 transition">
                                    <i class="fas fa-paper-plane mr-2"></i>إرسال رسالة اختبار
                                </button>
                            </form>
                        </div>
                        
                        <!-- Current Config -->
                        <div>
                            <h2 class="text-lg font-bold mb-4">
                                <i class="fas fa-server mr-2"></i>التكوين الحالي
                            </h2>
                            
                            <?php if ($config): ?>
                            <div class="bg-amber-50 border border-amber-200 p-4 rounded-lg space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <strong>الحالة:</strong> 
                                    <span class="text-amber-600 font-bold">نشط ✅</span>
                                </div>
                                <div class="flex justify-between">
                                    <strong>الخادم:</strong> 
                                    <span><?php echo htmlspecialchars($config['smtp_host']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <strong>المنفذ:</strong> 
                                    <span><?php echo htmlspecialchars($config['smtp_port']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <strong>التشفير:</strong> 
                                    <span class="uppercase"><?php echo htmlspecialchars($config['smtp_encryption']); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <strong>المرسل:</strong> 
                                    <span><?php echo htmlspecialchars($config['from_email']); ?></span>
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-lg">
                                <i class="fas fa-exclamation-triangle mr-2"></i>
                                لا توجد إعدادات نشطة
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <?php if ($debug_output): ?>
                <div class="border-t p-6">
                    <h3 class="font-bold text-lg mb-3">
                        <i class="fas fa-terminal mr-2"></i>سجل الاتصال SMTP:
                    </h3>
                    <pre class="bg-gray-900 text-amber-400 p-4 rounded-lg overflow-auto text-xs max-h-60"><?php echo htmlspecialchars($debug_output); ?></pre>
                </div>
                <?php endif; ?>
                
                <div class="border-t p-6 bg-gray-50 rounded-b-lg">
                    <div class="flex flex-wrap gap-3 justify-center">
                        <a href="email_settings_config.php" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition">
                            <i class="fas fa-cogs mr-2"></i>تعديل الإعدادات
                        </a>
                        <a href="test_enterprise_email.php" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition">
                            <i class="fas fa-flask mr-2"></i>اختبار شامل
                        </a>
                        <a href="modules/orders/send_email.php?id=1" class="bg-purple-600 text-white px-4 py-2 rounded-lg hover:bg-purple-700 transition">
                            <i class="fas fa-envelope mr-2"></i>اختبار إرسال طلب
                        </a>
                        <a href="modules/orders/index.php" class="bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 transition">
                            <i class="fas fa-shopping-cart mr-2"></i>العودة للطلبات
                        </a>
                    </div>
                    
                    <div class="mt-4 text-center text-sm text-gray-600">
                        <p>🚀 النظام جاهز للاستخدام مع خادم Hostinger SMTP</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
