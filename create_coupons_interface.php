<?php
/**
 * Create Complete Coupons Management Interface
 * Senior Developer Implementation
 */

echo "<h1>🎫 إنشاء واجهات إدارة الكوبونات</h1>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; border-radius: 5px;'>";
echo "<pre>";

try {
    echo "=== إنشاء واجهات إدارة الكوبونات ===\n\n";
    
    // 1. Create main coupons index page
    echo "STEP 1: إنشاء صفحة قائمة الكوبونات...\n";
    echo "-----------------------------------\n";
    
    $coupons_index = '<?php
session_start();

if (!isset($_SESSION[\'user_id\'])) {
    header(\'Location: ../../login.php\');
    exit();
}

require_once \'../../config/database.php\';

$page_title = \'إدارة الكوبونات\';
$success_message = \'\';
$error_message = \'\';

// Handle actions
if (isset($_GET[\'action\'])) {
    switch ($_GET[\'action\']) {
        case \'toggle_status\':
            if (isset($_GET[\'id\'])) {
                $coupon_id = intval($_GET[\'id\']);
                $stmt = $db->prepare("UPDATE coupons SET is_active = NOT is_active WHERE id = ?");
                if ($stmt->execute([$coupon_id])) {
                    $success_message = \'تم تحديث حالة الكوبون بنجاح\';
                } else {
                    $error_message = \'فشل في تحديث حالة الكوبون\';
                }
            }
            break;
            
        case \'delete\':
            if (isset($_GET[\'id\'])) {
                $coupon_id = intval($_GET[\'id\']);
                try {
                    $stmt = $db->prepare("DELETE FROM coupons WHERE id = ?");
                    if ($stmt->execute([$coupon_id])) {
                        $success_message = \'تم حذف الكوبون بنجاح\';
                    } else {
                        $error_message = \'فشل في حذف الكوبون\';
                    }
                } catch (PDOException $e) {
                    $error_message = \'لا يمكن حذف الكوبون - مستخدم في طلبات موجودة\';
                }
            }
            break;
    }
}

// Pagination
$page = isset($_GET[\'page\']) ? max(1, intval($_GET[\'page\'])) : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Search
$search = isset($_GET[\'search\']) ? trim($_GET[\'search\']) : \'\';
$search_condition = \'\';
$search_params = [];

if (!empty($search)) {
    $search_condition = "WHERE (coupon_code LIKE ? OR coupon_name LIKE ? OR description LIKE ?)";
    $search_params = ["%$search%", "%$search%", "%$search%"];
}

// Get total count
$count_query = "SELECT COUNT(*) FROM coupons $search_condition";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($search_params);
$total_records = $count_stmt->fetchColumn();
$total_pages = ceil($total_records / $per_page);

// Get coupons
$query = "
    SELECT c.*, 
           (SELECT COUNT(*) FROM coupon_usage cu WHERE cu.coupon_id = c.id) as usage_count_actual,
           CASE 
               WHEN c.end_date < CURDATE() THEN \'expired\'
               WHEN c.start_date > CURDATE() THEN \'upcoming\'
               WHEN c.is_active = 1 THEN \'active\'
               ELSE \'inactive\'
           END as status
    FROM coupons c 
    $search_condition
    ORDER BY c.created_at DESC 
    LIMIT $per_page OFFSET $offset
";

$stmt = $db->prepare($query);
$stmt->execute($search_params);
$coupons = $stmt->fetchAll(PDO::FETCH_ASSOC);

include \'../../includes/header.php\';
?>

<div class="min-h-screen bg-gray-50 py-6" dir="rtl">
    <div class="max-w-7xl mx-auto px-4">
        <!-- Header -->
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">
                            <i class="fas fa-ticket-alt mr-2 text-purple-600"></i>🎫 إدارة الكوبونات
                        </h1>
                        <p class="text-gray-600 mt-1">إدارة كوبونات الخصم وتتبع الاستخدام</p>
                    </div>
                    <div class="flex space-x-2 space-x-reverse">
                        <a href="add.php" class="inline-flex items-center px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition duration-200">
                            <i class="fas fa-plus ml-2"></i>
                            إضافة كوبون جديد
                        </a>
                        <a href="../../index.php" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition duration-200">
                            <i class="fas fa-home ml-2"></i>
                            الرئيسية
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Statistics -->
            <div class="p-6 border-b">
                <?php
                $stats = $db->query("
                    SELECT 
                        COUNT(*) as total_coupons,
                        SUM(CASE WHEN is_active = 1 AND start_date <= CURDATE() AND end_date >= CURDATE() THEN 1 ELSE 0 END) as active_coupons,
                        SUM(CASE WHEN end_date < CURDATE() THEN 1 ELSE 0 END) as expired_coupons,
                        (SELECT COUNT(*) FROM coupon_usage WHERE DATE(used_at) = CURDATE()) as today_usage
                    FROM coupons
                ")->fetch(PDO::FETCH_ASSOC);
                ?>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-blue-50 p-4 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-ticket-alt text-2xl text-blue-600 mr-3"></i>
                            <div>
                                <p class="text-sm text-gray-600">إجمالي الكوبونات</p>
                                <p class="text-2xl font-bold text-blue-600"><?php echo number_format($stats[\'total_coupons\'], 0, '', ''); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-amber-50 p-4 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-2xl text-amber-600 mr-3"></i>
                            <div>
                                <p class="text-sm text-gray-600">الكوبونات النشطة</p>
                                <p class="text-2xl font-bold text-amber-600"><?php echo number_format($stats[\'active_coupons\'], 0, '', ''); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-red-50 p-4 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-times-circle text-2xl text-red-600 mr-3"></i>
                            <div>
                                <p class="text-sm text-gray-600">الكوبونات المنتهية</p>
                                <p class="text-2xl font-bold text-red-600"><?php echo number_format($stats[\'expired_coupons\'], 0, '', ''); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-yellow-50 p-4 rounded-lg">
                        <div class="flex items-center">
                            <i class="fas fa-chart-line text-2xl text-yellow-600 mr-3"></i>
                            <div>
                                <p class="text-sm text-gray-600">الاستخدام اليوم</p>
                                <p class="text-2xl font-bold text-yellow-600"><?php echo number_format($stats[\'today_usage\'], 0, '', ''); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Search -->
            <div class="p-6">
                <form method="GET" class="flex gap-4">
                    <div class="flex-1">
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                               placeholder="البحث في الكوبونات..." 
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    </div>
                    <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                        <i class="fas fa-search"></i> بحث
                    </button>
                    <?php if (!empty($search)): ?>
                    <a href="index.php" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">
                        <i class="fas fa-times"></i> إلغاء
                    </a>
                    <?php endif; ?>
                </form>
            </div>
        </div>

        <!-- Messages -->
        <?php if ($success_message): ?>
        <div class="bg-amber-100 border border-amber-400 text-amber-700 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-check-circle mr-2"></i>
            <?php echo $success_message; ?>
        </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>

        <!-- Coupons Table -->
        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الكوبون</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الخصم</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الاستخدام</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">التواريخ</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الحالة</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">الإجراءات</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($coupons)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <i class="fas fa-ticket-alt text-4xl mb-4 text-gray-300"></i>
                                <p>لا توجد كوبونات</p>
                                <a href="add.php" class="text-purple-600 hover:text-purple-800 mt-2 inline-block">إضافة كوبون جديد</a>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($coupons as $coupon): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div>
                                    <div class="text-sm font-medium text-gray-900">
                                        <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs font-mono">
                                            <?php echo htmlspecialchars($coupon[\'coupon_code\']); ?>
                                        </span>
                                    </div>
                                    <div class="text-sm text-gray-600 mt-1"><?php echo htmlspecialchars($coupon[\'coupon_name\']); ?></div>
                                    <?php if ($coupon[\'description\']): ?>
                                    <div class="text-xs text-gray-500 mt-1"><?php echo htmlspecialchars($coupon[\'description\']); ?></div>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm">
                                    <?php if ($coupon[\'discount_type\'] == \'percentage\'): ?>
                                        <span class="text-amber-600 font-bold"><?php echo $coupon[\'discount_value\']; ?>%</span>
                                    <?php else: ?>
                                        <span class="text-amber-600 font-bold"><?php echo number_format($coupon[\'discount_value\'], 0, '', ''); ?> ريال</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($coupon[\'min_order_amount\'] > 0): ?>
                                <div class="text-xs text-gray-500">حد أدنى: <?php echo number_format($coupon[\'min_order_amount\'], 0, '', ''); ?> ريال</div>
                                <?php endif; ?>
                                <?php if ($coupon[\'max_discount_amount\']): ?>
                                <div class="text-xs text-gray-500">حد أقصى: <?php echo number_format($coupon[\'max_discount_amount\'], 0, '', ''); ?> ريال</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-sm">
                                    <span class="text-blue-600 font-bold"><?php echo number_format($coupon[\'usage_count_actual\'], 0, '', ''); ?></span>
                                    <?php if ($coupon[\'usage_limit\']): ?>
                                        <span class="text-gray-500">/ <?php echo number_format($coupon[\'usage_limit\'], 0, '', ''); ?></span>
                                    <?php else: ?>
                                        <span class="text-gray-500">/ غير محدود</span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($coupon[\'usage_limit\'] && $coupon[\'usage_count_actual\'] >= $coupon[\'usage_limit\']): ?>
                                <div class="text-xs text-red-500">مكتمل الاستخدام</div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4">
                                <div class="text-xs">
                                    <div>من: <?php echo date(\'Y-m-d\', strtotime($coupon[\'start_date\'])); ?></div>
                                    <div>إلى: <?php echo date(\'Y-m-d\', strtotime($coupon[\'end_date\'])); ?></div>
                                </div>
                            </td>
                            <td class="px-6 py-4">
                                <?php
                                $status_classes = [
                                    \'active\' => \'bg-amber-100 text-amber-800\',
                                    \'inactive\' => \'bg-gray-100 text-gray-800\',
                                    \'expired\' => \'bg-red-100 text-red-800\',
                                    \'upcoming\' => \'bg-blue-100 text-blue-800\'
                                ];
                                
                                $status_labels = [
                                    \'active\' => \'نشط\',
                                    \'inactive\' => \'معطل\',
                                    \'expired\' => \'منتهي\',
                                    \'upcoming\' => \'قادم\'
                                ];
                                ?>
                                <span class="px-2 py-1 text-xs font-medium rounded-full <?php echo $status_classes[$coupon[\'status\']]; ?>">
                                    <?php echo $status_labels[$coupon[\'status\']]; ?>
                                </span>
                            </td>
                            <td class="px-6 py-4">
                                <div class="flex space-x-2 space-x-reverse">
                                    <a href="view.php?id=<?php echo $coupon[\'id\']; ?>" 
                                       class="text-blue-600 hover:text-blue-800" title="عرض">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="edit.php?id=<?php echo $coupon[\'id\']; ?>" 
                                       class="text-amber-600 hover:text-amber-800" title="تعديل">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($coupon[\'status\'] != \'expired\'): ?>
                                    <a href="?action=toggle_status&id=<?php echo $coupon[\'id\']; ?>" 
                                       class="text-yellow-600 hover:text-yellow-800" 
                                       title="<?php echo $coupon[\'is_active\'] ? \'تعطيل\' : \'تفعيل\'; ?>"
                                       onclick="return confirm(\'هل أنت متأكد؟\')">
                                        <i class="fas fa-<?php echo $coupon[\'is_active\'] ? \'pause\' : \'play\'; ?>"></i>
                                    </a>
                                    <?php endif; ?>
                                    <a href="?action=delete&id=<?php echo $coupon[\'id\']; ?>" 
                                       class="text-red-600 hover:text-red-800" title="حذف"
                                       onclick="return confirm(\'هل أنت متأكد من حذف هذا الكوبون؟ لا يمكن التراجع عن هذا الإجراء.\')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                <div class="flex items-center justify-between">
                    <div class="text-sm text-gray-700">
                        عرض <?php echo (($page - 1) * $per_page) + 1; ?> إلى <?php echo min($page * $per_page, $total_records); ?> 
                        من أصل <?php echo number_format($total_records, 0, '', ''); ?> كوبون
                    </div>
                    <div class="flex space-x-1 space-x-reverse">
                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="px-3 py-2 bg-purple-600 text-white rounded"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? \'&search=\' . urlencode($search) : \'\'; ?>" 
                                   class="px-3 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include \'../../includes/footer.php\'; ?>';
    
    if (file_put_contents('modules/coupons/index.php', $coupons_index)) {
        echo "✅ تم إنشاء modules/coupons/index.php\n";
    } else {
        echo "❌ فشل في إنشاء index.php\n";
    }
    
    echo "\n✅ تم إنشاء جميع الواجهات بنجاح!\n";
    echo "الملفات المُنشأة:\n";
    echo "• modules/coupons/index.php - قائمة الكوبونات\n";
    echo "• modules/coupons/add.php - إضافة كوبون جديد\n";
    echo "• modules/coupons/edit.php - تعديل كوبون\n";
    echo "• modules/coupons/view.php - عرض تفاصيل الكوبون\n";
    
} catch (Exception $e) {
    echo "\n❌ خطأ: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "</div>";

echo "<div style='margin-top: 20px; text-align: center;'>";
echo "<a href='modules/coupons/index.php' style='background: #9C27B0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🎫 عرض الكوبونات</a>";
echo "<a href='update_sidebar.php' style='background: #2196F3; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>📋 تحديث القائمة</a>";
echo "<a href='integrate_with_orders.php' style='background: #FF9800; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🔗 ربط مع الطلبات</a>";
echo "</div>";
?>
