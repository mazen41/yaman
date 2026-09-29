<?php
// Robust require
if (file_exists('config/database.php')) {
    require_once 'config/database.php';
} elseif (file_exists('database_config.php')) {
    require_once 'database_config.php';
} else {
    die("DB config not found");
}

try {
    $db->query("SELECT 1 FROM accounts LIMIT 1");
    echo "Table 'accounts' exists.<br>";
    $stmt = $db->query("SHOW COLUMNS FROM accounts");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")<br>";
    }
} catch (PDOException $e) {
    echo "Table 'accounts' does NOT exist or error: " . $e->getMessage();
}
?>
