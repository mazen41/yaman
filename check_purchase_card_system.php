<?php
require_once 'config/database.php';

echo "<h2>Checking Purchase Card System</h2>";
echo "<hr>";

try {
    // Check purchase_cards table
    echo "<h3>1. Purchase Cards Table:</h3>";
    $cards = $db->query("SELECT id, card_number, card_name, balance FROM purchase_cards LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Card Number</th><th>Card Name</th><th>Balance</th></tr>";
    foreach ($cards as $card) {
        echo "<tr>";
        echo "<td>{$card['id']}</td>";
        echo "<td>{$card['card_number']}</td>";
        echo "<td>{$card['card_name']}</td>";
        echo "<td>" . number_format($card['balance'], 2) . " SAR</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<hr>";
    
    // Check if purchase_card_transactions table exists
    echo "<h3>2. Purchase Card Transactions Table:</h3>";
    $tables = $db->query("SHOW TABLES LIKE 'purchase_card_transactions'")->fetchAll();
    
    if (empty($tables)) {
        echo "<p style='color:red;'><strong>❌ Table 'purchase_card_transactions' does NOT exist!</strong></p>";
        echo "<p>Creating table...</p>";
        
        $db->exec("
            CREATE TABLE IF NOT EXISTS `purchase_card_transactions` (
              `id` int NOT NULL AUTO_INCREMENT,
              `purchase_card_id` int NOT NULL,
              `transaction_type` enum('debit','credit','purchase','refund','transfer_in','transfer_out') NOT NULL,
              `amount` decimal(15,2) NOT NULL,
              `balance_before` decimal(15,2) NOT NULL,
              `balance_after` decimal(15,2) NOT NULL,
              `reference_type` varchar(50) DEFAULT NULL COMMENT 'basket, order, transfer, etc',
              `reference_id` int DEFAULT NULL,
              `description` text,
              `created_by` int DEFAULT NULL,
              `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
              PRIMARY KEY (`id`),
              KEY `purchase_card_id` (`purchase_card_id`),
              KEY `reference` (`reference_type`,`reference_id`),
              CONSTRAINT `purchase_card_transactions_ibfk_1` FOREIGN KEY (`purchase_card_id`) REFERENCES `purchase_cards` (`id`) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        echo "<p style='color:green;'><strong>✅ Table created successfully!</strong></p>";
    } else {
        echo "<p style='color:green;'><strong>✅ Table exists</strong></p>";
        
        // Show sample transactions
        $transactions = $db->query("
            SELECT pct.*, pc.card_name, pc.card_number
            FROM purchase_card_transactions pct
            JOIN purchase_cards pc ON pct.purchase_card_id = pc.id
            ORDER BY pct.created_at DESC
            LIMIT 10
        ")->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($transactions)) {
            echo "<p>No transactions found</p>";
        } else {
            echo "<table border='1' cellpadding='5'>";
            echo "<tr><th>ID</th><th>Card</th><th>Type</th><th>Amount</th><th>Balance Before</th><th>Balance After</th><th>Reference</th><th>Date</th></tr>";
            foreach ($transactions as $txn) {
                echo "<tr>";
                echo "<td>{$txn['id']}</td>";
                echo "<td>{$txn['card_name']}</td>";
                echo "<td>{$txn['transaction_type']}</td>";
                echo "<td>" . number_format($txn['amount'], 2) . "</td>";
                echo "<td>" . number_format($txn['balance_before'], 2) . "</td>";
                echo "<td>" . number_format($txn['balance_after'], 2) . "</td>";
                echo "<td>" . ($txn['reference_type'] ?? 'N/A') . " #" . ($txn['reference_id'] ?? '') . "</td>";
                echo "<td>{$txn['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
    echo "<hr>";
    
    // Check baskets with purchase card payment
    echo "<h3>3. Baskets Using Purchase Cards:</h3>";
    $baskets = $db->query("
        SELECT id, basket_code, basket_name, payment_source_type, payment_source_id, final_amount, status
        FROM purchase_baskets
        WHERE payment_source_type = 'purchase_card'
        ORDER BY created_at DESC
        LIMIT 10
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($baskets)) {
        echo "<p>No baskets found using purchase cards</p>";
    } else {
        echo "<table border='1' cellpadding='5'>";
        echo "<tr><th>ID</th><th>Code</th><th>Name</th><th>Card ID</th><th>Amount</th><th>Status</th></tr>";
        foreach ($baskets as $basket) {
            echo "<tr>";
            echo "<td>{$basket['id']}</td>";
            echo "<td>{$basket['basket_code']}</td>";
            echo "<td>" . ($basket['basket_name'] ?? 'N/A') . "</td>";
            echo "<td>{$basket['payment_source_id']}</td>";
            echo "<td>" . number_format($basket['final_amount'], 2) . "</td>";
            echo "<td>{$basket['status']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "<p style='color:red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>
