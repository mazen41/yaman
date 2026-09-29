<?php
if (file_exists('config/database.php')) {
    require_once 'config/database.php';
} elseif (file_exists('database_config.php')) {
    require_once 'database_config.php';
} else {
    die("DB config not found");
}

try {
    $stmt = $db->query("SHOW COLUMNS FROM bank_accounts");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")<br>";
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
