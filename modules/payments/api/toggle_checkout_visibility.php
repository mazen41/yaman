<?php
/**
 * API: Toggle bank account checkout visibility
 * POST /modules/payments/api/toggle_checkout_visibility.php
 */
session_start();

header('Content-Type: application/json; charset=utf-8');

// Auth check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'غير مصرح']);
    exit();
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'طريقة الطلب غير مسموحة']);
    exit();
}

// CSRF check
$csrf_token = $_POST['csrf_token'] ?? '';
if (empty($csrf_token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'رمز الحماية غير صالح']);
    exit();
}

require_once '../../../config/database.php';

// Validate inputs
$account_id = filter_var($_POST['account_id'] ?? null, FILTER_VALIDATE_INT);
$new_value   = filter_var($_POST['show_in_checkout'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 1]]);

if ($account_id === false || $account_id === null || $account_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'معرّف الحساب غير صالح']);
    exit();
}

if ($new_value === false || $new_value === null) {
    echo json_encode(['success' => false, 'message' => 'قيمة الحالة غير صالحة']);
    exit();
}

try {
    // Verify account exists
    $check_stmt = $db->prepare("SELECT id, bank_name FROM bank_accounts WHERE id = ?");
    $check_stmt->execute([$account_id]);
    $account = $check_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        echo json_encode(['success' => false, 'message' => 'الحساب البنكي غير موجود']);
        exit();
    }

    // Update visibility
    $update_stmt = $db->prepare("UPDATE bank_accounts SET show_in_checkout = ?, updated_at = NOW() WHERE id = ?");
    $update_stmt->execute([$new_value, $account_id]);

    $status_text = $new_value ? 'ظاهر للعملاء في الدفع' : 'مخفي عن العملاء في الدفع';
    echo json_encode([
        'success'          => true,
        'message'          => "تم تحديث حالة الحساب \"{$account['bank_name']}\" — {$status_text}",
        'show_in_checkout' => (int)$new_value,
        'account_id'       => $account_id,
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'خطأ في قاعدة البيانات']);
}
