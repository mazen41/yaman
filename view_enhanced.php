<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/database.php';

$page_title = 'عرض بيانات العميل';
$error_message = '';

// Check if customer ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$customer_id = intval($_GET['id']);
$active_tab = $_GET['tab'] ?? 'details';

// Fetch customer data
try {
    $stmt = $db->prepare("
        SELECT c.*, 
               ct.name as customer_type_name,
               ct.discount_percentage as type_discount_percentage
        FROM customers c
        LEFT JOIN customer_types ct ON c.customer_type_id = ct.id
        WHERE c.id = ?
    ");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$customer) {
        $error_message = "العميل غير موجود (Customer ID: $customer_id)";
        // Don't redirect, show error instead
    }
    
    // Fetch customer orders with all columns matching main orders index
    $orders_stmt = $db->prepare("
        SELECT co.id,
               co.order_number,
               co.customer_id,
               co.order_link,
               co.additional_link,
               co.status,
               co.subtotal_amount,
               co.discount_amount,
               co.automatic_discount_percentage,
               co.total_amount,
               co.final_amount,
               co.shipping_cost,
               co.coupon_code,
               co.notes,
               co.created_at,
               co.created_by,
               c.name as customer_name,
               c.mobile_number,
               DATE(co.created_at) as order_date,
               COALESCE(SUM(oi.quantity), 0) as total_quantity,
               COALESCE(SUM(cp.amount), 0) as paid_amount,
               GROUP_CONCAT(DISTINCT ci.invoice_number ORDER BY ci.invoice_number SEPARATOR ', ') as invoice_numbers
        FROM customer_orders co
        LEFT JOIN customers c ON co.customer_id = c.id
        LEFT JOIN order_items oi ON co.id = oi.order_id
        LEFT JOIN customer_invoices ci ON co.id = ci.order_id
        LEFT JOIN customer_payments cp ON ci.id = cp.invoice_id
        WHERE co.customer_id = ?
        GROUP BY co.id, co.order_number, co.customer_id, co.order_link, co.additional_link, 
                 co.status, co.subtotal_amount, co.discount_amount, co.automatic_discount_percentage,
                 co.total_amount, co.final_amount, co.shipping_cost, co.coupon_code, co.notes,
                 co.created_at, co.created_by, c.name, c.mobile_number
        ORDER BY co.created_at DESC
        LIMIT 10
    ");
    $orders_stmt->execute([$customer_id]);
    $orders = $orders_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count total orders
    $orders_count_stmt = $db->prepare("SELECT COUNT(*) FROM customer_orders WHERE customer_id = ?");
    $orders_count_stmt->execute([$customer_id]);
    $total_orders = $orders_count_stmt->fetchColumn();
    
    // Calculate total spent
    $spent_stmt = $db->prepare("SELECT SUM(final_amount) FROM customer_orders WHERE customer_id = ? AND status != 'cancelled'");
    $spent_stmt->execute([$customer_id]);
    $total_spent = $spent_stmt->fetchColumn() ?? 0;
    
    // **MODIFIED**: Fetch customer invoices and calculate the total paid amount for each
    $invoices_stmt = $db->prepare("
        SELECT 
            ci.*, 
            co.order_number,
            (SELECT SUM(cp.amount) FROM customer_payments cp WHERE cp.invoice_id = ci.id) as total_paid
        FROM customer_invoices ci
        LEFT JOIN customer_orders co ON ci.order_id = co.id
        WHERE ci.customer_id = ?
        ORDER BY ci.created_at DESC
        LIMIT 10
    ");
    $invoices_stmt->execute([$customer_id]);
    $invoices = $invoices_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count total invoices
    $invoices_count_stmt = $db->prepare("SELECT COUNT(*) FROM customer_invoices WHERE customer_id = ?");
    $invoices_count_stmt->execute([$customer_id]);
    $total_invoices = $invoices_count_stmt->fetchColumn();
    
    // Fetch customer payments
    $payments_stmt = $db->prepare("
        SELECT cp.*, ci.invoice_number 
        FROM customer_payments cp
        LEFT JOIN customer_invoices ci ON cp.invoice_id = ci.id
        WHERE cp.customer_id = ?
        ORDER BY cp.created_at DESC
        LIMIT 10
    ");
    $payments_stmt->execute([$customer_id]);
    $payments = $payments_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count total payments
    $payments_count_stmt = $db->prepare("SELECT COUNT(*) FROM customer_payments WHERE customer_id = ?");
    $payments_count_stmt->execute([$customer_id]);
    $total_payments = $payments_count_stmt->fetchColumn();
    
} catch (PDOException $e) {
    $error_message = 'حدث خطأ أثناء استرجاع بيانات العميل: ' . $e->getMessage();
    // Initialize empty arrays to prevent undefined variable errors
    $orders = [];
    $total_orders = 0;
    $total_spent = 0;
    $invoices = [];
    $total_invoices = 0;
    $payments = [];
    $total_payments = 0;
}

include '../../includes/header.php';
?>

<style>
    /* Mobile responsive tables */
    @media (max-width: 768px) {
        .responsive-table thead {
            display: none;
        }
        
        .responsive-table tbody {
            display: block;
        }
        
        .responsive-table tr {
            display: block;
            margin-bottom: 1rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            padding: 1rem;
            background: white;
        }
        
        .responsive-table td {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0 !important;
            border: none !important;
            text-align: right !important;
        }
        
        .responsive-table td:before {
            content: attr(data-label);
            font-weight: 600;
            color: #6b7280;
            margin-left: 1rem;
        }
        
        .responsive-table td:last-child {
            border-top: 1px solid #e5e7eb;
            padding-top: 0.75rem !important;
            margin-top: 0.5rem;
        }
    }
</style>

<div class="min-h-screen bg-gray-50 py-6" dir="rtl">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-bold text-gray-900">بيانات العميل</h1>
                        <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($customer['name']); ?></p>
                    </div>
                    <div class="grid grid-cols-2 sm:flex gap-2">
                        <a href="edit.php?id=<?php echo $customer_id; ?>" class="inline-flex items-center justify-center px-3 sm:px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200 text-sm"><i class="fas fa-edit ml-2"></i><span class="hidden sm:inline">تعديل</span><span class="sm:hidden">تعديل</span></a>
                        <a href="../orders/sync_customer_invoices.php?customer_id=<?php echo $customer_id; ?>&redirect=<?php echo urlencode($_SERVER['REQUEST_URI']); ?>" class="inline-flex items-center justify-center px-3 sm:px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition duration-200 text-sm"><i class="fas fa-sync ml-2"></i><span class="hidden sm:inline">مزامنة</span><span class="sm:hidden">مزامنة</span></a>
                        
                        <?php if (!empty($customer['portal_token'])): ?>
                        <button onclick="copyPortalLink('<?php echo $customer['portal_token']; ?>')" class="inline-flex items-center justify-center px-3 sm:px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition duration-200 text-sm">
                            <i class="fas fa-copy ml-2"></i><span class="hidden sm:inline">نسخ رابط البوابة</span><span class="sm:hidden">نسخ</span>
                        </button>
                        <a href="../../customer_portal/portal.php?token=<?php echo $customer['portal_token']; ?>" target="_blank" class="inline-flex items-center justify-center px-3 sm:px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition duration-200 text-sm">
                            <i class="fas fa-external-link-alt ml-2"></i><span class="hidden sm:inline">فتح البوابة</span><span class="sm:hidden">فتح</span>
                        </a>
                        <?php else: ?>
                        <span class="inline-flex items-center justify-center px-3 sm:px-4 py-2 bg-gray-400 text-white rounded-lg text-sm cursor-not-allowed" title="يجب تشغيل add_portal_tokens.php أولاً">
                            <i class="fas fa-lock ml-2"></i><span class="hidden sm:inline">لا يوجد رابط</span>
                        </span>
                        <?php endif; ?>
                        
                        <a href="index.php" class="inline-flex items-center justify-center px-3 sm:px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition duration-200 text-sm"><i class="fas fa-arrow-right ml-2"></i><span class="hidden sm:inline">العودة</span><span class="sm:hidden">عودة</span></a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Error Message Display -->
        <?php if (!empty($error_message)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6 rounded" role="alert">
            <div class="flex">
                <div class="flex-shrink-0">
                    <i class="fas fa-exclamation-triangle text-red-500"></i>
                </div>
                <div class="mr-3">
                    <p class="font-bold">خطأ</p>
                    <p class="text-sm"><?php echo htmlspecialchars($error_message); ?></p>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Customer Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white p-5 shadow rounded-lg flex items-center"><i class="fas fa-shopping-cart text-2xl text-green-600"></i><div class="mr-3"><dt class="text-sm font-medium text-gray-500 truncate">إجمالي الطلبات</dt><dd class="text-lg font-medium text-gray-900"><?php echo $total_orders; ?></dd></div></div>
            <div class="bg-white p-5 shadow rounded-lg flex items-center"><i class="fas fa-coins text-2xl text-blue-600"></i><div class="mr-3"><dt class="text-sm font-medium text-gray-500 truncate">إجمالي المبلغ</dt><dd class="text-lg font-medium text-gray-900"><?php echo number_format($total_spent, 0, '', ''); ?></dd></div></div>
            <div class="bg-white p-5 shadow rounded-lg flex items-center"><i class="fas fa-file-invoice text-2xl text-orange-600"></i><div class="mr-3"><dt class="text-sm font-medium text-gray-500 truncate">الفواتير</dt><dd class="text-lg font-medium text-gray-900"><?php echo $total_invoices ?? 0; ?></dd></div></div>
            <div class="bg-white p-5 shadow rounded-lg flex items-center"><i class="fas fa-credit-card text-2xl text-purple-600"></i><div class="mr-3"><dt class="text-sm font-medium text-gray-500 truncate">المدفوعات</dt><dd class="text-lg font-medium text-gray-900"><?php echo $total_payments ?? 0; ?></dd></div></div>
        </div>

        <!-- Tabs -->
        <div class="bg-white shadow rounded-lg">
            <div class="px-4 sm:px-6 py-4 border-b border-gray-200">
                <nav class="flex space-x-4 sm:space-x-8 space-x-reverse overflow-x-auto" aria-label="Tabs">
                    <a href="?id=<?php echo $customer_id; ?>&tab=details" class="<?php echo $active_tab == 'details' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-xs sm:text-sm">تفاصيل</a>
                    <a href="?id=<?php echo $customer_id; ?>&tab=orders" class="<?php echo $active_tab == 'orders' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-xs sm:text-sm">الطلبات (<?php echo $total_orders; ?>)</a>
                    <a href="?id=<?php echo $customer_id; ?>&tab=invoices" class="<?php echo $active_tab == 'invoices' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-xs sm:text-sm">الفواتير (<?php echo $total_invoices ?? 0; ?>)</a>
                    <a href="?id=<?php echo $customer_id; ?>&tab=payments" class="<?php echo $active_tab == 'payments' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'; ?> whitespace-nowrap py-2 px-1 border-b-2 font-medium text-xs sm:text-sm">المدفوعات (<?php echo $total_payments ?? 0; ?>)</a>
                </nav>
            </div>
            
            <div class="p-6">
                <?php if ($active_tab == 'details'): ?>
                    <!-- Customer Details Tab -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <div class="flex items-center mb-4">
                                <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center text-blue-600"><i class="fas <?php echo $customer['customer_type'] == 'company' ? 'fa-building' : 'fa-user'; ?> text-2xl"></i></div>
                                <div class="mr-4">
                                    <h3 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($customer['name']); ?></h3>
                                    <p class="text-sm text-gray-600"><span class="px-2 py-1 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800"><?php echo htmlspecialchars($customer['customer_type']); ?></span></p>
                                </div>
                            </div>
                            <div class="mt-4 space-y-4">
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="text-sm font-medium text-gray-500 mb-3">المعلومات الأساسية</h4>
                                    <div class="space-y-2">
                                        <div class="flex justify-between"><span class="text-sm text-gray-600">رقم العميل:</span><span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($customer['customer_code']); ?></span></div>
                                        <div class="flex justify-between"><span class="text-sm text-gray-600">الرصيد الحالي:</span><span class="text-sm font-medium <?php echo ($customer['current_balance'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600'; ?>"><?php echo number_format($customer['current_balance'] ?? 0, 0, '', ''); ?> ريال</span></div>
                                        <?php if (!empty($customer['customer_type_name'])): ?>
                                        <div class="flex justify-between"><span class="text-sm text-gray-600">تصنيف العميل:</span><span class="text-sm font-medium text-indigo-600"><?php echo htmlspecialchars($customer['customer_type_name']); ?> <?php if ($customer['type_discount_percentage'] > 0): ?><span class="text-xs bg-green-100 text-green-700 px-1 rounded"><?php echo $customer['type_discount_percentage']; ?>% خصم</span><?php endif; ?></span></div>
                                        <?php endif; ?>
                                        <?php if (!empty($customer['city_name'])): ?>
                                        <div class="flex justify-between"><span class="text-sm text-gray-600">المدينة:</span><span class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($customer['city_name']); ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-lg">
                                    <h4 class="text-sm font-medium text-gray-500 mb-3">معلومات الإنشاء</h4>
                                    <div class="space-y-2">
                                        <div class="flex justify-between"><span class="text-sm text-gray-600">تاريخ الإنشاء:</span><span class="text-sm font-medium text-gray-900"><?php echo !empty($customer['created_at']) ? date('Y-m-d H:i', strtotime($customer['created_at'])) : '-'; ?></span></div>
                                        <?php if (!empty($customer['updated_at'])): ?>
                                        <div class="flex justify-between"><span class="text-sm text-gray-600">آخر تحديث:</span><span class="text-sm font-medium text-gray-900"><?php echo date('Y-m-d H:i', strtotime($customer['updated_at'])); ?></span></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                                <h4 class="text-sm font-medium text-gray-500 mb-3">معلومات الاتصال</h4>
                                <div class="space-y-3">
                                    <div class="flex items-center"><i class="fas fa-phone text-gray-400 w-5"></i><span class="text-sm text-gray-900 mr-2"><?php echo htmlspecialchars($customer['phone'] ?? '-'); ?></span></div>
                                    <div class="flex items-center"><i class="fas fa-mobile-alt text-gray-400 w-5"></i><span class="text-sm text-gray-900 mr-2"><?php echo htmlspecialchars($customer['mobile_number'] ?? '-'); ?></span></div>
                                    <div class="flex items-center"><i class="fab fa-whatsapp text-green-400 w-5"></i><span class="text-sm text-gray-900 mr-2"><?php echo htmlspecialchars($customer['whatsapp_number'] ?? '-'); ?></span></div>
                                    <div class="flex items-center"><i class="fas fa-envelope text-gray-400 w-5"></i><span class="text-sm text-gray-900 mr-2"><?php echo htmlspecialchars($customer['email'] ?? '-'); ?></span></div>
                                </div>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <h4 class="text-sm font-medium text-gray-500 mb-3"><i class="fas fa-map-marker-alt ml-1 text-red-400"></i>العنوان</h4>
                                <p class="text-sm text-gray-900"><?php echo !empty($customer['address']) ? nl2br(htmlspecialchars($customer['address'])) : '<span class="text-gray-400">لم يتم تحديد العنوان</span>'; ?></p>
                            </div>
                            <?php if (!empty($customer['notes'])): ?>
                            <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
                                <h4 class="text-sm font-medium text-yellow-700 mb-3"><i class="fas fa-sticky-note ml-1"></i>ملاحظات</h4>
                                <p class="text-sm text-yellow-800"><?php echo nl2br(htmlspecialchars($customer['notes'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                <?php elseif ($active_tab == 'orders'): ?>
                    <!-- Orders Tab -->
                    <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                        <?php if (empty($orders)): ?>
                            <div class="text-center py-12">
                                <i class="fas fa-shopping-cart text-4xl text-gray-300 mb-4"></i>
                                <p class="text-gray-500">لا توجد طلبات لهذا العميل</p>
                            </div>
                        <?php else: ?>
                            <div style="overflow-x: auto;">
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
                                            $remaining_amount = $order['final_amount'] - $order['paid_amount'];
                                        ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <strong class="text-gray-900"><?php echo htmlspecialchars(formatOrderNumber($order['order_number'])); ?></strong>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                                <?php echo htmlspecialchars($order['order_date']); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                                <strong class="text-gray-900"><?php echo $order['total_quantity']; ?></strong>
                                            </td>
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
                                            <td class="px-4 py-3 whitespace-nowrap">
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                                    <?php 
                                                    $status_colors = [
                                                        'new' => 'bg-blue-100 text-blue-800',
                                                        'approved' => 'bg-green-100 text-green-800',
                                                        'in_preparation' => 'bg-yellow-100 text-yellow-800',
                                                        'shipped' => 'bg-purple-100 text-purple-800',
                                                        'completed' => 'bg-green-100 text-green-800',
                                                        'cancelled' => 'bg-red-100 text-red-800'
                                                    ];
                                                    echo $status_colors[$order['status']] ?? 'bg-gray-100 text-gray-800';
                                                    ?>">
                                                    <?php 
                                                    $status_text = [
                                                        'new' => 'جديد',
                                                        'approved' => 'معتمد',
                                                        'in_preparation' => 'قيد التحضير',
                                                        'shipped' => 'تم الشحن',
                                                        'notes' => 'ملاحظات',
                                                        'under_sorting' => 'قيد الفرز',
                                                        'sorted' => 'تم الفرز',
                                                        'in_delivery' => 'قيد التوصيل',
                                                        'received' => 'تم الاستلام',
                                                        'completed' => 'مكتمل',
                                                        'cancelled' => 'ملغي'
                                                    ];
                                                    echo $status_text[$order['status']] ?? $order['status'];
                                                    ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-bold" style="color: #3b82f6;">
                                                <?php echo number_format($order['subtotal_amount'] ?? $order['final_amount'], 0, '', ''); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-bold" style="color: #10b981;">
                                                <?php echo number_format($order['discount_amount'], 0, '', ''); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-semibold" style="color: #d97706;">
                                                <?php echo !empty($order['automatic_discount_percentage']) ? number_format($order['automatic_discount_percentage'], 0, '', '') . '%' : '-'; ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-bold" style="color: #059669;">
                                                <?php echo number_format($order['final_amount'], 0, '', ''); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-bold" style="color: #2563eb;">
                                                <?php echo number_format($order['paid_amount'], 0, '', ''); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-bold" style="color: <?php echo $remaining_amount > 0 ? '#dc2626' : '#059669'; ?>;">
                                                <?php echo number_format($remaining_amount, 0, '', ''); ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                                <?php echo $order['invoice_numbers'] ? htmlspecialchars($order['invoice_numbers']) : '-'; ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-center">
                                                <a href="../orders/edit.php?id=<?php echo $order['id']; ?>" 
                                                   class="inline-flex items-center px-3 py-1 bg-blue-600 text-white text-xs font-semibold rounded hover:bg-blue-700">
                                                    <i class="fas fa-eye ml-1"></i> عرض
                                                </a>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php elseif ($active_tab == 'invoices'): ?>
                    <!-- **MODIFIED**: Invoices Tab -->
                    <div class="space-y-4">
                        <?php if (empty($invoices)): ?>
                            <div class="text-center py-12"><i class="fas fa-file-invoice text-4xl text-gray-300 mb-4"></i><p class="text-gray-500">لا توجد فواتير لهذا العميل</p></div>
                        <?php else: ?>
                            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200 responsive-table">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">رقم الفاتورة</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">مبلغ الفاتورة</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">المبلغ المدفوع</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">المبلغ المتبقي</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">الحالة</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">تاريخ الإصدار</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">العمليات</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($invoices as $invoice): ?>
                                        <?php
                                            $invoice_amount = $invoice['total_amount'];
                                            $paid_amount = $invoice['total_paid'] ?? 0;
                                            $remaining_amount = $invoice_amount - $paid_amount;
                                        ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900" data-label="رقم الفاتورة"><?php echo htmlspecialchars($invoice['invoice_number']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" data-label="مبلغ الفاتورة"><?php echo number_format($invoice_amount, 0, '', ''); ?> ريال</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600" data-label="المبلغ المدفوع"><?php echo number_format($paid_amount, 0, '', ''); ?> ريال</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold <?php echo $remaining_amount > 0 ? 'text-red-600' : 'text-gray-500'; ?>" data-label="المبلغ المتبقي"><?php echo number_format($remaining_amount, 0, '', ''); ?> ريال</td>
                                            <td class="px-6 py-4 whitespace-nowrap" data-label="الحالة">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php 
                                                    if ($remaining_amount <= 0 && $invoice_amount > 0) echo 'bg-green-100 text-green-800';
                                                    elseif ($paid_amount > 0) echo 'bg-yellow-100 text-yellow-800';
                                                    else echo 'bg-blue-100 text-blue-800';
                                                ?>">
                                                    <?php 
                                                        if ($remaining_amount <= 0 && $invoice_amount > 0) echo 'مدفوعة بالكامل';
                                                        elseif ($paid_amount > 0) echo 'مدفوعة جزئياً';
                                                        else echo 'قيد الانتظار';
                                                    ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" data-label="تاريخ الإصدار"><?php echo date('d/m/Y', strtotime($invoice['created_at'])); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium" data-label="العمليات">
                                                <div class="flex space-x-2 space-x-reverse">
                                                    <a href="../invoices/view.php?id=<?php echo $invoice['id']; ?>" class="text-blue-600 hover:text-blue-900" title="عرض"><i class="fas fa-eye"></i></a>
                                                    <?php if ($remaining_amount > 0): ?>
                                                    <a href="../payments/add.php?invoice_id=<?php echo $invoice['id']; ?>" class="text-purple-600 hover:text-purple-900" title="تسجيل دفعة"><i class="fas fa-money-bill-wave"></i></a>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php elseif ($active_tab == 'payments'): ?>
                    <!-- Payments Tab -->
                    <div class="space-y-4">
                        <?php if (empty($payments)): ?>
                            <div class="text-center py-12"><i class="fas fa-credit-card text-4xl text-gray-300 mb-4"></i><p class="text-gray-500">لا توجد مدفوعات لهذا العميل</p></div>
                        <?php else: ?>
                            <div class="flex justify-between items-center mb-4"><h3 class="text-lg font-semibold">مدفوعات العميل</h3><a href="../payments/add.php?customer_id=<?php echo $customer_id; ?>" class="text-sm text-blue-600 hover:text-blue-800"><i class="fas fa-plus-circle ml-1"></i>إضافة دفعة جديدة</a></div>
                            <div class="bg-white overflow-hidden shadow-sm rounded-lg border border-gray-200">
                                <table class="min-w-full divide-y divide-gray-200 responsive-table">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">رقم الدفعة</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">رقم الفاتورة</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">المبلغ</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">طريقة الدفع</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">تاريخ الدفع</th>
                                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500">العمليات</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($payments as $payment): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900" data-label="رقم الدفعة"><?php echo htmlspecialchars($payment['payment_number']); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" data-label="رقم الفاتورة"><?php echo htmlspecialchars($payment['invoice_number'] ?? '-'); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900" data-label="المبلغ"><?php echo number_format($payment['amount'], 0, '', ''); ?> ريال</td>
                                            <td class="px-6 py-4 whitespace-nowrap" data-label="طريقة الدفع"><span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800"><?php echo htmlspecialchars($payment['payment_method']); ?></span></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500" data-label="تاريخ الدفع"><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium" data-label="العمليات"><div class="flex space-x-2 space-x-reverse"><a href="../payments/view.php?id=<?php echo $payment['id']; ?>" class="text-blue-600 hover:text-blue-900" title="عرض"><i class="fas fa-eye"></i></a></div></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
function copyPortalLink(token) {
    const baseUrl = window.location.origin;
    const portalUrl = `${baseUrl}/customer_portal/portal.php?token=${token}`;
    
    // Create temporary textarea
    const textarea = document.createElement('textarea');
    textarea.value = portalUrl;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    
    try {
        document.execCommand('copy');
        alert('✅ تم نسخ رابط البوابة!\n\n' + portalUrl + '\n\nيمكنك إرساله للعميل الآن.');
    } catch (err) {
        alert('❌ فشل نسخ الرابط. الرابط هو:\n\n' + portalUrl);
    }
    
    document.body.removeChild(textarea);
}
</script>

<?php include '../../includes/footer.php'; ?>