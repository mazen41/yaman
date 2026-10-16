<?php
/**
 * Self-order details (pending / under-review / approval requests).
 * Read-only view of an order_approvals row, its items, attached images, and payment receipt.
 */
error_reporting(E_ALL);
ini_set('display_errors', 0);
date_default_timezone_set('Asia/Aden');

require_once __DIR__ . '/../config/database.php';

$token       = $_GET['token'] ?? '';
$approval_id = (int)($_GET['approval_id'] ?? 0);

if ($token === '' || $approval_id <= 0) {
    die('Invalid access.');
}

// 1. Verify customer by token
$stmt = $db->prepare("SELECT * FROM customers WHERE portal_token = ?");
$stmt->execute([$token]);
$customer = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$customer) {
    die('Link expired or invalid.');
}

// 2. Approval must belong to this customer
$stmt = $db->prepare("SELECT * FROM order_approvals WHERE id = ? AND customer_id = ?");
$stmt->execute([$approval_id, $customer['id']]);
$approval = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$approval) {
    die('Order not found.');
}

// 3. Items
$items_stmt = $db->prepare("SELECT * FROM order_approval_items WHERE approval_id = ? ORDER BY id");
$items_stmt->execute([$approval_id]);
$items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

$total_quantity = 0;
foreach ($items as $it) { 
    $total_quantity += (int)($it['item_count'] ?? 1); 
}

// 4. Attached Images
$images_stmt = $db->prepare("SELECT * FROM order_approvals_images WHERE approval_id = ? ORDER BY display_order ASC, id ASC");
$images_stmt->execute([$approval_id]);
$images = $images_stmt->fetchAll(PDO::FETCH_ASSOC);

function getPortalAssetUrl($path) {
    if (empty($path)) return '';
    $path = trim($path);
    if (preg_match('#^https?://#i', $path)) return $path;
    $path = ltrim($path, '/');
    if (strpos($path, '../../') === 0) {
        $path = substr($path, 6);
    } elseif (strpos($path, '../') === 0) {
        $path = substr($path, 3);
    }
    return '../' . $path;
}

$gallery_paths = [];
foreach ($images as $img) {
    $p = getPortalAssetUrl($img['image_path']);
    if (!empty($p)) {
        $gallery_paths[] = $p;
    }
}

$payment_proof_url = !empty($approval['payment_proof_path']) ? getPortalAssetUrl($approval['payment_proof_path']) : '';

$subtotal   = (float)($approval['subtotal_amount'] ?? 0);
$discount   = (float)($approval['coupon_discount_amount'] ?? 0) + (float)($approval['automatic_discount_amount'] ?? 0);
$shipping   = (float)($approval['shipping_cost'] ?? 0);
$final      = (float)($approval['total_after_discounts'] ?? 0) + $shipping;
$paid       = (float)($approval['paid_amount'] ?? 0);
$remaining  = max(0, $final - $paid);
$currency   = !empty($approval['currency']) ? $approval['currency'] : ($customer['currency'] ?? 'YER');

$status_map = [
    'pending'  => ['قيد المراجعة', 'bg-amber-100 text-amber-800 border-amber-200', 'fa-clock'],
    'approved' => ['تمت الموافقة', 'bg-emerald-100 text-emerald-800 border-emerald-200', 'fa-check-circle'],
    'rejected' => ['مرفوض', 'bg-red-100 text-red-800 border-red-200', 'fa-times-circle'],
];
[$status_label, $status_class, $status_icon] = $status_map[$approval['status']] ?? ['حالة غير معروفة', 'bg-gray-100 text-gray-600 border-gray-200', 'fa-question-circle'];

