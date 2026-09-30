<?php
session_start();

// --- 1. CONFIG & PERMISSIONS ---
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../../config/database.php';
require_once '../../includes/check_permissions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$order_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Permissions check
// CHANGE START: Separate permissions for view, approve, and reject
$can_view = hasPermission($user_id, 'shop_orders', 'view');
$can_approve = hasPermission($user_id, 'shop_orders', 'approve');
$can_reject = hasPermission($user_id, 'shop_orders', 'reject');
// CHANGE END

// If user does not have view permission, they should not see anything
if (!$can_view) {
    echo "<div class='alert alert-danger'>ليس لديك صلاحية لعرض تفاصيل الطلبات.</div>";
    include '../../includes/footer.php';
    exit();
}


$page_title = 'عرض تفاصيل الطلب';
$error_message = '';
$success_message = '';

// --- 2. HANDLE POST ACTIONS (APPROVE / REJECT) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') { // No global permission check here, specific checks inside
    $action = $_POST['action'] ?? '';
    
    // --- APPROVE ACTION ---
    if ($action === 'approve') {
        // CHANGE START: Check for specific approve permission
        if (!$can_approve) {
            $error_message = "ليس لديك صلاحية لاعتماد الطلبات.";
        } else {
        // CHANGE END
            $bank_account_id = filter_input(INPUT_POST, 'bank_account_id', FILTER_VALIDATE_INT);
            $order_total_amount = filter_input(INPUT_POST, 'order_total', FILTER_VALIDATE_FLOAT);

            if (!$bank_account_id || !$order_total_amount) {
                $error_message = "يرجى اختيار الحساب البنكي. المبلغ غير صحيح.";
            } else {
                try {
                    $db->beginTransaction();

                    // 1. Lock bank account row to prevent race conditions
                    $stmt = $db->prepare("SELECT id, bank_name, current_balance FROM bank_accounts WHERE id = ? FOR UPDATE");
                    $stmt->execute([$bank_account_id]);
                    $bank_account = $stmt->fetch(PDO::FETCH_ASSOC);

                    if (!$bank_account) throw new Exception("الحساب البنكي المختار غير موجود.");

                    // 2. Fetch order items to update product stock
                    $items_stmt = $db->prepare("SELECT product_id, quantity FROM shop_order_items WHERE order_id = ?");
                    $items_stmt->execute([$order_id]);
                    $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

                 // 3. Decrease product quantities
    $update_product_qty_stmt = $db->prepare("
        UPDATE products 
        SET product_quantity = product_quantity - ? 
        WHERE id = ? AND product_quantity >= ?
    ");

    foreach ($order_items as $item) {
        $update_product_qty_stmt->execute([
            $item['quantity'],
            $item['product_id'],
            $item['quantity']
        ]);

        if ($update_product_qty_stmt->rowCount() === 0) {
            throw new Exception("الكمية غير كافية في المخزون للمنتج رقم #{$item['product_id']}.");
        }
    }
                    // 4. Update bank account balance
                    $new_balance = $bank_account['current_balance'] + $order_total_amount;
                    $update_bank_stmt = $db->prepare("UPDATE bank_accounts SET current_balance = ? WHERE id = ?");
                    $update_bank_stmt->execute([$new_balance, $bank_account_id]);

                    // 5. Log the bank transaction
                    $log_desc = "إيداع من الطلب رقم #{$_POST['order_number']}";
                    $log_stmt = $db->prepare("INSERT INTO bank_account_transactions (account_id, transaction_type, amount, balance_before, balance_after, description, created_by) VALUES (?, 'deposit', ?, ?, ?, ?, ?)");
                    $log_stmt->execute([$bank_account_id, $order_total_amount, $bank_account['current_balance'], $new_balance, $log_desc, $user_id]);

                    // 6. Update order status to 'Approved'
                    $update_order_stmt = $db->prepare("UPDATE shop_orders SET order_status = 'طلب معتمد', approved_by = ? WHERE id = ?");
                    $update_order_stmt->execute([$user_id, $order_id]);

                    $db->commit();
                    $success_message = "تم اعتماد الطلب بنجاح! تم تحديث المخزون ورصيد البنك.";

                } catch (Exception $e) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    $error_message = "فشل اعتماد الطلب: " . $e->getMessage();
                }
            }
        }
    }
    // --- REJECT ACTION ---
    elseif ($action === 'reject') {
        // CHANGE START: Check for specific reject permission
        if (!$can_reject) {
            $error_message = "ليس لديك صلاحية لرفض الطلبات.";
        } else {
        // CHANGE END
            $rejection_reason = trim($_POST['rejection_reason'] ?? '');
            if (empty($rejection_reason)) {
                $error_message = "سبب الرفض مطلوب.";
            } else {
                $stmt = $db->prepare("UPDATE shop_orders SET order_status = 'مرفوض', rejection_reason = ? WHERE id = ?");
                if ($stmt->execute([$rejection_reason, $order_id])) {
                    $success_message = "تم رفض الطلب بنجاح.";
                } else {
                    $error_message = "فشل تحديث حالة الطلب.";
                }
            }
        }
    }
}

