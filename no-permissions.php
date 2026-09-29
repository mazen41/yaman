<?php
session_start();
require_once 'config/database.php';

$page_title = 'لا توجد صلاحيات';
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
            display: flex;
            align-items: center;
            justify-content: center;
            direction: rtl;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            padding: 60px 40px;
            text-align: center;
            max-width: 500px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        
        .icon {
            font-size: 80px;
            color: #f59e0b;
            margin-bottom: 30px;
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }
        
        h1 {
            font-size: 32px;
            color: #1f2937;
            margin-bottom: 20px;
        }
        
        p {
            font-size: 18px;
            color: #6b7280;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .user-info {
            background: #f3f4f6;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .user-info strong {
            color: #374151;
        }
        
        .buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s ease;
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
        
        .btn-secondary:hover {
            background: #d1d5db;
        }
        
        .contact-info {
            margin-top: 30px;
            padding-top: 30px;
            border-top: 1px solid #e5e7eb;
        }
        
        .contact-info p {
            font-size: 14px;
            color: #9ca3af;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">
            <i class="fas fa-lock"></i>
        </div>
        
        <h1>لا توجد صلاحيات</h1>
        
        <p>
            عذراً، حسابك لا يملك أي صلاحيات للوصول إلى صفحات النظام حالياً.
            <br>
            يرجى الاتصال بمسؤول النظام لمنحك الصلاحيات المناسبة.
        </p>
        
        <?php if (isset($_SESSION['user_name'])): ?>
        <div class="user-info">
            <p style="margin: 0;">
                <strong>المستخدم:</strong> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                <br>
                <strong>البريد:</strong> <?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?>
                <br>
                <strong>الدور:</strong> <?php echo htmlspecialchars($_SESSION['role_name'] ?? 'غير محدد'); ?>
            </p>
        </div>
        <?php endif; ?>
        
        <div class="buttons">
            <form method="POST" action="logout.php" style="display: inline;">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-out-alt"></i>
                    تسجيل الخروج
                </button>
            </form>
            
            <a href="mailto:admin@taksoride.com" class="btn btn-secondary">
                <i class="fas fa-envelope"></i>
                الاتصال بالدعم
            </a>
        </div>
        
        <div class="contact-info">
            <p>
                <i class="fas fa-info-circle"></i>
                إذا كنت تعتقد أن هذا خطأ، يرجى الاتصال بمسؤول النظام
            </p>
        </div>
    </div>
</body>
</html>
