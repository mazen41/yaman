#!/usr/bin/php
<?php
/**
 * Session Cleanup Script
 * Run via cron every 5 minutes to clean expired sessions
 */

require_once '/home/taksoride-admin/htdocs/config/database.php';

echo "Starting session cleanup...\n";

try {
    // Get sessions that should be timed out (older than 30 minutes)
    $stmt = $db->query("
        SELECT sl.user_id, sl.session_id, sl.ip_address, sl.user_agent
        FROM session_logs sl
        WHERE sl.action = 'login'
        AND sl.created_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
        AND NOT EXISTS (
            SELECT 1 FROM session_logs sl2 
            WHERE sl2.session_id = sl.session_id 
            AND sl2.action IN ('logout', 'timeout', 'session_expired')
            AND sl2.created_at > sl.created_at
        )
    ");
    
    $expired_count = 0;
    
    while ($session = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Log timeout
        $log_stmt = $db->prepare("
            INSERT INTO session_logs (user_id, session_id, action, ip_address, user_agent)
            VALUES (?, ?, 'timeout', ?, ?)
        ");
        $log_stmt->execute([
            $session['user_id'],
            $session['session_id'],
            $session['ip_address'],
            $session['user_agent']
        ]);
        
        // Delete PHP session file
        $session_file = session_save_path() . '/sess_' . $session['session_id'];
        if (file_exists($session_file)) {
            unlink($session_file);
            echo "Deleted session file: sess_{$session['session_id']}\n";
        }
        
        $expired_count++;
    }
    
    echo "Expired $expired_count sessions\n";
    
    // Clean old auth tokens
    $db->exec("DELETE FROM auth_tokens WHERE expires_at < NOW()");
    echo "Cleaned expired auth tokens\n";
    
    // Clean old failed login attempts
    $db->exec("DELETE FROM failed_login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
    echo "Cleaned old failed login attempts\n";
    
    echo "Session cleanup completed successfully\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