// --- 3. FETCH DATA FOR VIEW ---
try {
    // Fetch main order details with full customer information
    $stmt = $db->prepare("
        SELECT o.*,
               c.name as customer_name,
               c.mobile_number,
               c.whatsapp_number,
               c.email,
               c.address,
               c.city_name,
               c.location_area,
               c.customer_code
        FROM shop_orders o
        LEFT JOIN customers c ON o.customer_id = c.id
        WHERE o.id = ?
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        throw new Exception("الطلب غير موجود.");
    }

    // Fetch order items with product details, SKU, and image
    $items_stmt = $db->prepare("
        SELECT
            soi.*,
            p.sku,
            p.purchase_amount,
            pi.image_url,
            (soi.unit_price * soi.quantity) as item_total_sale,
            (p.purchase_amount * soi.quantity) as item_total_cost,
            ((soi.unit_price * soi.quantity) - (p.purchase_amount * soi.quantity)) as item_profit,
            CASE WHEN soi.original_unit_price > soi.unit_price THEN (soi.original_unit_price - soi.unit_price) * soi.quantity ELSE 0 END as item_discount_amount
        FROM shop_order_items soi
        LEFT JOIN products p ON soi.product_id = p.id
        LEFT JOIN product_images pi ON pi.product_id = soi.product_id AND pi.is_main = 1
        WHERE soi.order_id = ?
    ");
    $items_stmt->execute([$order_id]);
    $order_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch active bank accounts for the approval modal (only if user can approve)
    $bank_accounts = [];
    if ($can_approve) {
        $bank_accounts = $db->query("SELECT id, bank_name, account_number FROM bank_accounts WHERE is_active = 1")->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $error_message = $e->getMessage();
    $order = null;
}

include '../../includes/header.php';
?>
<style>
    :root {
        --primary-gold: #C7A46D;
        --primary-gold-dark: #B8956A;
        --gray-50: #f9fafb;
        --gray-100: #f3f4f6;
        --gray-200: #e5e7eb;
        --gray-300: #d1d5db;
        --gray-600: #4b5563;
        --gray-700: #374151;
        --gray-800: #1f2937;
        --gray-900: #111827;
    }

    .card {
        background: white;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1), 0 1px 2px rgba(0,0,0,0.06);
        margin-bottom: 24px;
        border: 1px solid var(--gray-200);
    }

    .card-header {
        padding: 20px 24px;
        border-bottom: 1px solid var(--gray-200);
        display: flex;
        align-items: center;
        gap: 12px;
        background: linear-gradient(to left, rgba(199, 164, 109, 0.05), transparent);
    }

    .card-header h2 {
        font-size: 1.25rem;
        font-weight: 700;
        color: var(--gray-900);
        margin: 0;
    }

    .card-body {
        padding: 24px;
    }

    .product-card {
        background: white;
        border: 1px solid var(--gray-200);
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 16px;
        transition: all 0.2s ease;
    }

    .product-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
        border-color: var(--primary-gold);
    }

    .product-image {
        width: 120px;
        height: 120px;
        object-fit: cover;
        border-radius: 12px;
        border: 2px solid var(--gray-200);
        background: var(--gray-100);
    }

    .product-image-placeholder {
        width: 120px;
        height: 120px;
        background: linear-gradient(135deg, var(--gray-100), var(--gray-200));
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 2px dashed var(--gray-300);
    }

    .variant-tag {
        display: inline-block;
        background: rgba(199, 164, 109, 0.1);
        color: var(--primary-gold);
        border: 1px solid rgba(199, 164, 109, 0.2);
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 12px;
        font-weight: 600;
        margin-left: 6px;
        margin-bottom: 6px;
    }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 13px;
        font-weight: 600;
    }

    .status-new {
        background: #fef3c7;
        color: #92400e;
    }

    .status-approved {
        background: #d1fae5;
        color: #065f46;
    }

    .status-rejected {
        background: #fee2e2;
        color: #991b1b;
    }

    .status-completed {
        background: #dbeafe;
        color: #1e40af;
    }

    .status-cancelled {
        background: #f3f4f6;
        color: #4b5563;
    }

    .info-item {
        display: flex;
        justify-content: space-between;
        padding: 14px 0;
        border-bottom: 1px solid var(--gray-100);
    }

    .info-item:last-child {
        border-bottom: none;
    }

    .info-label {
        font-weight: 600;
        color: var(--gray-600);
        font-size: 14px;
    }

    .info-value {
        font-weight: 500;
        color: var(--gray-900);
        font-size: 14px;
    }

    .info-value.highlight {
        font-weight: 700;
        color: var(--primary-gold);
    }

    .total-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 16px 0;
        border-top: 2px solid var(--gray-900);
        margin-top: 16px;
    }

    .total-label {
        font-size: 18px;
        font-weight: 700;
        color: var(--gray-900);
    }

    .total-value {
        font-size: 22px;
        font-weight: 800;
        color: #10b981;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        padding: 12px 24px;
        border-radius: 10px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.2s ease;
        border: none;
        text-decoration: none;
        font-size: 14px;
    }

    .btn-success {
        background: linear-gradient(135deg, #10b981, #059669);
        color: white;
        box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
    }

    .btn-success:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }

    .btn-danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
        color: white;
        box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    }

    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
    }

    .btn-secondary {
        background: linear-gradient(135deg, #6b7280, #4b5563);
        color: white;
        box-shadow: 0 2px 8px rgba(107, 114, 128, 0.3);
    }

    .btn-secondary:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(107, 114, 128, 0.4);
    }

    .btn-whatsapp {
        background: linear-gradient(135deg, #25d366, #128c7e);
        color: white;
        box-shadow: 0 2px 8px rgba(37, 211, 102, 0.3);
    }

    .btn-whatsapp:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(37, 211, 102, 0.4);
    }

    .btn-icon {
        width: 36px;
        height: 36px;
        padding: 0;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: var(--gray-100);
        color: var(--gray-600);
        transition: all 0.2s ease;
    }

    .btn-icon:hover {
        background: var(--primary-gold);
        color: white;
    }

    .modal-overlay {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,0.6);
        z-index: 999;
        align-items: center;
        justify-content: center;
        backdrop-filter: blur(4px);
        padding: 16px;
    }

    .modal-box {
        background: white;
        border-radius: 16px;
        padding: 32px;
        max-width: 500px;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        text-align: right;
        box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
    }

    .section-title {
        font-size: 13px;
        font-weight: 700;
        color: var(--gray-600);
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 12px;
        padding-bottom: 8px;
        border-bottom: 2px solid var(--primary-gold);
    }

    .price-original {
        text-decoration: line-through;
        color: var(--gray-400);
        font-size: 13px;
    }

    .price-discount {
        color: #ef4444;
        font-weight: 600;
        font-size: 14px;
    }

    .price-final {
        color: var(--gray-900);
        font-weight: 700;
        font-size: 16px;
    }

    .price-total {
        color: #10b981;
        font-weight: 800;
        font-size: 18px;
    }

    @media (max-width: 1024px) {
        .product-image {
            width: 100px;
            height: 100px;
        }
        .product-image-placeholder {
            width: 100px;
            height: 100px;
        }
    }

    @media (max-width: 768px) {
        .card-header {
            padding: 16px 20px;
        }

        .card-header h2 {
            font-size: 1.1rem;
        }

        .card-body {
            padding: 20px;
        }

        .product-card {
            padding: 16px;
        }

        .product-image {
            width: 80px;
            height: 80px;
        }

        .product-image-placeholder {
            width: 80px;
            height: 80px;
        }

        .info-item {
            flex-direction: column;
            gap: 4px;
        }

        .info-label {
            font-size: 13px;
        }

        .info-value {
            font-size: 13px;
        }

        .btn {
            padding: 10px 20px;
            font-size: 13px;
        }

        .modal-box {
            padding: 24px;
        }

        .total-label {
            font-size: 16px;
        }

        .total-value {
            font-size: 18px;
        }
    }

    @media (max-width: 480px) {
        .card-body {
            padding: 16px;
        }

        .product-card {
            padding: 12px;
        }

        .product-image {
            width: 70px;
            height: 70px;
        }

        .product-image-placeholder {
            width: 70px;
            height: 70px;
        }

        .btn {
            padding: 10px 16px;
            font-size: 12px;
            width: 100%;
        }

        .modal-box {
            padding: 20px;
        }

        .section-title {
            font-size: 12px;
        }
    }
