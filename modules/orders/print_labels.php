<?php
session_start();
require_once '../../config/database.php';

$raw_ids = $_GET['ids'] ?? '';
$ids = array_filter(array_map('intval', explode(',', $raw_ids)));
if (empty($ids)) { die('<p style="text-align:center;padding:40px;">لا توجد طلبات محددة</p>'); }

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $db->prepare("
    SELECT co.id, co.order_number, c.name, c.mobile_number, c.location_area, c.address,
           co.final_amount, co.paid_amount,
           COALESCE((SELECT SUM(oi.quantity) FROM order_items oi WHERE oi.order_id = co.id), 0) AS piece_count
    FROM customer_orders co
    JOIN customers c ON c.id = co.customer_id
    WHERE co.id IN ($placeholders)
    ORDER BY co.order_number ASC
");
$stmt->execute($ids);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Company phone and currency from System Settings (key/value table)
$settings = [];
try {
    foreach ($db->query("SELECT setting_key, setting_value FROM system_settings") as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    $settings = [];
}
$currency_label = trim($settings['currency'] ?? '') !== '' ? trim($settings['currency']) : 'ريال';

// Company phone without country code (e.g. "+967 777 123 456" -> "777123456")
function label_local_phone($raw) {
    $d = preg_replace('/\D+/', '', (string)$raw);
    if (strpos($d, '00') === 0) $d = substr($d, 2);
    if (strpos($d, '967') === 0 && strlen($d) > 9) $d = substr($d, 3);
    return $d;
}
$company_phone = label_local_phone($settings['company_phone'] ?? '');

// Logo from project assets (first one that exists)
$logo_src = null;
foreach (['yamman_logo.png', 'logo.png'] as $logo_file) {
    if (file_exists(__DIR__ . '/../../assets/images/' . $logo_file)) {
        $logo_src = '../../assets/images/' . $logo_file;
        break;
    }
}

// Fixed city line shown on every label
$fixed_city_line = 'صنعاء - شارع تونس خلف استوديو القاهره';
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
    width: 60mm; height: 40mm; padding: 1.2mm;
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
    padding: 1mm 1.5mm;
    overflow: hidden;
  }

  /* Header: logo + brand on the left, company phone on the right */
  .head {
    display: flex; justify-content: space-between; align-items: center; gap: 1mm;
    border-bottom: 0.3mm dashed var(--navy);
    padding-bottom: 0.6mm;
  }
  .brand { display: flex; align-items: center; gap: 1mm; }
  .brand img { height: 5mm; width: auto; max-width: 6mm; object-fit: contain; }
  .brand-en { font-size: 6.5pt; font-weight: 900; color: var(--navy); direction: ltr; unicode-bidi: isolate; }
  .phone-top { font-size: 6.5pt; font-weight: 900; color: var(--navy); direction: ltr; unicode-bidi: isolate; white-space: nowrap; }

  /* Customer name, divider, then one field per row */
  .name-row { display: flex; justify-content: space-between; align-items: center; gap: 1mm; margin-top: 0.6mm; }
  .name { font-size: 8pt; font-weight: 900; line-height: 1.2; min-width: 0; overflow-wrap: anywhere; }
  .ordinfo { font-size: 6pt; font-weight: 900; color: var(--navy); white-space: nowrap; }
  .divider { border-top: 0.3mm solid var(--navy); margin: 0.6mm 0 0.8mm; }

  /* Label column is sized to the widest label, so every value starts on the same line */
  .fields {
    display: grid;
    grid-template-columns: max-content 1fr;
    column-gap: 1.2mm;
    row-gap: 0.5mm;
    font-size: 6.5pt;
    line-height: 1.25;
  }
  .fk { font-weight: 900; color: var(--navy); white-space: nowrap; }
  .fv { font-weight: 700; min-width: 0; overflow-wrap: anywhere; }
  .fv.ltr { direction: ltr; text-align: right; unicode-bidi: isolate; }
  .fv.addr { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }

  /* Bottom bar */
  .bottom { margin-top: auto; display: flex; gap: 1mm; align-items: stretch; }
  .chip {
    border: 0.3mm solid var(--navy); border-radius: 1mm;
    background: #fff; color: var(--navy);
    padding: 0.5mm 1.2mm; font-size: 6.5pt; font-weight: 900; white-space: nowrap;
    display: flex; align-items: center; justify-content: center;
  }
  .chip.rem { flex: 1 1 auto; background: var(--navy); color: #fff; }
  .chip small { font-size: 5.8pt; font-weight: 700; margin-right: 0.5mm; }

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
    $area    = trim((string)($o['location_area'] ?? ''));
    $mobile  = trim((string)($o['mobile_number'] ?? ''));
?>
<div class="label">
  <div class="card">

    <div class="head">
        <div class="brand">
            <?php if ($logo_src): ?><img src="<?= htmlspecialchars($logo_src) ?>" alt="Yaman"><?php endif; ?>
            <span class="brand-en">YAMAN STORE</span>
        </div>
        <?php if ($company_phone !== ''): ?>
        <span class="phone-top"><?= htmlspecialchars($company_phone) ?></span>
        <?php endif; ?>
    </div>

    <div class="name-row">
        <span class="name"><?= htmlspecialchars($o['name']) ?></span>
        <span class="ordinfo">الطلب: <?= htmlspecialchars($o['order_number']) ?> | القطع: <?= (int)$o['piece_count'] ?></span>
    </div>
    <div class="divider"></div>

    <div class="fields">
        <span class="fk">المدينة:</span>
        <span class="fv"><?= htmlspecialchars($fixed_city_line) ?></span>

        <span class="fk">المنطقة / الحي:</span>
        <span class="fv"><?= $area !== '' ? htmlspecialchars($area) : '-' ?></span>

        <span class="fk">الرقم:</span>
        <span class="fv ltr"><?= $mobile !== '' ? htmlspecialchars($mobile) : '-' ?></span>

        <span class="fk">العنوان التفصيلي:</span>
        <span class="fv addr"><?= $address !== '' ? htmlspecialchars($address) : '-' ?></span>
    </div>

    <div class="bottom">
        <span class="chip rem <?= $is_paid ? '' : '' ?>">
            <?php if ($is_paid): ?>مدفوع<?php else: ?>المتبقي: <?= number_format($remaining, 0) ?> <small><?= htmlspecialchars($currency_label) ?></small><?php endif; ?>
        </span>
    </div>

  </div>
</div>
<?php endforeach; ?>
</div>
</body>
</html>
