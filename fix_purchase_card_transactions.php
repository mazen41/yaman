<?php
require_once 'config/database.php';

echo "<h2>Fixing Purchase Card Transactions Table</h2>";
echo "<hr>";

try {
    // Check current table structure
    echo "<h3>Current Table Structure:</h3>";
    $columns = $db->query("SHOW COLUMNS FROM purchase_card_transactions")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check if purchase_card_id column exists
    $has_purchase_card_id = false;
    $has_card_id = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'purchase_card_id') $has_purchase_card_id = true;
        if ($col['Field'] === 'card_id') $has_card_id = true;
    }
    
    echo "<hr>";
    echo "<h3>Column Check:</h3>";
    echo "<p><strong>'purchase_card_id':</strong> " . ($has_purchase_card_id ? '✅ EXISTS' : '❌ MISSING') . "</p>";
    echo "<p><strong>'card_id':</strong> " . ($has_card_id ? '✅ EXISTS' : '❌ MISSING') . "</p>";
    
    // Fix: Add purchase_card_id column if missing
    if (!$has_purchase_card_id && $has_card_id) {
        echo "<hr>";
        echo "<h3>Fixing: Renaming 'card_id' to 'purchase_card_id'...</h3>";
        $db->exec("ALTER TABLE purchase_card_transactions CHANGE card_id purchase_card_id INT NOT NULL");
        echo "<p style='color:green;'><strong>✅ Column renamed successfully!</strong></p>";
    } elseif (!$has_purchase_card_id && !$has_card_id) {
        echo "<hr>";
        echo "<h3>Fixing: Adding 'purchase_card_id' column...</h3>";
        $db->exec("ALTER TABLE purchase_card_transactions ADD COLUMN purchase_card_id INT NOT NULL AFTER id");
        echo "<p style='color:green;'><strong>✅ Column added successfully!</strong></p>";
    }
    
    // Show updated structure
    echo "<hr>";
    echo "<h3>Updated Table Structure:</h3>";
    $columns_new = $db->query("SHOW COLUMNS FROM purchase_card_transactions")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
    foreach ($columns_new as $col) {
        echo "<tr>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>{$col['Key']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Now try to fetch transactions
    echo "<hr>";
    echo "<h3>Sample Transactions:</h3>";
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
    
    echo "<hr>";
    echo "<h3 style='color:green;'>✅ All Fixed! You can now use the purchase card system.</h3>";
    
} catch (PDOException $e) {
    echo "<p style='color:red;'><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>
