<?php
session_start();
require_once '../../config/database.php';

$raw_ids = $_GET['ids'] ?? '';
$ids = array_filter(array_map('intval', explode(',', $raw_ids)));
if (empty($ids)) { die('<p style="text-align:center;padding:40px;">لا توجد طلبات محددة</p>'); }

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $db->prepare("
    SELECT co.id, co.order_number, c.name, c.address, c.mobile_number,
           co.final_amount, co.paid_amount,
           COALESCE((SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.order_id = co.id), 0) AS piece_count
    FROM customer_orders co
    JOIN customers c ON c.id = co.customer_id
    WHERE co.id IN ($placeholders)
    ORDER BY co.order_number ASC
");
$stmt->execute($ids);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Company info from System Settings (key/value table: setting_key / setting_value)
$settings = [];
try {
    foreach ($db->query("SELECT setting_key, setting_value FROM system_settings") as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $settings = [];
}
$company_name    = trim($settings['company_name'] ?? '');
$currency_label  = trim($settings['currency'] ?? '') !== '' ? trim($settings['currency']) : 'ريال';
$show_company_name = ($company_name !== '' && strcasecmp($company_name, 'Yaman') !== 0);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>طباعة ملصقات</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
  /* Label size: 60 mm wide x 40 mm high */
  :root {
    --paper: #ecd9a0;
    --navy: #27346b;
    --navy-deep: #1b2347;
  }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  html, body { background: #f3f4f6; }
  body {
    font-family: 'Cairo', 'Segoe UI', Tahoma, Arial, sans-serif;
    color: var(--navy-deep);
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .toolbar { padding: 16px; text-align: center; background: #fff; border-bottom: 1px solid #e5e7eb; margin-bottom: 20px; }
  .toolbar button { padding: 10px 30px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; font-weight: 700; font-family: inherit; }
  .btn-print { background: #3b82f6; color: #fff; }
  .btn-close { background: #6b7280; color: #fff; font-size: 14px !important; padding: 10px 20px !important; margin-right: 10px; }
  .toolbar .count { font-size: 14px; color: #374151; margin-right: 16px; }

  /* ===== Label: 60 mm x 40 mm ===== */
  .label {
    width: 60mm; height: 40mm; padding: 1.5mm;
    margin: 0 auto 6mm; background: #fff; overflow: hidden;
    page-break-after: always; break-after: page;
  }
  .label:last-child { page-break-after: auto; break-after: auto; }

  .card {
    width: 100%; height: 100%;
    display: flex; flex-direction: column;
    background: var(--paper);
    border: 0.3mm solid var(--navy);
    border-radius: 2mm;
    padding: 1.2mm 1.8mm;
    overflow: hidden;
  }

  .head {
    display: flex; justify-content: space-between; align-items: center;
    border-bottom: 0.3mm dashed var(--navy);
    padding-bottom: 0.8mm;
  }
  .order-no { font-size: 11pt; font-weight: 900; line-height: 1.1; direction: ltr; unicode-bidi: isolate; }
  .brand { font-size: 6.5pt; font-weight: 800; line-height: 1.2; text-align: left; color: var(--navy); }

  .row {
    display: flex; gap: 1mm; align-items: baseline;
    font-size: 7.5pt; font-weight: 700; line-height: 1.3; margin-top: 0.8mm;
  }
  .row .lbl { font-weight: 900; color: var(--navy); white-space: nowrap; }
  .row .val { flex: 1; min-width: 0; overflow-wrap: anywhere; }
  .row .val.ltr { direction: ltr; unicode-bidi: isolate; text-align: right; }
  .row.addr .val {
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden;
  }

  .bottom {
    margin-top: auto;
    display: flex; justify-content: space-between; align-items: center; gap: 1mm;
  }
  .chip {
    border: 0.3mm solid var(--navy); border-radius: 1mm;
    background: #fff; color: var(--navy);
    padding: 0.4mm 1.4mm; font-size: 7pt; font-weight: 900; white-space: nowrap;
  }
  .chip.due { background: var(--navy); color: #fff; }
  .chip small { font-size: 6pt; font-weight: 700; margin-right: 0.6mm; }

  /* ===== Print ===== */
  @page { size: 60mm 40mm; margin: 0; }
  @media print {
    html, body { background: #fff !important; margin: 0; }
    .no-print { display: none !important; }
    .label { margin: 0; }
  }
</style>
</head>
<body>
<div class="labels">

<div class="toolbar no-print">
    <button class="btn-print" onclick="window.print()">🖨 طباعة الآن</button>
    <button class="btn-close" onclick="window.close()">إغلاق</button>
    <span class="count"><?= count($orders) ?> ملصق</span>
</div>
<?php foreach ($orders as $o):
    $remaining = max(0, $o['final_amount'] - $o['paid_amount']);
    $is_paid = $remaining <= 0.01;
    $address = trim((string)($o['address'] ?? ''));
    $mobile  = trim((string)($o['mobile_number'] ?? ''));
?>
<div class="label">
  <div class="card">

    <div class="head">
        <span class="order-no"><?= htmlspecialchars($o['order_number']) ?></span>
        <span class="brand">
            Yaman<?php if ($show_company_name): ?><br><?= htmlspecialchars($company_name) ?><?php endif; ?>
        </span>
    </div>

    <div class="row"><span class="lbl">الاسم:</span><span class="val"><?= htmlspecialchars($o['name']) ?></span></div>

    <div class="row addr"><span class="lbl">العنوان التفصيلي:</span><span class="val"><?= $address !== '' ? nl2br(htmlspecialchars($address)) : '-' ?></span></div>

    <div class="row">
        <span class="lbl">الرقم:</span><span class="val ltr"><?= $mobile !== '' ? htmlspecialchars($mobile) : '-' ?></span>
    </div>

    <div class="bottom">
        <span class="chip">القطع: <?= (int)$o['piece_count'] ?></span>
        <span class="chip <?= $is_paid ? '' : 'due' ?>">
            <?php if ($is_paid): ?>مدفوع<?php else: ?>المتبقي: <?= number_format($remaining, 0) ?> <small><?= htmlspecialchars($currency_label) ?></small><?php endif; ?>
        </span>
    </div>

  </div>
</div>
<?php endforeach; ?>
</div>
</body>
</html>
