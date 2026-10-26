<?php
/**
 * Server-side PDF generator for order stickers (4 x 6 in, one label per page).
 * Usage: labels_pdf.php?ids=1,2,3
 */
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Not logged in');
}

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/database.php';

use Mpdf\Mpdf;
use Mpdf\Output\Destination;

function h($v): string {
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

// ---------- Input ----------
$ids = array_values(array_filter(array_map('intval', explode(',', $_GET['ids'] ?? ''))));
if (!$ids) {
    http_response_code(400);
    exit('No orders selected');
}

// ---------- Data ----------
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
if (!$orders) {
    http_response_code(404);
    exit('No orders found');
}

$settings = [];
foreach ($db->query("SELECT setting_key, setting_value FROM system_settings") as $row) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
$company_name    = trim($settings['company_name'] ?? '');
$company_address = trim($settings['company_address'] ?? '');
$company_phone   = trim($settings['company_phone'] ?? '');
$company_email   = trim($settings['company_email'] ?? '');
$company_site    = preg_replace('#^https?://#i', '', rtrim(trim($settings['company_website'] ?? ''), '/'));
$currency_label  = trim($settings['currency'] ?? '') !== '' ? trim($settings['currency']) : 'ريال';
$show_company_name = ($company_name !== '' && strcasecmp($company_name, 'Yaman') !== 0);

$logo_path = null;
foreach (['yamman_logo.png', 'logo.png'] as $f) {
    $p = __DIR__ . '/../../assets/images/' . $f;
    if (is_file($p)) { $logo_path = $p; break; }
}

// ---------- Build PDF ----------
$mpdf = new Mpdf([
    'mode'            => 'utf-8',
    'format'          => [101.6, 106],   // sticker size: 4 in wide x ~4.2 in tall
    'orientation'     => 'P',
    'margin_left'     => 0,
    'margin_right'    => 0,
    'margin_top'      => 0,
    'margin_bottom'   => 0,
    'margin_header'   => 0,
    'margin_footer'   => 0,
    'default_font'    => 'dejavusans',
    'autoScriptToLang' => true,
    'autoLangToFont'  => true,
    'tempDir'         => sys_get_temp_dir(),
]);
$mpdf->autoArabic = true;
$mpdf->SetDirectionality('rtl');
$mpdf->SetTitle('Order Labels');
$mpdf->SetAuthor($company_name !== '' ? $company_name : 'Yaman');

$css = <<<CSS
body { font-family: dejavusans; color: #1b2347; font-size: 10pt; }
.card { width: 376px; height: 396px; overflow: hidden; border: 1.5px solid #cfb86f; background: #ecd9a0; border-radius: 18px; padding: 10px 14px; }
.brand-name { font-size: 20pt; font-weight: bold; color: #27346b; }
.brand-sub  { font-size: 10pt; font-weight: bold; }
.meta       { font-size: 7.5pt; font-weight: bold; color: #1b2347; }
.logo-box   { background: #27346b; border-radius: 12px; width: 52px; height: 52px; text-align: center; }
.divider    { border-top: 1.5px dashed #27346b; margin: 8px 0 10px 0; }
.panel      { background: #e8f0fc; border: 2.5px solid #27346b; border-radius: 16px; padding: 10px 12px 16px 12px; }
.order-box  { background: #ffffff; border: 2px solid #27346b; border-radius: 10px; padding: 4px 12px; }
.order-lbl  { font-size: 11pt; font-weight: bold; color: #27346b; }
.order-val  { font-size: 22pt; font-weight: bold; direction: ltr; }
.field      { border-bottom: 1.5px dotted #27346b; padding: 3px 0; }
.f-lbl      { font-size: 9.5pt; font-weight: bold; color: #27346b; }
.f-val      { font-size: 13pt; font-weight: bold; }
.ltr        { direction: ltr; }
.stat       { border: 2px solid #27346b; border-radius: 10px; text-align: center; padding: 4px; }
.stat-lbl   { font-size: 9pt; font-weight: bold; }
.stat-val   { font-size: 18pt; font-weight: bold; }
.stat-count { background: #ffffff; color: #27346b; }
.stat-due   { background: #27346b; color: #ffffff; }
.stat-paid  { background: #ffffff; color: #27346b; }
.phone      { text-align: center; margin-top: -12px; }
.phone span { background: #ecd9a0; border: 2.5px solid #27346b; border-radius: 30px; padding: 4px 18px; font-size: 13pt; font-weight: bold; direction: ltr; }
CSS;

foreach ($orders as $i => $o) {
    $remaining = max(0, (float)$o['final_amount'] - (float)$o['paid_amount']);
    $is_paid   = $remaining <= 0.01;

    $logo_html = $logo_path
        ? '<img src="' . h($logo_path) . '" style="width:40px; height:40px;" />'
        : '<span style="color:#fff; font-size:22pt; font-weight:bold;">Y</span>';

    $meta = '';
    if ($company_address !== '') $meta .= '<div>' . h($company_address) . '</div>';
    if ($company_email !== '')   $meta .= '<div class="ltr">' . h($company_email) . '</div>';
    if ($company_site !== '')    $meta .= '<div class="ltr">' . h($company_site) . '</div>';

    $html = '<style>' . $css . '</style>
    <div class="card">
      <table width="100%" cellspacing="0" cellpadding="0"><tr>
        <td width="60" valign="middle"><div class="logo-box">' . $logo_html . '</div></td>
        <td valign="middle" style="padding-right:8px;">
          <div class="brand-name">Yaman</div>'
          . ($show_company_name ? '<div class="brand-sub">' . h($company_name) . '</div>' : '') .
          '<div class="meta">' . $meta . '</div>
        </td>
      </tr></table>

      <div class="divider"></div>

      <div class="panel">
        <table width="100%" cellspacing="0" cellpadding="0"><tr><td class="order-box">
          <table width="100%" cellspacing="0" cellpadding="0"><tr>
            <td class="order-lbl" align="right">رقم الطلب</td>
            <td class="order-val" align="left">' . h($o['order_number']) . '</td>
          </tr></table>
        </td></tr></table>

        <div style="height:8px;"></div>
        <table width="100%" cellspacing="0" cellpadding="0">
          <tr><td class="field"><span class="f-lbl">الاسم:</span> &nbsp; <span class="f-val">' . h($o['name']) . '</span></td></tr>
          <tr><td class="field"><span class="f-lbl">الموقع:</span> &nbsp; <span class="f-val">' . h(($o['city_name'] ?? '') !== '' ? $o['city_name'] : '-') . '</span></td></tr>
          <tr><td class="field"><span class="f-lbl">الرقم:</span> &nbsp; <span class="f-val ltr">' . h(($o['mobile_number'] ?? '') !== '' ? $o['mobile_number'] : '-') . '</span></td></tr>
        </table>

        <div style="height:10px;"></div>
        <table width="100%" cellspacing="8" cellpadding="0"><tr>
          <td class="stat stat-due" width="55%">
            <div class="stat-lbl">المتبقي</div>
            <div class="stat-val">' . ($is_paid ? 'مدفوع' : number_format($remaining, 0) . ' ' . h($currency_label)) . '</div>
          </td>
          <td class="stat stat-count" width="45%">
            <div class="stat-lbl">عدد القطع</div>
            <div class="stat-val">' . (int)$o['piece_count'] . '</div>
          </td>
        </tr></table>
      </div>

      ' . ($company_phone !== '' ? '<div class="phone"><span>' . h($company_phone) . '</span></div>' : '') . '
    </div>';

    if ($i > 0) {
        $mpdf->AddPage();
    }
    $mpdf->WriteHTML($html);
}

$filename = 'labels-' . date('Y-m-d') . '.pdf';
$mpdf->Output($filename, Destination::DOWNLOAD);
