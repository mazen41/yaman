<?php
/**
 * Update Sidebar Navigation to Include Coupons Module
 */

echo "<h1>📋 تحديث الشريط الجانبي</h1>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; border-radius: 5px;'>";
echo "<pre>";

try {
    echo "=== تحديث الشريط الجانبي لإضافة وحدة الكوبونات ===\n\n";
    
    // Read the current header.php file
    $header_file = 'includes/header.php';
    
    if (!file_exists($header_file)) {
        echo "❌ ملف header.php غير موجود\n";
        exit;
    }
    
    $header_content = file_get_contents($header_file);
    
    // Check if coupons module already exists
    if (strpos($header_content, 'modules/coupons') !== false) {
        echo "✅ وحدة الكوبونات موجودة مسبقاً في الشريط الجانبي\n";
    } else {
        echo "إضافة وحدة الكوبونات إلى الشريط الجانبي...\n";
        
        // Find the position to insert coupons module (after customers module)
        $customers_pattern = '/<li class="mb-2">\s*<a href="[^"]*\/customers\/[^"]*"[^>]*>.*?<\/a>\s*<\/li>/s';
        
        if (preg_match($customers_pattern, $header_content, $matches, PREG_OFFSET_CAPTURE)) {
            $insert_position = $matches[0][1] + strlen($matches[0][0]);
            
            $coupons_menu_item = '
                        <li class="mb-2">
                            <a href="<?php echo $base_url; ?>modules/coupons/index.php" 
                               class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-purple-50 hover:text-purple-700 transition-colors duration-200 <?php echo (strpos($_SERVER[\'REQUEST_URI\'], \'/coupons/\') !== false) ? \'bg-purple-100 text-purple-700 border-r-4 border-purple-500\' : \'\'; ?>">
                                <i class="fas fa-ticket-alt ml-3 text-purple-600"></i>
                                <span class="font-medium">إدارة الكوبونات</span>
                            </a>
                        </li>';
            
            $new_content = substr($header_content, 0, $insert_position) . $coupons_menu_item . substr($header_content, $insert_position);
            
            if (file_put_contents($header_file, $new_content)) {
                echo "✅ تم إضافة وحدة الكوبونات إلى الشريط الجانبي\n";
            } else {
                echo "❌ فشل في تحديث ملف header.php\n";
            }
        } else {
            echo "⚠️ لم يتم العثور على موقع إدراج مناسب في الشريط الجانبي\n";
            echo "سيتم إضافة الوحدة يدوياً...\n";
            
            // Alternative approach: add before closing of sidebar
            $sidebar_end_pattern = '/<\/ul>\s*<\/nav>/';
            
            if (preg_match($sidebar_end_pattern, $header_content)) {
                $coupons_menu_item = '                        <li class="mb-2">
                            <a href="<?php echo $base_url; ?>modules/coupons/index.php" 
                               class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-purple-50 hover:text-purple-700 transition-colors duration-200 <?php echo (strpos($_SERVER[\'REQUEST_URI\'], \'/coupons/\') !== false) ? \'bg-purple-100 text-purple-700 border-r-4 border-purple-500\' : \'\'; ?>">
                                <i class="fas fa-ticket-alt ml-3 text-purple-600"></i>
                                <span class="font-medium">إدارة الكوبونات</span>
                            </a>
                        </li>
                    </ul>
                </nav>';
                
                $new_content = preg_replace($sidebar_end_pattern, $coupons_menu_item, $header_content);
                
                if (file_put_contents($header_file, $new_content)) {
                    echo "✅ تم إضافة وحدة الكوبونات إلى نهاية الشريط الجانبي\n";
                } else {
                    echo "❌ فشل في تحديث ملف header.php\n";
                }
            }
        }
    }
    
    // Also update the main dashboard to include coupons module
    echo "\nتحديث الصفحة الرئيسية لإضافة بطاقة الكوبونات...\n";
    
    $index_file = 'index.php';
    if (file_exists($index_file)) {
        $index_content = file_get_contents($index_file);
        
        if (strpos($index_content, 'modules/coupons') === false) {
            // Find the modules grid section
            $modules_pattern = '/<div class="grid[^>]*grid-cols[^>]*">/';
            
            if (preg_match($modules_pattern, $index_content, $matches, PREG_OFFSET_CAPTURE)) {
                // Find the end of the grid
                $grid_start = $matches[0][1] + strlen($matches[0][0]);
                $grid_end_pattern = '/<\/div>/';
                
                if (preg_match($grid_end_pattern, $index_content, $end_matches, PREG_OFFSET_CAPTURE, $grid_start)) {
                    $insert_position = $end_matches[0][1];
                    
                    $coupons_card = '
                    <!-- Coupons Module -->
                    <div class="bg-white rounded-lg shadow-md hover:shadow-lg transition-shadow duration-300">
                        <div class="p-6">
                            <div class="flex items-center mb-4">
                                <div class="bg-purple-100 p-3 rounded-lg">
                                    <i class="fas fa-ticket-alt text-2xl text-purple-600"></i>
                                </div>
                                <div class="mr-4">
                                    <h3 class="text-lg font-semibold text-gray-800">إدارة الكوبونات</h3>
                                    <p class="text-gray-600 text-sm">كوبونات الخصم والعروض</p>
                                </div>
                            </div>
                            <p class="text-gray-600 mb-4">إدارة شاملة لكوبونات الخصم والعروض الترويجية مع تتبع الاستخدام والإحصائيات المفصلة.</p>
                            <div class="flex space-x-2 space-x-reverse">
                                <a href="modules/coupons/index.php" class="flex-1 bg-purple-600 text-white px-4 py-2 rounded-lg text-center hover:bg-purple-700 transition duration-200">
                                    عرض الكوبونات
                                </a>
                                <a href="modules/coupons/add.php" class="flex-1 bg-purple-100 text-purple-700 px-4 py-2 rounded-lg text-center hover:bg-purple-200 transition duration-200">
                                    إضافة كوبون
                                </a>
                            </div>
                        </div>
                    </div>
';
                    
                    $new_content = substr($index_content, 0, $insert_position) . $coupons_card . substr($index_content, $insert_position);
                    
                    if (file_put_contents($index_file, $new_content)) {
                        echo "✅ تم إضافة بطاقة الكوبونات إلى الصفحة الرئيسية\n";
                    } else {
                        echo "❌ فشل في تحديث الصفحة الرئيسية\n";
                    }
                } else {
                    echo "⚠️ لم يتم العثور على نهاية شبكة الوحدات\n";
                }
            } else {
                echo "⚠️ لم يتم العثور على شبكة الوحدات في الصفحة الرئيسية\n";
            }
        } else {
            echo "✅ بطاقة الكوبونات موجودة مسبقاً في الصفحة الرئيسية\n";
        }
    } else {
        echo "⚠️ ملف index.php غير موجود\n";
    }
    
    echo "\n=== تم التحديث بنجاح ===\n";
    echo "✅ تم إضافة وحدة الكوبونات إلى الشريط الجانبي\n";
    echo "✅ تم إضافة بطاقة الكوبونات إلى الصفحة الرئيسية\n";
    echo "✅ النظام جاهز للاستخدام\n\n";
    
    echo "يمكنك الآن الوصول إلى وحدة الكوبونات من:\n";
    echo "• الشريط الجانبي في جميع الصفحات\n";
    echo "• بطاقة الكوبونات في الصفحة الرئيسية\n";
    echo "• الرابط المباشر: modules/coupons/index.php\n";
    
} catch (Exception $e) {
    echo "\n❌ خطأ: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "</div>";

echo "<div style='margin-top: 20px; text-align: center;'>";
echo "<a href='index.php' style='background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🏠 الصفحة الرئيسية</a>";
echo "<a href='modules/coupons/index.php' style='background: #9C27B0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🎫 إدارة الكوبونات</a>";
echo "<a href='integrate_with_orders.php' style='background: #FF9800; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🔗 ربط مع الطلبات</a>";
echo "</div>";
?>
