<?php
require_once 'config/database.php';

function getTableStructure($db, $tableName) {
    try {
        $stmt = $db->prepare("DESCRIBE $tableName");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return ["Error" => $e->getMessage()];
    }
}

function checkTableExists($db, $tableName) {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Tables to check
$tables = [
    'customers',
    'customer_orders',
    'order_items',
    'order_status_history',
    'customer_invoices',
    'customer_payments',
    'products',
    'notification_templates'
];

echo "<h1>Database Structure</h1>";

foreach ($tables as $table) {
    echo "<h2>Table: $table</h2>";
    
    if (checkTableExists($db, $table)) {
        echo "<pre>";
        print_r(getTableStructure($db, $table));
        echo "</pre>";
        
        // Get sample data
        try {
            $stmt = $db->prepare("SELECT * FROM $table LIMIT 1");
            $stmt->execute();
            $sample = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($sample) {
                echo "<h3>Sample Data:</h3>";
                echo "<pre>";
                print_r($sample);
                echo "</pre>";
            } else {
                echo "<p>No data in table.</p>";
            }
        } catch (PDOException $e) {
            echo "<p>Error getting sample data: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p>Table does not exist.</p>";
    }
    
    echo "<hr>";
}
?>
