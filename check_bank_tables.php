<?php
require_once 'config/database.php';

echo "<h2>Bank-Related Tables</h2><pre>";

try {
    // Show all tables
    $stmt = $db->query("SHOW TABLES LIKE '%bank%'");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "=== TABLES WITH 'bank' ===\n";
    foreach ($tables as $table) {
        echo "- $table\n";
    }
    
    // If bank_accounts exists, show its structure
    if (in_array('bank_accounts', $tables)) {
        echo "\n=== BANK_ACCOUNTS STRUCTURE ===\n";
        $stmt = $db->query("DESCRIBE bank_accounts");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($columns as $col) {
            echo "{$col['Field']} - {$col['Type']}\n";
        }
        
        echo "\n=== SAMPLE DATA ===\n";
        $stmt = $db->query("SELECT * FROM bank_accounts LIMIT 1");
        $sample = $stmt->fetch(PDO::FETCH_ASSOC);
        print_r($sample);
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

echo "</pre>";
?>
