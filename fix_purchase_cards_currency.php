<?php
// Temporary script to normalize currency for purchase cards
// Run once via browser, then DELETE this file for security.

require_once 'config/database.php';

header('Content-Type: text/plain; charset=utf-8');

echo "== Fix purchase_cards currency ==\n\n";

try {
    // 1) Ensure column exists; if not, create it
    $stmt = $db->query("SHOW COLUMNS FROM purchase_cards LIKE 'currency'");
    $col = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$col) {
        echo "Column 'currency' does NOT exist on purchase_cards. Creating it...\n";
        $db->exec("ALTER TABLE purchase_cards ADD COLUMN currency VARCHAR(3) NOT NULL DEFAULT 'YER'");
        echo "Column created with default 'YER'.\n";
    }

    // 2) Update invalid / empty currencies to YER
    $update = $db->prepare("UPDATE purchase_cards SET currency = 'YER' WHERE currency IS NULL OR currency = '' OR currency NOT IN ('YER','SAR')");
    $affected = $update->execute();

    echo "Updated existing rows to YER for NULL/empty/invalid values.\n";

    // 3) Set default to YER (ignore error if already so)
    try {
        $db->exec("ALTER TABLE purchase_cards MODIFY COLUMN currency VARCHAR(3) NOT NULL DEFAULT 'YER'");
        echo "Column default changed to YER.\n";
    } catch (PDOException $e) {
        echo "Could not modify column default (maybe already YER): " . $e->getMessage() . "\n";
    }

    echo "\nDONE. Now open modules/purchase_cards/index.php?currency=YER to verify.\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
