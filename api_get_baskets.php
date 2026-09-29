<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'config/database.php';

try {
    $query = "SELECT 
                pb.id,
                pb.basket_code,
                pb.basket_name,
                pb.created_at,
                pg.group_name,
                pg.group_number,
                COUNT(DISTINCT bt.id) as tracking_count
              FROM purchase_baskets pb
              LEFT JOIN purchase_groups pg ON pb.purchase_group_id = pg.id
              LEFT JOIN basket_tracking bt ON pb.id = bt.basket_id
              GROUP BY pb.id
              ORDER BY pb.created_at DESC";
    
    $baskets = $db->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'baskets' => $baskets
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
