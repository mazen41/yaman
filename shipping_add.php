<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/database.php';

$page_title = 'إضافة شحنة جديدة';
$error_message = '';
$success_message = '';

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $order_id = intval($_POST['order_id']);
        $sender_id = intval($_POST['sender_id'] ?? 0);
        $tracking_number = trim($_POST['tracking_number'] ?? '');
        $shipping_cost = floatval($_POST['shipping_cost']);
        $delivery_address = trim($_POST['delivery_address']);
        $recipient_name = trim($_POST['recipient_name']);
        $recipient_phone = trim($_POST['recipient_phone']);
        $estimated_delivery = $_POST['estimated_delivery'] ?? null;
        $notes = trim($_POST['notes'] ?? '');
        $status = $_POST['status'] ?? 'preparing';

        // Validation
        if (empty($order_id)) throw new Exception('يرجى اختيار الطلب');
        if (empty($sender_id)) throw new Exception('يرجى اختيار المرسل');
        if (empty($delivery_address)) throw new Exception('عنوان التسليم مطلوب');
        if (empty($recipient_name)) throw new Exception('اسم المستلم مطلوب');
        if (empty($recipient_phone)) throw new Exception('رقم هاتف المستلم مطلوب');

        // Generate shipment number
        $shipment_number = 'SHP-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Check if shipment number exists
        $check = $db->prepare("SELECT id FROM shipments WHERE shipment_number = ?");
        $check->execute([$shipment_number]);
        if ($check->fetch()) {
            $shipment_number = 'SHP-' . date('Y') . '-' . str_pad(rand(10000, 99999), 5, '0', STR_PAD_LEFT);
        }

        // Insert shipment
        $stmt = $db->prepare("
            INSERT INTO shipments 
            (shipment_number, order_id, sender_id, tracking_number, shipping_cost,
             delivery_address, recipient_name, recipient_phone, estimated_delivery, notes, status, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $shipment_number, $order_id, $sender_id, $tracking_number ?: null,
            $shipping_cost, $delivery_address, $recipient_name, $recipient_phone,
            $estimated_delivery ?: null, $notes, $status, $_SESSION['user_id']
        ]);

        $shipment_id = $db->lastInsertId();

        // Update order shipping status
        $db->prepare("UPDATE customer_orders SET shipping_status = ? WHERE id = ?")->execute([$status, $order_id]);

        // Add initial tracking entry
        $db->prepare("
            INSERT INTO shipment_tracking 
            (shipment_id, status, description, occurred_at) 
            VALUES (?, ?, 'تم إنشاء الشحنة', NOW())
        ")->execute([$shipment_id, $status]);

        $success_message = 'تم إضافة الشحنة بنجاح!';
        header("refresh:2;url=view.php?id=$shipment_id");

    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Fetch orders with customer details
$orders = $db->query("
    SELECT 
        o.id, o.order_number, o.final_amount, o.status,
        c.name as customer_name, c.mobile_number, c.address, c.location_url, c.city_name,
        (SELECT COUNT(*) FROM shipments WHERE order_id = o.id) as shipment_count
    FROM customer_orders o
    LEFT JOIN customers c ON o.customer_id = c.id
    WHERE o.status != 'cancelled'
    ORDER BY o.created_at DESC
    LIMIT 100
")->fetchAll();

// Fetch shipping companies
$companies = $db->query("
    SELECT * FROM shipping_companies 
    WHERE is_active = 1 
    ORDER BY company_name
")->fetchAll();

// Fetch senders
$senders = $db->query("
    SELECT id, name, phone, email 
    FROM senders 
    ORDER BY name ASC
")->fetchAll();

include '../../includes/header.php';
?>

<style>
.order-details-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    border-radius: 12px;
    padding: 1.5rem;
    margin-bottom: 1.5rem;
    display: none;
}

.order-details-card.active {
    display: block;
    animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.info-row {
    display: flex;
    justify-content: space-between;
    padding: 0.5rem 0;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.info-row:last-child {
    border-bottom: none;
}
</style>

<div class="min-h-screen bg-gray-50 py-6" dir="rtl">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-amber-600 to-emerald-700 shadow-xl rounded-2xl mb-8 overflow-hidden">
            <div class="px-8 py-6">
                <h1 class="text-3xl font-bold text-white flex items-center gap-3">
                    <i class="fas fa-shipping-fast"></i>
                    إضافة شحنة جديدة
                </h1>
                <p class="text-amber-100 mt-2">اختر الطلب وسيتم تعبئة بيانات العميل تلقائياً</p>
            </div>
        </div>

        <?php if ($success_message): ?>
            <div class="bg-amber-100 border-r-4 border-amber-500 text-amber-700 p-4 rounded-lg mb-6 shadow-md">
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-2xl ml-3"></i>
                    <div>
                        <p class="font-medium"><?php echo $success_message; ?></p>
                        <p class="text-sm mt-1">جاري التحويل إلى صفحة الشحنة...</p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="bg-red-100 border-r-4 border-red-500 text-red-700 p-4 rounded-lg mb-6 shadow-md">
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle text-2xl ml-3"></i>
                    <p class="font-medium"><?php echo $error_message; ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Form -->
        <form method="POST" class="bg-white rounded-xl shadow-lg p-8 space-y-6" id="shipmentForm">
            
            <!-- Order Selection -->
            <div>
                <label class="block text-sm font-bold text-gray-700 mb-2">
                    <i class="fas fa-shopping-cart text-blue-600 ml-1"></i>
                    اختر الطلب <span class="text-red-500">*</span>
                </label>
                <select 
                    name="order_id" 
                    id="order_id"
                    required
                    class="w-full px-4 py-3 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-amber-500 text-lg font-medium"
                    onchange="loadOrderDetails(this.value)"
                >
                    <option value="">-- اختر الطلب --</option>
                    <?php foreach ($orders as $order): ?>
                        <option value="<?php echo $order['id']; ?>" 
                                data-customer="<?php echo htmlspecialchars($order['customer_name'] ?? ''); ?>"
                                data-phone="<?php echo htmlspecialchars($order['mobile_number'] ?? ''); ?>"
                                data-address="<?php echo htmlspecialchars($order['address'] ?? ''); ?>"
                                data-location="<?php echo htmlspecialchars($order['location_url'] ?? ''); ?>"
                                data-city="<?php echo htmlspecialchars($order['city_name'] ?? ''); ?>"
                                data-amount="<?php echo $order['final_amount']; ?>"
                                data-status="<?php echo $order['status']; ?>">
                            <?php echo htmlspecialchars($order['order_number']); ?> - 
                            <?php echo htmlspecialchars($order['customer_name'] ?? 'غير محدد'); ?> - 
                            <?php echo number_format($order['final_amount'], 2); ?> ر.ي
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Order Details Card (Hidden until order selected) -->
            <div id="orderDetailsCard" class="order-details-card">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <i class="fas fa-info-circle"></i>
                    تفاصيل الطلب
                </h3>
                <div class="space-y-2">
                    <div class="info-row">
                        <span class="font-semibold">رقم الطلب:</span>
                        <span id="detail-order-number">-</span>
                    </div>
                    <div class="info-row">
                        <span class="font-semibold">اسم العميل:</span>
                        <span id="detail-customer-name">-</span>
                    </div>
                    <div class="info-row">
                        <span class="font-semibold">رقم الهاتف:</span>
                        <span id="detail-phone">-</span>
                    </div>
                    <div class="info-row">
                        <span class="font-semibold">المبلغ الإجمالي:</span>
                        <span id="detail-amount">-</span>
                    </div>
                    <div class="info-row">
                        <span class="font-semibold">حالة الطلب:</span>
                        <span id="detail-status">-</span>
                    </div>
                </div>
            </div>

            <!-- Customer & Delivery Info -->
            <div id="deliverySection" style="display: none;">
                <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2 bg-blue-50 px-4 py-3 rounded-lg">
                    <i class="fas fa-user-circle text-blue-600"></i>
                    بيانات العميل والتوصيل
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            اسم المستلم <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="text" 
                            name="recipient_name" 
                            id="recipient_name"
                            required
                            class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-amber-500"
                        >
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            رقم هاتف المستلم <span class="text-red-500">*</span>
                        </label>
                        <input 
                            type="tel" 
                            name="recipient_phone" 
                            id="recipient_phone"
                            required
                            class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-amber-500"
                        >
                    </div>
                </div>

                <div class="mt-6">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        عنوان التسليم <span class="text-red-500">*</span>
                    </label>
                    <textarea 
                        name="delivery_address" 
                        id="delivery_address"
                        required
                        rows="3"
                        class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-amber-500"
                    ></textarea>
                    <p class="text-xs text-gray-500 mt-1">سيتم تعبئة العنوان تلقائياً من بيانات العميل</p>
                </div>

                <!-- Shipping Company -->
                <div class="mt-6">
                    <h3 class="text-xl font-bold text-gray-800 mb-4 flex items-center gap-2 bg-amber-50 px-4 py-3 rounded-lg">
                        <i class="fas fa-truck text-amber-600"></i>
                        معلومات الشحن
                    </h3>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                اسم المرسل <span class="text-red-500">*</span>
                            </label>
                            <div class="flex gap-2">
                                <select 
                                    name="sender_id" 
                                    id="sender_id"
                                    required
                                    class="flex-1 px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-amber-500"
                                >
                                    <option value="">-- اختر المرسل --</option>
                                    <?php foreach ($senders as $sender): ?>
                                        <option value="<?php echo $sender['id']; ?>">
                                            <?php echo htmlspecialchars($sender['name']); ?>
                                            <?php if ($sender['phone']): ?>
                                                - <?php echo htmlspecialchars($sender['phone']); ?>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="senders.php" target="_blank" class="btn btn-outline-primary" title="إدارة المرسلين">
                                    <i class="fas fa-cog"></i>
                                </a>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                <i class="fas fa-info-circle"></i> 
                                لإضافة مرسل جديد، اضغط على زر الإعدادات
                            </p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                رقم التتبع
                            </label>
                            <input 
                                type="text" 
                                name="tracking_number" 
                                id="tracking_number"
                                class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-amber-500"
                                placeholder="اختياري"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                تكلفة الشحن <span class="text-red-500">*</span>
                            </label>
                            <input 
                                type="number" 
                                name="shipping_cost" 
                                id="shipping_cost"
                                step="0.01"
                                min="0"
                                value="0"
                                required
                                class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-amber-500"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                تاريخ التسليم المتوقع
                            </label>
                            <input 
                                type="date" 
                                name="estimated_delivery" 
                                id="estimated_delivery"
                                class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-amber-500"
                            >
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                حالة الشحنة
                            </label>
                            <select 
                                name="status" 
                                id="status"
                                class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-amber-500"
                            >
                                <option value="preparing">قيد التجهيز</option>
                                <option value="picked_up">تم الاستلام</option>
                                <option value="in_transit">في الطريق</option>
                                <option value="out_for_delivery">خرج للتوصيل</option>
                                <option value="delivered">تم التسليم</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-6">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            ملاحظات
                        </label>
                        <textarea 
                            name="notes" 
                            id="notes"
                            rows="3"
                            class="w-full px-4 py-2 border-2 border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-amber-500"
                            placeholder="أي ملاحظات إضافية..."
                        ></textarea>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="flex gap-4 pt-6 border-t">
                    <button 
                        type="submit" 
                        class="flex-1 bg-amber-600 text-white px-6 py-3 rounded-lg font-bold hover:bg-amber-700 transition-all duration-300 shadow-lg hover:shadow-xl transform hover:scale-105">
                        <i class="fas fa-check-circle ml-2"></i>
                        إنشاء الشحنة
                    </button>
                    <a 
                        href="index.php" 
                        class="flex-1 bg-gray-200 text-gray-700 px-6 py-3 rounded-lg font-bold hover:bg-gray-300 transition-all duration-300 text-center">
                        <i class="fas fa-times ml-2"></i>
                        إلغاء
                    </a>
                </div>
            </div>

        </form>

    </div>
</div>

<script>
function loadOrderDetails(orderId) {
    const select = document.getElementById('order_id');
    const selectedOption = select.options[select.selectedIndex];
    
    if (!orderId || orderId === '') {
        document.getElementById('orderDetailsCard').classList.remove('active');
        document.getElementById('deliverySection').style.display = 'none';
        return;
    }
    
    // Get data from selected option
    const customerName = selectedOption.dataset.customer || '';
    const phone = selectedOption.dataset.phone || '';
    const address = selectedOption.dataset.address || '';
    const location = selectedOption.dataset.location || '';
    const city = selectedOption.dataset.city || '';
    const amount = selectedOption.dataset.amount || '0';
    const status = selectedOption.dataset.status || '';
    const orderNumber = selectedOption.textContent.split(' - ')[0];
    
    // Show order details card
    document.getElementById('orderDetailsCard').classList.add('active');
    document.getElementById('detail-order-number').textContent = orderNumber;
    document.getElementById('detail-customer-name').textContent = customerName;
    document.getElementById('detail-phone').textContent = phone;
    document.getElementById('detail-amount').textContent = parseFloat(amount).toFixed(2) + ' ر.ي';
    document.getElementById('detail-status').textContent = status;
    
    // Auto-fill customer data
    document.getElementById('recipient_name').value = customerName;
    document.getElementById('recipient_phone').value = phone;
    document.getElementById('delivery_address').value = address + (city ? '\n' + city : '');
    
    // Show delivery section
    document.getElementById('deliverySection').style.display = 'block';
    
    // Smooth scroll to delivery section
    setTimeout(() => {
        document.getElementById('deliverySection').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }, 300);
}
</script>

<?php include '../../includes/footer.php'; ?>
