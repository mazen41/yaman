<?php
/**
 * Secure Login Page with CSRF Protection
 */
session_start();
require_once 'config/database.php';
require_once 'security_config.php';

$security = new SecurityManager($db);
$csrf_token = $security->generateCSRFToken();

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);

$timeout = isset($_GET['timeout']);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - نظام آمن</title>
    <?php echo csrf_meta(); ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen flex items-center justify-center p-4">
    
    <div class="max-w-md w-full">
        <!-- Security Badge -->
        <div class="text-center mb-6">
            <div class="inline-flex items-center justify-center w-20 h-20 bg-amber-100 rounded-full mb-4">
                <i class="fas fa-shield-alt text-4xl text-amber-600"></i>
            </div>
            <h1 class="text-3xl font-bold text-gray-900">نظام آمن</h1>
            <p class="text-gray-600 mt-2">محمي بتشفير AES-256-CBC</p>
        </div>

        <!-- Login Card -->
        <div class="bg-white rounded-2xl shadow-2xl p-8">
            <h2 class="text-2xl font-bold text-gray-900 mb-6 text-center">تسجيل الدخول</h2>
            
            <!-- Timeout Message -->
            <?php if ($timeout): ?>
            <div class="bg-yellow-100 border border-yellow-400 text-yellow-800 px-4 py-3 rounded-lg mb-4 flex items-center">
                <i class="fas fa-clock ml-2"></i>
                <span>تم تسجيل الخروج تلقائياً بسبب عدم النشاط (30 دقيقة)</span>
            </div>
            <?php endif; ?>
            
            <!-- Error Message -->
            <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-4 flex items-center">
                <i class="fas fa-exclamation-circle ml-2"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form id="loginForm" method="POST" action="secure_login_handler.php">
                <?php echo csrf_field(); ?>
                
                <!-- Username -->
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="username">
                        <i class="fas fa-user ml-2"></i>
                        اسم المستخدم أو البريد الإلكتروني
                    </label>
                    <input type="text" id="username" name="username" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                           placeholder="أدخل اسم المستخدم">
                </div>
                
                <!-- Password -->
                <div class="mb-4">
                    <label class="block text-gray-700 text-sm font-bold mb-2" for="password">
                        <i class="fas fa-lock ml-2"></i>
                        كلمة المرور
                    </label>
                    <div class="relative">
                        <input type="password" id="password" name="password" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="أدخل كلمة المرور">
                        <button type="button" onclick="togglePassword()" class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700">
                            <i id="passwordIcon" class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Remember Me -->
                <div class="mb-6 flex items-center">
                    <input type="checkbox" id="remember_me" name="remember_me" class="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500">
                    <label for="remember_me" class="mr-2 text-sm text-gray-700">تذكرني لمدة 120 دقيقة</label>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" id="submitBtn"
                        class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 text-white font-bold py-3 px-4 rounded-lg hover:from-blue-700 hover:to-indigo-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200 shadow-lg hover:shadow-xl">
                    <i class="fas fa-sign-in-alt ml-2"></i>
                    تسجيل الدخول
                </button>
            </form>
            
            <!-- Security Features -->
            <div class="mt-6 pt-6 border-t border-gray-200">
                <p class="text-xs text-gray-600 text-center mb-3">ميزات الأمان المفعلة:</p>
                <div class="grid grid-cols-2 gap-2 text-xs">
                    <div class="flex items-center text-amber-600">
                        <i class="fas fa-check-circle ml-1"></i>
                        <span>تشفير AES-256</span>
                    </div>
                    <div class="flex items-center text-amber-600">
                        <i class="fas fa-check-circle ml-1"></i>
                        <span>حماية CSRF</span>
                    </div>
                    <div class="flex items-center text-amber-600">
                        <i class="fas fa-check-circle ml-1"></i>
                        <span>حماية XSS</span>
                    </div>
                    <div class="flex items-center text-amber-600">
                        <i class="fas fa-check-circle ml-1"></i>
                        <span>حماية SQL Injection</span>
                    </div>
                    <div class="flex items-center text-amber-600">
                        <i class="fas fa-check-circle ml-1"></i>
                        <span>جلسات آمنة</span>
                    </div>
                    <div class="flex items-center text-amber-600">
                        <i class="fas fa-check-circle ml-1"></i>
                        <span>خروج تلقائي</span>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="text-center mt-6 text-sm text-gray-600">
            <p>
                <i class="fas fa-shield-alt text-amber-600 ml-1"></i>
                محمي بأعلى معايير الأمان السيبراني
            </p>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('passwordIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordIcon.classList.remove('fa-eye');
                passwordIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordIcon.classList.remove('fa-eye-slash');
                passwordIcon.classList.add('fa-eye');
            }
        }
        
        // Form submission with loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin ml-2"></i> جاري تسجيل الدخول...';
        });
        
        // Auto-focus username field
        document.getElementById('username').focus();
    </script>
</body>
</html>
