<?php
require_once 'config/database.php';

echo "<h2>Purchase Cards Table Structure</h2><pre>";

try {
    // Show table structure
    $stmt = $db->query("DESCRIBE purchase_cards");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== COLUMNS ===\n";
    foreach ($columns as $col) {
        echo "{$col['Field']} - {$col['Type']}\n";
    }
    
    echo "\n=== SAMPLE DATA ===\n";
    $stmt = $db->query("SELECT * FROM purchase_cards LIMIT 1");
    $sample = $stmt->fetch(PDO::FETCH_ASSOC);
    print_r($sample);
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

echo "</pre>";
?>
