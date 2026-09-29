<?php
require_once 'config/database.php';

try {
    echo "=== Current permissions table structure ===\n\n";
    
    $columns = $db->query("SHOW COLUMNS FROM permissions")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo "Column: {$col['Field']}\n";
        echo "  Type: {$col['Type']}\n";
        echo "  Null: {$col['Null']}\n";
        echo "  Default: {$col['Default']}\n\n";
    }
    
    echo "\n=== Sample data ===\n\n";
    $data = $db->query("SELECT * FROM permissions LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
    print_r($data);
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
