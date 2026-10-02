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
$company_address = trim($settings['company_address'] ?? '');
$company_phone   = trim($settings['company_phone'] ?? '');
$company_email   = trim($settings['company_email'] ?? '');
$company_website = trim($settings['company_website'] ?? '');
$company_site_display = preg_replace('#^https?://#i', '', rtrim($company_website, '/'));
$currency_label  = trim($settings['currency'] ?? '') !== '' ? trim($settings['currency']) : 'ريال';
$show_company_name = ($company_name !== '' && strcasecmp($company_name, 'Yaman') !== 0);

// Logo from project assets (first one that exists)
$logo_src = null;
foreach (['yamman_logo.png', 'logo.png'] as $logo_file) {
    if (file_exists(__DIR__ . '/../../assets/images/' . $logo_file)) {
        $logo_src = '../../assets/images/' . $logo_file;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>طباعة ملصقات</title>
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
  /* Colors taken from the reference sticker: cream-gold paper, navy outline, light-blue panel */
  :root {
    --gold: #ecd9a0;          /* sticker paper (set to #ffffff for plain white stock) */
    --gold-edge: #cfb86f;
    --navy: #27346b;
    --navy-deep: #1b2347;
    --panel: #e8f0fc;
    --dot: rgba(39, 52, 107, .45);
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

  /* ===== Label page: 4in x 6in ===== */
  .label { width: 4in; height: 6in; padding: 0.12in; background: #fff; margin: 0 auto 24px; page-break-after: always; break-after: page; }
  .label:last-child { page-break-after: auto; break-after: auto; }

  .card {
    position: relative; width: 100%; height: 100%;
    display: flex; flex-direction: column;
    background: var(--gold);
    border: 1.5px solid var(--gold-edge);
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 6px 18px rgba(39, 52, 107, 0.18);
  }

  /* ----- Header: logo + company info ----- */
  .card-header { display: flex; align-items: center; gap: 12px; padding: 12px 16px 10px; }
  .brand-logo {
    width: 60px; height: 60px; flex-shrink: 0;
    background: var(--navy); border-radius: 15px;
    display: flex; align-items: center; justify-content: center; padding: 8px;
  }
  .brand-logo img { max-width: 100%; max-height: 100%; object-fit: contain; }
  .brand-logo .logo-fallback { color: #fff; font-size: 30px; font-weight: 900; }
  .brand-info { flex: 1; min-width: 0; }
  .brand-name { font-size: 25px; font-weight: 900; line-height: 1.1; color: var(--navy); letter-spacing: .5px; }
  .brand-sub { font-size: 13px; font-weight: 800; color: var(--navy-deep); margin-top: 1px; }
  .brand-meta { margin-top: 4px; display: grid; gap: 1px; font-size: 10.5px; font-weight: 700; line-height: 1.45; color: var(--navy-deep); }
  .brand-meta div { display: flex; align-items: flex-start; gap: 5px; }
  .brand-meta svg { width: 11px; height: 11px; margin-top: 3px; flex-shrink: 0; fill: var(--navy); }
  .ltr { direction: ltr; unicode-bidi: isolate; display: inline-block; }

  /* ----- Divider with ticket notches ----- */
  .divider { position: relative; height: 0; border-top: 2px dashed var(--dot); margin: 0 16px 12px; }
  .divider::before, .divider::after {
    content: ''; position: absolute; top: -11px; width: 20px; height: 20px; border-radius: 50%;
    background: #fff; border: 1.5px solid var(--gold-edge);
  }
  .divider::before { right: -26px; }
  .divider::after  { left: -26px; }

  /* ----- Light-blue panel with navy outline (like the reference) ----- */
  .panel {
    flex: 1; margin: 0 14px;
    background: var(--panel); border: 2.5px solid var(--navy); border-radius: 18px;
    padding: 12px 14px 24px;
    display: flex; flex-direction: column; gap: 9px;
  }
  .order-box { display: flex; align-items: center; justify-content: space-between; background: #fff; border: 2px solid var(--navy); border-radius: 12px; padding: 5px 14px; }
  .order-box .lbl { font-size: 14px; font-weight: 800; color: var(--navy); }
  .order-box .val { font-size: 29px; font-weight: 900; letter-spacing: 1px; line-height: 1.2; direction: ltr; unicode-bidi: isolate; }

  .field { display: flex; align-items: baseline; gap: 8px; border-bottom: 2px dotted var(--dot); padding-bottom: 3px; }
  .field .lbl { font-size: 13px; font-weight: 800; color: var(--navy); white-space: nowrap; min-width: 52px; }
  .field .val { flex: 1; font-size: 19px; font-weight: 800; line-height: 1.3; overflow-wrap: anywhere; }
  .field .val.ltr { direction: ltr; unicode-bidi: isolate; text-align: right; display: block; }

  .stats { display: grid; grid-template-columns: 1fr 1.35fr; gap: 10px; margin-top: 2px; }
  .stat { border-radius: 12px; padding: 5px 10px; text-align: center; border: 2px solid var(--navy); }
  .stat .lbl { font-size: 12px; font-weight: 800; }
  .stat .val { font-size: 25px; font-weight: 900; line-height: 1.2; }
  .stat .val small { font-size: 11px; font-weight: 700; margin-right: 3px; }
  .stat.count { background: #fff; color: var(--navy); }
  .stat.due   { background: var(--navy); color: #fff; }
  .stat.paid  { background: #fff; color: var(--navy); }

  /* ----- Footer phone tab, straddling the panel edge ----- */
  .footer-tab {
    align-self: center; position: relative; z-index: 2;
    margin: -19px 0 12px;
    background: var(--gold); border: 2.5px solid var(--navy); border-radius: 999px;
    padding: 4px 22px; display: inline-flex; align-items: center; gap: 8px;
    font-size: 17px; font-weight: 900; color: var(--navy-deep);
  }
  .footer-tab svg { width: 15px; height: 15px; fill: var(--navy); }
  .footer-tab .ltr { letter-spacing: 1.5px; }
  .footer-spacer { height: 12px; }

  /* ===== Print ===== */
  @page { size: 4in 6in; margin: 0; }
  @media print {
    html, body { background: #fff !important; margin: 0; }
    .no-print { display: none !important; }
    .label { margin: 0; }
    .card { box-shadow: none; }
  }
</style>
</head>
<body>
<!-- Icon sprite -->
<svg width="0" height="0" style="position:absolute" aria-hidden="true">
  <symbol id="i-pin" viewBox="0 0 24 24"><path d="M12 2a7 7 0 0 0-7 7c0 5.2 7 13 7 13s7-7.8 7-13a7 7 0 0 0-7-7zm0 9.5A2.5 2.5 0 1 1 12 6.5a2.5 2.5 0 0 1 0 5z"/></symbol>
  <symbol id="i-phone" viewBox="0 0 24 24"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z"/></symbol>
  <symbol id="i-mail" viewBox="0 0 24 24"><path d="M3 5h18a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1zm9 7.2L4.5 7.5v1.2L12 13.4l7.5-4.7V7.5z"/></symbol>
  <symbol id="i-globe" viewBox="0 0 24 24"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm6.9 9h-3a15.7 15.7 0 0 0-1.3-6A8 8 0 0 1 18.9 11zM12 4c.8 1 1.6 3.100 1.9 7h-3.800C10.400 7.100 11.200 5 12 4zM4.600 13h3a15.700 15.700 0 0 0 1.300 6A8 8 0 0 1 4.600 13zm3-2h-3a8 8 0 0 1 4.300-6 15.700 15.700 0 0 0-1.300 6zM12 20c-.8-1-1.600-3.100-1.900-7h3.800c-.3 3.900-1.100 6-1.900 7zm2.600-1a15.700 15.700 0 0 0 1.300-6h3a8 8 0 0 1-4.300 6z"/></symbol>
</svg>

<div class="toolbar no-print">
    <button class="btn-print" onclick="window.print()">🖨 طباعة الآن</button>
    <button class="btn-close" onclick="window.close()">إغلاق</button>
    <span class="count"><?= count($orders) ?> ملصق</span>
</div>
<?php foreach ($orders as $o):
    $remaining = max(0, $o['final_amount'] - $o['paid_amount']);
    $is_paid = $remaining <= 0.01;
?>
<div class="label">
  <div class="card">

    <!-- Header: logo + company name / location / contact (from System Settings) -->
    <div class="card-header">
        <div class="brand-logo">
            <?php if ($logo_src): ?>
                <img src="<?= htmlspecialchars($logo_src) ?>" alt="Yaman">
            <?php else: ?>
                <span class="logo-fallback">Y</span>
            <?php endif; ?>
        </div>
        <div class="brand-info">
            <div class="brand-name">Yaman</div>
            <?php if ($show_company_name): ?><div class="brand-sub"><?= htmlspecialchars($company_name) ?></div><?php endif; ?>
            <div class="brand-meta">
                <?php if ($company_address !== ''): ?>
                    <div><svg><use href="#i-pin"/></svg><span><?= htmlspecialchars($company_address) ?></span></div>
                <?php endif; ?>
                <?php if ($company_email !== ''): ?>
                    <div><svg><use href="#i-mail"/></svg><span class="ltr"><?= htmlspecialchars($company_email) ?></span></div>
                <?php endif; ?>
                <?php if ($company_site_display !== ''): ?>
                    <div><svg><use href="#i-globe"/></svg><span class="ltr"><?= htmlspecialchars($company_site_display) ?></span></div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="divider"></div>

    <!-- Order details -->
    <div class="panel">
        <div class="order-box">
            <span class="lbl">رقم الطلب</span>
            <span class="val"><?= htmlspecialchars($o['order_number']) ?></span>
        </div>

        <div class="field"><span class="lbl">الاسم:</span><span class="val"><?= htmlspecialchars($o['name']) ?></span></div>
        <div class="field"><span class="lbl">الموقع:</span><span class="val"><?= htmlspecialchars(($o['city_name'] ?? '') !== '' ? $o['city_name'] : '-') ?></span></div>
        <div class="field"><span class="lbl">الرقم:</span><span class="val ltr"><?= htmlspecialchars(($o['mobile_number'] ?? '') !== '' ? $o['mobile_number'] : '-') ?></span></div>

        <div class="stats">
            <div class="stat count">
                <div class="lbl">عدد القطع</div>
                <div class="val"><?= (int)$o['piece_count'] ?></div>
            </div>
            <div class="stat <?= $is_paid ? 'paid' : 'due' ?>">
                <div class="lbl">المتبقي</div>
                <div class="val"><?= $is_paid ? 'مدفوع' : number_format($remaining, 0) . '<small>' . htmlspecialchars($currency_label) . '</small>' ?></div>
            </div>
        </div>
    </div>

    <!-- Footer: company phone -->
    <?php if ($company_phone !== ''): ?>
    <div class="footer-tab">
        <svg><use href="#i-phone"/></svg>
        <span class="ltr"><?= htmlspecialchars($company_phone) ?></span>
    </div>
    <?php else: ?>
    <div class="footer-spacer"></div>
    <?php endif; ?>

  </div>
</div>
<?php endforeach; ?>
</body>
</html>
