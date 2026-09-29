<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();

// If already logged in as courier, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    require_once 'config/database.php';
    
    try {
        $check = $db->prepare("
            SELECT r.name 
            FROM user_roles ur 
            JOIN roles r ON ur.role_id = r.id 
            WHERE ur.user_id = ? AND r.name = 'courier'
        ");
        $check->execute([$_SESSION['user_id']]);
        
        if ($check->fetch()) {
            header('Location: /modules/courier/dashboard/');
            exit();
        }
    } catch (Exception $e) {
        // Continue to login page
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($username && $password) {
        try {
            require_once 'config/database.php';
            
            // Get user with courier role
            $stmt = $db->prepare("
                SELECT u.id, u.username, u.password, u.full_name
                FROM users u
                JOIN user_roles ur ON u.id = ur.user_id
                JOIN roles r ON ur.role_id = r.id
                WHERE u.username = ? 
                AND r.name = 'courier'
                AND u.is_active = 1
            ");
            $stmt->execute([$username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'] ?? $user['username'];
                $_SESSION['role'] = 'courier';
                
                header('Location: /modules/courier/dashboard/');
                exit();
            } else {
                $error = 'اسم المستخدم أو كلمة المرور غير صحيحة';
            }
        } catch (Exception $e) {
            error_log("Courier login error: " . $e->getMessage());
            $error = 'حدث خطأ في النظام - يرجى المحاولة لاحقاً';
        }
    } else {
        $error = 'الرجاء إدخال اسم المستخدم وكلمة المرور';
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل دخول الموصل - تكسو رايد</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #059669 0%, #C7A46D 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .login-card {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 400px;
            width: 90%;
        }
        .input-field {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.2s;
        }
        .input-field:focus {
            outline: none;
            border-color: #C7A46D;
            box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
        }
        .btn-login {
            width: 100%;
            background: linear-gradient(135deg, #059669, #C7A46D);
            color: white;
            padding: 1rem;
            border-radius: 8px;
            font-weight: 700;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(5, 150, 105, 0.3);
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div style="text-align: center; margin-bottom: 2rem;">
            <div style="width: 80px; height: 80px; background: linear-gradient(135deg, #059669, #C7A46D); border-radius: 50%; margin: 0 auto 1rem; display: flex; align-items: center; justify-content: center;">
                <i class="fas fa-truck" style="font-size: 2rem; color: white;"></i>
            </div>
            <h1 style="font-size: 1.75rem; font-weight: 700; color: #059669;">بوابة الموصلين</h1>
            <p style="color: #6b7280; margin-top: 0.5rem;">تسجيل الدخول لإدارة طلباتك</p>
        </div>

        <?php if ($error): ?>
            <div style="background: #fee2e2; border-right: 4px solid #ef4444; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; color: #991b1b;">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #374151;">
                    <i class="fas fa-user"></i> اسم المستخدم
                </label>
                <input type="text" name="username" required autofocus class="input-field" placeholder="أدخل اسم المستخدم" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>">
            </div>

            <div style="margin-bottom: 1.5rem;">
                <label style="display: block; font-weight: 600; margin-bottom: 0.5rem; color: #374151;">
                    <i class="fas fa-lock"></i> كلمة المرور
                </label>
                <input type="password" name="password" required class="input-field" placeholder="أدخل كلمة المرور">
            </div>

            <button type="submit" class="btn-login">
                <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
            </button>
        </form>

        <div style="text-align: center; margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid #e5e7eb;">
            <a href="/login.php" style="color: #6b7280; font-size: 0.875rem; text-decoration: none;">
                <i class="fas fa-arrow-left"></i> تسجيل دخول الموظفين
            </a>
        </div>
    </div>
</body>
</html>
