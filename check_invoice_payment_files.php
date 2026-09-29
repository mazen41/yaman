<?php
echo "<h2>Files in modules/invoices/:</h2>";
$invoice_dir = '/home/taksoride-admin/htdocs/modules/invoices/';
if (is_dir($invoice_dir)) {
    $files = scandir($invoice_dir);
    echo "<pre>";
    print_r($files);
    echo "</pre>";
}

echo "<hr><h2>Files in modules/payments/:</h2>";
$payments_dir = '/home/taksoride-admin/htdocs/modules/payments/';
if (is_dir($payments_dir)) {
    $files = scandir($payments_dir);
    echo "<pre>";
    print_r($files);
    echo "</pre>";
}

echo "<hr><h2>Looking for PDF/download files:</h2>";
$possible_files = [
    '/home/taksoride-admin/htdocs/modules/invoices/download.php',
    '/home/taksoride-admin/htdocs/modules/invoices/pdf.php',
    '/home/taksoride-admin/htdocs/modules/invoices/generate_pdf.php',
    '/home/taksoride-admin/htdocs/modules/payments/download.php',
    '/home/taksoride-admin/htdocs/modules/payments/pdf.php',
    '/home/taksoride-admin/htdocs/modules/payments/download_receipt.php',
    '/home/taksoride-admin/htdocs/download_receipt.php',
];

foreach ($possible_files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ FOUND: {$file}</p>";
    } else {
        echo "<p style='color: red;'>❌ NOT FOUND: {$file}</p>";
    }
}
?>
