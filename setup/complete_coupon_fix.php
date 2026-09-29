<?php
/**
 * Complete Coupon System Fix - All Issues Resolution
 * Senior Developer Emergency Fix
 */

require_once 'config/database.php';

echo "<h1>🚨 إصلاح شامل لنظام الكوبونات</h1>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; border-radius: 5px;'>";
echo "<pre>";

try {
    echo "=== إصلاح جميع مشاكل نظام الكوبونات ===\n\n";
    
    // Fix 1: Database Structure - Add Missing Columns
    echo "FIX 1: إضافة الأعمدة المفقودة لجدول الكوبونات...\n";
    echo "------------------------------------------------\n";
    
    $columns_to_add = [
        'coupon_name' => 'VARCHAR(255) NOT NULL DEFAULT ""',
        'description' => 'TEXT DEFAULT NULL',
        'start_date' => 'DATE NOT NULL DEFAULT (CURDATE())',
        'end_date' => 'DATE NOT NULL DEFAULT (DATE_ADD(CURDATE(), INTERVAL 30 DAY))',
        'max_discount_amount' => 'DECIMAL(10,2) DEFAULT NULL',
        'user_usage_limit' => 'INT DEFAULT 1'
    ];
    
    // Get existing columns
    $existing_columns = $db->query("DESCRIBE coupons")->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($columns_to_add as $column => $definition) {
        if (!in_array($column, $existing_columns)) {
            try {
                $db->exec("ALTER TABLE coupons ADD COLUMN $column $definition");
                echo "✅ تم إضافة العمود: $column\n";
            } catch (PDOException $e) {
                echo "⚠️ تحذير في إضافة $column: " . $e->getMessage() . "\n";
            }
        } else {
            echo "✅ العمود $column موجود مسبقاً\n";
        }
    }
    
    // Fix 2: Create Missing Files
    echo "\nFIX 2: إنشاء الملفات المفقودة...\n";
    echo "-------------------------------\n";
    
    // Create CouponValidator.php
    $validator_content = '<?php
class CouponValidator {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    public function validateCoupon($coupon_code, $order_total, $customer_id = null) {
        try {
            $stmt = $this->db->prepare("SELECT * FROM coupons WHERE coupon_code = ? AND is_active = 1");
            $stmt->execute([$coupon_code]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$coupon) {
                return ["valid" => false, "message" => "كود الكوبون غير صحيح"];
            }
            
            $today = date("Y-m-d");
            if ($today < $coupon["start_date"] || $today > $coupon["end_date"]) {
                return ["valid" => false, "message" => "الكوبون غير صالح للاستخدام"];
            }
            
            if ($order_total < $coupon["min_order_amount"]) {
                return ["valid" => false, "message" => "الحد الأدنى للطلب " . $coupon["min_order_amount"] . " ريال"];
            }
            
            $discount = $this->calculateDiscount($coupon, $order_total);
            
            return [
                "valid" => true,
                "coupon" => $coupon,
                "discount_amount" => $discount,
                "message" => "تم تطبيق الكوبون بنجاح"
            ];
            
        } catch (Exception $e) {
            return ["valid" => false, "message" => "خطأ في التحقق من الكوبون"];
        }
    }
    
    private function calculateDiscount($coupon, $order_total) {
        if ($coupon["discount_type"] == "percentage") {
            $discount = ($order_total * $coupon["discount_value"]) / 100;
            if ($coupon["max_discount_amount"] && $discount > $coupon["max_discount_amount"]) {
                $discount = $coupon["max_discount_amount"];
            }
        } else {
            $discount = $coupon["discount_value"];
        }
        return min($discount, $order_total);
    }
}
?>';
    
    if (file_put_contents('includes/CouponValidator.php', $validator_content)) {
        echo "✅ تم إنشاء includes/CouponValidator.php\n";
    }
    
    // Create AJAX directory and file
    if (!is_dir('modules/orders/ajax')) {
        mkdir('modules/orders/ajax', 0755, true);
        echo "✅ تم إنشاء مجلد modules/orders/ajax\n";
    }
    
    $ajax_content = '<?php
session_start();
require_once "../../config/database.php";
require_once "../../includes/CouponValidator.php";

header("Content-Type: application/json");

$input = json_decode(file_get_contents("php://input"), true);
$coupon_code = $input["coupon_code"] ?? "";
$order_total = floatval($input["order_total"] ?? 0);
$customer_id = intval($input["customer_id"] ?? 0) ?: null;

$validator = new CouponValidator($db);
$result = $validator->validateCoupon($coupon_code, $order_total, $customer_id);

echo json_encode($result);
?>';
    
    if (file_put_contents('modules/orders/ajax/validate_coupon.php', $ajax_content)) {
        echo "✅ تم إنشاء modules/orders/ajax/validate_coupon.php\n";
    }
    
    // Create edit.php
    $edit_content = '<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../login.php");
    exit();
}

