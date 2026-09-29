<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/database.php';

// Check if order ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit();
}

$order_id = intval($_GET['id']);

// Fetch order details
try {
    $stmt = $db->prepare("
        SELECT o.*, 
               c.name as customer_name, 
               c.customer_code, 
               c.mobile_number, 
               c.whatsapp_number, 
               c.email, 
               c.city_name, 
               c.address,
               (SELECT GROUP_CONCAT(invoice_number SEPARATOR ', ') 
                FROM customer_invoices 
                WHERE order_id = o.id) as invoice_numbers
        FROM customer_orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        die('لم يتم العثور على الطلب.');
    }
    
    // Initialize missing fields to prevent undefined variable warnings
    if (!isset($order['subtotal_amount'])) $order['subtotal_amount'] = $order['total_amount'] ?? 0;
    if (!isset($order['discount_type'])) $order['discount_type'] = null;
    if (!isset($order['discount_value'])) $order['discount_value'] = 0;
    if (!isset($order['discount_amount'])) $order['discount_amount'] = 0;
    if (!isset($order['final_amount'])) $order['final_amount'] = $order['total_amount'] ?? 0;
    if (!isset($order['shipping_cost'])) $order['shipping_cost'] = 0;
    if (!isset($order['paid_amount'])) $order['paid_amount'] = 0;
    
    // Calculate remaining amount
    $remaining_amount = $order['final_amount'] - $order['paid_amount'];

    // Fetch order items
    $items_stmt = $db->prepare("
        SELECT * FROM order_items WHERE order_id = ? ORDER BY id
    ");
    $items_stmt->execute([$order_id]);
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch order status history (with error handling)
    $status_history = [];
    try {
        $history_stmt = $db->prepare("
            SELECT h.*, u.username 
            FROM order_status_history h
            LEFT JOIN users u ON h.created_by = u.id
            WHERE h.order_id = ? 
            ORDER BY h.created_at DESC
        ");
        $history_stmt->execute([$order_id]);
        $status_history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table doesn't exist yet
        $status_history = [];
    }
    
    // Fetch order notes (with error handling)
    $order_notes = [];
    try {
        $notes_stmt = $db->prepare("
            SELECT n.*, u.username 
            FROM order_notes n
            LEFT JOIN users u ON n.created_by = u.id
            WHERE n.order_id = ? 
            ORDER BY n.created_at DESC
        ");
        $notes_stmt->execute([$order_id]);
        $order_notes = $notes_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Table doesn't exist yet
        $order_notes = [];
    }

    // Fetch company settings (with error handling)
    try {
        $settings_stmt = $db->query("SELECT setting_key, setting_value FROM system_settings");
        $company_settings = $settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $e) {
        // If settings table doesn't exist, use defaults
        $company_settings = [
            'company_name' => 'شركة يمان',
            'company_address' => 'الرياض, المملكة العربية السعودية',
            'company_phone' => '+966 11 123 4567',
            'company_email' => 'info@yassin.com'
        ];
    }

} catch (PDOException $e) {
    die('حدث خطأ في قاعدة البيانات: ' . $e->getMessage());
}

$page_title = 'طباعة الطلب #' . htmlspecialchars($order['order_number']);

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f3f4f6;
        }
        .print-container {
            max-width: 800px;
            margin: 2rem auto;
            background-color: white;
            padding: 2.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        }
        table th, table td {
            padding: 0.75rem;
            text-align: right;
            border-bottom: 1px solid #e5e7eb;
        }
        table th {
            background-color: #f9fafb;
            font-weight: 600;
        }
        .print-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #e5e7eb;
            padding-bottom: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .no-print {
            margin: 2rem auto;
            text-align: center;
        }
        @media print {
            body {
                background-color: white;
            }
            .print-container {
                margin: 0;
                box-shadow: none;
                border-radius: 0;
                max-width: 100%;
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>

    <div class="no-print max-w-lg mx-auto flex justify-center space-x-4 space-x-reverse">
        <button onclick="window.print()" class="px-6 py-2 bg-blue-600 text-white font-semibold rounded-lg shadow-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-75">
            طباعة
        </button>
        <a href="view.php?id=<?php echo $order_id; ?>" class="px-6 py-2 bg-gray-600 text-white font-semibold rounded-lg shadow-md hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-gray-500 focus:ring-opacity-75">
            العودة للطلب
        </a>
    </div>

    <div class="print-container">
        <div class="print-header">
            <div>
                <h1 class="text-3xl font-bold text-gray-800"><?php echo htmlspecialchars($company_settings['company_name'] ?? 'اسم الشركة'); ?></h1>
                <p class="text-gray-600"><?php echo htmlspecialchars($company_settings['company_address'] ?? 'العنوان'); ?></p>
                <p class="text-gray-600">هاتف: <?php echo htmlspecialchars($company_settings['company_phone'] ?? 'رقم الهاتف'); ?></p>
                <p class="text-gray-600">البريد الإلكتروني: <?php echo htmlspecialchars($company_settings['company_email'] ?? 'البريد الإلكتروني'); ?></p>
            </div>
            <div class="text-left">
                <h2 class="text-2xl font-semibold text-gray-700">طلب عميل</h2>
                <p class="text-gray-600"><strong>رقم الطلب:</strong> #<?php echo htmlspecialchars($order['order_number']); ?></p>
                <p class="text-gray-600"><strong>تاريخ الطلب:</strong> <?php echo date('Y-m-d', strtotime($order['created_at'])); ?></p>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-8 mb-8">
            <div>
                <h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-2">معلومات العميل</h3>
                <p><strong>اسم العميل:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                <p><strong>رقم العميل:</strong> <?php echo htmlspecialchars($order['customer_code']); ?></p>
                <p><strong>رقم الجوال:</strong> <?php echo htmlspecialchars($order['mobile_number']); ?></p>
                <p><strong>رقم الواتساب:</strong> <?php echo htmlspecialchars($order['whatsapp_number']); ?></p>
                <p><strong>المدينة:</strong> <?php echo htmlspecialchars($order['city_name']); ?></p>
                <p><strong>البريد الإلكتروني:</strong> <?php echo htmlspecialchars($order['email']); ?></p>
            </div>
            <div>
                <h3 class="text-lg font-semibold text-gray-700 border-b pb-2 mb-2">تفاصيل الطلب</h3>
                <p><strong>رقم الطلب:</strong> <?php echo htmlspecialchars(formatOrderNumber($order['order_number'])); ?></p>
                <p><strong>تاريخ الإنشاء:</strong> <?php echo date('Y-m-d', strtotime($order['created_at'])); ?></p>
                <?php if (!empty($order['invoice_numbers'])): ?>
                <p><strong>رقم الفاتورة:</strong> <?php echo htmlspecialchars($order['invoice_numbers']); ?></p>
                <?php endif; ?>
                <p><strong>طريقة الدفع:</strong> <?php echo htmlspecialchars($order['payment_method'] ?? '-'); ?></p>
                <p><strong>طريقة الشحن:</strong> <?php echo htmlspecialchars($order['shipping_method'] ?? '-'); ?></p>
                <p><strong>تاريخ التسليم المتوقع:</strong> <?php echo htmlspecialchars($order['expected_delivery_date'] ?? '-'); ?></p>
            </div>
        </div>

        <div>
            <h3 class="text-lg font-semibold text-gray-700 mb-2">المنتجات المطلوبة</h3>
            <table class="min-w-full">
                <thead>
                    <tr>
                        <th class="text-right">#</th>
                        <th class="text-right">المنتج</th>
                        <th class="text-center">الكمية</th>
                        <th class="text-left">سعر الوحدة</th>
                        <th class="text-left">الإجمالي</th>
                    </tr>
                </thead>
                <tbody class="bg-white">
                    <?php foreach ($items as $index => $item): ?>
                        <tr>
                            <td><?php echo $index + 1; ?></td>
                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                            <td class="text-left"><?php echo number_format($item['unit_price'], 0, '', ''); ?> ريال</td>
                            <td class="text-left"><?php echo number_format($item['total_price'], 0, '', ''); ?> ريال</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="mt-8 flex justify-end">
            <div class="w-full max-w-xs">
                <div class="space-y-2">
                    <div class="flex justify-between">
                        <span class="font-semibold text-gray-600">المجموع الفرعي:</span>
                        <span><?php echo number_format($order['subtotal_amount'], 0, '', ''); ?> ريال</span>
                    </div>
                    <?php if ($order['discount_amount'] > 0): ?>
                    <div class="flex justify-between text-amber-600">
                        <span class="font-semibold">تكلفة الشحن:</span>
                        <span>-<?php echo number_format($order['discount_amount'], 0, '', ''); ?> ريال</span>
                    </div>
                    <?php endif; ?>
                    <?php if ($order['shipping_cost'] > 0): ?>
                    <div class="flex justify-between">
                        <span class="font-semibold text-gray-600">تكلفة الشحن:</span>
                        <span><?php echo number_format($order['shipping_cost'], 0, '', ''); ?> ريال</span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between text-xl font-bold text-gray-800 border-t-2 pt-2 mt-2">
                        <span>الإجمالي النهائي:</span>
                        <span><?php echo number_format($order['final_amount'], 0, '', ''); ?> ريال</span>
                    </div>
                    <?php if ($order['paid_amount'] > 0): ?>
                    <div class="flex justify-between text-blue-600 border-t pt-2">
                        <span class="font-semibold">المبلغ المدفوع:</span>
                        <span class="font-bold"><?php echo number_format($order['paid_amount'], 0, '', ''); ?> ريال</span>
                    </div>
                    <div class="flex justify-between text-lg font-bold <?php echo $remaining_amount > 0 ? 'text-orange-600' : 'text-amber-600'; ?> border-t-2 pt-2">
                        <span>المبلغ المتبقي:</span>
                        <span><?php echo number_format($remaining_amount, 0, '', ''); ?> ريال</span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if (!empty($order['notes'])): ?>
        <div class="mt-8 border-t pt-4">
            <h4 class="font-semibold text-gray-700">ملاحظات الطلب:</h4>
            <p class="text-gray-600"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($status_history)): ?>
        <div class="mt-8 border-t pt-4">
            <h4 class="font-semibold text-gray-700 mb-3">سجل الحالة:</h4>
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="bg-gray-50">
                        <th class="text-right py-2 px-3">التاريخ</th>
                        <th class="text-right py-2 px-3">الحالة</th>
                        <th class="text-right py-2 px-3">بواسطة</th>
                        <th class="text-right py-2 px-3">ملاحظات</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($status_history as $history): ?>
                    <tr>
                        <td class="py-2 px-3"><?php echo date('Y-m-d H:i', strtotime($history['created_at'])); ?></td>
                        <td class="py-2 px-3"><?php echo htmlspecialchars($history['status']); ?></td>
                        <td class="py-2 px-3"><?php echo htmlspecialchars($history['username'] ?? '-'); ?></td>
                        <td class="py-2 px-3"><?php echo htmlspecialchars($history['notes'] ?? '-'); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <?php if (!empty($order_notes)): ?>
        <div class="mt-8 border-t pt-4">
            <h4 class="font-semibold text-gray-700 mb-3">ملاحظات إضافية:</h4>
            <?php foreach ($order_notes as $note): ?>
            <div class="mb-3 p-3 bg-gray-50 rounded">
                <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($note['note'])); ?></p>
                <p class="text-xs text-gray-500 mt-1">
                    بواسطة: <?php echo htmlspecialchars($note['username'] ?? 'مجهول'); ?> - 
                    <?php echo date('Y-m-d H:i', strtotime($note['created_at'])); ?>
                </p>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="mt-12 text-center text-gray-500 text-sm">
            <p>شكراً لتعاملكم معنا!</p>
            <p><?php echo htmlspecialchars($company_settings['company_name'] ?? 'اسم الشركة'); ?></p>
        </div>

    </div>

</body>
</html>
