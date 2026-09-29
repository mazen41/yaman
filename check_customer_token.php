<?php
require_once 'config/database.php';

echo "<h2>Checking customers table structure:</h2>";

$stmt = $db->query("SHOW COLUMNS FROM customers");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<pre>";
print_r($columns);
echo "</pre>";

// Check if portal_token exists
$has_token = false;
foreach ($columns as $col) {
    if ($col['Field'] == 'portal_token' || $col['Field'] == 'unique_token' || $col['Field'] == 'access_token') {
        $has_token = true;
        echo "<p style='color: green;'><strong>✅ Token column found: " . $col['Field'] . "</strong></p>";
    }
}

if (!$has_token) {
    echo "<p style='color: red;'><strong>❌ No token column found. Need to create one.</strong></p>";
    echo "<h3>SQL to add token column:</h3>";
    echo "<pre>ALTER TABLE customers ADD COLUMN portal_token VARCHAR(64) UNIQUE AFTER id;</pre>";
}
?>
