<?php
require_once 'config/database.php';

try {
    echo "Fixing shipments table status column...\n";
    
    // Change status column to VARCHAR to support all statuses without truncation
    $db->exec("ALTER TABLE shipments MODIFY COLUMN status VARCHAR(50) DEFAULT 'preparing'");
    echo "✅ Shipments status column updated to VARCHAR(50).\n";
    
    // Also check customer_orders shipping_status just in case
    $db->exec("ALTER TABLE customer_orders MODIFY COLUMN shipping_status VARCHAR(50) DEFAULT 'new'");
    echo "✅ Customer orders shipping_status column updated to VARCHAR(50).\n";
    
    // And shipment_tracking status
    $db->exec("ALTER TABLE shipment_tracking MODIFY COLUMN status VARCHAR(50)");
    echo "✅ Shipment tracking status column updated to VARCHAR(50).\n";
    
    echo "Done.";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
