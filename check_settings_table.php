<?php
require_once 'config/database.php';

echo "<h2>Checking for settings table:</h2>";

try {
    // Check if settings table exists
    $stmt = $db->query("SHOW TABLES LIKE 'settings'");
    $exists = $stmt->rowCount() > 0;
    
    if ($exists) {
        echo "<p style='color: green;'>✅ settings table EXISTS</p>";
        
        // Show structure
        $stmt = $db->query("SHOW COLUMNS FROM settings");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<h3>Table structure:</h3><pre>";
        print_r($columns);
        echo "</pre>";
        
        // Show data
        $stmt = $db->query("SELECT * FROM settings LIMIT 5");
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<h3>Sample data:</h3><pre>";
        print_r($data);
        echo "</pre>";
    } else {
        echo "<p style='color: red;'>❌ settings table DOES NOT EXIST</p>";
        
        echo "<h3>Available tables:</h3>";
        $stmt = $db->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "<pre>";
        print_r($tables);
        echo "</pre>";
    }
} catch (PDOException $e) {
    echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
}
?>
