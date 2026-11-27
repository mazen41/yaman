<?php
// Shared financial calculations for P&L, financial index and report cards (P4).
// Read-only: these functions never change stored data.
//
// Owner decisions (27-11-2026):
//   REVENUE  = paid_amount of ALL orders in the period, any status (cancelled/pending included), by order_date.
//   EXPENSES = approved expenses only (status = 'approved'), by expense_date. Same total as modules/expenses/index.php "معتمد".
//   BASKET PROFIT = sale value (final_amount of the basket's orders) minus purchase cost, per basket.
//   COUPONS  = show both the discount given and the profit after coupons.
//
// Decisions still to confirm with the owner (default used, change here if needed):
//   - Basket cost excludes baskets with status 'cancelled' or 'rejected'.
//   - Basket period uses purchase_date, falling back to created_at.

const PL_BASKET_EXCLUDED_STATUSES = ['cancelled', 'rejected'];

/** Paid amount of all orders in the period (owner rule: revenue = paid, any status). */
function calcOrdersRevenue(PDO $db, string $from, string $to): float
{
    $stmt = $db->prepare("SELECT COALESCE(SUM(paid_amount), 0) FROM customer_orders WHERE order_date BETWEEN ? AND ?");
    $stmt->execute([$from, $to]);
    return (float)$stmt->fetchColumn();
}

/** Final amount (after discounts) of all orders in the period. Info only. */
function calcOrdersFinalTotal(PDO $db, string $from, string $to): float
{
    $stmt = $db->prepare("SELECT COALESCE(SUM(final_amount), 0) FROM customer_orders WHERE order_date BETWEEN ? AND ?");
    $stmt->execute([$from, $to]);
    return (float)$stmt->fetchColumn();
}

/** Manual + additional discounts in the period. Info only. */
function calcOrdersDiscounts(PDO $db, string $from, string $to): float
{
    $stmt = $db->prepare("SELECT COALESCE(SUM(discount_amount + additional_discount), 0) FROM customer_orders WHERE order_date BETWEEN ? AND ?");
    $stmt->execute([$from, $to]);
    return (float)$stmt->fetchColumn();
}

/** Coupon discount given in the period. */
function calcCouponDiscounts(PDO $db, string $from, string $to): float
{
    $stmt = $db->prepare("SELECT COALESCE(SUM(coupon_discount_amount), 0) FROM customer_orders WHERE order_date BETWEEN ? AND ?");
    $stmt->execute([$from, $to]);
    return (float)$stmt->fetchColumn();
}

/** Shipping charged on orders in the period. Info only (not added to revenue). */
function calcShippingRevenue(PDO $db, string $from, string $to): float
{
    $stmt = $db->prepare("SELECT COALESCE(SUM(shipping_cost), 0) FROM customer_orders WHERE order_date BETWEEN ? AND ?");
    $stmt->execute([$from, $to]);
    return (float)$stmt->fetchColumn();
}

/** Approved expenses in the period. Same total as the "معتمد" card on modules/expenses/index.php. */
function calcApprovedExpenses(PDO $db, string $from, string $to): float
{
    $stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN ? AND ? AND status = 'approved'");
    $stmt->execute([$from, $to]);
    return (float)$stmt->fetchColumn();
}

/**
 * Per-basket profit rows: sale value (orders linked to the basket) minus purchase cost.
 * Purchase cost = grand_total_yer, falling back to final_amount.
 */
function getBasketProfitRows(PDO $db, string $from, string $to): array
{
    $excluded = PL_BASKET_EXCLUDED_STATUSES;
    $placeholders = implode(',', array_fill(0, count($excluded), '?'));
    $sql = "
        SELECT
            pb.id,
            pb.basket_code,
            pb.basket_name,
            pb.status,
            COALESCE(pb.purchase_date, DATE(pb.created_at)) AS basket_date,
            COALESCE(sales.sale_value, 0) AS sale_value,
            COALESCE(pb.grand_total_yer, pb.final_amount, 0) AS purchase_cost
        FROM purchase_baskets pb
        LEFT JOIN (
            SELECT basket_id, SUM(final_amount) AS sale_value
            FROM customer_orders
            WHERE basket_id IS NOT NULL
            GROUP BY basket_id
        ) sales ON sales.basket_id = pb.id
        WHERE COALESCE(pb.purchase_date, DATE(pb.created_at)) BETWEEN ? AND ?
          AND pb.status NOT IN ($placeholders)
        ORDER BY basket_date DESC, pb.id DESC
    ";
    $stmt = $db->prepare($sql);
    $stmt->execute(array_merge([$from, $to], $excluded));
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as &$r) {
        $r['sale_value'] = (float)$r['sale_value'];
        $r['purchase_cost'] = (float)$r['purchase_cost'];
        $r['profit'] = $r['sale_value'] - $r['purchase_cost'];
    }
    unset($r);
    return $rows;
}

/** Sum of basket profit rows. */
function calcBasketsProfit(PDO $db, string $from, string $to): float
{
    return array_sum(array_column(getBasketProfitRows($db, $from, $to), 'profit'));
}

/**
 * Per-coupon rows: orders used, discount given, and the final amount after the coupon.
 */
function getCouponRows(PDO $db, string $from, string $to): array
{
    $stmt = $db->prepare("
        SELECT
            coupon_code,
            COUNT(*) AS orders_affected,
            COALESCE(SUM(coupon_discount_amount), 0) AS discount_given,
            COALESCE(SUM(final_amount), 0) AS final_after_coupon
        FROM customer_orders
        WHERE order_date BETWEEN ? AND ?
          AND coupon_code IS NOT NULL AND coupon_code <> ''
        GROUP BY coupon_code
        ORDER BY discount_given DESC
    ");
    $stmt->execute([$from, $to]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as &$r) {
        $r['discount_given'] = (float)$r['discount_given'];
        $r['final_after_coupon'] = (float)$r['final_after_coupon'];
    }
    unset($r);
    return $rows;
}

/**
 * Full P&L numbers for one period. Every page that shows revenue, expenses or profit should use this.
 * Net profit = paid revenue - approved expenses. Basket profit and coupons are shown as detail,
 * not added again, so nothing is counted twice.
 */
function getProfitReport(PDO $db, string $from, string $to): array
{
    $revenue = calcOrdersRevenue($db, $from, $to);
    $expenses = calcApprovedExpenses($db, $from, $to);
    $net = $revenue - $expenses;

    // Second reading (owner confirmed): net profit minus the purchase cost of the baskets in the period.
    $basket_rows = getBasketProfitRows($db, $from, $to);
    $basket_cost = array_sum(array_column($basket_rows, 'purchase_cost'));

    return [
        'from' => $from,
        'to' => $to,
        'revenue' => $revenue,
        'expenses' => $expenses,
        'net_profit' => $net,
        'margin' => $revenue > 0 ? ($net / $revenue) * 100 : 0.0,
        'basket_cost' => $basket_cost,
        'net_after_basket_cost' => $net - $basket_cost,
        'orders_final_total' => calcOrdersFinalTotal($db, $from, $to),
        'discounts' => calcOrdersDiscounts($db, $from, $to),
        'coupon_discounts' => calcCouponDiscounts($db, $from, $to),
        'shipping' => calcShippingRevenue($db, $from, $to),
        'baskets_profit' => array_sum(array_column($basket_rows, 'profit')),
    ];
}
