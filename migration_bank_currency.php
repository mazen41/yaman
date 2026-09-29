<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (file_exists('config/database.php')) {
    require_once 'config/database.php';
} elseif (file_exists('database_config.php')) {
    require_once 'database_config.php';
} else {
    die("DB config not found");
}

try {
    // Check if column exists
    $stmt = $db->query("SHOW COLUMNS FROM bank_accounts LIKE 'currency'");
    if ($stmt->rowCount() == 0) {
        $db->exec("ALTER TABLE bank_accounts ADD COLUMN currency VARCHAR(3) NOT NULL DEFAULT 'SAR' AFTER account_number");
        echo "Added currency column to bank_accounts table.<br>";
    } else {
        echo "Currency column already exists.<br>";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
