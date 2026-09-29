<?php
if (file_exists('config/database.php')) {
    require_once 'config/database.php';
} elseif (file_exists('database_config.php')) {
    require_once 'database_config.php';
} else {
    die("DB config not found");
}

// Check the actual file content on server
$file_path = '/home/taksoride-admin/htdocs/modules/payments/add.php';
if (file_exists($file_path)) {
    $content = file_get_contents($file_path);
    
    // Check for 'bank_transfer' occurrences
    $count = substr_count($content, 'bank_transfer');
    echo "Occurrences of 'bank_transfer': $count<br>";
    
    // Check for 'transfer' in payment_method context
    if (strpos($content, "value=\"transfer\"") !== false) {
        echo "✓ HTML option uses 'transfer'<br>";
    } else {
        echo "✗ HTML option still uses 'bank_transfer'<br>";
    }
    
    if (strpos($content, "=== 'transfer'") !== false) {
        echo "✓ JS uses 'transfer'<br>";
    } else {
        echo "✗ JS still uses 'bank_transfer'<br>";
    }
    
    // Show payment_method enum from DB
    $stmt = $db->query("SHOW COLUMNS FROM customer_payments LIKE 'payment_method'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<br>DB Column Type: " . $col['Type'];
    
} else {
    echo "File not found at: $file_path";
}
?>
