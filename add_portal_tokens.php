<?php
/**
 * Add portal_token column and generate unique tokens for all customers
 */
require_once 'config/database.php';

try {
    echo "<h2>Step 1: Adding portal_token column...</h2>";
    
    // Add portal_token column
    $db->exec("ALTER TABLE customers ADD COLUMN portal_token VARCHAR(64) UNIQUE AFTER id");
    echo "<p style='color: green;'>✅ portal_token column added successfully!</p>";
    
    echo "<h2>Step 2: Generating unique tokens for existing customers...</h2>";
    
    // Get all customers
    $stmt = $db->query("SELECT id, customer_code, name FROM customers WHERE portal_token IS NULL");
    $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $update_stmt = $db->prepare("UPDATE customers SET portal_token = ? WHERE id = ?");
    
    $count = 0;
    foreach ($customers as $customer) {
        // Generate unique token
        $token = bin2hex(random_bytes(32)); // 64 character hex string
        $update_stmt->execute([$token, $customer['id']]);
        $count++;
        
        echo "<p>✅ Generated token for: <strong>" . htmlspecialchars($customer['name']) . "</strong> (ID: {$customer['id']})</p>";
        echo "<p style='margin-left: 20px; color: #666;'>Portal URL: <code>https://taksoride.com/customer_portal/portal.php?token={$token}</code></p>";
    }
    
    echo "<hr>";
    echo "<h2 style='color: green;'>✅ Migration Complete!</h2>";
    echo "<p><strong>Total customers updated:</strong> {$count}</p>";
    echo "<p><strong>Next steps:</strong></p>";
    echo "<ol>";
    echo "<li>Copy the portal URLs and send them to customers</li>";
    echo "<li>Customers can access their portal without login using their unique URL</li>";
    echo "<li>You can find each customer's portal URL in the admin panel</li>";
    echo "</ol>";
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "<p style='color: orange;'>⚠️ Column already exists. Proceeding to generate tokens...</p>";
        
        // Just generate tokens for customers without them
        $stmt = $db->query("SELECT id, customer_code, name FROM customers WHERE portal_token IS NULL OR portal_token = ''");
        $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($customers) > 0) {
            $update_stmt = $db->prepare("UPDATE customers SET portal_token = ? WHERE id = ?");
            
            foreach ($customers as $customer) {
                $token = bin2hex(random_bytes(32));
                $update_stmt->execute([$token, $customer['id']]);
                echo "<p>✅ Generated token for: <strong>" . htmlspecialchars($customer['name']) . "</strong></p>";
            }
        } else {
            echo "<p style='color: green;'>✅ All customers already have tokens!</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    }
}
?>