function fmtMoney($v) { return number_format((float)$v, 0); }
function fmtDate($d) {
    if (empty($d)) return '-';
    try {
        $dt = new DateTime($d, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone('Asia/Aden'));
        return $dt->format('Y-m-d h:i A');
    } catch (Exception $e) { return $d; }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الطلب الذاتي #<?= (int)$approval['id'] ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Cairo', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen pb-12">

    <!-- Top Navbar -->
    <nav style="background: linear-gradient(to left, #C7A46D, #B8956A);" class="text-white shadow-lg sticky top-0 z-50">
        <div class="max-w-4xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center">
                <a href="portal.php?token=<?= urlencode($token) ?>" class="text-white ml-4 p-2 rounded-lg hover:bg-white/10 transition" title="العودة للبوابة">
                    <i class="fas fa-arrow-right text-lg"></i>
                </a>
                <div>
                    <h1 class="text-lg font-bold flex items-center gap-2">
                        <span>تفاصيل الطلب الذاتي</span>
                        <span class="text-xs bg-white/20 px-2 py-0.5 rounded-full font-normal">#<?= (int)$approval['id'] ?></span>
                    </h1>
                    <p class="text-xs text-white/80"><?= fmtDate($approval['created_at']) ?></p>
                </div>
            </div>
            <a href="portal.php?token=<?= urlencode($token) ?>" class="text-xs bg-white/15 hover:bg-white/25 px-3 py-1.5 rounded-lg transition font-semibold">
                <i class="fas fa-list-alt ml-1"></i> كل الطلبات
            </a>
        </div>
    </nav>

    <div class="max-w-4xl mx-auto px-4 py-6 space-y-6">

        <!-- Approved Link Banner -->
        <?php if ($approval['status'] === 'approved' && !empty($approval['final_order_id'])): ?>
            <div class="bg-emerald-50 border border-emerald-200 rounded-xl p-4 flex flex-col sm:flex-row items-center justify-between gap-3 text-emerald-800 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
                        <i class="fas fa-check-circle text-emerald-600 text-xl"></i>
                    </div>
                    <div>
                        <h4 class="font-bold text-sm">تم اعتماد هذا الطلب كطلب رسمي</h4>
                        <p class="text-xs text-emerald-700">رقم الطلب الرسمي في النظام: #<?= (int)$approval['final_order_id'] ?></p>
                    </div>
                </div>
                <a href="order_details.php?token=<?= urlencode($token) ?>&order_id=<?= (int)$approval['final_order_id'] ?>" 
                   class="inline-flex items-center justify-center px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-lg transition shadow">
                    <span>عرض الطلب الرسمي</span>
                    <i class="fas fa-arrow-left mr-2"></i>
                </a>
            </div>
        <?php endif; ?>

        <!-- Rejection Reason Banner -->
        <?php if ($approval['status'] === 'rejected'): ?>
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 flex items-start gap-3 text-red-800 shadow-sm">
                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                    <i class="fas fa-times-circle text-red-600 text-xl"></i>
                </div>
                <div>
                    <h4 class="font-bold text-sm">تم رفض الطلب</h4>
                    <?php if (!empty($approval['rejection_reason'])): ?>
                        <p class="text-xs text-red-700 mt-1">السبب: <?= htmlspecialchars($approval['rejection_reason']) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Info Cards -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                    <span class="text-xs text-gray-500 block mb-1">رقم الطلب</span>
                    <span class="font-bold text-[#B8956A]">#<?= (int)$approval['id'] ?></span>
                </div>
                <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                    <span class="text-xs text-gray-500 block mb-1">تاريخ الإنشاء</span>
                    <span class="font-bold text-gray-800 text-xs sm:text-sm"><?= fmtDate($approval['created_at']) ?></span>
                </div>
                <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                    <span class="text-xs text-gray-500 block mb-1">إجمالي القطع</span>
                    <span class="font-bold text-gray-800"><?= $total_quantity ?></span>
                </div>
                <div class="bg-gray-50 p-3 rounded-lg border border-gray-100">
                    <span class="text-xs text-gray-500 block mb-1">الحالة</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-bold border <?= $status_class ?>">
                        <i class="fas <?= $status_icon ?> ml-1"></i> <?= $status_label ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Products Section -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                <h3 class="font-bold text-gray-800 text-sm flex items-center">
                    <i class="fas fa-boxes text-[#C7A46D] ml-2"></i>
                    <span>المنتجات المطلوبة</span>
                </h3>
                <span class="text-xs bg-gray-200 text-gray-700 px-2.5 py-1 rounded-full font-bold"><?= count($items) ?> عنصر</span>
            </div>
            <div class="p-6 divide-y divide-gray-100 space-y-4">
                <?php if (empty($items)): ?>
                    <p class="text-gray-500 text-sm text-center py-4">لا توجد منتجات مسجلة.</p>
                <?php else: ?>
                    <?php foreach ($items as $idx => $it): ?>
                        <div class="pt-4 first:pt-0">
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-2 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="w-6 h-6 rounded-full bg-[#C7A46D]/20 text-[#B8956A] text-xs font-bold flex items-center justify-center">
                                            <?= $idx + 1 ?>
                                        </span>
                                        <span class="font-bold text-gray-800 text-sm">منتج <?= $idx + 1 ?></span>
                                        <span class="text-xs text-gray-500 font-semibold">(الكمية: <?= (int)($it['item_count'] ?? 1) ?>)</span>
                                    </div>

                                    <?php if (!empty(trim((string)($it['product_link'] ?? '')))): ?>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-gray-500 shrink-0">رابط المنتج:</span>
                                            <a href="<?= htmlspecialchars(trim($it['product_link'])) ?>" target="_blank" class="inline-flex items-center text-xs text-blue-600 hover:text-blue-800 bg-blue-50 px-2 py-1 rounded break-all transition">
                                                <i class="fas fa-external-link-alt ml-1"></i>
                                                <span><?= htmlspecialchars(trim($it['product_link'])) ?></span>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty(trim((string)($it['additional_link'] ?? '')))): ?>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs text-gray-500 shrink-0">رابط إضافي:</span>
                                            <a href="<?= htmlspecialchars(trim($it['additional_link'])) ?>" target="_blank" class="inline-flex items-center text-xs text-amber-700 hover:text-amber-900 bg-amber-50 px-2 py-1 rounded break-all transition">
                                                <i class="fas fa-link ml-1"></i>
                                                <span><?= htmlspecialchars(trim($it['additional_link'])) ?></span>
                                            </a>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty(trim((string)($it['notes'] ?? '')))): ?>
                                        <div class="text-xs text-gray-600 bg-gray-50 p-2.5 rounded border border-gray-100">
                                            <span class="text-gray-400 font-bold block mb-0.5">ملاحظات المنتج:</span>
                                            <?= nl2br(htmlspecialchars($it['notes'])) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!empty($it['total']) && (float)$it['total'] > 0): ?>
                                    <div class="text-left shrink-0">
                                        <span class="text-xs text-gray-400 block">السعر</span>
                                        <span class="font-bold text-[#B8956A] text-sm"><?= fmtMoney($it['total']) ?> <?= htmlspecialchars($currency) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Attached Images -->
        <?php if (!empty($gallery_paths)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="font-bold text-gray-800 text-sm flex items-center">
                        <i class="fas fa-images text-[#C7A46D] ml-2"></i>
                        <span>الصور المرفقة بالطلب</span>
                    </h3>
                </div>
                <div class="p-6 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    <?php foreach ($gallery_paths as $idx => $path): ?>
                        <button type="button" onclick="openOrderGallery(<?= (int)$idx ?>)" class="group relative overflow-hidden rounded-xl border border-gray-200 shadow-sm text-right h-28 bg-gray-100" aria-label="عرض الصورة">
                            <img src="<?= htmlspecialchars($path) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" alt="صورة مرفقة">
                            <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition flex items-center justify-center">
                                <span class="bg-white/90 p-2 rounded-full shadow text-gray-700 opacity-0 group-hover:opacity-100 transition"><i class="fas fa-search-plus"></i></span>
                            </div>
                        </button>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Payment Proof / Receipt -->
        <?php if (!empty($payment_proof_url)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="bg-gray-50 px-6 py-4 border-b border-gray-200">
                    <h3 class="font-bold text-gray-800 text-sm flex items-center">
                        <i class="fas fa-receipt text-[#C7A46D] ml-2"></i>
                        <span>سند الدفع / إيصال التحويل</span>
                    </h3>
                </div>
                <div class="p-6 flex flex-col sm:flex-row items-center gap-4">
                    <button type="button" onclick="openPaymentImageModal('<?= htmlspecialchars($payment_proof_url) ?>')" class="relative overflow-hidden rounded-xl border border-gray-200 shadow-sm w-36 h-36 bg-gray-100 group shrink-0" aria-label="عرض الإيصال">
                        <img src="<?= htmlspecialchars($payment_proof_url) ?>" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" alt="سند الدفع">
                        <div class="absolute inset-0 bg-black/0 group-hover:bg-black/20 transition flex items-center justify-center">
                            <span class="bg-white/90 p-2 rounded-full shadow text-gray-700 opacity-0 group-hover:opacity-100 transition"><i class="fas fa-search-plus"></i></span>
                        </div>
                    </button>
                    <div class="text-sm space-y-1">
                        <div class="font-bold text-gray-800">تم إرفاق إشعار تحويل</div>
                        <div class="text-xs text-gray-500">انقر على الصورة لمعاينتها بالحجم الكامل</div>
                        <?php if ($paid > 0): ?>
                            <div class="text-xs text-green-700 font-bold mt-2">المبلغ المدفوع: <?= fmtMoney($paid) ?> <?= htmlspecialchars($currency) ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Financial Summary & Notes -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-3 text-sm">
            <h3 class="font-bold text-gray-800 text-sm pb-2 border-b border-gray-100 flex items-center">
                <i class="fas fa-calculator text-[#C7A46D] ml-2"></i>
                <span>الملخص المالي</span>
            </h3>

            <div class="flex justify-between text-gray-600">
                <span>المجموع الفرعي</span>
                <span class="font-semibold text-gray-900"><?= fmtMoney($subtotal) ?> <?= htmlspecialchars($currency) ?></span>
            </div>

            <?php if ($discount > 0): ?>
                <div class="flex justify-between text-green-600">
                    <span>الخصم</span>
                    <span class="font-semibold">-<?= fmtMoney($discount) ?> <?= htmlspecialchars($currency) ?></span>
                </div>
            <?php endif; ?>

            <?php if ($shipping > 0): ?>
                <div class="flex justify-between text-gray-600">
                    <span>رسوم التوصيل</span>
                    <span class="font-semibold text-gray-900"><?= fmtMoney($shipping) ?> <?= htmlspecialchars($currency) ?></span>
                </div>
            <?php endif; ?>

            <div class="flex justify-between font-bold border-t border-gray-200 pt-3 text-base text-gray-900">
                <span>الإجمالي النهائي</span>
                <span class="text-emerald-700"><?= fmtMoney($final) ?> <?= htmlspecialchars($currency) ?></span>
            </div>

            <div class="flex justify-between text-gray-600">
                <span>المدفوع</span>
                <span class="font-semibold text-blue-600"><?= fmtMoney($paid) ?> <?= htmlspecialchars($currency) ?></span>
            </div>

            <div class="flex justify-between font-bold">
                <span>المتبقي</span>
                <span class="<?= $remaining > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= fmtMoney($remaining) ?> <?= htmlspecialchars($currency) ?></span>
            </div>

            <?php if (!empty(trim((string)($approval['notes'] ?? '')))): ?>
                <div class="pt-4 border-t border-gray-100">
                    <span class="text-xs font-bold text-gray-500 block mb-1">ملاحظات العميل:</span>
                    <div class="text-xs text-gray-700 bg-gray-50 p-3 rounded-lg leading-relaxed"><?= nl2br(htmlspecialchars($approval['notes'])) ?></div>
                </div>
            <?php endif; ?>
        </div>

    </div>

    <!-- Gallery Lightbox Modal -->
    <?php if (!empty($gallery_paths)): ?>
    <div id="orderGalleryModal" class="fixed inset-0 z-[9998] hidden bg-black/90 items-center justify-center p-4" onclick="if(event.target === this) closeOrderGallery()">
        <button type="button" onclick="closeOrderGallery()" class="absolute top-4 left-4 w-10 h-10 rounded-full bg-white text-gray-800 flex items-center justify-center shadow hover:bg-gray-200 transition" aria-label="إغلاق"><i class="fas fa-times"></i></button>
        <button type="button" onclick="showOrderGalleryImage(currentOrderGalleryIndex - 1)" class="absolute right-4 md:right-8 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/90 text-gray-900 flex items-center justify-center shadow hover:bg-white transition" aria-label="السابق"><i class="fas fa-chevron-right"></i></button>
        <img id="orderGalleryImage" src="" alt="صورة مرفقة" class="max-w-full max-h-[85vh] rounded-lg shadow-2xl bg-white object-contain">
        <button type="button" onclick="showOrderGalleryImage(currentOrderGalleryIndex + 1)" class="absolute left-4 md:left-8 top-1/2 -translate-y-1/2 w-11 h-11 rounded-full bg-white/90 text-gray-900 flex items-center justify-center shadow hover:bg-white transition" aria-label="التالي"><i class="fas fa-chevron-left"></i></button>
        <div id="orderGalleryCounter" class="absolute bottom-4 left-1/2 -translate-x-1/2 bg-white/90 text-gray-800 px-4 py-1 rounded-full text-sm font-bold"></div>
    </div>
    <script>
    const orderGalleryImages = <?= json_encode($gallery_paths, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    let currentOrderGalleryIndex = 0;
    function openOrderGallery(index) {
        const modal = document.getElementById('orderGalleryModal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        showOrderGalleryImage(index);
    }
    function closeOrderGallery() {
        const modal = document.getElementById('orderGalleryModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.getElementById('orderGalleryImage').src = '';
    }
    function showOrderGalleryImage(index) {
        if (!orderGalleryImages.length) return;
        currentOrderGalleryIndex = (index + orderGalleryImages.length) % orderGalleryImages.length;
        document.getElementById('orderGalleryImage').src = orderGalleryImages[currentOrderGalleryIndex];
        document.getElementById('orderGalleryCounter').textContent = (currentOrderGalleryIndex + 1) + ' / ' + orderGalleryImages.length;
    }
    document.addEventListener('keydown', function(event) {
        const modal = document.getElementById('orderGalleryModal');
        if (!modal || modal.classList.contains('hidden')) return;
        if (event.key === 'Escape') closeOrderGallery();
        if (event.key === 'ArrowRight') showOrderGalleryImage(currentOrderGalleryIndex - 1);
        if (event.key === 'ArrowLeft') showOrderGalleryImage(currentOrderGalleryIndex + 1);
    });
    </script>
    <?php endif; ?>

    <!-- Payment Proof Modal -->
    <div id="paymentImageModal" class="fixed inset-0 z-[9999] hidden bg-black/85 items-center justify-center p-4" onclick="if(event.target === this) closePaymentImageModal()">
        <button type="button" onclick="closePaymentImageModal()" class="absolute top-4 left-4 w-10 h-10 rounded-full bg-white text-gray-800 flex items-center justify-center shadow hover:bg-gray-200 transition"><i class="fas fa-times"></i></button>
        <img id="paymentImagePreview" src="" alt="Payment receipt" class="max-w-full max-h-[85vh] rounded-lg shadow-2xl bg-white object-contain">
    </div>
    <script>
    function openPaymentImageModal(src) {
        const modal = document.getElementById('paymentImageModal');
        document.getElementById('paymentImagePreview').src = src;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
    }
    function closePaymentImageModal() {
        const modal = document.getElementById('paymentImageModal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.getElementById('paymentImagePreview').src = '';
    }
    </script>

</body>
</html>
