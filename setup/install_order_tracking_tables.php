<?php
/**
 * Install Order Tracking Tables
 * Creates order_notes and order_status_history tables
 */

require_once 'config/database.php';

echo "<!DOCTYPE html>
<html lang='ar' dir='rtl'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>تثبيت جداول تتبع الطلبات</title>
    <script src='https://cdn.tailwindcss.com'></script>
    <link href='https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap' rel='stylesheet'>
    <style>* { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class='bg-gray-50'>
    <div class='min-h-screen py-12 px-4'>
        <div class='max-w-4xl mx-auto'>
            <div class='bg-white rounded-lg shadow-lg overflow-hidden'>
                <div class='bg-gradient-to-r from-blue-600 to-blue-700 px-8 py-6'>
                    <h1 class='text-3xl font-bold text-white'>تثبيت جداول تتبع الطلبات</h1>
                    <p class='text-blue-100 mt-2'>إنشاء الجداول المطلوبة للنظام</p>
                </div>
                <div class='p-8'>";

try {
    $sql_file = __DIR__ . '/database/create_order_tracking_tables.sql';
    
    if (!file_exists($sql_file)) {
        throw new Exception("ملف SQL غير موجود: $sql_file");
    }
    
    $sql = file_get_contents($sql_file);
    
    // Split SQL into individual statements
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && !preg_match('/^--/', $stmt);
        }
    );
    
    echo "<div class='space-y-4'>";
    
    foreach ($statements as $statement) {
        try {
            $db->exec($statement);
            
            if (preg_match('/CREATE TABLE.*?`?(\w+)`?/i', $statement, $matches)) {
                echo "<div class='flex items-center p-4 bg-amber-50 border-r-4 border-amber-500 rounded'>
                        <svg class='w-6 h-6 text-amber-500 ml-3' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 13l4 4L19 7'></path>
                        </svg>
                        <span class='text-amber-800'>تم إنشاء الجدول: {$matches[1]}</span>
                      </div>";
            }
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'already exists') === false) {
                echo "<div class='flex items-start p-4 bg-red-50 border-r-4 border-red-500 rounded'>
                        <svg class='w-6 h-6 text-red-500 ml-3 flex-shrink-0' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 18L18 6M6 6l12 12'></path>
                        </svg>
                        <span class='text-red-800'>{$e->getMessage()}</span>
                      </div>";
            }
        }
    }
    
    echo "</div>";
    
    // Verify tables
    echo "<div class='mt-8 p-6 bg-gray-50 rounded-lg'>
            <h2 class='text-xl font-bold text-gray-800 mb-4'>التحقق من الجداول</h2>";
    
    $tables_to_check = ['order_notes', 'order_status_history'];
    
    foreach ($tables_to_check as $table) {
        try {
            $stmt = $db->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() > 0) {
                echo "<div class='flex items-center mb-2'>
                        <svg class='w-5 h-5 text-amber-500 ml-2' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                            <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M5 13l4 4L19 7'></path>
                        </svg>
                        <span class='text-gray-700'>الجدول <strong>$table</strong> موجود</span>
                      </div>";
            }
        } catch (PDOException $e) {
            echo "<div class='flex items-center mb-2'>
                    <svg class='w-5 h-5 text-red-500 ml-2' fill='none' stroke='currentColor' viewBox='0 0 24 24'>
                        <path stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M6 18L18 6M6 6l12 12'></path>
                    </svg>
                    <span class='text-gray-700'>خطأ في التحقق من الجدول <strong>$table</strong></span>
                  </div>";
        }
    }
    
    echo "</div>";
    
    echo "<div class='mt-8 p-6 bg-amber-50 border-2 border-amber-500 rounded-lg'>
            <h2 class='text-2xl font-bold text-amber-800 mb-2'>✓ تم التثبيت بنجاح!</h2>
            <p class='text-amber-700 mb-4'>تم إنشاء جداول تتبع الطلبات بنجاح</p>
            <div class='space-y-2'>
                <a href='modules/orders/print.php?id=37' class='inline-block px-6 py-3 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition duration-200'>
                    اختبار الطباعة
                </a>
                <a href='modules/orders/index.php' class='inline-block px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200 mr-3'>
                    الذهاب إلى قائمة الطلبات
                </a>
            </div>
          </div>";
    
} catch (Exception $e) {
    echo "<div class='p-6 bg-red-50 border-2 border-red-500 rounded-lg'>
            <h2 class='text-2xl font-bold text-red-800 mb-2'>✗ فشل التثبيت</h2>
            <p class='text-red-700'>{$e->getMessage()}</p>
          </div>";
}

echo "      </div>
            </div>
        </div>
    </div>
</body>
</html>";
?>
