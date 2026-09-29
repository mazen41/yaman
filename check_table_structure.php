<?php
require_once '../../config/database.php';

try {
    $stmt = $db->query("DESCRIBE purchase_baskets");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>purchase_baskets Table Structure:</h2>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    foreach ($columns as $col) {
        echo "<tr>";
        echo "<td>" . $col['Field'] . "</td>";
        echo "<td>" . $col['Type'] . "</td>";
        echo "<td>" . $col['Null'] . "</td>";
        echo "<td>" . $col['Key'] . "</td>";
        echo "<td>" . $col['Default'] . "</td>";
        echo "<td>" . $col['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
