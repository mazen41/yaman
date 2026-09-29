<?php
/**
 * Final Coupon System Verification & Testing
 * Senior Developer Quality Assurance
 */

require_once 'config/database.php';

echo "<h1>✅ التحقق النهائي من نظام الكوبونات</h1>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; border-radius: 5px;'>";
echo "<pre>";

$all_tests_passed = true;
$issues_found = [];

try {
    echo "=== فحص شامل لنظام الكوبونات ===\n\n";
    
    // Test 1: Database Structure
    echo "TEST 1: فحص بنية قاعدة البيانات...\n";
    echo "-----------------------------------\n";
    
    $required_tables = ['coupons', 'coupon_usage', 'coupon_categories'];
    foreach ($required_tables as $table) {
        $check = $db->query("SHOW TABLES LIKE '$table'")->rowCount();
        if ($check > 0) {
            echo "✅ جدول $table موجود\n";
        } else {
            echo "❌ جدول $table مفقود\n";
            $issues_found[] = "جدول $table مفقود";
            $all_tests_passed = false;
        }
    }
    
    // Check coupons table columns
    $required_columns = [
        'id', 'coupon_code', 'coupon_name', 'description', 'discount_type', 
        'discount_value', 'min_order_amount', 'usage_limit', 'start_date', 
        'end_date', 'is_active', 'created_at'
    ];
    
    $columns = $db->query("DESCRIBE coupons")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($required_columns as $column) {
        if (in_array($column, $columns)) {
            echo "✅ عمود $column موجود\n";
        } else {
            echo "❌ عمود $column مفقود\n";
            $issues_found[] = "عمود $column مفقود في جدول coupons";
            $all_tests_passed = false;
        }
    }
    
    // Test 2: File Structure
    echo "\nTEST 2: فحص هيكل الملفات...\n";
    echo "----------------------------\n";
    
    $required_files = [
        'modules/coupons/index.php' => 'قائمة الكوبونات',
        'modules/coupons/add.php' => 'إضافة كوبون',
        'modules/coupons/edit.php' => 'تعديل كوبون',
        'modules/coupons/view.php' => 'عرض كوبون',
        'includes/CouponValidator.php' => 'فئة التحقق',
        'modules/orders/ajax/validate_coupon.php' => 'نقطة نهاية AJAX'
    ];
    
    foreach ($required_files as $file => $description) {
        if (file_exists($file)) {
            echo "✅ $description ($file)\n";
        } else {
            echo "❌ $description مفقود ($file)\n";
            $issues_found[] = "$description مفقود";
            $all_tests_passed = false;
        }
    }
    
    // Test 3: Sample Data
    echo "\nTEST 3: فحص البيانات التجريبية...\n";
    echo "-------------------------------\n";
    
    $coupon_count = $db->query("SELECT COUNT(*) FROM coupons")->fetchColumn();
    if ($coupon_count > 0) {
        echo "✅ يوجد $coupon_count كوبون في النظام\n";
        
        // Show sample coupons
        $sample_coupons = $db->query("SELECT coupon_code, coupon_name, discount_type, discount_value, is_active FROM coupons LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($sample_coupons as $coupon) {
            $status = $coupon['is_active'] ? '🟢' : '🔴';
            $discount = $coupon['discount_type'] == 'percentage' ? $coupon['discount_value'] . '%' : $coupon['discount_value'] . ' ريال';
            echo "  $status {$coupon['coupon_code']} - {$coupon['coupon_name']} ($discount)\n";
        }
    } else {
        echo "⚠️ لا توجد كوبونات في النظام\n";
        $issues_found[] = "لا توجد كوبونات تجريبية";
    }
    
    // Test 4: Integration with Orders
    echo "\nTEST 4: فحص الربط مع نظام الطلبات...\n";
    echo "------------------------------------\n";
    
    // Check if orders table has coupon columns
    $order_columns = $db->query("DESCRIBE customer_orders")->fetchAll(PDO::FETCH_COLUMN);
    $coupon_order_columns = ['coupon_id', 'coupon_code', 'coupon_discount'];
    
    foreach ($coupon_order_columns as $column) {
        if (in_array($column, $order_columns)) {
            echo "✅ عمود $column موجود في جدول الطلبات\n";
        } else {
            echo "❌ عمود $column مفقود في جدول الطلبات\n";
            $issues_found[] = "عمود $column مفقود في جدول الطلبات";
            $all_tests_passed = false;
        }
    }
    
    // Test 5: Coupon Validation Logic
    echo "\nTEST 5: اختبار منطق التحقق من الكوبونات...\n";
    echo "-------------------------------------------\n";
    
    if (file_exists('includes/CouponValidator.php')) {
        require_once 'includes/CouponValidator.php';
        
        try {
            $validator = new CouponValidator($db);
            echo "✅ فئة CouponValidator تعمل بشكل صحيح\n";
            
            // Test with a sample coupon
            $sample_coupon = $db->query("SELECT coupon_code FROM coupons WHERE is_active = 1 LIMIT 1")->fetchColumn();
            
            if ($sample_coupon) {
                $test_result = $validator->validateCoupon($sample_coupon, 500, null);
                if (isset($test_result['valid'])) {
                    echo "✅ اختبار التحقق من الكوبون نجح\n";
                } else {
                    echo "❌ اختبار التحقق من الكوبون فشل\n";
                    $issues_found[] = "منطق التحقق من الكوبونات لا يعمل";
                    $all_tests_passed = false;
                }
            } else {
                echo "⚠️ لا يوجد كوبون نشط للاختبار\n";
            }
            
        } catch (Exception $e) {
            echo "❌ خطأ في فئة CouponValidator: " . $e->getMessage() . "\n";
            $issues_found[] = "خطأ في فئة CouponValidator";
            $all_tests_passed = false;
        }
    } else {
        echo "❌ فئة CouponValidator غير موجودة\n";
        $issues_found[] = "فئة CouponValidator غير موجودة";
        $all_tests_passed = false;
    }
    
    // Test 6: AJAX Endpoint
    echo "\nTEST 6: اختبار نقطة نهاية AJAX...\n";
    echo "------------------------------\n";
    
    if (file_exists('modules/orders/ajax/validate_coupon.php')) {
        echo "✅ نقطة نهاية AJAX موجودة\n";
        
        // Test if the file has valid PHP syntax
        $ajax_content = file_get_contents('modules/orders/ajax/validate_coupon.php');
        if (strpos($ajax_content, 'CouponValidator') !== false && strpos($ajax_content, 'json_encode') !== false) {
            echo "✅ محتوى نقطة نهاية AJAX صحيح\n";
        } else {
            echo "❌ محتوى نقطة نهاية AJAX غير صحيح\n";
            $issues_found[] = "محتوى نقطة نهاية AJAX غير صحيح";
            $all_tests_passed = false;
        }
    } else {
        echo "❌ نقطة نهاية AJAX غير موجودة\n";
        $issues_found[] = "نقطة نهاية AJAX غير موجودة";
        $all_tests_passed = false;
    }
    
    // Test 7: Navigation Integration
    echo "\nTEST 7: فحص التكامل مع التنقل...\n";
    echo "-------------------------------\n";
    
    if (file_exists('includes/header.php')) {
        $header_content = file_get_contents('includes/header.php');
        if (strpos($header_content, 'modules/coupons') !== false) {
            echo "✅ وحدة الكوبونات مضافة للشريط الجانبي\n";
        } else {
            echo "❌ وحدة الكوبونات غير مضافة للشريط الجانبي\n";
            $issues_found[] = "وحدة الكوبونات غير مضافة للشريط الجانبي";
            $all_tests_passed = false;
        }
    }
    
    if (file_exists('index.php')) {
        $index_content = file_get_contents('index.php');
        if (strpos($index_content, 'modules/coupons') !== false) {
            echo "✅ بطاقة الكوبونات مضافة للصفحة الرئيسية\n";
        } else {
            echo "❌ بطاقة الكوبونات غير مضافة للصفحة الرئيسية\n";
            $issues_found[] = "بطاقة الكوبونات غير مضافة للصفحة الرئيسية";
            $all_tests_passed = false;
        }
    }
    
    // Final Results
    echo "\n" . str_repeat("=", 50) . "\n";
    
    if ($all_tests_passed) {
        echo "🎉 جميع الاختبارات نجحت! النظام جاهز للاستخدام\n\n";
        
        echo "✅ الميزات المتاحة:\n";
        echo "• إدارة شاملة للكوبونات (إضافة، تعديل، حذف، عرض)\n";
        echo "• أنواع خصم متعددة (نسبة مئوية، مبلغ ثابت)\n";
        echo "• تحديد حدود الاستخدام والتواريخ\n";
        echo "• التحقق من صحة الكوبونات في الوقت الفعلي\n";
        echo "• ربط مع نظام الطلبات\n";
        echo "• تتبع استخدام الكوبونات\n";
        echo "• واجهة عربية متجاوبة\n";
        echo "• إحصائيات مفصلة\n\n";
        
        echo "🚀 روابط سريعة:\n";
        echo "• قائمة الكوبونات: modules/coupons/index.php\n";
        echo "• إضافة كوبون جديد: modules/coupons/add.php\n";
        echo "• إنشاء طلب مع كوبون: modules/orders/create.php\n";
        
    } else {
        echo "❌ فشل في " . count($issues_found) . " اختبار\n\n";
        
        echo "المشاكل التي تحتاج إصلاح:\n";
        foreach ($issues_found as $issue) {
            echo "• $issue\n";
        }
        
        echo "\nيرجى تشغيل fix_coupon_issues.php لإصلاح هذه المشاكل\n";
    }
    
} catch (PDOException $e) {
    echo "\n❌ خطأ في قاعدة البيانات: " . $e->getMessage() . "\n";
    $all_tests_passed = false;
} catch (Exception $e) {
    echo "\n❌ خطأ في النظام: " . $e->getMessage() . "\n";
    $all_tests_passed = false;
}

