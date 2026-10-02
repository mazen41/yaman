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

// Company info (same source used by the invoice print pages)
$settings = [];
try {
    $settings = $db->query("SELECT * FROM system_settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $settings = [];
}
$company_name_ar = trim($settings['company_name'] ?? '');
$company_address = trim($settings['company_address'] ?? '');
$company_phone   = trim($settings['company_phone'] ?? '');
$company_email   = trim($settings['company_email'] ?? '');
$show_ar_name    = ($company_name_ar !== '' && strcasecmp($company_name_ar, 'Yaman') !== 0);

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
  :root {
    --primary: #3b82f6;
    --primary-dark: #2563eb;
    --primary-soft: #eff6ff;
    --success: #10b981;
    --success-soft: #ecfdf5;
    --danger: #ef4444;
    --danger-soft: #fef2f2;
    --ink: #111827;
    --muted: #6b7280;
    --line: #d1d5db;
  }
  * { margin: 0; padding: 0; box-sizing: border-box; }
  html, body { background: #f3f4f6; }
  body {
    font-family: 'Cairo', 'Segoe UI', Tahoma, Arial, sans-serif;
    color: var(--ink);
    -webkit-print-color-adjust: exact;
    print-color-adjust: exact;
  }
  .toolbar {
    padding: 16px; text-align: center; background: #fff;
    border-bottom: 1px solid #e5e7eb; margin-bottom: 20px;
  }
  .toolbar button {
    padding: 10px 30px; border: none; border-radius: 6px; font-size: 16px;
    cursor: pointer; font-weight: 700; font-family: inherit;
  }
  .btn-print { background: var(--primary); color: #fff; }
  .btn-close { background: var(--muted); color: #fff; font-size: 14px !important; padding: 10px 20px !important; margin-right: 10px; }
  .toolbar .count { font-size: 14px; color: #374151; margin-right: 16px; }

  /* ===== Label page: 4in x 6in ===== */
  .label {
    width: 4in; height: 6in;
    padding: 0.14in;
    background: #fff;
    margin: 0 auto 24px;
    page-break-after: always;
    break-after: page;
  }
  .label:last-child { page-break-after: auto; break-after: auto; }

  .card {
    position: relative;
    width: 100%; height: 100%;
    display: flex; flex-direction: column;
    border: 2.5px solid var(--primary-dark);
    border-radius: 18px;
    overflow: hidden;
    background: #fff;
    box-shadow: 0 6px 18px rgba(37, 99, 235, 0.18);
  }

  /* ----- Header ----- */
  .card-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
    color: #fff;
    padding: 12px 14px 10px;
    display: flex; align-items: center; gap: 12px;
  }
  .brand-logo {
    width: 62px; height: 62px; flex-shrink: 0;
    border: 2px solid rgba(255,255,255,.55);
    border-radius: 14px;
    background: rgba(255,255,255,.12);
    display: flex; align-items: center; justify-content: center;
    padding: 6px;
  }
  .brand-logo img { max-width: 100%; max-height: 100%; object-fit: contain; }
  .brand-logo .logo-fallback { font-size: 30px; font-weight: 900; }
  .brand-info { flex: 1; min-width: 0; }
  .brand-name { font-size: 26px; font-weight: 900; letter-spacing: .5px; line-height: 1.1; }
  .brand-name small { font-size: 14px; font-weight: 700; opacity: .9; margin-right: 6px; }
  .brand-meta { margin-top: 4px; font-size: 10.5px; line-height: 1.55; opacity: .95; font-weight: 600; }
  .brand-meta .ltr { direction: ltr; unicode-bidi: isolate; display: inline-block; }

  /* ----- Divider with ticket notches ----- */
  .divider { position: relative; height: 0; border-top: 2px dashed var(--line); margin: 0 14px; }
  .divider::before, .divider::after {
    content: ''; position: absolute; top: -11px;
    width: 20px; height: 20px; border-radius: 50%;
    background: #fff; border: 2.5px solid var(--primary-dark);
  }
  .divider::before { right: -25px; }
  .divider::after  { left: -25px; }

  /* ----- Body ----- */
  .card-body { flex: 1; padding: 14px 16px 6px; display: flex; flex-direction: column; gap: 9px; }

  .order-box {
    display: flex; align-items: center; justify-content: space-between;
    background: var(--primary-soft);
    border: 2px solid var(--primary);
    border-radius: 12px; padding: 6px 14px;
  }
  .order-box .lbl { font-size: 14px; font-weight: 800; color: var(--primary-dark); }
  .order-box .val { font-size: 30px; font-weight: 900; letter-spacing: 1px; line-height: 1.2; direction: ltr; unicode-bidi: isolate; }

  .field { display: flex; align-items: baseline; gap: 8px; border-bottom: 2px dotted var(--line); padding-bottom: 3px; }
  .field .lbl { font-size: 13px; font-weight: 800; color: var(--primary-dark); white-space: nowrap; min-width: 52px; }
  .field .val { flex: 1; font-size: 19px; font-weight: 800; line-height: 1.3; overflow-wrap: anywhere; }
  .field .val.ltr { direction: ltr; unicode-bidi: isolate; text-align: right; }

  .stats { display: grid; grid-template-columns: 1fr 1.35fr; gap: 10px; margin-top: 2px; }
  .stat { border-radius: 12px; padding: 6px 10px; text-align: center; border: 2px solid; }
  .stat .lbl { font-size: 12px; font-weight: 800; }
  .stat .val { font-size: 26px; font-weight: 900; line-height: 1.2; }
  .stat .val small { font-size: 12px; font-weight: 700; margin-right: 3px; }
  .stat.count { background: var(--primary-soft); border-color: var(--primary); color: var(--primary-dark); }
  .stat.due   { background: var(--danger-soft);  border-color: var(--danger);  color: #b91c1c; }
  .stat.paid  { background: var(--success-soft); border-color: var(--success); color: #047857; }

  /* ----- Footer ----- */
  .card-footer {
    background: var(--ink);
    color: #fff; text-align: center;
    padding: 8px 12px;
    font-size: 17px; font-weight: 800;
    display: flex; align-items: center; justify-content: center; gap: 8px;
  }
  .card-footer .ltr { direction: ltr; unicode-bidi: isolate; letter-spacing: 1.5px; }
  .card-footer svg { width: 16px; height: 16px; fill: #fff; }

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

    <!-- Header: logo + company info -->
    <div class="card-header">
        <div class="brand-logo">
            <?php if ($logo_src): ?>
                <img src="<?= htmlspecialchars($logo_src) ?>" alt="Yaman">
            <?php else: ?>
                <span class="logo-fallback">Y</span>
            <?php endif; ?>
        </div>
        <div class="brand-info">
            <div class="brand-name">Yaman<?php if ($show_ar_name): ?><small><?= htmlspecialchars($company_name_ar) ?></small><?php endif; ?></div>
            <div class="brand-meta">
                <?php if ($company_address !== ''): ?><div><?= htmlspecialchars($company_address) ?></div><?php endif; ?>
                <?php if ($company_phone !== ''): ?><div>هاتف: <span class="ltr"><?= htmlspecialchars($company_phone) ?></span></div><?php endif; ?>
                <?php if ($company_email !== ''): ?><div><span class="ltr"><?= htmlspecialchars($company_email) ?></span></div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="divider"></div>

    <!-- Order details -->
    <div class="card-body">
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
                <div class="val"><?= $is_paid ? 'مدفوع' : number_format($remaining, 0) . '<small>ريال</small>' ?></div>
            </div>
        </div>
    </div>

    <!-- Footer phone -->
    <?php if ($company_phone !== ''): ?>
    <div class="card-footer">
        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M6.6 10.8a15.1 15.1 0 0 0 6.6 6.6l2.2-2.2a1 1 0 0 1 1-.25 11.4 11.4 0 0 0 3.6.57 1 1 0 0 1 1 1V20a1 1 0 0 1-1 1A17 17 0 0 1 3 4a1 1 0 0 1 1-1h3.5a1 1 0 0 1 1 1c0 1.25.2 2.45.57 3.57a1 1 0 0 1-.25 1z"/></svg>
        <span class="ltr"><?= htmlspecialchars($company_phone) ?></span>
    </div>
    <?php endif; ?>

  </div>
</div>
<?php endforeach; ?>
</body>
</html>
