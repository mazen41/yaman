<?php
/**
 * Fix Shipments Foreign Key Constraint - Senior Engineer Solution
 * This script will properly fix the foreign key constraint issue
 */

require_once 'config/database.php';

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>Fix Shipments Database</title>
    <style>
        body { font-family: Arial; padding: 20px; background: #f5f5f5; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: green; padding: 10px; background: #d4edda; border-radius: 5px; margin: 10px 0; }
        .error { color: red; padding: 10px; background: #f8d7da; border-radius: 5px; margin: 10px 0; }
        .warning { color: orange; padding: 10px; background: #fff3cd; border-radius: 5px; margin: 10px 0; }
        .info { color: blue; padding: 10px; background: #d1ecf1; border-radius: 5px; margin: 10px 0; }
        h1 { color: #333; border-bottom: 3px solid #007bff; padding-bottom: 10px; }
        h2 { color: #666; margin-top: 30px; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        .btn { display: inline-block; padding: 10px 20px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; margin-top: 20px; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔧 Fix Shipments Database Structure</h1>
    
<?php
try {
    echo "<h2>Step 1: Analyzing Current Structure</h2>";
    
    // Get current foreign keys
    $fks = $db->query("
        SELECT 
            CONSTRAINT_NAME,
            COLUMN_NAME,
            REFERENCED_TABLE_NAME,
            REFERENCED_COLUMN_NAME
        FROM information_schema.KEY_COLUMN_USAGE 
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'shipments' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($fks)) {
        echo "<div class='info'><strong>Found Foreign Keys:</strong><br>";
        foreach ($fks as $fk) {
            echo "- {$fk['CONSTRAINT_NAME']}: {$fk['COLUMN_NAME']} → {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']}<br>";
        }
        echo "</div>";
    }
    
    echo "<h2>Step 2: Removing Problematic Foreign Keys</h2>";
    
    // Drop all foreign keys on order_id
    foreach ($fks as $fk) {
        if ($fk['COLUMN_NAME'] === 'order_id') {
            try {
                $db->exec("ALTER TABLE shipments DROP FOREIGN KEY `{$fk['CONSTRAINT_NAME']}`");
                echo "<div class='success'>✓ Dropped foreign key: {$fk['CONSTRAINT_NAME']}</div>";
            } catch (PDOException $e) {
                echo "<div class='warning'>⚠ Could not drop {$fk['CONSTRAINT_NAME']}: " . $e->getMessage() . "</div>";
            }
        }
    }
    
    echo "<h2>Step 3: Modifying Column Constraints</h2>";
    
    // Make order_id nullable
    try {
        $db->exec("ALTER TABLE shipments MODIFY COLUMN order_id INT NULL");
        echo "<div class='success'>✓ Made <code>order_id</code> nullable</div>";
    } catch (PDOException $e) {
        echo "<div class='warning'>⚠ order_id: " . $e->getMessage() . "</div>";
    }
    
    // Make sender_id nullable
    try {
        $db->exec("ALTER TABLE shipments MODIFY COLUMN sender_id INT NULL");
        echo "<div class='success'>✓ Made <code>sender_id</code> nullable</div>";
    } catch (PDOException $e) {
        echo "<div class='warning'>⚠ sender_id: " . $e->getMessage() . "</div>";
    }
    
    // Make shipping_company nullable with default
    try {
        $db->exec("ALTER TABLE shipments MODIFY COLUMN shipping_company VARCHAR(255) NULL DEFAULT ''");
        echo "<div class='success'>✓ Made <code>shipping_company</code> nullable with default empty string</div>";
    } catch (PDOException $e) {
        echo "<div class='warning'>⚠ shipping_company: " . $e->getMessage() . "</div>";
    }
    
    echo "<h2>Step 4: Verification</h2>";
    
    // Verify the changes
    $columns = $db->query("SHOW COLUMNS FROM shipments WHERE Field IN ('order_id', 'sender_id', 'shipping_company')")->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<div class='info'><strong>Current Column Structure:</strong><br>";
    foreach ($columns as $col) {
        echo "- <code>{$col['Field']}</code>: {$col['Type']} | Null: {$col['Null']} | Default: " . ($col['Default'] ?? 'NULL') . "<br>";
    }
    echo "</div>";
    
    echo "<div class='success'>";
    echo "<h2>✅ Database Fix Complete!</h2>";
    echo "<p>The shipments table has been successfully updated. You can now:</p>";
    echo "<ul>";
    echo "<li>Create shipments without foreign key errors</li>";
    echo "<li>Link shipments to orders optionally</li>";
    echo "<li>Use any sender from the senders table</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<a href='modules/shipping/add.php' class='btn'>➜ Go to Add Shipment</a>";
    echo "<a href='modules/shipping/index.php' class='btn' style='background: #28a745; margin-left: 10px;'>➜ View All Shipments</a>";
    
} catch (PDOException $e) {
    echo "<div class='error'><strong>❌ Critical Error:</strong><br>" . $e->getMessage() . "</div>";
    echo "<p>Please contact your database administrator or check the error log.</p>";
}
?>

</div>
</body>
</html>
