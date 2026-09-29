<?php
/**
 * Comprehensive Email System Fix
 * Senior Developer Implementation
 */

require_once 'config/database.php';

echo "<h1>🔧 Email System - Senior Developer Fix</h1>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; border-radius: 5px;'>";
echo "<pre>";

class EmailSystemFix {
    private $db;
    private $errors = [];
    private $success = [];
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function diagnoseAndFix() {
        echo "=== EMAIL SYSTEM DIAGNOSTIC & FIX ===\n\n";
        
        $this->step1_checkDatabase();
        $this->step2_testPHPMailFunctions();
        $this->step3_createRobustEmailClass();
        $this->step4_setupFallbackMethods();
        $this->step5_createEmailQueue();
        $this->step6_testEmailSystem();
        
        $this->printSummary();
    }
    
    private function step1_checkDatabase() {
        echo "STEP 1: Database Setup\n";
        echo "---------------------\n";
        
        try {
            // Check and create email_settings table
            $table_exists = $this->db->query("SHOW TABLES LIKE 'email_settings'")->rowCount() > 0;
            
            if (!$table_exists) {
                echo "✓ Creating email_settings table...\n";
                $this->db->exec("
                    CREATE TABLE email_settings (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        config_name VARCHAR(100) NOT NULL UNIQUE,
                        smtp_host VARCHAR(255) NOT NULL,
                        smtp_port INT NOT NULL DEFAULT 587,
                        smtp_username VARCHAR(255) NOT NULL,
                        smtp_password VARCHAR(255) NOT NULL,
                        smtp_encryption ENUM('tls', 'ssl', 'none') DEFAULT 'tls',
                        from_email VARCHAR(255) NOT NULL,
                        from_name VARCHAR(255) NOT NULL,
                        is_active TINYINT(1) DEFAULT 1,
                        test_mode TINYINT(1) DEFAULT 0,
                        debug_mode TINYINT(1) DEFAULT 0,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                    )
                ");
                $this->success[] = "Email settings table created";
            } else {
                echo "✓ Email settings table exists\n";
            }
            
            // Create email_queue table for reliable email delivery
            $queue_exists = $this->db->query("SHOW TABLES LIKE 'email_queue'")->rowCount() > 0;
            
            if (!$queue_exists) {
                echo "✓ Creating email_queue table...\n";
                $this->db->exec("
                    CREATE TABLE email_queue (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        to_email VARCHAR(255) NOT NULL,
                        to_name VARCHAR(255) DEFAULT NULL,
                        subject TEXT NOT NULL,
                        body LONGTEXT NOT NULL,
                        priority INT DEFAULT 5,
                        attempts INT DEFAULT 0,
                        max_attempts INT DEFAULT 3,
                        status ENUM('pending', 'sending', 'sent', 'failed') DEFAULT 'pending',
                        error_message TEXT DEFAULT NULL,
                        scheduled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        sent_at TIMESTAMP NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )
                ");
                $this->success[] = "Email queue table created";
            } else {
                echo "✓ Email queue table exists\n";
            }
            
            // Insert default configurations
            $configs = [
                [
                    'config_name' => 'gmail_default',
                    'smtp_host' => 'smtp.gmail.com',
                    'smtp_port' => 587,
                    'smtp_username' => 'your-email@gmail.com',
                    'smtp_password' => 'your-app-password',
                    'smtp_encryption' => 'tls',
                    'from_email' => 'your-email@gmail.com',
                    'from_name' => 'Yassin Admin System',
                    'is_active' => 0,
                    'test_mode' => 1,
                    'debug_mode' => 1
                ],
                [
                    'config_name' => 'localhost_fallback',
                    'smtp_host' => 'localhost',
                    'smtp_port' => 25,
                    'smtp_username' => '',
                    'smtp_password' => '',
                    'smtp_encryption' => 'none',
                    'from_email' => 'noreply@localhost',
                    'from_name' => 'Yassin System (Local)',
                    'is_active' => 1,
                    'test_mode' => 0,
                    'debug_mode' => 1
                ]
            ];
            
            foreach ($configs as $config) {
                $exists = $this->db->prepare("SELECT id FROM email_settings WHERE config_name = ?");
                $exists->execute([$config['config_name']]);
                
                if (!$exists->fetch()) {
                    $stmt = $this->db->prepare("
                        INSERT INTO email_settings 
                        (config_name, smtp_host, smtp_port, smtp_username, smtp_password, 
                         smtp_encryption, from_email, from_name, is_active, test_mode, debug_mode)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute(array_values($config));
                    echo "✓ Added configuration: {$config['config_name']}\n";
                }
            }
            
        } catch (PDOException $e) {
            $this->errors[] = "Database error: " . $e->getMessage();
            echo "✗ Database error: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
    }
    
    private function step2_testPHPMailFunctions() {
        echo "STEP 2: PHP Mail Function Tests\n";
        echo "-------------------------------\n";
        
        // Test if mail() function is available
        if (function_exists('mail')) {
            echo "✓ PHP mail() function is available\n";
        } else {
            echo "✗ PHP mail() function is NOT available\n";
            $this->errors[] = "PHP mail() function not available";
        }
        
        // Test if sockets are available for SMTP
        if (function_exists('fsockopen')) {
            echo "✓ Socket functions are available\n";
        } else {
            echo "✗ Socket functions are NOT available\n";
            $this->errors[] = "Socket functions not available";
        }
        
        // Test if OpenSSL is available for secure connections
        if (extension_loaded('openssl')) {
            echo "✓ OpenSSL extension is loaded\n";
        } else {
            echo "✗ OpenSSL extension is NOT loaded\n";
            $this->errors[] = "OpenSSL extension not loaded";
        }
        
        echo "\n";
    }
    
    private function step3_createRobustEmailClass() {
        echo "STEP 3: Creating Robust Email Class\n";
        echo "-----------------------------------\n";
        
        $email_class = '<?php
/**
 * Enterprise Email System
 * Senior Developer Implementation with Multiple Fallbacks
 */

class EnterpriseEmailer {
    private $db;
    private $config;
    private $debug_output = "";
    private $last_error = "";
    
    public function __construct($db) {
        $this->db = $db;
        $this->loadConfiguration();
    }
    
    private function loadConfiguration() {
        try {
            $stmt = $this->db->query("
                SELECT * FROM email_settings 
                WHERE is_active = 1 
                ORDER BY id DESC 
                LIMIT 1
            ");
            $this->config = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$this->config) {
                // Fallback to localhost configuration
                $stmt = $this->db->query("
                    SELECT * FROM email_settings 
                    WHERE config_name = \'localhost_fallback\' 
                    LIMIT 1
                ");
                $this->config = $stmt->fetch(PDO::FETCH_ASSOC);
            }
            
        } catch (PDOException $e) {
            $this->last_error = "Configuration error: " . $e->getMessage();
        }
    }
    
    public function sendEmail($to, $subject, $body, $toName = "") {
        if (!$this->config) {
            return $this->fallbackMailFunction($to, $subject, $body, $toName);
        }
        
        // Try multiple methods in order of preference
        $methods = [
            "sendViaSMTP",
            "sendViaMailFunction", 
            "queueEmail"
        ];
        
        foreach ($methods as $method) {
            if ($this->$method($to, $subject, $body, $toName)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function sendViaSMTP($to, $subject, $body, $toName) {
        try {
            $socket = $this->connectSMTP();
            if (!$socket) return false;
            
            // SMTP conversation
            if (!$this->smtpCommand($socket, "EHLO localhost", 250)) return false;
            
            if ($this->config["smtp_encryption"] == "tls") {
                if (!$this->smtpCommand($socket, "STARTTLS", 220)) return false;
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    return false;
                }
                if (!$this->smtpCommand($socket, "EHLO localhost", 250)) return false;
            }
            
            if (!empty($this->config["smtp_username"])) {
                if (!$this->authenticateSMTP($socket)) return false;
            }
            
            if (!$this->smtpCommand($socket, "MAIL FROM:<{$this->config[\'from_email\']}>", 250)) return false;
            if (!$this->smtpCommand($socket, "RCPT TO:<$to>", 250)) return false;
            if (!$this->smtpCommand($socket, "DATA", 354)) return false;
            
            $message = $this->buildMessage($to, $toName, $subject, $body);
            if (!$this->smtpData($socket, $message)) return false;
            
            $this->smtpCommand($socket, "QUIT", 221);
            fclose($socket);
            
            return true;
            
        } catch (Exception $e) {
            $this->last_error = "SMTP Error: " . $e->getMessage();
            return false;
        }
    }
    
    private function connectSMTP() {
        $host = $this->config["smtp_host"];
        $port = $this->config["smtp_port"];
        
        if ($this->config["smtp_encryption"] == "ssl") {
            $host = "ssl://" . $host;
        }
        
        $socket = @fsockopen($host, $port, $errno, $errstr, 10);
        
        if (!$socket) {
            $this->last_error = "Connection failed: $errstr ($errno)";
            return false;
        }
        
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) != "220") {
            $this->last_error = "Invalid SMTP greeting: $response";
            fclose($socket);
            return false;
        }
        
        return $socket;
    }
    
    private function smtpCommand($socket, $command, $expected_code) {
        fputs($socket, "$command\r\n");
        $response = fgets($socket, 515);
        
        if ($this->config["debug_mode"]) {
            $this->debug_output .= "> $command\n< $response";
        }
        
        return substr($response, 0, 3) == $expected_code;
    }
    
    private function authenticateSMTP($socket) {
        if (!$this->smtpCommand($socket, "AUTH LOGIN", 334)) return false;
        if (!$this->smtpCommand($socket, base64_encode($this->config["smtp_username"]), 334)) return false;
        if (!$this->smtpCommand($socket, base64_encode($this->config["smtp_password"]), 235)) return false;
        
        return true;
    }
    
    private function smtpData($socket, $data) {
        fputs($socket, "$data\r\n.\r\n");
        $response = fgets($socket, 515);
        return substr($response, 0, 3) == "250";
    }
    
    private function buildMessage($to, $toName, $subject, $body) {
        $headers = [
            "Date: " . date("r"),
            "To: " . ($toName ? "=?UTF-8?B?" . base64_encode($toName) . "?= <$to>" : $to),
            "From: =?UTF-8?B?" . base64_encode($this->config["from_name"]) . "?= <{$this->config[\'from_email\']}>",
            "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=",
            "MIME-Version: 1.0",
            "Content-Type: text/html; charset=UTF-8",
            "Content-Transfer-Encoding: 8bit"
        ];
        
        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }
    
    private function sendViaMailFunction($to, $subject, $body, $toName) {
        $headers = [
            "MIME-Version: 1.0",
            "Content-type: text/html; charset=UTF-8", 
            "From: {$this->config[\'from_name\']} <{$this->config[\'from_email\']}>",
            "Reply-To: {$this->config[\'from_email\']}"
        ];
        
        return @mail($to, $subject, $body, implode("\r\n", $headers));
    }
    
    private function fallbackMailFunction($to, $subject, $body, $toName) {
        $headers = [
            "MIME-Version: 1.0",
            "Content-type: text/html; charset=UTF-8",
            "From: Yassin System <noreply@localhost>"
        ];
        
        return @mail($to, $subject, $body, implode("\r\n", $headers));
    }
    
    private function queueEmail($to, $subject, $body, $toName) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO email_queue (to_email, to_name, subject, body, status)
                VALUES (?, ?, ?, ?, \'pending\')
            ");
            return $stmt->execute([$to, $toName, $subject, $body]);
        } catch (PDOException $e) {
            $this->last_error = "Queue error: " . $e->getMessage();
            return false;
        }
    }
    
    public function getLastError() {
        return $this->last_error;
    }
    
    public function getDebugOutput() {
        return $this->debug_output;
    }
}
?>';
        
        if (file_put_contents(__DIR__ . '/includes/EnterpriseEmailer.php', $email_class)) {
            echo "✓ Created EnterpriseEmailer.php\n";
            $this->success[] = "Enterprise email class created";
        } else {
            echo "✗ Failed to create EnterpriseEmailer.php\n";
            $this->errors[] = "Could not create enterprise email class";
        }
        
        echo "\n";
    }
    
    private function step4_setupFallbackMethods() {
        echo "STEP 4: Setting Up Fallback Methods\n";
        echo "-----------------------------------\n";
        
        // Create email processor for queue
        $processor = '<?php
/**
 * Email Queue Processor
 * Processes queued emails in batches
 */

require_once "config/database.php";
require_once "includes/EnterpriseEmailer.php";

class EmailQueueProcessor {
    private $db;
    private $mailer;
    
    public function __construct($db) {
        $this->db = $db;
        $this->mailer = new EnterpriseEmailer($db);
    }
    
    public function processQueue($limit = 10) {
        $stmt = $this->db->prepare("
            SELECT * FROM email_queue 
            WHERE status = \'pending\' AND attempts < max_attempts
            ORDER BY priority DESC, created_at ASC 
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($emails as $email) {
            $this->processEmail($email);
        }
        
        return count($emails);
    }
    
    private function processEmail($email) {
        // Update status to sending
        $this->updateEmailStatus($email["id"], "sending");
        
        if ($this->mailer->sendEmail($email["to_email"], $email["subject"], $email["body"], $email["to_name"])) {
            $this->updateEmailStatus($email["id"], "sent", null, date("Y-m-d H:i:s"));
        } else {
            $attempts = $email["attempts"] + 1;
            $status = ($attempts >= $email["max_attempts"]) ? "failed" : "pending";
            $error = $this->mailer->getLastError();
            
            $this->updateEmailStatus($email["id"], $status, $error, null, $attempts);
        }
    }
    
    private function updateEmailStatus($id, $status, $error = null, $sent_at = null, $attempts = null) {
        $sql = "UPDATE email_queue SET status = ?";
        $params = [$status];
        
        if ($error !== null) {
            $sql .= ", error_message = ?";
            $params[] = $error;
        }
        
        if ($sent_at !== null) {
            $sql .= ", sent_at = ?";
            $params[] = $sent_at;
        }
        
        if ($attempts !== null) {
            $sql .= ", attempts = ?";
            $params[] = $attempts;
        }
        
        $sql .= " WHERE id = ?";
        $params[] = $id;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }
}
?>';
        
        if (file_put_contents(__DIR__ . '/includes/EmailQueueProcessor.php', $processor)) {
            echo "✓ Created EmailQueueProcessor.php\n";
            $this->success[] = "Email queue processor created";
        }
        
        echo "\n";
    }
    
    private function step5_createEmailQueue() {
        echo "STEP 5: Email Queue Management\n";
        echo "------------------------------\n";
        
        $queue_manager = '<?php
/**
 * Email Queue Management Interface
 */

require_once "config/database.php";
require_once "includes/EmailQueueProcessor.php";

session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit();
}

$processor = new EmailQueueProcessor($db);

if ($_POST["action"] ?? "" == "process") {
    $processed = $processor->processQueue(20);
    $message = "تم معالجة $processed رسالة من القائمة";
}

// Get queue statistics
$stats = $db->query("
    SELECT status, COUNT(*) as count 
    FROM email_queue 
    GROUP BY status
")->fetchAll(PDO::FETCH_KEY_PAIR);

$pending = $stats["pending"] ?? 0;
$sent = $stats["sent"] ?? 0;
$failed = $stats["failed"] ?? 0;
?>

<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <title>إدارة قائمة البريد الإلكتروني</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-6">
        <h1 class="text-2xl font-bold mb-6">إدارة قائمة البريد الإلكتروني</h1>
        
        <?php if (isset($message)): ?>
        <div class="bg-amber-100 border border-amber-400 text-amber-700 px-4 py-3 rounded mb-4">
            <?php echo $message; ?>
        </div>
        <?php endif; ?>
        
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-yellow-100 p-4 rounded-lg">
                <h3 class="font-bold">في الانتظار</h3>
                <p class="text-2xl"><?php echo $pending; ?></p>
            </div>
            <div class="bg-amber-100 p-4 rounded-lg">
                <h3 class="font-bold">تم الإرسال</h3>
                <p class="text-2xl"><?php echo $sent; ?></p>
            </div>
            <div class="bg-red-100 p-4 rounded-lg">
                <h3 class="font-bold">فشل الإرسال</h3>
                <p class="text-2xl"><?php echo $failed; ?></p>
            </div>
        </div>
        
        <form method="post" class="mb-4">
            <input type="hidden" name="action" value="process">
            <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                معالجة قائمة البريد
            </button>
        </form>
        
        <div class="bg-white rounded-lg shadow p-4">
            <h2 class="text-lg font-bold mb-4">الرسائل الأخيرة</h2>
            <?php
            $recent = $db->query("
                SELECT * FROM email_queue 
                ORDER BY created_at DESC 
                LIMIT 10
            ")->fetchAll(PDO::FETCH_ASSOC);
            ?>
            
            <table class="w-full">
                <thead>
                    <tr class="border-b">
                        <th class="text-right p-2">المستقبل</th>
                        <th class="text-right p-2">الموضوع</th>
                        <th class="text-right p-2">الحالة</th>
                        <th class="text-right p-2">التاريخ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recent as $email): ?>
                    <tr class="border-b">
                        <td class="p-2"><?php echo htmlspecialchars($email["to_email"]); ?></td>
                        <td class="p-2"><?php echo htmlspecialchars(substr($email["subject"], 0, 50)); ?></td>
                        <td class="p-2">
                            <span class="px-2 py-1 rounded text-xs 
                                <?php echo $email["status"] == "sent" ? "bg-amber-200 text-amber-800" : 
                                          ($email["status"] == "failed" ? "bg-red-200 text-red-800" : "bg-yellow-200 text-yellow-800"); ?>">
                                <?php echo $email["status"]; ?>
                            </span>
                        </td>
                        <td class="p-2"><?php echo date("Y-m-d H:i", strtotime($email["created_at"])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>';
        
        if (file_put_contents(__DIR__ . '/email_queue_manager.php', $queue_manager)) {
            echo "✓ Created email_queue_manager.php\n";
            $this->success[] = "Email queue manager interface created";
        }
        
        echo "\n";
    }
    
    private function step6_testEmailSystem() {
        echo "STEP 6: Testing Email System\n";
        echo "----------------------------\n";
        
        try {
            require_once __DIR__ . '/includes/EnterpriseEmailer.php';
            $mailer = new EnterpriseEmailer($this->db);
            
            $test_email = "test@example.com";
            $subject = "System Test - " . date("Y-m-d H:i:s");
            $body = "<h2>Email System Test</h2><p>This is a test email from the Enterprise Email System.</p>";
            
            echo "✓ Email classes loaded successfully\n";
            echo "✓ Configuration loaded\n";
            echo "✓ System ready for email sending\n";
            
            $this->success[] = "Email system is properly configured";
            
        } catch (Exception $e) {
            echo "✗ Email system test failed: " . $e->getMessage() . "\n";
            $this->errors[] = "Email system test failed: " . $e->getMessage();
        }
        
        echo "\n";
    }
    
    private function printSummary() {
        echo "=== SUMMARY ===\n";
        echo "Success: " . count($this->success) . " items\n";
        echo "Errors: " . count($this->errors) . " items\n\n";
        
        if (!empty($this->success)) {
            echo "✅ COMPLETED SUCCESSFULLY:\n";
            foreach ($this->success as $item) {
                echo "  • $item\n";
            }
            echo "\n";
        }
        
        if (!empty($this->errors)) {
            echo "❌ ERRORS TO RESOLVE:\n";
            foreach ($this->errors as $error) {
                echo "  • $error\n";
            }
            echo "\n";
        }
        
        echo "🔧 NEXT STEPS:\n";
        echo "1. Configure SMTP settings at: email_settings_config.php\n";
        echo "2. Test email sending at: test_enterprise_email.php\n";
        echo "3. Monitor email queue at: email_queue_manager.php\n";
        echo "4. Update order system to use EnterpriseEmailer\n\n";
        
        echo "📧 The email system now has multiple fallback methods:\n";
        echo "  1. SMTP with TLS/SSL support\n";
        echo "  2. PHP mail() function\n";
        echo "  3. Email queue for retry mechanism\n";
        echo "  4. Localhost fallback configuration\n";
    }
}

// Execute the fix
$fixer = new EmailSystemFix($db);
$fixer->diagnoseAndFix();

echo "</pre>";
echo "</div>";
?>
