<?php
require_once __DIR__ . '/config/database.php';

try {
    $stmt = $db->query("SHOW COLUMNS FROM customer_types LIKE 'is_active'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$col) {
        $db->exec("ALTER TABLE customer_types ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1 AFTER discount_percentage");
        echo "Column is_active added to customer_types table.\n";
    } else {
        echo "Column is_active already exists on customer_types table.\n";
    }
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
