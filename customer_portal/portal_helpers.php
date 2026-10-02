<?php
/**
 * Customer Portal Shared Helpers
 */

/**
 * Format price for customer portal — comma thousands separator, NO decimals.
 * Examples: 20000 → "20,000", 25000.00 → "25,000", 1250000 → "1,250,000", 20000.50 → "20,001"
 */
function formatPrice($amount) {
    return number_format(round((float)$amount), 0, '.', ',');
}

/**
 * Get effective product stock - prioritize total_quantity, fallback to product_quantity
 * Matches the JavaScript effectiveProductStock logic
 */
function getEffectiveStock($product) {
    $total_qty = (int)($product['total_quantity'] ?? 0);
    $product_qty = (int)($product['product_quantity'] ?? 0);
    if ($total_qty > 0) return $total_qty;
    if ($product_qty > 0) return $product_qty;
    return 0;
}

/**
 * Translate order/approval status to Arabic
 */
function translateOrderStatus($status) {
    $map = [
        'pending'           => 'قيد المراجعة',
        'pending_review'    => 'قيد المراجعة',
        'approved'          => 'تمت الموافقة',
        'rejected'          => 'مرفوض',
        'new'               => 'جديد',
        'processing'        => 'قيد المعالجة',
        'shipped'           => 'تم الشحن',
        'delivered'         => 'تم التسليم',
        'cancelled'         => 'ملغي',
        'returned'          => 'مُرتجع',
        'scanned'           => 'تم الفرز',
        'ready'             => 'جاهز للتسليم',
        'available'         => 'متاح',
    ];
    return $map[$status] ?? $status;
}
