<?php
// This script checks if our invoice and payment tables exist and have data
require_once 'config/database.php';

echo "<h1>Database Table Check</h1>";

function checkTable($db, $tableName) {
    try {
        $result = $db->query("SELECT COUNT(*) as count FROM $tableName");
        $count = $result->fetch(PDO::FETCH_ASSOC)['count'];
        echo "<p>✅ Table <strong>$tableName</strong> exists with $count records</p>";
        return true;
    } catch (PDOException $e) {
        echo "<p>❌ Table <strong>$tableName</strong> does not exist or has an error: " . $e->getMessage() . "</p>";
        return false;
    }
}

// Check tables
checkTable($db, 'customers');
checkTable($db, 'customer_orders');
checkTable($db, 'customer_invoices');
checkTable($db, 'customer_payments');

// If invoices table exists, show some sample data
try {
    $result = $db->query("SELECT * FROM customer_invoices LIMIT 5");
    $invoices = $result->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($invoices) > 0) {
        echo "<h2>Sample Invoices</h2>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Invoice Number</th><th>Customer ID</th><th>Order ID</th><th>Amount</th><th>Status</th></tr>";
        
        foreach ($invoices as $invoice) {
            echo "<tr>";
            echo "<td>" . $invoice['id'] . "</td>";
            echo "<td>" . $invoice['invoice_number'] . "</td>";
            echo "<td>" . $invoice['customer_id'] . "</td>";
            echo "<td>" . $invoice['order_id'] . "</td>";
            echo "<td>" . number_format($invoice['total_amount'], 2) . "</td>";
            echo "<td>" . $invoice['status'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
} catch (PDOException $e) {
    // Table doesn't exist or other error
}

// If payments table exists, show some sample data
try {
    $result = $db->query("SELECT * FROM customer_payments LIMIT 5");
    $payments = $result->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($payments) > 0) {
        echo "<h2>Sample Payments</h2>";
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Payment Number</th><th>Customer ID</th><th>Invoice ID</th><th>Amount</th><th>Method</th></tr>";
        
        foreach ($payments as $payment) {
            echo "<tr>";
            echo "<td>" . $payment['id'] . "</td>";
            echo "<td>" . $payment['payment_number'] . "</td>";
            echo "<td>" . $payment['customer_id'] . "</td>";
            echo "<td>" . $payment['invoice_id'] . "</td>";
            echo "<td>" . number_format($payment['amount'], 2) . "</td>";
            echo "<td>" . $payment['payment_method'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
} catch (PDOException $e) {
    // Table doesn't exist or other error
}

// Check if we have orders with no invoices
try {
    $result = $db->query("
        SELECT co.* 
        FROM customer_orders co 
        LEFT JOIN customer_invoices ci ON co.id = ci.order_id 
        WHERE ci.id IS NULL
    ");
    $missing_invoices = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Orders Without Invoices</h2>";
    if (count($missing_invoices) > 0) {
        echo "<p>Found " . count($missing_invoices) . " orders without invoices</p>";
        echo "<ul>";
        foreach ($missing_invoices as $order) {
            echo "<li>Order #" . $order['order_number'] . " (ID: " . $order['id'] . ") - Customer ID: " . $order['customer_id'] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>✅ All orders have invoices</p>";
    }
} catch (PDOException $e) {
    echo "<p>Error checking for missing invoices: " . $e->getMessage() . "</p>";
}

// Check if we have completed orders with no payments
try {
    $result = $db->query("
        SELECT co.*, ci.id as invoice_id
        FROM customer_orders co 
        JOIN customer_invoices ci ON co.id = ci.order_id 
        LEFT JOIN customer_payments cp ON ci.id = cp.invoice_id
        WHERE co.status = 'completed' AND cp.id IS NULL
    ");
    $missing_payments = $result->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Completed Orders Without Payments</h2>";
    if (count($missing_payments) > 0) {
        echo "<p>Found " . count($missing_payments) . " completed orders without payments</p>";
        echo "<ul>";
        foreach ($missing_payments as $order) {
            echo "<li>Order #" . $order['order_number'] . " (ID: " . $order['id'] . ") - Invoice ID: " . $order['invoice_id'] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>✅ All completed orders have payments</p>";
    }
} catch (PDOException $e) {
    echo "<p>Error checking for missing payments: " . $e->getMessage() . "</p>";
}

echo "<p><a href='run_sync.php'>Run Sync Script</a> | <a href='setup_sample_data.php'>Setup Sample Data</a></p>";
?>
