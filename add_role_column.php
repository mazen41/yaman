<?php
// Add role_id column to users table
require_once 'config/database.php';

echo "<h2>Adding role_id Column</h2><pre>";

try {
    // Check if column exists
    $stmt = $db->query("SHOW COLUMNS FROM users LIKE 'role_id'");
    $exists = $stmt->fetch();
    
    if ($exists) {
        echo "✅ role_id column already exists\n";
    } else {
        echo "Adding role_id column...\n";
        $db->exec("ALTER TABLE users ADD COLUMN role_id INT UNSIGNED NULL AFTER email");
        $db->exec("ALTER TABLE users ADD INDEX idx_role_id (role_id)");
        echo "✅ role_id column added successfully\n";
    }
    
    // Verify
    echo "\nVerifying structure:\n";
    $stmt = $db->query("DESCRIBE users");
    while ($row = $stmt->fetch()) {
        if ($row['Field'] == 'role_id' || $row['Field'] == 'email' || $row['Field'] == 'full_name') {
            echo "  {$row['Field']}: {$row['Type']}\n";
        }
    }
    
    echo "\n✅ Done! Refresh the employee permissions page now.\n";
    echo "</pre>";
    echo "<p><a href='modules/financial/employee-permissions-fixed.php?user_id=13'>Go to Employee Permissions</a></p>";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
