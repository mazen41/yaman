<?php
/**
 * Customer Portal Shared Helpers
 */

/**
 * BUG 5 FIX: Format price — remove trailing .00, keep .50, add thousands separator.
 * Examples: 150.00 → "150", 15000.00 → "15,000", 150.50 → "150.50"
 */
function formatPrice($amount) {
    $amount = (float)$amount;
    // If integer value (no meaningful decimals), format without decimal
    if ($amount == floor($amount)) {
        return number_format($amount, 0, '.', ',');
    }
    // Otherwise keep 2 decimal places but strip trailing zeros
    $formatted = number_format($amount, 2, '.', ',');
    // Remove trailing zero after decimal: 150.50 → "150.50", 150.10 → "150.1" (keep at least 1 decimal)
    $formatted = rtrim($formatted, '0');
    $formatted = rtrim($formatted, '.');
    return $formatted;
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
