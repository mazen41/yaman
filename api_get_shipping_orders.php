<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'config/database.php';

try {
    $query = "SELECT 
                o.id, 
                o.order_number, 
                o.final_amount, 
                o.status,
                o.created_at,
                c.name as customer_name, 
                c.mobile_number, 
                c.address, 
                c.city_name
              FROM customer_orders o
              LEFT JOIN customers c ON o.customer_id = c.id
              WHERE o.status != 'cancelled'
              ORDER BY o.created_at DESC
              LIMIT 500";
    
    $stmt = $db->query($query);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'orders' => $orders
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
