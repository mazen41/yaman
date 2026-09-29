<?php
session_start();
require_once 'config/database.php';

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Get form data
        $smtp_host = $_POST['smtp_host'] ?? '';
        $smtp_port = intval($_POST['smtp_port'] ?? 587);
        $smtp_username = $_POST['smtp_username'] ?? '';
        $smtp_password = $_POST['smtp_password'] ?? '';
        $smtp_encryption = $_POST['smtp_encryption'] ?? 'tls';
        $from_email = $_POST['from_email'] ?? '';
        $from_name = $_POST['from_name'] ?? '';
        
        // Validate required fields
        if (empty($smtp_host) || empty($smtp_username) || empty($smtp_password) || empty($from_email) || empty($from_name)) {
            throw new Exception("جميع الحقول مطلوبة");
        }
        
        // Check if email_settings table exists
        $table_check = $db->query("SHOW TABLES LIKE 'email_settings'");
        if ($table_check->rowCount() == 0) {
            // Create table if it doesn't exist
            $db->exec("CREATE TABLE email_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                smtp_host VARCHAR(255) NOT NULL,
                smtp_port INT NOT NULL DEFAULT 587,
                smtp_username VARCHAR(255) NOT NULL,
                smtp_password VARCHAR(255) NOT NULL,
                smtp_encryption ENUM('tls', 'ssl', 'none') DEFAULT 'tls',
                from_email VARCHAR(255) NOT NULL,
                from_name VARCHAR(255) NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )");
        }
        
        // Deactivate all existing settings
        $db->exec("UPDATE email_settings SET is_active = 0");
        
        // Insert new settings
        $stmt = $db->prepare("
            INSERT INTO email_settings 
            (smtp_host, smtp_port, smtp_username, smtp_password, smtp_encryption, from_email, from_name, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $stmt->execute([
            $smtp_host,
            $smtp_port,
            $smtp_username,
            $smtp_password,
            $smtp_encryption,
            $from_email,
            $from_name
        ]);
        
        $_SESSION['success_message'] = "تم حفظ إعدادات البريد الإلكتروني بنجاح";
        header("Location: ./setup/fix_email_settings.php");
        exit();
        
    } catch (Exception $e) {
        $_SESSION['error_message'] = "حدث خطأ: " . $e->getMessage();
        header("Location: fix_email_settings.php");
        exit();
    }
} else {
    // Not a POST request
    header("Location: fix_email_settings.php");
    exit();
}
?>
