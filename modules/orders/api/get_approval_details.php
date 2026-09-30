<?php
// modules/orders/api/get_approval_details.php
ini_set('display_errors', 0); // Hide errors in production API
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

session_start();
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once '../../../config/database.php';
require_once '../../../includes/check_permissions.php';

if (!canOpenOrderApprovalDetail($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Forbidden']);
    exit();
}

$approval_id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);

if (!$approval_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing or invalid approval ID.']);
    exit();
}

try {
    // Fetch Approval Details with customer information
    $app_stmt = $db->prepare("
        SELECT 
            oa.*,
            c.name AS customer_name_from_db,
            c.customer_code,
            c.mobile_number,
            c.whatsapp_number,
            c.email,
            c.address AS customer_address,
            c.city_name,
            c.customer_notes,
            ct.name AS customer_type_name_from_db,
            u.full_name AS approver_name
        FROM order_approvals oa
        LEFT JOIN customers c ON oa.customer_id = c.id
        LEFT JOIN customer_types ct ON c.customer_type_id = ct.id
        LEFT JOIN users u ON oa.approved_by = u.id
        WHERE oa.id = ?
    ");
    $app_stmt->execute([$approval_id]);
    $approval = $app_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$approval) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'طلب الاعتماد غير موجود.']);
        exit();
    }

    // Prefer DB customer name if set, else fallback to oa.customer_name
    if (!empty($approval['customer_name_from_db'])) {
        $approval['display_customer_name'] = $approval['customer_name_from_db'];
    } else {
        $approval['display_customer_name'] = $approval['customer_name'] ?? 'غير محدد';
    }

    // Calculate final amounts consistently
    $subtotal = (float)($approval['subtotal_amount'] ?? 0);
    $auto_disc = (float)($approval['automatic_discount_amount'] ?? 0);
    $coupon_disc = (float)($approval['coupon_discount_amount'] ?? 0);
    $shipping = (float)($approval['shipping_cost'] ?? 0);
    $paid = (float)($approval['paid_amount'] ?? 0);
    $total_disc = $auto_disc + $coupon_disc;
    $final_amount = max(0, $subtotal - $total_disc) + $shipping;
    $remaining = max(0, $final_amount - $paid);

    $approval['calc_subtotal'] = $subtotal;
    $approval['calc_total_discount'] = $total_disc;
    $approval['calc_final_amount'] = $final_amount;
    $approval['calc_remaining'] = $remaining;

    // Fetch Items for this Approval
    $items_stmt = $db->prepare("SELECT * FROM order_approval_items WHERE approval_id = ? ORDER BY id ASC");
    $items_stmt->execute([$approval_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    $approval['items'] = $items;

    // Fetch Images for this Approval
    $images_stmt = $db->prepare("SELECT * FROM order_approvals_images WHERE approval_id = ? ORDER BY display_order ASC, id ASC");
    $images_stmt->execute([$approval_id]);
    $images = $images_stmt->fetchAll(PDO::FETCH_ASSOC);

    $approval['images'] = $images;

    echo json_encode(['success' => true, 'data' => $approval], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Error fetching approval details for ID $approval_id: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error fetching approval details for ID $approval_id: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}