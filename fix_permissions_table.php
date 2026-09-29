<?php
/**
 * Fix permissions table structure
 */

require_once 'config/database.php';

try {
    echo "Checking permissions table structure...\n";
    
    // Check if permission_type column exists
    $columns = $db->query("SHOW COLUMNS FROM permissions LIKE 'permission_type'")->fetchAll();
    
    if (empty($columns)) {
        echo "Adding permission_type column...\n";
        $db->exec("
            ALTER TABLE permissions 
            ADD COLUMN permission_type ENUM('view', 'edit', 'add', 'delete', 'full') DEFAULT 'view' AFTER permission_name
        ");
        echo "✓ permission_type column added\n";
    } else {
        echo "✓ permission_type column already exists\n";
    }
    
    // Check if module_name column exists
    $columns = $db->query("SHOW COLUMNS FROM permissions LIKE 'module_name'")->fetchAll();
    
    if (empty($columns)) {
        echo "Adding module_name column...\n";
        $db->exec("
            ALTER TABLE permissions 
            ADD COLUMN module_name VARCHAR(100) NOT NULL AFTER permission_type
        ");
        echo "✓ module_name column added\n";
    } else {
        echo "✓ module_name column already exists\n";
    }
    
    // Check if description column exists
    $columns = $db->query("SHOW COLUMNS FROM permissions LIKE 'description'")->fetchAll();
    
    if (empty($columns)) {
        echo "Adding description column...\n";
        $db->exec("
            ALTER TABLE permissions 
            ADD COLUMN description TEXT AFTER module_name
        ");
        echo "✓ description column added\n";
    } else {
        echo "✓ description column already exists\n";
    }
    
    // Add indexes if they don't exist
    echo "Adding indexes...\n";
    try {
        $db->exec("ALTER TABLE permissions ADD INDEX idx_module (module_name)");
        echo "✓ idx_module index added\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "✓ idx_module index already exists\n";
        } else {
            throw $e;
        }
    }
    
    try {
        $db->exec("ALTER TABLE permissions ADD INDEX idx_type (permission_type)");
        echo "✓ idx_type index added\n";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
            echo "✓ idx_type index already exists\n";
        } else {
            throw $e;
        }
    }
    
    echo "\n✅ Permissions table structure fixed!\n";
    echo "Now run: php create_granular_permissions.php\n";
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
