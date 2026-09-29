<?php
/**
 * Auto-prepend Security File
 * This file is automatically included before every PHP script
 * Handles session timeout and security checks
 */

// Only run for non-login pages
$current_script = basename($_SERVER['PHP_SELF']);
$excluded_files = ['login.php', 'logout.php', 'security_config.php', 'security_middleware.php'];

if (!in_array($current_script, $excluded_files)) {
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in
    if (isset($_SESSION['user_id'])) {
        $current_time = time();
        
        // Check inactivity timeout (30 minutes = 1800 seconds)
        if (isset($_SESSION['last_activity'])) {
            $inactive_time = $current_time - $_SESSION['last_activity'];
            
            if ($inactive_time > 1800) { // 30 minutes
                // Log timeout
                if (file_exists(__DIR__ . '/config/database.php')) {
                    require_once __DIR__ . '/config/database.php';
                    
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO session_logs (user_id, session_id, action, ip_address, user_agent)
                            VALUES (?, ?, 'timeout', ?, ?)
                        ");
                        $stmt->execute([
                            $_SESSION['user_id'],
                            session_id(),
                            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                            $_SERVER['HTTP_USER_AGENT'] ?? ''
                        ]);
                    } catch (Exception $e) {
                        error_log("Session timeout logging failed: " . $e->getMessage());
                    }
                }
                
                // Destroy session
                session_unset();
                session_destroy();
                
                // Redirect to login with timeout message
                header('Location: /login.php?timeout=1');
                exit();
            }
        }
        
        // Update last activity time
        $_SESSION['last_activity'] = $current_time;
        
        // Check session lifetime (120 minutes = 7200 seconds)
        if (isset($_SESSION['login_time'])) {
            $session_duration = $current_time - $_SESSION['login_time'];
            
            if ($session_duration > 7200) { // 120 minutes
                // Log session expiration
                if (file_exists(__DIR__ . '/config/database.php')) {
                    require_once __DIR__ . '/config/database.php';
                    
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO session_logs (user_id, session_id, action, ip_address, user_agent)
                            VALUES (?, ?, 'session_expired', ?, ?)
                        ");
                        $stmt->execute([
                            $_SESSION['user_id'],
                            session_id(),
                            $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
                            $_SERVER['HTTP_USER_AGENT'] ?? ''
                        ]);
                    } catch (Exception $e) {
                        error_log("Session expiration logging failed: " . $e->getMessage());
                    }
                }
                
                // Destroy session
                session_unset();
                session_destroy();
                
                // Redirect to login
                header('Location: /login.php?expired=1');
                exit();
            }
        }
        
        // Session hijacking detection
        if (isset($_SESSION['user_agent']) && isset($_SESSION['ip_address'])) {
            $current_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
            $current_ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            
            // Check user agent
            if ($_SESSION['user_agent'] !== $current_user_agent) {
                // Log security event
                if (file_exists(__DIR__ . '/config/database.php')) {
                    require_once __DIR__ . '/config/database.php';
                    
                    try {
                        $stmt = $db->prepare("
                            INSERT INTO security_logs (event_type, user_id, ip_address, user_agent, details, severity)
                            VALUES ('session_hijacking_detected', ?, ?, ?, ?, 'critical')
                        ");
                        $stmt->execute([
                            $_SESSION['user_id'],
                            $current_ip,
                            $current_user_agent,
                            json_encode([
                                'expected_user_agent' => $_SESSION['user_agent'],
                                'received_user_agent' => $current_user_agent
                            ])
                        ]);
                    } catch (Exception $e) {
                        error_log("Security logging failed: " . $e->getMessage());
                    }
                }
                
                // Destroy session
                session_unset();
                session_destroy();
                
                // Redirect to login
                header('Location: /login.php?security=1');
                exit();
            }
        }
    }
}
