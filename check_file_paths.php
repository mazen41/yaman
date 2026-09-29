<?php
echo "<h2>Checking File Paths on Server:</h2>";

$files_to_check = [
    '/home/taksoride-admin/htdocs/invoices_print.php',
    '/home/taksoride-admin/htdocs/download_receipt.php',
    '/home/taksoride-admin/htdocs/modules/invoices/print.php',
    '/home/taksoride-admin/htdocs/modules/payments/receipt.php',
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✅ EXISTS: {$file}</p>";
    } else {
        echo "<p style='color: red;'>❌ NOT FOUND: {$file}</p>";
    }
}

echo "<hr><h2>Listing htdocs directory:</h2>";
$files = scandir('/home/taksoride-admin/htdocs/');
echo "<pre>";
print_r($files);
echo "</pre>";

echo "<hr><h2>Checking modules directory:</h2>";
if (is_dir('/home/taksoride-admin/htdocs/modules')) {
    $modules = scandir('/home/taksoride-admin/htdocs/modules/');
    echo "<pre>";
    print_r($modules);
    echo "</pre>";
}

echo "<hr><h2>Document Root:</h2>";
echo "<p>" . $_SERVER['DOCUMENT_ROOT'] . "</p>";

echo "<hr><h2>Current Script Path:</h2>";
echo "<p>" . __FILE__ . "</p>";
?>
