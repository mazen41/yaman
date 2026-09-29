<?php
if (file_exists('config/database.php')) {
    require_once 'config/database.php';
} elseif (file_exists('database_config.php')) {
    require_once 'database_config.php';
} else {
    die("DB config not found");
}

try {
    $stmt = $db->query("SHOW COLUMNS FROM customer_payments LIKE 'payment_method'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($col);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
