<?php
/**
 * System Reset Script - Clear All Data for Production
 * ⚠️ WARNING: This will DELETE ALL DATA except system users and settings
 * Run this ONCE then DELETE this file immediately!
 */

// Security check - require confirmation parameter
if (!isset($_GET['confirm']) || $_GET['confirm'] !== 'YES_DELETE_ALL') {
    echo "<html dir='rtl'><head><meta charset='UTF-8'><title>إعادة تعيين النظام</title></head><body style='font-family: Arial; padding: 40px; text-align: center;'>";
    echo "<h1 style='color: red;'>⚠️ تحذير: إعادة تعيين النظام</h1>";
    echo "<p style='font-size: 18px;'>هذا السكريبت سيحذف <strong>جميع البيانات</strong> من النظام:</p>";
    echo "<ul style='text-align: right; max-width: 400px; margin: 20px auto; font-size: 16px;'>";
    echo "<li>جميع الطلبات</li>";
    echo "<li>جميع الفواتير</li>";
    echo "<li>جميع المدفوعات</li>";
    echo "<li>جميع العملاء</li>";
    echo "<li>جميع المنتجات</li>";
    echo "<li>جميع السلات</li>";
    echo "<li>جميع بطاقات الشراء</li>";
    echo "<li>جميع الكوبونات</li>";
    echo "</ul>";
    echo "<p style='color: green; font-size: 16px;'>✅ سيتم الاحتفاظ بـ: المستخدمين، الإعدادات، أنواع العملاء، المدن</p>";
    echo "<br><br>";
    echo "<a href='?confirm=YES_DELETE_ALL' style='background: red; color: white; padding: 15px 30px; text-decoration: none; font-size: 18px; border-radius: 5px;' onclick=\"return confirm('هل أنت متأكد تماماً؟ لا يمكن التراجع!');\">🗑️ نعم، احذف جميع البيانات</a>";
    echo "<br><br><br>";
    echo "<a href='/' style='color: #666;'>إلغاء والعودة للرئيسية</a>";
    echo "</body></html>";
    exit;
}

require_once __DIR__ . '/config/database.php';

echo "<html dir='rtl'><head><meta charset='UTF-8'><title>جاري إعادة التعيين...</title></head><body style='font-family: monospace; padding: 20px;'>";
echo "<h2>🔄 جاري إعادة تعيين النظام...</h2>";
echo "<pre style='background: #f5f5f5; padding: 20px; border-radius: 5px;'>";

try {
    $db->beginTransaction();
    
    // Disable foreign key checks temporarily
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Tables to clear (order matters for foreign keys)
    $tables_to_clear = [
        // Order related
        'order_items',
        'order_status_history',
        'order_notifications',
        'order_images',
        'order_documents',
        
        // Invoice & Payment related
        'customer_payments',
        'customer_invoices',
        'invoice_items',
        
        // Orders
        'customer_orders',
        
        // Customers
        'customers',
        
        // Products
        'products',
        'product_categories',
        
        // Purchase/Baskets
        'basket_items',
        'purchase_baskets',
        'purchase_groups',
        
        // Purchase Cards
        'purchase_card_items',
        'purchase_cards',
        
        // Coupons
        'coupon_usage',
        'coupons',
        
        // Shipping
        'shipping_companies',
        
        // Notifications
        'notifications',
        
        // Activity logs (optional - keep for audit)
        // 'activity_logs',
    ];
    
    $cleared = 0;
    $skipped = 0;
    
    foreach ($tables_to_clear as $table) {
        try {
            // Check if table exists
            $check = $db->query("SHOW TABLES LIKE '$table'");
            if ($check->rowCount() > 0) {
                // Get count before delete
                $count_stmt = $db->query("SELECT COUNT(*) FROM `$table`");
                $count = $count_stmt->fetchColumn();
                
                // Truncate table (faster than DELETE, resets AUTO_INCREMENT)
                $db->exec("TRUNCATE TABLE `$table`");
                
                echo "✅ $table - تم حذف $count سجل\n";
                $cleared++;
            } else {
                echo "⏭️ $table - الجدول غير موجود\n";
                $skipped++;
            }
        } catch (PDOException $e) {
            echo "⚠️ $table - خطأ: " . $e->getMessage() . "\n";
        }
    }
    
    // Re-enable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    $db->commit();
    
    echo "\n";
    echo "═══════════════════════════════════════════\n";
    echo "✅ تم إعادة تعيين النظام بنجاح!\n";
    echo "═══════════════════════════════════════════\n";
    echo "📊 جداول تم مسحها: $cleared\n";
    echo "⏭️ جداول تم تخطيها: $skipped\n";
    echo "\n";
    echo "📌 تم الاحتفاظ بـ:\n";
    echo "   - المستخدمين (users)\n";
    echo "   - الإعدادات (settings)\n";
    echo "   - أنواع العملاء (customer_types)\n";
    echo "   - المدن (cities)\n";
    echo "   - الأدوار والصلاحيات (roles, permissions)\n";
    echo "\n";
    echo "</pre>";
    
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 5px; margin-top: 20px;'>";
    echo "<h3 style='color: #155724; margin: 0;'>🎉 النظام جاهز للإنتاج!</h3>";
    echo "<p style='margin: 10px 0 0 0;'>يمكنك الآن البدء بإضافة البيانات الحقيقية.</p>";
    echo "</div>";
    
    echo "<br><br>";
    echo "<p style='color: red; font-weight: bold;'>⚠️ مهم جداً: احذف هذا الملف الآن!</p>";
    echo "<code style='background: #fee; padding: 10px; display: block;'>rm /home/taksoride-admin/htdocs/reset_system.php</code>";
    
    echo "<br><br>";
    echo "<a href='/' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>🏠 الذهاب للرئيسية</a>";
    
} catch (PDOException $e) {
    $db->rollBack();
    echo "\n❌ خطأ: " . $e->getMessage() . "\n";
    echo "</pre>";
}

echo "</body></html>";
?>
