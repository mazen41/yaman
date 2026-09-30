<?php
session_start();
require_once '../../config/database.php';

$raw_ids = $_GET['ids'] ?? '';
$ids = array_filter(array_map('intval', explode(',', $raw_ids)));
if (empty($ids)) { die('<p style="text-align:center;padding:40px;">لا توجد طلبات محددة</p>'); }

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $db->prepare("
    SELECT co.id, co.order_number, c.name, c.city_name, c.mobile_number,
           co.final_amount, co.paid_amount,
           COALESCE((SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.order_id = co.id), 0) AS piece_count
    FROM customer_orders co
    JOIN customers c ON c.id = co.customer_id
    WHERE co.id IN ($placeholders)
    ORDER BY co.order_number ASC
");
$stmt->execute($ids);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>طباعة ملصقات</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; font-family: Arial, sans-serif; }
  body { background: #fff; }
  .label {
    width: 4in; height: 6in;
    border: 2px solid #000;
    padding: 18px;
    page-break-after: always;
    display: flex; flex-direction: column; justify-content: space-between;
    margin: 0 auto;
  }
  .label:last-child { page-break-after: auto; }
  .label-order-num { font-size: 30px; font-weight: 900; text-align: center; border: 3px solid #000; padding: 10px; border-radius: 6px; margin-bottom: 14px; }
  .label-field { font-size: 16px; margin-bottom: 10px; line-height: 1.5; }
  .label-field strong { font-size: 20px; display: block; }
  .label-remaining { font-size: 22px; font-weight: 900; color: #dc2626; border-top: 2px dashed #000; padding-top: 12px; margin-top: 8px; }
  .label-pieces { font-size: 18px; font-weight: 700; }
  @media print {
    body { margin: 0; }
    .no-print { display: none; }
    .label { margin: 0; border: 2px solid #000; }
  }
</style>
</head>
<body>
<div class="no-print" style="padding:16px;text-align:center;border-bottom:1px solid #ccc;margin-bottom:10px;">
    <button onclick="window.print()" style="background:#3b82f6;color:white;padding:10px 30px;border:none;border-radius:6px;font-size:16px;cursor:pointer;font-weight:700;">🖨 طباعة الآن</button>
    <button onclick="window.close()" style="background:#6b7280;color:white;padding:10px 20px;border:none;border-radius:6px;font-size:14px;cursor:pointer;margin-right:10px;">إغلاق</button>
    <span style="font-size:14px;color:#374151;margin-right:16px;"><?= count($orders) ?> ملصق</span>
</div>
<?php foreach ($orders as $o):
    $remaining = max(0, $o['final_amount'] - $o['paid_amount']);
?>
<div class="label">
    <div class="label-order-num">طلب # <?= htmlspecialchars($o['order_number']) ?></div>
    <div class="label-field">الاسم:<strong><?= htmlspecialchars($o['name']) ?></strong></div>
    <div class="label-field">الموقع:<strong><?= htmlspecialchars($o['city_name'] ?? '-') ?></strong></div>
    <div class="label-field">الهاتف:<strong><?= htmlspecialchars($o['mobile_number'] ?? '-') ?></strong></div>
    <div class="label-pieces">عدد القطع: <strong><?= (int)$o['piece_count'] ?></strong></div>
    <div class="label-remaining">المتبقي: <?= number_format($remaining, 0) ?> ريال</div>
</div>
<?php endforeach; ?>
</body>
</html>
