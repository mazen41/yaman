<?php
require_once '../../config/database.php';

try {
    // Check what payment data exists
    $stmt = $db->query("SELECT id, basket_name, basket_code, payment_source_type, payment_source_id FROM purchase_baskets ORDER BY id DESC LIMIT 10");
    $baskets = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Purchase Baskets - Payment Source Data:</h2>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Basket Name</th><th>Code</th><th>Payment Type</th><th>Payment ID</th></tr>";
    
    foreach ($baskets as $basket) {
        echo "<tr>";
        echo "<td>" . $basket['id'] . "</td>";
        echo "<td>" . htmlspecialchars($basket['basket_name']) . "</td>";
        echo "<td>" . htmlspecialchars($basket['basket_code']) . "</td>";
        echo "<td>" . ($basket['payment_source_type'] ?: 'NULL') . "</td>";
        echo "<td>" . ($basket['payment_source_id'] ?: 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Check bank accounts
    echo "<h2>Bank Accounts:</h2>";
    $stmt = $db->query("SELECT id, bank_name, account_number FROM bank_accounts LIMIT 5");
    $banks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($banks);
    echo "</pre>";
    
    // Check purchase cards
    echo "<h2>Purchase Cards:</h2>";
    $stmt = $db->query("SELECT id, card_name FROM purchase_cards LIMIT 5");
    $cards = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "<pre>";
    print_r($cards);
    echo "</pre>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
