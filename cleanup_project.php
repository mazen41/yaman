<?php
/**
 * Project Cleanup Script
 * Removes mock data files and organizes setup scripts
 */

echo "<!DOCTYPE html>
<html lang='ar' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <title>تنظيف المشروع</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link href='https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap' rel='stylesheet'>
    <style>* { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class='bg-gray-50 p-8'>
    <div class='max-w-4xl mx-auto'>
        <div class='bg-white rounded-lg shadow-lg p-8'>
            <h1 class='text-3xl font-bold text-gray-800 mb-6'>🧹 تنظيف المشروع</h1>";

// Files to delete (test/mock data files)
$files_to_delete = [
    'add_sample_data.php',
    'create_sample_orders.php',
    'setup_sample_data.php',
    'add_inventory_sample_data.php',
    'test_reports_system.php',
    'test_orders.php',
    'test_db.php',
    'final_test.php',
    'debug_db.php',
    'debug_stock_movements.php',
    'run_sync.php'
];

// Files to move to setup folder
$files_to_move = [
    'fix_view_warnings.php',
    'fix_reports_database.php',
    'complete_database_fix.php',
    'check_db_structure.php',
    'check_tables.php',
    'complete_fix.php',
    'fix_database_tables.php',
    'create_advanced_purchases.php',
    'create_purchases_database.php',
    'create_reports_system.php',
    'check_database_structure.php',
    'complete_coupon_fix.php',
    'fix_stock_movements_table.php',
    'setup_coupons_system.php',
    'fix_notifications_table.php',
    'setup_invoice_tables.php',
    'final_coupon_verification.php',
    'reports_system_summary.php',
    'purchases_system_summary.php',
    'install_customer_portal.php',
    'install_order_tracking_tables.php'
];

echo "<div class='space-y-4'>";

// Delete test files
echo "<h2 class='text-2xl font-bold text-red-600 mb-4'>🗑️ حذف ملفات الاختبار</h2>";
foreach ($files_to_delete as $file) {
    if (file_exists($file)) {
        if (unlink($file)) {
            echo "<div class='flex items-center p-3 bg-red-50 border-r-4 border-red-500 rounded'>
                    <span class='text-red-800'>✓ تم حذف: {$file}</span>
                  </div>";
        } else {
            echo "<div class='flex items-center p-3 bg-yellow-50 border-r-4 border-yellow-500 rounded'>
                    <span class='text-yellow-800'>⚠️ فشل حذف: {$file}</span>
                  </div>";
        }
    }
}

// Move setup files
echo "<h2 class='text-2xl font-bold text-blue-600 mb-4 mt-8'>📦 نقل ملفات الإعداد</h2>";
foreach ($files_to_move as $file) {
    if (file_exists($file)) {
        $destination = 'setup/' . $file;
        if (rename($file, $destination)) {
            echo "<div class='flex items-center p-3 bg-blue-50 border-r-4 border-blue-500 rounded'>
                    <span class='text-blue-800'>✓ تم نقل: {$file} → setup/</span>
                  </div>";
        } else {
            echo "<div class='flex items-center p-3 bg-yellow-50 border-r-4 border-yellow-500 rounded'>
                    <span class='text-yellow-800'>⚠️ فشل نقل: {$file}</span>
                  </div>";
        }
    }
}

echo "</div>";

// Summary
echo "<div class='mt-8 p-6 bg-amber-50 border-2 border-amber-500 rounded-lg'>
        <h2 class='text-2xl font-bold text-amber-800 mb-4'>✅ تم التنظيف بنجاح!</h2>
        <ul class='list-disc list-inside text-amber-700 space-y-2'>
            <li>تم حذف جميع ملفات الاختبار والبيانات الوهمية</li>
            <li>تم نقل ملفات الإعداد إلى مجلد setup/</li>
            <li>المشروع الآن نظيف ويعتمد على قاعدة البيانات فقط</li>
            <li>جميع البيانات المعروضة حقيقية من قاعدة البيانات</li>
        </ul>
        
        <div class='mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded'>
            <h3 class='font-bold text-yellow-800 mb-2'>📝 ملاحظات:</h3>
            <ul class='list-disc list-inside text-yellow-700 space-y-1'>
                <li>ملفات الإعداد موجودة في مجلد setup/ للرجوع إليها عند الحاجة</li>
                <li>يمكنك حذف مجلد setup/ بعد التأكد من عمل النظام</li>
                <li>جميع الوحدات تستخدم بيانات حقيقية من قاعدة البيانات</li>
            </ul>
        </div>
        
        <div class='mt-6'>
            <a href='index.php' class='inline-block px-6 py-3 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition'>
                الذهاب إلى لوحة التحكم
            </a>
            <a href='customer_portal/login.php' class='inline-block px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition mr-3'>
                بوابة العملاء
            </a>
        </div>
      </div>";

echo "  </div>
    </div>
</body>
</html>";
?>
