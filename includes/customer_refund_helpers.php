<?php
// includes/customer_refund_helpers.php (P8)
// ONE place for "how much do we owe a customer".
//
// owed(order) = (paid_amount - final_amount) - SUM(customer_refund_allocations for that order)
// An order counts only if owed > 0.01 and its status is not cancelled.
// Revenue is NOT changed by this: revenue stays = paid_amount.
// The status "تم الفرز" is a normal status and stays included.

const CR_THRESHOLD = 0.01;

/**
 * Orders that we still owe money on, optionally for one customer.
 * Pass $lock = true (inside a transaction) to lock the order rows FOR UPDATE.
 */
function crOwedOrders(PDO $db, ?int $customer_id = null, bool $lock = false): array
{
    $sql = "
        SELECT o.id, o.order_number, o.customer_id, o.order_date, o.status,
               o.final_amount, o.paid_amount,
               COALESCE(a.alloc, 0) AS allocated,
               ((o.paid_amount - o.final_amount) - COALESCE(a.alloc, 0)) AS owed
        FROM customer_orders o
        LEFT JOIN (
            SELECT order_id, SUM(amount) AS alloc
            FROM customer_refund_allocations
            GROUP BY order_id
        ) a ON a.order_id = o.id
        WHERE COALESCE(o.status, '') <> 'cancelled'
          AND ((o.paid_amount - o.final_amount) - COALESCE(a.alloc, 0)) > " . CR_THRESHOLD;
    $params = [];
    if ($customer_id !== null) {
        $sql .= " AND o.customer_id = ?";
        $params[] = $customer_id;
    }
    $sql .= " ORDER BY o.order_date ASC, o.id ASC";
    if ($lock) {
        $sql .= " FOR UPDATE";
    }
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

/** Customers we owe money to, with their total owed. */
function crCustomersOwed(PDO $db): array
{
    $rows = crOwedOrders($db);
    $by = [];
    foreach ($rows as $r) {
        $cid = (int)$r['customer_id'];
        if (!isset($by[$cid])) $by[$cid] = ['customer_id' => $cid, 'total_owed' => 0.0, 'orders' => 0];
        $by[$cid]['total_owed'] += (float)$r['owed'];
        $by[$cid]['orders']++;
    }
    if (!$by) return [];
    $ids = array_keys($by);
    $in = implode(',', array_fill(0, count($ids), '?'));
    $st = $db->prepare("SELECT id, name FROM customers WHERE id IN ($in)");
    $st->execute($ids);
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $c) {
        $by[(int)$c['id']]['name'] = $c['name'];
    }
    usort($by, fn($a, $b) => $b['total_owed'] <=> $a['total_owed']);
    return array_values($by);
}

/**
 * Spread $amount over the owed orders, oldest first.
 * Returns [ [order_id, amount], ... ]. Sum equals $amount when $amount <= total owed.
 */
function crAllocate(array $owed_orders, float $amount): array
{
    $out = [];
    $remaining = round($amount, 3);
    foreach ($owed_orders as $o) {
        if ($remaining <= 0) break;
        $take = round(min((float)$o['owed'], $remaining), 3);
        if ($take > 0) {
            $out[] = ['order_id' => (int)$o['id'], 'amount' => $take];
            $remaining = round($remaining - $take, 3);
        }
    }
    return $out;
}

/** Total of all amounts we still owe customers (positive number). */
function crTotalOwed(PDO $db): float
{
    $t = 0.0;
    foreach (crOwedOrders($db) as $r) $t += (float)$r['owed'];
    return round($t, 3);
}
