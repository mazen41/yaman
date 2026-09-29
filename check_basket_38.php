<?php
require_once 'config/database.php';

$basket_id = 38;

echo "<h2>Basket #38 Payment Source Debug:</h2>";

// Check basket record
$stmt = $db->prepare("SELECT id, basket_code, basket_name, payment_source_type, payment_source_id FROM purchase_baskets WHERE id = ?");
$stmt->execute([$basket_id]);
$basket = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Basket Payment Info:</h3>";
echo "<pre>";
print_r($basket);
echo "</pre>";

// Check if payment_source_type and payment_source_id exist
if (!empty($basket['payment_source_type']) && !empty($basket['payment_source_id'])) {
    echo "<h3>Payment Source Type: " . $basket['payment_source_type'] . "</h3>";
    echo "<h3>Payment Source ID: " . $basket['payment_source_id'] . "</h3>";
    
    if ($basket['payment_source_type'] == 'bank_account') {
        $stmt = $db->prepare("SELECT * FROM bank_accounts WHERE id = ?");
        $stmt->execute([$basket['payment_source_id']]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<h3>Bank Account Details:</h3>";
        echo "<pre>";
        print_r($payment);
        echo "</pre>";
    } elseif ($basket['payment_source_type'] == 'purchase_card') {
        $stmt = $db->prepare("SELECT * FROM purchase_cards WHERE id = ?");
        $stmt->execute([$basket['payment_source_id']]);
        $payment = $stmt->fetch(PDO::FETCH_ASSOC);
        
        echo "<h3>Purchase Card Details:</h3>";
        echo "<pre>";
        print_r($payment);
        echo "</pre>";
    }
} else {
    echo "<p style='color: red;'>⚠️ No payment_source_type or payment_source_id found!</p>";
}

// Also check show_baskets.php query
echo "<hr><h2>Testing show_baskets.php Query:</h2>";
$stmt = $db->prepare("
    SELECT
        pb.id,
        pb.basket_name,
        pb.payment_source_type,
        pb.payment_source_id,
        ba.bank_name,
        ba.account_number,
        pc.card_name
    FROM
        purchase_baskets pb
    LEFT JOIN
        bank_accounts ba ON pb.payment_source_type = 'bank_account' AND pb.payment_source_id = ba.id
    LEFT JOIN
        purchase_cards pc ON pb.payment_source_type = 'purchase_card' AND pb.payment_source_id = pc.id
    WHERE pb.id = ?
");
$stmt->execute([$basket_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Query Result:</h3>";
echo "<pre>";
print_r($result);
echo "</pre>";
?>
