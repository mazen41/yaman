<?php
/**
 * ajax_search_orders.php
 * AJAX endpoint: search orders with credit balance by customer name or order number.
 * Used by expenses/add.php when "منتجات تالفه" category is selected.
 *
 * GET ?q=<search term>
 * Returns JSON array of matching orders.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require_once '../../config/database.php';

$q = trim($_GET['q'] ?? '');

// Minimum 1 character to search
if (mb_strlen($q) < 1) {
    echo json_encode([]);
    exit;
}

$search = "%{$q}%";

try {
    $stmt = $db->prepare("
        SELECT
            co.id AS order_id,
            co.order_number,
            c.id AS customer_id,
            c.name AS customer_name,
            c.customer_code,
            (co.paid_amount - co.final_amount) AS credit_amount
        FROM customer_orders co
        JOIN customers c ON co.customer_id = c.id
        WHERE (co.paid_amount - co.final_amount) > 0.01
          AND co.status NOT IN ('cancelled', 'returned')
          AND (
              c.name LIKE ?
              OR co.order_number LIKE ?
              OR c.customer_code LIKE ?
          )
        ORDER BY c.name ASC, co.order_date DESC
        LIMIT 30
    ");
    $stmt->execute([$search, $search, $search]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format for frontend
    $output = [];
    foreach ($results as $row) {
        $output[] = [
            'order_id'      => (int) $row['order_id'],
            'order_number'  => $row['order_number'],
            'customer_id'   => (int) $row['customer_id'],
            'customer_name' => $row['customer_name'],
            'customer_code' => $row['customer_code'] ?? '',
            'credit_amount' => round((float) $row['credit_amount'], 2),
        ];
    }

    echo json_encode($output, JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("ajax_search_orders error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