</style>

<div class="container-fluid pt-32 pb-8 px-4" dir="rtl">
    <div class="page-header mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div class="flex items-center gap-4">
            <div>
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-800"><?php echo $page_title; ?></h1>
                <p class="text-gray-500 text-sm mt-1">#<?php echo htmlspecialchars($order['order_number'] ?? ''); ?></p>
            </div>
            <?php if ($order): ?>
                <?php
                $status_class = '';
                $status_icon = '';
                switch($order['order_status']) {
                    case 'طلب جديد':
                        $status_class = 'status-new';
                        $status_icon = 'fa-clock';
                        break;
                    case 'طلب معتمد':
                        $status_class = 'status-approved';
                        $status_icon = 'fa-check-circle';
                        break;
                    case 'مرفوض':
                        $status_class = 'status-rejected';
                        $status_icon = 'fa-times-circle';
                        break;
                    case 'مكتمل':
                        $status_class = 'status-completed';
                        $status_icon = 'fa-check-double';
                        break;
                    case 'ملغي':
                        $status_class = 'status-cancelled';
                        $status_icon = 'fa-ban';
                        break;
                    default:
                        $status_class = 'status-new';
                        $status_icon = 'fa-circle';
                }
                ?>
                <span class="status-badge <?php echo $status_class; ?>">
                    <i class="fas <?php echo $status_icon; ?>"></i>
                    <?php echo htmlspecialchars($order['order_status']); ?>
                </span>
            <?php endif; ?>
        </div>
        <a href="shop_orders_manage.php" class="btn btn-secondary"><i class="fas fa-arrow-left mr-2"></i> العودة لقائمة الطلبات</a>
    </div>

    <?php if ($success_message): ?><div class="alert alert-success"><?php echo $success_message; ?></div><?php endif; ?>
    <?php if ($error_message): ?><div class="alert alert-danger"><?php echo $error_message; ?></div><?php endif; ?>

    <?php if ($order): ?>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Main Column - Order Items -->
        <div class="lg:col-span-2 space-y-6">
            <!-- Order Items Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-shopping-bag text-[#C7A46D]"></i>
                    <h2>منتجات الطلب</h2>
                    <span class="mr-auto bg-gray-100 text-gray-600 px-3 py-1 rounded-full text-sm font-semibold">
                        <?php echo count($order_items); ?> منتج
                    </span>
                </div>
                <div class="card-body">
                    <?php if (empty($order_items)): ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-box-open text-4xl mb-4 text-gray-300"></i>
                            <p>لا توجد منتجات في هذا الطلب</p>
                        </div>
                    <?php else: ?>
                        <?php foreach($order_items as $item): ?>
                            <div class="product-card">
                                <div class="flex gap-4">
                                    <!-- Product Image -->
                                    <div class="flex-shrink-0">
                                        <?php if (!empty($item['image_url'])): ?>
                                            <img src="../../../<?= htmlspecialchars($item['image_url']) ?>"
                                                 class="product-image cursor-pointer hover:opacity-90 transition-opacity"
                                                 onclick="openImageModal('../../../<?= htmlspecialchars($item['image_url']) ?>')"
                                                 alt="<?php echo htmlspecialchars($item['product_name']); ?>">
                                        <?php else: ?>
                                            <div class="product-image-placeholder">
                                                <i class="fas fa-image text-3xl text-gray-400"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Product Details -->
                                    <div class="flex-grow min-w-0">
                                        <!-- Product Name & SKU -->
                                        <div class="mb-3">
                                            <h3 class="font-bold text-gray-900 text-lg mb-1">
                                                <?php echo htmlspecialchars($item['product_name']); ?>
                                            </h3>
                                            <?php if (!empty($item['sku'])): ?>
                                                <div class="text-sm text-gray-500 flex items-center gap-2">
                                                    <span class="font-mono bg-gray-100 px-2 py-0.5 rounded text-xs">
                                                        SKU: <?php echo htmlspecialchars($item['sku']); ?>
                                                    </span>
                                                    <span class="text-gray-400">|</span>
                                                    <span class="text-xs">ID: <?php echo $item['product_id']; ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Variant/Attributes -->
                                        <?php if (!empty($item['variant_text'])): ?>
                                            <div class="mb-3">
                                                <?php
                                                $variant_parts = explode(' / ', $item['variant_text']);
                                                foreach ($variant_parts as $part) {
                                                    if (!empty(trim($part))) {
                                                        echo '<span class="variant-tag">' . htmlspecialchars(trim($part)) . '</span>';
                                                    }
                                                }
                                                ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Price & Quantity -->
                                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mt-4">
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1">الكمية</div>
                                                <div class="font-bold text-gray-900 text-lg"><?php echo $item['quantity']; ?></div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1">سعر الوحدة</div>
                                                <div class="price-final">
                                                    <?php
                                                    $unit_price = floatval($item['unit_price']);
                                                    echo preg_replace('/\.?0+$/', '', number_format($unit_price, 2));
                                                    ?>
                                                </div>
                                                <?php if (!empty($item['original_unit_price']) && floatval($item['original_unit_price']) > $unit_price): ?>
                                                    <div class="price-original mt-1">
                                                        <?php echo preg_replace('/\.?0+$/', '', number_format($item['original_unit_price'], 2)); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1">الخصم</div>
                                                <div class="price-discount">
                                                    <?php
                                                    $discount = floatval($item['item_discount_amount'] ?? 0);
                                                    if ($discount > 0) {
                                                        echo '-' . preg_replace('/\.?0+$/', '', number_format($discount, 2));
                                                    } else {
                                                        echo '-';
                                                    }
                                                    ?>
                                                </div>
                                            </div>
                                            <div>
                                                <div class="text-xs text-gray-500 mb-1">الإجمالي</div>
                                                <div class="price-total">
                                                    <?php echo preg_replace('/\.?0+$/', '', number_format($item['total_price'], 2)); ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment Evidence Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-receipt text-green-500"></i>
                    <h2>إيصال التحويل</h2>
                </div>
                <div class="card-body text-center">
                    <?php if (!empty($order['payment_evidence_url'])): ?>
                        <a href="../../<?php echo htmlspecialchars($order['payment_evidence_url']); ?>" target="_blank" class="inline-block">
                            <img src="../../<?php echo htmlspecialchars($order['payment_evidence_url']); ?>"
                                 class="max-w-full max-h-96 mx-auto rounded-lg shadow-md cursor-pointer hover:shadow-lg transition-shadow"
                                 alt="إيصال التحويل">
                        </a>
                        <p class="text-sm text-gray-500 mt-3">انقر لفتح الصورة في نافذة جديدة</p>
                    <?php else: ?>
                        <div class="py-12">
                            <i class="fas fa-file-invoice text-4xl text-gray-300 mb-4"></i>
                            <p class="text-gray-500">لم يتم رفع إيصال الدفع</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sidebar Column -->
        <div class="lg:col-span-1 space-y-6">
            <!-- Action Buttons Card -->
            <?php if ($order['order_status'] === 'طلب جديد'): ?>
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-tasks text-yellow-500"></i>
                    <h2>اتخاذ إجراء</h2>
                </div>
                <div class="card-body flex flex-col sm:flex-row gap-3">
                    <?php if ($can_approve): ?>
                        <button onclick="openApproveModal()" class="btn btn-success flex-1 w-full sm:w-auto">
                            <i class="fas fa-check-circle"></i> موافقة
                        </button>
                    <?php endif; ?>
                    <?php if ($can_reject): ?>
                        <button onclick="openRejectModal()" class="btn btn-danger flex-1 w-full sm:w-auto">
                            <i class="fas fa-times-circle"></i> رفض
                        </button>
                    <?php endif; ?>
                    <?php if (!$can_approve && !$can_reject): ?>
                        <p class="text-gray-500 text-center w-full text-sm">ليس لديك صلاحية لاتخاذ إجراء على هذا الطلب.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Approved Status Card -->
            <?php if ($order['order_status'] === 'طلب معتمد'):
                 $whatsapp_msg = "مرحباً {$order['customer_name']}\nتم اعتماد طلبك رقم #{$order['order_number']} بنجاح!\nسيتم شحنه قريباً. شكراً لثقتكم.";
                 $whatsapp_url = "https://wa.me/{$order['whatsapp_number']}?text=" . urlencode($whatsapp_msg);
            ?>
            <div class="card bg-green-50 border-green-300">
                <div class="card-body text-center">
                     <i class="fas fa-check-circle text-green-500 text-4xl mb-3"></i>
                     <p class="font-bold text-green-700 text-lg">تم اعتماد هذا الطلب</p>
                     <a href="<?php echo $whatsapp_url; ?>" target="_blank" class="mt-4 btn btn-whatsapp w-full">
                        <i class="fab fa-whatsapp"></i> إرسال إشعار للعميل
                     </a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Rejected Status Card -->
            <?php if ($order['order_status'] === 'مرفوض'): ?>
             <div class="card bg-red-50 border-red-300">
                <div class="card-body">
                    <div class="text-center mb-4">
                         <i class="fas fa-times-circle text-red-500 text-4xl"></i>
                         <p class="font-bold text-red-700 text-lg">تم رفض هذا الطلب</p>
                    </div>
                     <div class="bg-white rounded-lg p-3 border border-red-200">
                         <p class="text-sm text-gray-600 mb-1"><strong>سبب الرفض:</strong></p>
                         <p class="text-red-800"><?php echo htmlspecialchars($order['rejection_reason']); ?></p>
                     </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Customer Information Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-user text-blue-500"></i>
                    <h2>معلومات العميل</h2>
                </div>
                <div class="card-body">
                    <div class="info-item">
                        <span class="info-label">اسم العميل</span>
                        <span class="info-value highlight"><?php echo htmlspecialchars($order['customer_name']); ?></span>
                    </div>
                    <?php if (!empty($order['customer_code'])): ?>
                    <div class="info-item">
                        <span class="info-label">كود العميل</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['customer_code']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-item">
                        <span class="info-label">رقم الجوال</span>
                        <span class="info-value flex items-center gap-2">
                            <?php echo htmlspecialchars($order['mobile_number']); ?>
                            <button class="btn-icon" onclick="copyToClipboard('<?php echo htmlspecialchars($order['mobile_number']); ?>')" title="نسخ">
                                <i class="fas fa-copy"></i>
                            </button>
                        </span>
                    </div>
                    <?php if (!empty($order['whatsapp_number'])): ?>
                    <div class="info-item">
                        <span class="info-label">واتساب</span>
                        <span class="info-value">
                            <a href="https://wa.me/<?php echo htmlspecialchars($order['whatsapp_number']); ?>" target="_blank" class="text-green-600 hover:text-green-700 font-semibold">
                                <?php echo htmlspecialchars($order['whatsapp_number']); ?>
                                <i class="fab fa-whatsapp mr-1"></i>
                            </a>
                        </span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($order['email'])): ?>
                    <div class="info-item">
                        <span class="info-label">البريد الإلكتروني</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['email']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($order['address'])): ?>
                    <div class="info-item">
                        <span class="info-label">العنوان</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['address']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($order['city_name'])): ?>
                    <div class="info-item">
                        <span class="info-label">المدينة</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['city_name']); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($order['location_area'])): ?>
                    <div class="info-item">
                        <span class="info-label">المنطقة</span>
                        <span class="info-value"><?php echo htmlspecialchars($order['location_area']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Order Summary Card -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-file-invoice-dollar text-[#C7A46D]"></i>
                    <h2>ملخص الطلب</h2>
                </div>
                <div class="card-body">
                    <div class="section-title">معلومات الطلب</div>
                    <div class="info-item">
                        <span class="info-label">رقم الطلب</span>
                        <span class="info-value flex items-center gap-2">
                            <?php echo htmlspecialchars($order['order_number']); ?>
                            <button class="btn-icon" onclick="copyToClipboard('<?php echo htmlspecialchars($order['order_number']); ?>')" title="نسخ">
                                <i class="fas fa-copy"></i>
                            </button>
                        </span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">تاريخ الإنشاء</span>
                        <span class="info-value"><?php echo date('Y-m-d h:i A', strtotime($order['created_at'])); ?></span>
                    </div>
                    <?php if (!empty($order['updated_at']) && $order['updated_at'] != $order['created_at']): ?>
                    <div class="info-item">
                        <span class="info-label">آخر تحديث</span>
                        <span class="info-value"><?php echo date('Y-m-d h:i A', strtotime($order['updated_at'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($order['coupon_code'])): ?>
                    <div class="info-item">
                        <span class="info-label">كود الخصم</span>
                        <span class="info-value bg-green-100 text-green-700 px-2 py-1 rounded text-sm">
                            <?php echo htmlspecialchars($order['coupon_code']); ?>
                        </span>
                    </div>
                    <?php endif; ?>

                    <div class="section-title mt-6">التفاصيل المالية</div>
                    <div class="info-item">
                        <span class="info-label">مجموع المنتجات</span>
                        <span class="info-value"><?php echo preg_replace('/\.?0+$/', '', number_format($order['subtotal'], 2)); ?></span>
                    </div>
                    <div class="info-item">
                        <span class="info-label">رسوم الشحن</span>
                        <span class="info-value"><?php echo preg_replace('/\.?0+$/', '', number_format($order['shipping_fee'], 2)); ?></span>
                    </div>
                    <?php if (floatval($order['discount_amount']) > 0): ?>
                    <div class="info-item">
                        <span class="info-label text-red-600">الخصم</span>
                        <span class="info-value text-red-600 font-semibold">
                            -<?php echo preg_replace('/\.?0+$/', '', number_format($order['discount_amount'], 2)); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="total-row">
                        <span class="total-label">الإجمالي النهائي</span>
                        <span class="total-value">
                            <?php echo preg_replace('/\.?0+$/', '', number_format($order['total_amount'], 2)); ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- MODALS -->
    <!-- Approve Modal -->
    <?php if ($can_approve): // Only render modal if user has permission ?>
    <div id="approveModal" class="modal-overlay" onclick="closeApproveModal()">
        <div class="modal-box" onclick="event.stopPropagation()">
            <form method="POST">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="order_total" value="<?php echo $order['total_amount']; ?>">
                <input type="hidden" name="order_number" value="<?php echo $order['order_number']; ?>">
                <h3 class="text-xl font-bold mb-4 text-center">تأكيد الموافقة على الطلب</h3>
                <p class="text-center text-gray-600 mb-6">سيتم خصم الكميات من المخزون وإضافة المبلغ إلى الحساب البنكي.</p>
                <div>
                    <label for="bank_account_id" class="block font-bold text-gray-700 mb-2">اختر الحساب البنكي لإيداع المبلغ:</label>
                    <select name="bank_account_id" id="bank_account_id" required class="w-full border-2 border-gray-300 rounded-lg p-3 focus:border-blue-500 outline-none">
                        <option value="">-- اختر حساب --</option>
                        <?php foreach ($bank_accounts as $bank): ?>
                            <option value="<?php echo $bank['id']; ?>"><?php echo htmlspecialchars($bank['bank_name'] . ' - ' . $bank['account_number']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mt-8 flex gap-4">
                    <button type="button" onclick="closeApproveModal()" class="btn btn-secondary flex-1">إلغاء</button>
                    <button type="submit" class="btn btn-success flex-1">تأكيد الموافقة</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; // End can_approve ?>

    <!-- Reject Modal -->
    <?php if ($can_reject): ?>
    <div id="rejectModal" class="modal-overlay" onclick="closeRejectModal()">
        <div class="modal-box" onclick="event.stopPropagation()">
            <form method="POST">
                <input type="hidden" name="action" value="reject">
                <h3 class="text-xl font-bold mb-4 text-center">رفض الطلب</h3>
                <p class="text-center text-gray-600 mb-6">سيتم تغيير حالة الطلب إلى "مرفوض".</p>
                <div>
                    <label for="rejection_reason" class="block font-bold text-gray-700 mb-2">اكتب سبب الرفض (مطلوب):</label>
                    <textarea name="rejection_reason" id="rejection_reason" rows="4" required class="w-full border-2 border-gray-300 rounded-lg p-3 focus:border-red-500 outline-none" placeholder="مثال: صورة الحوالة غير واضحة..."></textarea>
                </div>
                <div class="mt-8 flex flex-col sm:flex-row gap-3">
                    <button type="button" onclick="closeRejectModal()" class="btn btn-secondary flex-1 w-full sm:w-auto">إلغاء</button>
                    <button type="submit" class="btn btn-danger flex-1 w-full sm:w-auto">تأكيد الرفض</button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <!-- Image Modal -->
    <div id="imageModal" class="modal-overlay" onclick="closeImageModal()">
        <div class="modal-box" onclick="event.stopPropagation()" style="max-width: 800px; padding: 0; overflow: hidden;">
            <button onclick="closeImageModal()" class="absolute top-4 left-4 w-10 h-10 bg-white rounded-full shadow-lg flex items-center justify-center text-gray-600 hover:text-gray-900 z-10">
                <i class="fas fa-times"></i>
            </button>
            <img id="modalImage" src="" alt="Product Image" class="w-full h-auto max-h-[80vh] object-contain">
        </div>
    </div>
</div>

<script>
    function openApproveModal() { document.getElementById('approveModal').style.display = 'flex'; }
    function closeApproveModal() { document.getElementById('approveModal').style.display = 'none'; }
    function openRejectModal() { document.getElementById('rejectModal').style.display = 'flex'; }
    function closeRejectModal() { document.getElementById('rejectModal').style.display = 'none'; }

    function openImageModal(imageSrc) {
        document.getElementById('modalImage').src = imageSrc;
        document.getElementById('imageModal').style.display = 'flex';
    }

    function closeImageModal() {
        document.getElementById('imageModal').style.display = 'none';
    }

    function copyToClipboard(text) {
        navigator.clipboard.writeText(text).then(function() {
            // Show a brief notification
            const notification = document.createElement('div');
            notification.className = 'fixed bottom-4 left-4 bg-gray-800 text-white px-4 py-2 rounded-lg shadow-lg z-50';
            notification.textContent = 'تم النسخ!';
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 2000);
        }).catch(function(err) {
            console.error('Failed to copy: ', err);
        });
    }

    // Close modals on Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeApproveModal();
            closeRejectModal();
            closeImageModal();
        }
    });
</script>

<?php include '../../includes/footer.php'; ?>