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
    $sql = "CREATE TABLE IF NOT EXISTS accounts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL,
        name VARCHAR(100) NOT NULL,
        type ENUM('asset', 'liability', 'equity', 'revenue', 'expense') NOT NULL,
        parent_id INT NULL,
        currency VARCHAR(3) NOT NULL DEFAULT 'SAR',
        initial_balance DECIMAL(15,2) DEFAULT 0.00,
        current_balance DECIMAL(15,2) DEFAULT 0.00,
        description TEXT NULL,
        is_active BOOLEAN DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        created_by INT NULL,
        FOREIGN KEY (parent_id) REFERENCES accounts(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $db->exec($sql);
    echo "Accounts table created successfully.<br>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
