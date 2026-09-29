<?php
/**
 * Customer Portal - No Login Required
 * Access via unique token URL
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/status_helpers.php';

// Get token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    die('Invalid access. Please use the link provided to you.');
}

// Get customer by token
$stmt = $db->prepare("SELECT * FROM customers WHERE portal_token = ?");
$stmt->execute([$token]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    die('Invalid or expired link. Please contact support.');
}

$customer_id = $customer['id'];

// Get statistics
$stats = [];

// Total orders
$stmt = $db->prepare("SELECT COUNT(*) as total FROM customer_orders WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$stats['total_orders'] = $stmt->fetchColumn();

// Total amount
$stmt = $db->prepare("SELECT SUM(final_amount) as total FROM customer_orders WHERE customer_id = ?");
$stmt->execute([$customer_id]);
$stats['total_amount'] = $stmt->fetchColumn() ?? 0;

// Get recent orders with calculated fields
try {
    $stmt = $db->prepare("
        SELECT o.*,
               (COALESCE(o.discount_amount, 0) + COALESCE(o.additional_discount, 0) + COALESCE(o.automatic_discount_amount, 0)) as total_discounts,
               (o.final_amount - COALESCE(o.paid_amount, 0)) as remaining_amount,
               (SELECT GROUP_CONCAT(invoice_number SEPARATOR ', ') 
                FROM customer_invoices 
                WHERE order_id = o.id) as invoice_numbers
        FROM customer_orders o
        WHERE o.customer_id = ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$customer_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Try to get quantity from order_items if table exists
    foreach ($orders as &$order) {
        try {
            $qty_stmt = $db->prepare("SELECT SUM(quantity) FROM order_items WHERE order_id = ?");
            $qty_stmt->execute([$order['id']]);
            $order['total_quantity'] = $qty_stmt->fetchColumn() ?? 0;
        } catch (PDOException $e) {
            // If order_items doesn't exist, use a default value
            $order['total_quantity'] = 0;
        }
    }
} catch (PDOException $e) {
    $orders = [];
}

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بوابة العميل - <?php echo htmlspecialchars($customer['name']); ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Header -->
    <nav class="bg-gradient-to-r from-blue-600 to-purple-600 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex items-center">
                    <i class="fas fa-user-circle text-3xl ml-3"></i>
                    <div>
                        <h1 class="text-xl font-bold"><?php echo htmlspecialchars($customer['name']); ?></h1>
                        <p class="text-sm text-blue-100">CUST<?php echo str_pad($customer['id'], 3, '0', STR_PAD_LEFT); ?></p>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Statistics Cards - Only 2 cards as requested -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Total Orders -->
            <div class="bg-white rounded-lg shadow-md p-6 border-r-4 border-blue-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm mb-1">إجمالي الطلبات</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $stats['total_orders']; ?></p>
                    </div>
                    <div class="bg-blue-100 p-4 rounded-full">
                        <i class="fas fa-shopping-cart text-blue-600 text-2xl"></i>
                    </div>
                </div>
            </div>

            <!-- Total Amount -->
            <div class="bg-white rounded-lg shadow-md p-6 border-r-4 border-yellow-500">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-gray-500 text-sm mb-1">إجمالي المبلغ</p>
                        <p class="text-3xl font-bold text-gray-800"><?php echo number_format($stats['total_amount'], 0, '', ''); ?> <span class="text-lg">ريال</span></p>
                    </div>
                    <div class="bg-yellow-100 p-4 rounded-full">
                        <i class="fas fa-coins text-yellow-600 text-2xl"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Orders Table -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <h2 class="text-xl font-bold text-gray-800">
                    <i class="fas fa-list ml-2"></i>
                    طلباتي
                </h2>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">رقم الطلب</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">تاريخ الطلب</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">عدد القطع</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">رابط الطلب</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">رابط إضافي</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">الحالة</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">المبلغ الأصلي</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">الخصم</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">نسبة الخصم</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">المبلغ النهائي</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">المدفوع</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">المتبقي</th>
                            <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">رقم الفاتورة</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($orders as $order): 
                            $remaining = $order['remaining_amount'] ?? ($order['final_amount'] - ($order['paid_amount'] ?? 0));
                        ?>
                        <tr class="hover:bg-gray-50">
                            <!-- رقم الطلب -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <a href="order_details.php?token=<?php echo $token; ?>&order_id=<?php echo $order['id']; ?>" 
                                   class="text-blue-600 hover:text-blue-800 font-bold">
                                    <?php echo htmlspecialchars(formatOrderNumber($order['order_number'])); ?>
                                </a>
                            </td>
                            <!-- تاريخ الطلب -->
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                <?php echo date('Y-m-d', strtotime($order['created_at'])); ?>
                            </td>
                            <!-- عدد القطع -->
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <span class="font-bold text-gray-900"><?php echo $order['total_quantity'] ?? 0; ?></span>
                            </td>
                            <!-- رابط الطلب -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php if (!empty($order['order_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($order['order_link']); ?>" target="_blank" 
                                       class="inline-flex items-center px-2 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded hover:bg-blue-200">
                                        <i class="fas fa-link ml-1"></i> رابط
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <!-- رابط إضافي -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php if (!empty($order['additional_link'])): ?>
                                    <a href="<?php echo htmlspecialchars($order['additional_link']); ?>" target="_blank" 
                                       class="inline-flex items-center px-2 py-1 bg-amber-100 text-amber-800 text-xs font-semibold rounded hover:bg-amber-200">
                                        <i class="fas fa-link ml-1"></i> رابط
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>
                            <!-- الحالة -->
                            <td class="px-4 py-3 whitespace-nowrap">
                                <?php echo getOrderStatusBadge($order['status'] ?? 'new'); ?>
                            </td>
                            <!-- المبلغ الأصلي -->
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-bold" style="color: #3b82f6;">
                                <?php echo number_format($order['subtotal_amount'] ?? $order['final_amount'], 0, '', ''); ?>
                            </td>
                            <!-- الخصم -->
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-bold" style="color: #10b981;">
                                <?php echo number_format($order['discount_amount'] ?? 0, 0, '', ''); ?>
                            </td>
                            <!-- نسبة الخصم -->
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold" style="color: #d97706;">
                                <?php echo !empty($order['automatic_discount_percentage']) ? number_format($order['automatic_discount_percentage'], 0, '', '') . '%' : '-'; ?>
                            </td>
                            <!-- المبلغ النهائي -->
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-bold" style="color: #059669;">
                                <?php echo number_format($order['final_amount'], 0, '', ''); ?>
                            </td>
                            <!-- المدفوع -->
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-bold" style="color: #2563eb;">
                                <?php echo number_format($order['paid_amount'] ?? 0, 0, '', ''); ?>
                            </td>
                            <!-- المتبقي -->
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-bold" style="color: <?php echo $remaining > 0 ? '#dc2626' : '#059669'; ?>;">
                                <?php echo number_format($remaining, 0, '', ''); ?>
                            </td>
                            <!-- رقم الفاتورة -->
                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                <?php echo $order['invoice_numbers'] ? htmlspecialchars($order['invoice_numbers']) : '-'; ?>
                            </td>
                            <!-- الإجراءات -->
                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                <a href="order_details.php?token=<?php echo $token; ?>&order_id=<?php echo $order['id']; ?>" 
                                   class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-xs font-semibold rounded hover:bg-blue-700">
                                    <i class="fas fa-eye ml-1"></i> عرض
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($orders)): ?>
                        <tr>
                            <td colspan="14" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-inbox text-4xl mb-4 text-gray-300"></i>
                                <p>لا توجد طلبات حالياً</p>
                            </td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
