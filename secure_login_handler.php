<?php
/**
 * Secure Login Handler with CSRF, Rate Limiting, and Token Management
 */

require_once 'config/database.php';
require_once 'security_config.php';

// Initialize security manager
$security = new SecurityManager($db);

// Rate limiting check
function checkRateLimit($db, $username, $ip_address) {
    $stmt = $db->prepare("
        SELECT COUNT(*) as attempts 
        FROM failed_login_attempts 
        WHERE (username = ? OR ip_address = ?) 
        AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
    ");
    $stmt->execute([$username, $ip_address]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result['attempts'] >= 5) {
        return false;
    }
    return true;
}

// Log failed attempt
function logFailedAttempt($db, $username, $ip_address) {
    $stmt = $db->prepare("
        INSERT INTO failed_login_attempts (username, ip_address, user_agent)
        VALUES (?, ?, ?)
    ");
    $stmt->execute([$username, $ip_address, $_SERVER['HTTP_USER_AGENT'] ?? '']);
}

// Handle login POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $response = ['success' => false, 'message' => ''];
    
    try {
        // CSRF Protection
        $csrf_token = $_POST['csrf_token'] ?? '';
        if (!$security->validateCSRFToken($csrf_token)) {
            throw new Exception('رمز الأمان غير صحيح. يرجى تحديث الصفحة والمحاولة مرة أخرى.');
        }
        
        // Sanitize inputs
        $username = $security->sanitizeInput($_POST['username'] ?? '', 'string');
        $password = $_POST['password'] ?? '';
        $remember_me = isset($_POST['remember_me']);
        
        if (empty($username) || empty($password)) {
            throw new Exception('اسم المستخدم وكلمة المرور مطلوبان');
        }
        
        // Rate limiting check
        $ip_address = $security->getClientIP();
        if (!checkRateLimit($db, $username, $ip_address)) {
            throw new Exception('تم تجاوز عدد محاولات تسجيل الدخول. يرجى المحاولة بعد 15 دقيقة.');
        }
        
        // Verify credentials
        $stmt = $db->prepare("
            SELECT id, username, password, full_name, email, is_admin, is_active 
            FROM users 
            WHERE username = ? OR email = ?
        ");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user || !password_verify($password, $user['password'])) {
            logFailedAttempt($db, $username, $ip_address);
            throw new Exception('اسم المستخدم أو كلمة المرور غير صحيحة');
        }
        
        if (!$user['is_active']) {
            throw new Exception('حسابك غير نشط. يرجى الاتصال بالمسؤول.');
        }
        
        // Clear failed attempts
        $db->prepare("DELETE FROM failed_login_attempts WHERE username = ? OR ip_address = ?")
           ->execute([$username, $ip_address]);
        
        // Create session
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['is_admin'] = $user['is_admin'];
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Create auth token
        $token = $security->createAuthToken($user['id']);
        if ($remember_me && $token) {
            setcookie('auth_token', $token, time() + SESSION_LIFETIME, '/', '', true, true);
        }
        
        // Log successful login
        $security->logSession($user['id'], 'login');
        
        // Update last login
        $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")
           ->execute([$user['id']]);
        
        $response['success'] = true;
        $response['message'] = 'تم تسجيل الدخول بنجاح';
        $response['redirect'] = 'index.php';
        
    } catch (Exception $e) {
        $response['message'] = $e->getMessage();
        
        // Log security event
        if (isset($security)) {
            $security->logSecurityEvent('login_failed', [
                'username' => $username ?? 'unknown',
                'error' => $e->getMessage()
            ]);
        }
    }
    
    // Return JSON response for AJAX
    if (isset($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
    }
    
    // Regular form submission
    if ($response['success']) {
        header('Location: ' . $response['redirect']);
    } else {
        $_SESSION['error_message'] = $response['message'];
        header('Location: login.php');
    }
    exit();
}
