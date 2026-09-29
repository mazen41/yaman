<?php
echo "<h2>Checking payments module:</h2>";

$payments_dir = '/home/taksoride-admin/htdocs/modules/payments/';
if (is_dir($payments_dir)) {
    $files = scandir($payments_dir);
    echo "<pre>";
    print_r($files);
    echo "</pre>";
} else {
    echo "<p style='color: red;'>Payments directory not found!</p>";
}

echo "<hr><h2>Looking for receipt files:</h2>";
$possible_receipt_files = [
    '/home/taksoride-admin/htdocs/modules/payments/receipt.php',
    '/home/taksoride-admin/htdocs/modules/payments/print_receipt.php',
    '/home/taksoride-admin/htdocs/modules/payments/download_receipt.php',
    '/home/taksoride-admin/htdocs/modules/payments/view.php',
];

foreach ($possible_receipt_files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ FOUND: {$file}</p>";
    } else {
        echo "<p style='color: red;'>❌ NOT FOUND: {$file}</p>";
    }
}
?>
