<?php
require_once '../config/database.php';

echo "<h1>Fixing View.php Undefined Variable Warnings</h1>";
echo "<pre>";

// Test the view.php query with a sample order
try {
    // Get a sample order ID
    $sample_stmt = $db->query("SELECT id FROM customer_orders LIMIT 1");
    $sample_order = $sample_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$sample_order) {
        echo "No orders found. Creating a sample order...\n";
        
        // Create a sample customer first
        $customer_stmt = $db->prepare("INSERT INTO customers (name, customer_code, mobile_number, whatsapp_number, email, city_name, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $customer_stmt->execute(['عميل تجريبي', 'TEST001', '0501234567', '966501234567', 'test@example.com', 'الرياض']);
        $customer_id = $db->lastInsertId();
        
        // Create a sample order
        $order_stmt = $db->prepare("
            INSERT INTO customer_orders (
                order_number, customer_id, subtotal_amount, total_amount, final_amount,
                discount_type, discount_value, discount_amount,
                status, payment_method, shipping_method, shipping_cost,
                notes, requires_approval, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        
        $order_stmt->execute([
            'ORD-TEST001', $customer_id, 500, 450, 470,
            'percentage', 10, 50,
            'new', 'cash', 'delivery', 20,
            'طلب تجريبي', 0
        ]);
        
        $sample_order_id = $db->lastInsertId();
        echo "Created sample order with ID: $sample_order_id\n";
    } else {
        $sample_order_id = $sample_order['id'];
        echo "Using existing order ID: $sample_order_id\n";
    }
    
    // Test the exact query from view.php
    echo "\nTesting view.php query...\n";
    $stmt = $db->prepare("
        SELECT o.*, c.name as customer_name, c.customer_code, c.mobile_number, c.whatsapp_number, c.email, c.city_name,
        u.name as approved_by_name
        FROM customer_orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        LEFT JOIN users u ON o.approved_by = u.id
        WHERE o.id = ?
    ");
    $stmt->execute([$sample_order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        echo "✅ Query successful!\n";
        
        // Check all fields that are accessed in view.php
        $required_fields = [
            'order_number', 'customer_name', 'customer_code', 'status',
            'total_amount', 'subtotal_amount', 'discount_type', 'discount_value', 'discount_amount',
            'final_amount', 'shipping_cost', 'created_at', 'requires_approval',
            'approved_by', 'approved_at', 'approved_by_name', 'customer_id',
            'whatsapp_number', 'mobile_number', 'email', 'city_name',
            'payment_method', 'shipping_method', 'expected_delivery_date', 'notes'
        ];
        
        echo "\nChecking required fields:\n";
        $missing_fields = [];
        
        foreach ($required_fields as $field) {
            if (!array_key_exists($field, $order)) {
                $missing_fields[] = $field;
                echo "❌ Missing: $field\n";
            } else {
                $value = $order[$field] ?? 'NULL';
                echo "✅ Present: $field = " . (is_string($value) ? substr($value, 0, 20) : $value) . "\n";
            }
        }
        
        if (empty($missing_fields)) {
            echo "\n✅ All required fields are present!\n";
        } else {
            echo "\n⚠️ Missing fields found: " . implode(', ', $missing_fields) . "\n";
            echo "These fields need to be added to the SQL query or initialized with default values.\n";
        }
        
        // Test the view page
        echo "\nTesting view page access...\n";
        $view_url = "http://localhost/yassin-admin-system/modules/orders/view.php?id=$sample_order_id";
        echo "View URL: $view_url\n";
        
    } else {
        echo "❌ Query failed - no order found\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
}

echo "</pre>";

if (isset($sample_order_id)) {
    echo "<p><a href='modules/orders/view.php?id=$sample_order_id' target='_blank'>Test View Page</a></p>";
}
echo "<p><a href='modules/orders/index.php'>Back to Orders List</a></p>";
?>
