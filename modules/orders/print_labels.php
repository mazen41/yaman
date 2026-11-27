<?php
session_start();
require_once '../../config/database.php';

$raw_ids = $_GET['ids'] ?? '';
$ids = array_filter(array_map('intval', explode(',', $raw_ids)));
if (empty($ids)) { die('<p style="text-align:center;padding:40px;">لا توجد طلبات محددة</p>'); }

$placeholders = implode(',', array_fill(0, count($ids), '?'));
$stmt = $db->prepare("
    SELECT co.id, co.order_number, c.name, c.mobile_number, c.alternative_number, c.location_area, c.address, c.city_name,
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

// Label sizes (mm). 50x70 is the default for Xprinter XP-365B.
$size_presets = [
    '70x50' => [70, 50],
    '75x50' => [75, 50],
    '50x70' => [50, 70],
    '60x40' => [60, 40],
];
$size_key = (isset($_GET['size']) && isset($size_presets[$_GET['size']])) ? $_GET['size'] : '50x70';
[$label_w, $label_h] = $size_presets[$size_key];
$is_landscape = $label_w > $label_h;
$base_query = 'ids=' . urlencode($raw_ids);

// Safe margin on all sides (2.5 mm, within 2-3 mm thermal safe zone)
$safe = 2.5;
$compact = false;

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
  * { margin: 0; padding: 0; box-sizing: border-box; }
  html, body { background: #f3f4f6; }
  body {
    font-family: 'Cairo', 'Segoe UI', Tahoma, Arial, sans-serif;
    color: #000;
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .toolbar { padding: 16px; text-align: center; background: #fff; border-bottom: 1px solid #e5e7eb; margin-bottom: 20px; }
  .toolbar button { padding: 10px 30px; border: none; border-radius: 6px; font-size: 16px; cursor: pointer; font-weight: 700; font-family: inherit; }
  .btn-print { background: #3b82f6; color: #fff; }
  .btn-close { background: #6b7280; color: #fff; font-size: 14px !important; padding: 10px 20px !important; margin-right: 10px; }
  .toolbar .count { font-size: 14px; color: #374151; margin-right: 16px; }

  /* ===== Label ===== */
  .label {
    width: <?= $label_w ?>mm; height: <?= $label_h ?>mm;
    padding: <?= $safe ?>mm;
    margin: 0 auto 6mm; background: #fff; overflow: hidden;
    page-break-after: always; break-after: page;
  }
  .label:last-child { page-break-after: auto; break-after: auto; }

  .card {
    width: 100%; height: 100%;
    display: flex; flex-direction: column;
    background: #fff;
    border: 0.5mm solid #000;
    border-radius: 1.5mm;
    padding: <?= $label_h <= 45 ? '0.9mm 1.4mm' : '1.2mm 1.6mm' ?>;
  }

  /* Header: logo + brand on the left, company phone on the right */
  .head {
    display: flex; justify-content: space-between; align-items: center; gap: 1mm;
    border-bottom: 0.5mm solid #000;
    padding-bottom: <?= $label_h <= 45 ? '0.6mm' : '0.9mm' ?>;
  }
  .brand { display: flex; align-items: center; gap: 1mm; }
  .brand img { height: <?= $label_h <= 45 ? '4mm' : '5mm' ?>; width: auto; max-width: 6mm; object-fit: contain; filter: brightness(0); }
  .brand-en { font-size: 8.5pt; font-weight: 900; color: #000; direction: ltr; unicode-bidi: isolate; }
  .phone-top { font-size: 8.5pt; font-weight: 900; color: #000; direction: ltr; unicode-bidi: isolate; white-space: nowrap; }

  /* Body: name then location then phones stacked */
  .body-row { display: flex; flex-direction: column; gap: <?= $label_h <= 45 ? '0.8mm' : '1.2mm' ?>; margin-top: <?= $label_h <= 45 ? '0.8mm' : '1.2mm' ?>; flex: 1 1 auto; }

  .name {
    font-size: <?= $label_h <= 45 ? '11pt' : '12pt' ?>; font-weight: 900; line-height: 1.2;
    overflow-wrap: anywhere; text-align: center;
    border: 0.7mm solid #000; border-radius: 1mm;
    background: #fff; color: #000;
    padding: <?= $label_h <= 45 ? '0.6mm 1.2mm' : '0.9mm 1.6mm' ?>; display: block; width: 100%;
  }

  .location-line { font-size: <?= $label_h <= 45 ? '7.5pt' : '8.5pt' ?>; line-height: 1.3; overflow-wrap: anywhere; }
  .phones-line { font-size: <?= $label_h <= 45 ? '7.5pt' : '8.5pt' ?>; line-height: 1.3; overflow-wrap: anywhere; }
  .fk { font-weight: 900; color: #000; white-space: nowrap; }
  .loc-val { font-weight: 700; overflow-wrap: anywhere; }
  .ltr-val { direction: ltr; unicode-bidi: isolate; font-weight: 700; }

  /* Bottom bar */
  .bottom { margin-top: auto; display: flex; flex-direction: column; gap: <?= $label_h <= 45 ? '0.8mm' : '1.1mm' ?>; }
  .chip {
    border: 0.5mm solid #000; border-radius: 1mm;
    background: #fff; color: #000;
    padding: <?= $label_h <= 45 ? '0.5mm 1mm' : '0.7mm 1.2mm' ?>; font-size: <?= $label_h <= 45 ? '7.5pt' : '8pt' ?>; font-weight: 900;
    display: flex; align-items: center; justify-content: center; text-align: center;
    line-height: 1.2;
  }
  .chip.rem { width: 100%; background: #fff; color: #000; border: 0.7mm solid #000; font-size: <?= $label_h <= 45 ? '11pt' : '12pt' ?>; font-weight: 900; padding: <?= $label_h <= 45 ? '0.6mm 1.2mm' : '0.9mm 1.4mm' ?>; }
  .chip.ordchip { flex: 1 1 0; font-size: <?= $label_h <= 45 ? '7.5pt' : '8pt' ?>; }
  .ord-stack { display: flex; flex-direction: row; gap: 1mm; }
  .chip small { font-size: 8pt; font-weight: 800; margin-right: 0.6mm; }

  /* ===== Print ===== */
  @page { size: <?= $label_w ?>mm <?= $label_h ?>mm; margin: 0; }
  @media print {
    html, body { width: <?= $label_w ?>mm; background: #fff !important; margin: 0; padding: 0; }
    .no-print, .size-indicator { display: none !important; }
    .label { margin: 0; }
  }
  .size-indicator { font-size: 12px; color: #4b5563; margin-bottom: 8px; font-weight: 700; }
  .size-picks { margin-top: 12px; }
  .size-btn { display: inline-block; margin: 0 4px 6px; padding: 8px 14px; border: 2px solid #000; border-radius: 6px; color: #000; text-decoration: none; font-weight: 700; font-size: 14px; background: #fff; }
  .size-btn.active { background: #000; color: #fff; }
</style>
</head>
<body>
<div class="labels">

<div class="toolbar no-print">
    <div class="size-indicator no-print">المقاس الحالي: <?= $label_w ?> × <?= $label_h ?> مم (<?= $is_landscape ? 'أفقي' : 'عمودي' ?>)</div>
    <button class="btn-print" onclick="window.print()">🖨 طباعة الآن</button>
    <button class="btn-close" onclick="window.close()">إغلاق</button>
    <div class="size-picks">
        <?php foreach ($size_presets as $k => [$pw, $ph]): ?>
            <a class="size-btn<?= $k === $size_key ? ' active' : '' ?>" href="?<?= htmlspecialchars($base_query) ?>&size=<?= $k ?>"><?= $pw ?> × <?= $ph ?> مم</a>
        <?php endforeach; ?>
    </div>
    <span class="count"><?= count($orders) ?> ملصق</span>
</div>
<?php foreach ($orders as $o):
    $remaining = max(0, $o['final_amount'] - $o['paid_amount']);
    $is_paid = $remaining <= 0.01;
    $address    = trim((string)($o['address'] ?? ''));
    $city_name  = trim((string)($o['city_name'] ?? ''));
    $mobile     = trim((string)($o['mobile_number'] ?? ''));
    $alt_mobile = trim((string)($o['alternative_number'] ?? ''));
    if ($compact) {
        $c_rem = $is_paid ? 'مدفوع' : number_format($remaining, 0);
        $c_loc = trim(implode(' - ', array_filter([$city_name, $address])));
        $c_phones = trim($mobile . ($alt_mobile !== '' ? ' / ' . $alt_mobile : ''));
        ?>
        <div class="label">
          <div class="card" style="position:absolute;left:<?= $safe ?>mm;top:<?= $safe ?>mm;width:<?= $iw ?>mm;height:<?= $ih ?>mm;display:flex;flex-direction:column;justify-content:space-between;padding:0.5mm 1mm;">
            <div class="head" style="padding-bottom:0.3mm;">
              <span class="brand-en" style="font-size:<?= $sp ?>pt">YAMAN STORE</span>
              <span class="phone-top" style="font-size:<?= $sp ?>pt"><?= htmlspecialchars($company_phone) ?></span>
            </div>
            <div class="name" style="font-size:<?= $np ?>pt;display:block;text-align:center;line-height:1.15;"><?= htmlspecialchars($o['name']) ?></div>
            <div style="font-size:<?= $lt ?>pt;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">الموقع: <?= htmlspecialchars($c_loc) ?></div>
            <div style="font-size:<?= $lt ?>pt;line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;direction:ltr;text-align:right;"><?= htmlspecialchars($c_phones) ?></div>
            <div style="display:flex;justify-content:space-between;align-items:stretch;gap:1mm;">
              <div class="chip rem" style="font-size:<?= $ap ?>pt;flex:1 1 auto;min-width:0;white-space:nowrap;overflow:hidden;"><?= htmlspecialchars($c_rem) ?></div>
              <div style="display:flex;flex-direction:column;gap:0.4mm;flex:0 0 auto;">
                <div class="chip ordchip" style="font-size:<?= $sp ?>pt;white-space:nowrap;"># <?= htmlspecialchars($o['order_number']) ?></div>
                <div class="chip ordchip" style="font-size:<?= $sp ?>pt;white-space:nowrap;">قطع: <?= (int)$o['piece_count'] ?></div>
              </div>
            </div>
          </div>
        </div>
        <?php
        continue;
    }
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

    <div class="body-row">
        <span class="name"><?= htmlspecialchars($o['name']) ?></span>
        <?php $location_full = trim(implode(' - ', array_filter([$city_name, $address]))); ?>
        <div class="location-line"><span class="fk">الموقع:</span> <span class="loc-val"><?= htmlspecialchars($location_full ?: '-') ?></span></div>
        <div class="phones-line">
          <span class="fk">الرقم:</span>
          <span class="ltr-val"><?= $mobile !== '' ? htmlspecialchars($mobile) : '-' ?><?= $alt_mobile !== '' ? ' / ' . htmlspecialchars($alt_mobile) : '' ?></span>
        </div>
    </div>

    <div class="bottom">
        <div class="ord-stack">
            <span class="chip ordchip">رقم الطلب: <?= htmlspecialchars($o['order_number']) ?></span>
            <span class="chip ordchip">عدد القطع: <?= (int)$o['piece_count'] ?></span>
        </div>
        <span class="chip rem">
            <?php if ($is_paid): ?>مدفوع<?php else: ?>المتبقي: <?= number_format($remaining, 0) ?> <small>YR</small><?php endif; ?>
        </span>
    </div>

  </div>
</div>
<?php endforeach; ?>
</div>
</body>
</html>