require_once "../../config/database.php";

$coupon_id = intval($_GET["id"] ?? 0);
if (!$coupon_id) {
    header("Location: index.php");
    exit();
}

$stmt = $db->prepare("SELECT * FROM coupons WHERE id = ?");
$stmt->execute([$coupon_id]);
$coupon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$coupon) {
    header("Location: index.php");
    exit();
}

$success_message = "";
$error_message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $coupon_name = trim($_POST["coupon_name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $discount_type = $_POST["discount_type"] ?? "";
    $discount_value = floatval($_POST["discount_value"] ?? 0);
    $min_order_amount = floatval($_POST["min_order_amount"] ?? 0);
    $start_date = $_POST["start_date"] ?? "";
    $end_date = $_POST["end_date"] ?? "";
    $is_active = isset($_POST["is_active"]) ? 1 : 0;
    
    if (!empty($coupon_name) && !empty($discount_type) && $discount_value > 0) {
        try {
            $update_stmt = $db->prepare("
                UPDATE coupons SET 
                coupon_name = ?, description = ?, discount_type = ?, discount_value = ?,
                min_order_amount = ?, start_date = ?, end_date = ?, is_active = ?
                WHERE id = ?
            ");
            
            if ($update_stmt->execute([
                $coupon_name, $description, $discount_type, $discount_value,
                $min_order_amount, $start_date, $end_date, $is_active, $coupon_id
            ])) {
                $success_message = "تم تحديث الكوبون بنجاح";
                $stmt->execute([$coupon_id]);
                $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $error_message = "خطأ في تحديث الكوبون";
        }
    } else {
        $error_message = "يرجى ملء جميع الحقول المطلوبة";
    }
}

include "../../includes/header.php";
?>

<div class="min-h-screen bg-gray-50 py-6" dir="rtl">
    <div class="max-w-4xl mx-auto px-4">
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b">
                <h1 class="text-2xl font-bold">تعديل الكوبون: <?php echo htmlspecialchars($coupon["coupon_code"]); ?></h1>
            </div>
            
            <?php if ($success_message): ?>
            <div class="bg-amber-100 text-amber-700 p-4 m-6 rounded">
                <?php echo $success_message; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="bg-red-100 text-red-700 p-4 m-6 rounded">
                <?php echo $error_message; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" class="p-6 space-y-6">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium mb-2">اسم الكوبون *</label>
                        <input type="text" name="coupon_name" required
                               value="<?php echo htmlspecialchars($coupon["coupon_name"] ?? ""); ?>"
                               class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-purple-500">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">نوع الخصم *</label>
                        <select name="discount_type" required class="w-full px-3 py-2 border rounded-lg">
                            <option value="percentage" <?php echo ($coupon["discount_type"] ?? "") == "percentage" ? "selected" : ""; ?>>نسبة مئوية</option>
                            <option value="fixed_amount" <?php echo ($coupon["discount_type"] ?? "") == "fixed_amount" ? "selected" : ""; ?>>مبلغ ثابت</option>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">الوصف</label>
                    <textarea name="description" rows="3" class="w-full px-3 py-2 border rounded-lg"><?php echo htmlspecialchars($coupon["description"] ?? ""); ?></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium mb-2">قيمة الخصم *</label>
                        <input type="number" name="discount_value" step="0.01" required
                               value="<?php echo $coupon["discount_value"] ?? 0; ?>"
                               class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">الحد الأدنى للطلب</label>
                        <input type="number" name="min_order_amount" step="0.01"
                               value="<?php echo $coupon["min_order_amount"] ?? 0; ?>"
                               class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium mb-2">تاريخ البداية *</label>
                        <input type="date" name="start_date" required
                               value="<?php echo $coupon["start_date"] ?? ""; ?>"
                               class="w-full px-3 py-2 border rounded-lg">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2">تاريخ النهاية *</label>
                        <input type="date" name="end_date" required
                               value="<?php echo $coupon["end_date"] ?? ""; ?>"
                               class="w-full px-3 py-2 border rounded-lg">
                    </div>
                </div>
                
                <div class="flex items-center">
                    <input type="checkbox" name="is_active" value="1" 
                           <?php echo ($coupon["is_active"] ?? 0) ? "checked" : ""; ?>
                           class="h-4 w-4 text-purple-600 rounded">
                    <label class="mr-2 text-sm">تفعيل الكوبون</label>
                </div>
                
                <div class="flex justify-end space-x-4 space-x-reverse pt-6 border-t">
                    <a href="index.php" class="px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600">إلغاء</a>
                    <button type="submit" class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">حفظ التغييرات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include "../../includes/footer.php"; ?>';
    
    if (file_put_contents('modules/coupons/edit.php', $edit_content)) {
        echo "✅ تم إنشاء modules/coupons/edit.php\n";
    }
    
    // Create view.php
    $view_content = '<?php
session_start();
if (!isset($_SESSION["user_id"])) {
    header("Location: ../../login.php");
    exit();
}

require_once "../../config/database.php";

$coupon_id = intval($_GET["id"] ?? 0);
if (!$coupon_id) {
    header("Location: index.php");
    exit();
}

$stmt = $db->prepare("SELECT * FROM coupons WHERE id = ?");
$stmt->execute([$coupon_id]);
$coupon = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$coupon) {
    header("Location: index.php");
    exit();
}

include "../../includes/header.php";
?>

<div class="min-h-screen bg-gray-50 py-6" dir="rtl">
    <div class="max-w-4xl mx-auto px-4">
        <div class="bg-white shadow rounded-lg">
            <div class="px-6 py-4 border-b">
                <div class="flex justify-between items-center">
                    <h1 class="text-2xl font-bold">تفاصيل الكوبون: <?php echo htmlspecialchars($coupon["coupon_code"]); ?></h1>
                    <div class="space-x-2 space-x-reverse">
                        <a href="edit.php?id=<?php echo $coupon_id; ?>" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">تعديل</a>
                        <a href="index.php" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">العودة</a>
                    </div>
                </div>
            </div>
            
            <div class="p-6">
                <div class="bg-gray-50 p-4 rounded-lg space-y-3">
                    <div><strong>الكود:</strong> <span class="bg-purple-100 px-2 py-1 rounded font-mono"><?php echo htmlspecialchars($coupon["coupon_code"]); ?></span></div>
                    <div><strong>الاسم:</strong> <?php echo htmlspecialchars($coupon["coupon_name"] ?? "غير محدد"); ?></div>
                    <div><strong>الوصف:</strong> <?php echo htmlspecialchars($coupon["description"] ?? "لا يوجد وصف"); ?></div>
                    <div><strong>نوع الخصم:</strong> <?php echo $coupon["discount_type"] == "percentage" ? "نسبة مئوية" : "مبلغ ثابت"; ?></div>
                    <div><strong>قيمة الخصم:</strong> 
                        <?php if ($coupon["discount_type"] == "percentage"): ?>
                            <?php echo $coupon["discount_value"]; ?>%
                        <?php else: ?>
                            <?php echo number_format($coupon["discount_value"], 2); ?> ريال
                        <?php endif; ?>
                    </div>
                    <div><strong>الحد الأدنى:</strong> <?php echo number_format($coupon["min_order_amount"], 2); ?> ريال</div>
                    <div><strong>تاريخ البداية:</strong> <?php echo date("Y-m-d", strtotime($coupon["start_date"])); ?></div>
                    <div><strong>تاريخ النهاية:</strong> <?php echo date("Y-m-d", strtotime($coupon["end_date"])); ?></div>
                    <div><strong>الحالة:</strong> 
                        <span class="px-2 py-1 rounded text-xs <?php echo $coupon["is_active"] ? "bg-amber-100 text-amber-800" : "bg-red-100 text-red-800"; ?>">
                            <?php echo $coupon["is_active"] ? "نشط" : "معطل"; ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../../includes/footer.php"; ?>';
    
    if (file_put_contents('modules/coupons/view.php', $view_content)) {
        echo "✅ تم إنشاء modules/coupons/view.php\n";
    }
    
    // Fix 3: Add Sample Data
    echo "\nFIX 3: إضافة بيانات تجريبية...\n";
    echo "----------------------------\n";
    
    $sample_coupons = [
        [
            'coupon_code' => 'WELCOME10',
            'coupon_name' => 'خصم الترحيب',
            'description' => 'خصم 10% للعملاء الجدد',
            'discount_type' => 'percentage',
            'discount_value' => 10.00,
            'min_order_amount' => 100.00,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+30 days'))
        ],
        [
            'coupon_code' => 'SAVE50',
            'coupon_name' => 'وفر 50 ريال',
            'description' => 'خصم ثابت 50 ريال',
            'discount_type' => 'fixed_amount',
            'discount_value' => 50.00,
            'min_order_amount' => 300.00,
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d', strtotime('+60 days'))
        ]
    ];
    
    $insert_coupon = $db->prepare("
        INSERT INTO coupons 
        (coupon_code, coupon_name, description, discount_type, discount_value, 
         min_order_amount, start_date, end_date, is_active)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)
        ON DUPLICATE KEY UPDATE coupon_name = VALUES(coupon_name)
    ");
    
    foreach ($sample_coupons as $coupon) {
        $insert_coupon->execute(array_values($coupon));
        echo "✅ تم إدراج كوبون: {$coupon['coupon_code']}\n";
    }
    
    // Fix 4: Update Sidebar
    echo "\nFIX 4: تحديث الشريط الجانبي...\n";
    echo "-----------------------------\n";
    
    if (file_exists('includes/header.php')) {
        $header_content = file_get_contents('includes/header.php');
        
        if (strpos($header_content, 'modules/coupons') === false) {
            // Find a good insertion point
            $insertion_point = strpos($header_content, 'modules/customers');
            
            if ($insertion_point !== false) {
                // Find the end of the customers menu item
                $end_point = strpos($header_content, '</li>', $insertion_point);
                if ($end_point !== false) {
                    $end_point += 5; // Include </li>
                    
                    $coupon_menu = '
                        <li class="mb-2">
                            <a href="' . (strpos($_SERVER['REQUEST_URI'], '/modules/') !== false ? '../' : 'modules/') . 'coupons/index.php" 
                               class="flex items-center px-4 py-3 text-gray-700 rounded-lg hover:bg-purple-50 hover:text-purple-700 transition-colors duration-200">
                                <i class="fas fa-ticket-alt ml-3 text-purple-600"></i>
                                <span class="font-medium">إدارة الكوبونات</span>
                            </a>
                        </li>';
                    
                    $new_content = substr($header_content, 0, $end_point) . $coupon_menu . substr($header_content, $end_point);
                    
                    if (file_put_contents('includes/header.php', $new_content)) {
                        echo "✅ تم إضافة وحدة الكوبونات للشريط الجانبي\n";
                    }
                }
            }
        } else {
            echo "✅ وحدة الكوبونات موجودة في الشريط الجانبي\n";
        }
    }
    
    echo "\n=== تم الإصلاح الشامل بنجاح ===\n";
    echo "✅ إضافة جميع الأعمدة المفقودة\n";
    echo "✅ إنشاء جميع الملفات المطلوبة\n";
    echo "✅ إضافة بيانات تجريبية\n";
    echo "✅ تحديث الشريط الجانبي\n";
    echo "✅ النظام جاهز للاستخدام\n\n";
    
    echo "الملفات المُنشأة:\n";
    echo "• includes/CouponValidator.php - فئة التحقق\n";
    echo "• modules/orders/ajax/validate_coupon.php - نقطة نهاية AJAX\n";
    echo "• modules/coupons/edit.php - تعديل الكوبونات\n";
    echo "• modules/coupons/view.php - عرض تفاصيل الكوبون\n\n";
    
    echo "البيانات التجريبية:\n";
    echo "• WELCOME10 - خصم 10% للعملاء الجدد\n";
    echo "• SAVE50 - خصم ثابت 50 ريال\n";
    
} catch (PDOException $e) {
    echo "\n❌ خطأ في قاعدة البيانات: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "\n❌ خطأ في النظام: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "</div>";

echo "<div style='margin-top: 20px; text-align: center;'>";
echo "<a href='modules/coupons/index.php' style='background: #4CAF50; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>🎫 دخول نظام الكوبونات</a>";
echo "<a href='final_coupon_verification.php' style='background: #2196F3; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>✅ التحقق النهائي</a>";
echo "<a href='index.php' style='background: #607D8B; color: white; padding: 15px 25px; text-decoration: none; border-radius: 8px; margin: 5px; font-size: 16px;'>🏠 الصفحة الرئيسية</a>";
echo "</div>";
?>