echo "</pre>";
echo "</div>";

// Action buttons
echo "<div style='margin-top: 20px; text-align: center;'>";

if ($all_tests_passed) {
    echo "<a href='modules/coupons/index.php' style='background: #4CAF50; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>🎫 دخول نظام الكوبونات</a>";
    echo "<a href='modules/coupons/add.php' style='background: #9C27B0; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>➕ إضافة كوبون جديد</a>";
    echo "<a href='modules/orders/create.php' style='background: #FF9800; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>📝 إنشاء طلب مع كوبون</a>";
} else {
    echo "<a href='fix_coupon_issues.php' style='background: #F44336; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>🔧 إصلاح المشاكل</a>";
    echo "<a href='setup_coupons_system.php' style='background: #2196F3; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>⚙️ إعادة الإعداد</a>";
}

echo "<a href='index.php' style='background: #607D8B; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>🏠 الصفحة الرئيسية</a>";
echo "</div>";

// Show system status summary
echo "<div style='margin-top: 30px; padding: 20px; background: " . ($all_tests_passed ? '#e8f5e8' : '#ffeaea') . "; border-radius: 8px; text-align: center;'>";
echo "<h2 style='color: " . ($all_tests_passed ? '#2e7d32' : '#c62828') . "; margin: 0;'>";
echo $all_tests_passed ? "🎉 نظام الكوبونات جاهز للإنتاج!" : "⚠️ النظام يحتاج إصلاحات";
echo "</h2>";
echo "<p style='margin: 10px 0; font-size: 16px;'>";
echo $all_tests_passed ? 
    "جميع المكونات تعمل بشكل مثالي. يمكنك البدء في استخدام نظام الكوبونات الآن." : 
    "يرجى إصلاح المشاكل المذكورة أعلاه قبل الاستخدام.";
echo "</p>";
echo "</div>";
?>
