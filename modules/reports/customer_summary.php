<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/check_permissions.php';

$user_id = $_SESSION['user_id'] ?? 0;
if (!hasPermission($user_id, 'reports', 'view')) {
    header('Location: ../../index.php'); exit();
}

$search    = trim($_GET['search'] ?? '');
$group     = trim($_GET['group'] ?? '');
$city      = trim($_GET['city'] ?? '');

$where  = ['1=1'];
$params = [];

if ($search) { $where[] = 'c.name LIKE ?'; $params[] = "%$search%"; }
if ($group)  { $where[] = 'c.customer_group = ?'; $params[] = $group; }
if ($city)   { $where[] = 'c.city_name = ?'; $params[] = $city; }

$where_sql = implode(' AND ', $where);

$stmt = $db->prepare("
    SELECT
        c.id, c.name, c.city_name, c.customer_group,
        c.mobile_number,
        COUNT(DISTINCT co.id) AS total_orders,
        COALESCE(SUM(CASE WHEN co.status = 'delivered' THEN 1 ELSE 0 END), 0) AS delivered_count,
        COALESCE(SUM(CASE WHEN co.status IN ('ready','جاهز للتسليم') THEN 1 ELSE 0 END), 0) AS ready_count,
        COALESCE(SUM(CASE WHEN co.status NOT IN ('delivered','cancelled','جاهز للتسليم','ready') THEN 1 ELSE 0 END), 0) AS other_count,
        COALESCE(SUM(co.final_amount), 0) AS total_amount,
        COALESCE(SUM(co.paid_amount), 0) AS total_paid,
        COALESCE(SUM(co.final_amount - co.paid_amount), 0) AS total_remaining,
        (SELECT co2.order_number FROM customer_orders co2 WHERE co2.customer_id = c.id ORDER BY co2.created_at DESC LIMIT 1) AS last_order_number
    FROM customers c
    LEFT JOIN customer_orders co ON co.customer_id = c.id
    WHERE $where_sql
    GROUP BY c.id
    ORDER BY c.name ASC
");
$stmt->execute($params);
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'ملخص العملاء';
include '../../includes/header.php';
?>
<style>
    @media print {
        .no-print { display: none !important; }
        body { font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 4px; }
    }
</style>

<div dir="rtl" style="padding: 20px;">
    <!-- Filter Bar -->
    <div class="no-print" style="background:#fff;padding:16px;border-radius:8px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,.1);">
        <form method="GET" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
            <div><label style="font-size:12px;font-weight:600;">بحث بالاسم</label><br>
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" class="form-control" style="width:160px;"></div>
            <div><label style="font-size:12px;font-weight:600;">المجموعة</label><br>
                <input type="text" name="group" value="<?= htmlspecialchars($group) ?>" class="form-control" style="width:120px;"></div>
            <div><label style="font-size:12px;font-weight:600;">المدينة</label><br>
                <input type="text" name="city" value="<?= htmlspecialchars($city) ?>" class="form-control" style="width:120px;"></div>
            <button type="submit" class="btn btn-primary">بحث</button>
            <a href="?" class="btn btn-secondary">مسح</a>
            <button type="button" onclick="window.print()" class="btn btn-success"><i class="fas fa-print"></i> طباعة</button>
        </form>
    </div>

    <h2 style="margin-bottom:12px;"><i class="fas fa-users"></i> ملخص العملاء (<?= count($customers) ?>)</h2>

    <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead style="background:#3b82f6;color:white;">
                <tr>
                    <th style="padding:10px;text-align:right;">#</th>
                    <th style="padding:10px;text-align:right;">الاسم</th>
                    <th style="padding:10px;text-align:right;">الموقع</th>
                    <th style="padding:10px;text-align:right;">المجموعة</th>
                    <th style="padding:10px;text-align:center;">جميع الطلبات</th>
                    <th style="padding:10px;text-align:center;">تم الاستلام</th>
                    <th style="padding:10px;text-align:center;">جاهز للتوصيل</th>
                    <th style="padding:10px;text-align:center;">باقي الحالات</th>
                    <th style="padding:10px;text-align:center;">مدفوع</th>
                    <th style="padding:10px;text-align:center;">متبقي</th>
                    <th class="no-print" style="padding:10px;text-align:right;">ملاحظات</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($customers as $i => $c): ?>
                <tr style="border-bottom:1px solid #e5e7eb;<?= $i%2===0?'background:#f9fafb;':'' ?>">
                    <td style="padding:8px;"><?= $i+1 ?></td>
                    <td style="padding:8px;font-weight:700;"><?= htmlspecialchars($c['name']) ?>
                        <br><small style="color:#6b7280;"><?= htmlspecialchars($c['mobile_number'] ?? '') ?></small></td>
                    <td style="padding:8px;"><?= htmlspecialchars($c['city_name'] ?? '-') ?></td>
                    <td style="padding:8px;"><?= htmlspecialchars($c['customer_group'] ?? '-') ?></td>
                    <td style="padding:8px;text-align:center;font-weight:700;"><?= $c['total_orders'] ?></td>
                    <td style="padding:8px;text-align:center;color:#059669;font-weight:700;"><?= $c['delivered_count'] ?></td>
                    <td style="padding:8px;text-align:center;color:#d97706;font-weight:700;"><?= $c['ready_count'] ?></td>
                    <td style="padding:8px;text-align:center;color:#7c3aed;font-weight:700;"><?= $c['other_count'] ?></td>
                    <td style="padding:8px;text-align:center;color:#059669;"><?= number_format($c['total_paid'], 0) ?></td>
                    <td style="padding:8px;text-align:center;color:#dc2626;font-weight:700;"><?= number_format($c['total_remaining'], 0) ?></td>
                    <td class="no-print" style="padding:8px;font-size:11px;color:#6b7280;">آخر طلب: <?= htmlspecialchars($c['last_order_number'] ?? '-') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include '../../includes/footer.php'; ?>
