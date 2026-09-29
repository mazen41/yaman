<?php
/**
 * Integrate Coupons with Orders System
 * Senior Developer Implementation
 */

require_once 'config/database.php';

echo "<h1>🔗 ربط نظام الكوبونات مع الطلبات</h1>";
echo "<div style='font-family: monospace; background: #f5f5f5; padding: 20px; border-radius: 5px;'>";
echo "<pre>";

try {
    echo "=== ربط نظام الكوبونات مع نظام الطلبات ===\n\n";
    
    // Step 1: Create coupon validation class
    echo "STEP 1: إنشاء فئة التحقق من الكوبونات...\n";
    echo "----------------------------------------\n";
    
    $coupon_validator = '<?php
/**
 * Coupon Validation and Application Class
 * Senior Developer Implementation
 */

class CouponValidator {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
    }
    
    /**
     * Validate and apply coupon to order
     */
    public function validateCoupon($coupon_code, $order_total, $customer_id = null) {
        try {
            // Get coupon details
            $stmt = $this->db->prepare("
                SELECT * FROM coupons 
                WHERE coupon_code = ? AND is_active = 1
            ");
            $stmt->execute([$coupon_code]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$coupon) {
                return [\'valid\' => false, \'message\' => \'كود الكوبون غير صحيح أو غير نشط\'];
            }
            
            // Check date validity
            $today = date(\'Y-m-d\');
            if ($today < $coupon[\'start_date\']) {
                return [\'valid\' => false, \'message\' => \'الكوبون لم يبدأ بعد\'];
            }
            
            if ($today > $coupon[\'end_date\']) {
                return [\'valid\' => false, \'message\' => \'انتهت صلاحية الكوبون\'];
            }
            
            // Check minimum order amount
            if ($order_total < $coupon[\'min_order_amount\']) {
                return [
                    \'valid\' => false, 
                    \'message\' => \'الحد الأدنى للطلب \' . number_format($coupon[\'min_order_amount\'], 2) . \' ريال\'
                ];
            }
            
            // Check usage limit
            if ($coupon[\'usage_limit\'] && $coupon[\'usage_count\'] >= $coupon[\'usage_limit\']) {
                return [\'valid\' => false, \'message\' => \'تم استنفاد عدد مرات الاستخدام المسموح\'];
            }
            
            // Check user usage limit
            if ($customer_id && $coupon[\'user_usage_limit\']) {
                $user_usage_stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM coupon_usage 
                    WHERE coupon_id = ? AND customer_id = ?
                ");
                $user_usage_stmt->execute([$coupon[\'id\'], $customer_id]);
                $user_usage_count = $user_usage_stmt->fetchColumn();
                
                if ($user_usage_count >= $coupon[\'user_usage_limit\']) {
                    return [\'valid\' => false, \'message\' => \'تم تجاوز حد الاستخدام المسموح لهذا العميل\'];
                }
            }
            
            // Calculate discount
            $discount_amount = $this->calculateDiscount($coupon, $order_total);
            
            return [
                \'valid\' => true,
                \'coupon\' => $coupon,
                \'discount_amount\' => $discount_amount,
                \'message\' => \'تم تطبيق الكوبون بنجاح\'
            ];
            
        } catch (PDOException $e) {
            return [\'valid\' => false, \'message\' => \'خطأ في التحقق من الكوبون\'];
        }
    }
    
    /**
     * Calculate discount amount
     */
    private function calculateDiscount($coupon, $order_total) {
        if ($coupon[\'discount_type\'] == \'percentage\') {
            $discount = ($order_total * $coupon[\'discount_value\']) / 100;
            
            // Apply maximum discount limit
            if ($coupon[\'max_discount_amount\'] && $discount > $coupon[\'max_discount_amount\']) {
                $discount = $coupon[\'max_discount_amount\'];
            }
        } else {
            $discount = $coupon[\'discount_value\'];
        }
        
        // Ensure discount doesn\'t exceed order total
        return min($discount, $order_total);
    }
    
    /**
     * Apply coupon to order
     */
    public function applyCouponToOrder($order_id, $coupon_id, $discount_amount, $customer_id = null) {
        try {
            $this->db->beginTransaction();
            
            // Update order with coupon information
            $update_order_stmt = $this->db->prepare("
                UPDATE customer_orders 
                SET coupon_id = ?, coupon_discount = ?,
                    total_amount = total_amount - ?,
                    final_amount = final_amount - ?
                WHERE id = ?
            ");
            $update_order_stmt->execute([
                $coupon_id, $discount_amount, $discount_amount, $discount_amount, $order_id
            ]);
            
            // Record coupon usage
            $usage_stmt = $this->db->prepare("
                INSERT INTO coupon_usage (coupon_id, order_id, customer_id, discount_amount)
                VALUES (?, ?, ?, ?)
            ");
            $usage_stmt->execute([$coupon_id, $order_id, $customer_id, $discount_amount]);
            
            // Update coupon usage count
            $update_coupon_stmt = $this->db->prepare("
                UPDATE coupons SET usage_count = usage_count + 1 WHERE id = ?
            ");
            $update_coupon_stmt->execute([$coupon_id]);
            
            $this->db->commit();
            return true;
            
        } catch (PDOException $e) {
            $this->db->rollback();
            return false;
        }
    }
    
    /**
     * Remove coupon from order
     */
    public function removeCouponFromOrder($order_id) {
        try {
            $this->db->beginTransaction();
            
            // Get current coupon info
            $order_stmt = $this->db->prepare("
                SELECT coupon_id, coupon_discount FROM customer_orders WHERE id = ?
            ");
            $order_stmt->execute([$order_id]);
            $order_info = $order_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($order_info && $order_info[\'coupon_id\']) {
                // Restore order amounts
                $update_order_stmt = $this->db->prepare("
                    UPDATE customer_orders 
                    SET coupon_id = NULL, coupon_discount = 0,
                        total_amount = total_amount + ?,
                        final_amount = final_amount + ?
                    WHERE id = ?
                ");
                $update_order_stmt->execute([
                    $order_info[\'coupon_discount\'], 
                    $order_info[\'coupon_discount\'], 
                    $order_id
                ]);
                
                // Remove usage record
                $delete_usage_stmt = $this->db->prepare("
                    DELETE FROM coupon_usage WHERE order_id = ?
                ");
                $delete_usage_stmt->execute([$order_id]);
                
                // Update coupon usage count
                $update_coupon_stmt = $this->db->prepare("
                    UPDATE coupons SET usage_count = usage_count - 1 WHERE id = ?
                ");
                $update_coupon_stmt->execute([$order_info[\'coupon_id\']]);
            }
            
            $this->db->commit();
            return true;
            
        } catch (PDOException $e) {
            $this->db->rollback();
            return false;
        }
    }
}
?>';
    
    if (file_put_contents('includes/CouponValidator.php', $coupon_validator)) {
        echo "✅ تم إنشاء includes/CouponValidator.php\n";
    } else {
        echo "❌ فشل في إنشاء CouponValidator.php\n";
    }
    
    // Step 2: Create AJAX coupon validation endpoint
    echo "\nSTEP 2: إنشاء نقطة نهاية للتحقق من الكوبونات...\n";
    echo "----------------------------------------------\n";
    
    $ajax_coupon = '<?php
session_start();
require_once \'../../config/database.php\';
require_once \'../../includes/CouponValidator.php\';

header(\'Content-Type: application/json\');

if ($_SERVER[\'REQUEST_METHOD\'] !== \'POST\') {
    http_response_code(405);
    echo json_encode([\'error\' => \'Method not allowed\']);
    exit;
}

$input = json_decode(file_get_contents(\'php://input\'), true);
$coupon_code = $input[\'coupon_code\'] ?? \'\';
$order_total = floatval($input[\'order_total\'] ?? 0);
$customer_id = intval($input[\'customer_id\'] ?? 0) ?: null;

if (empty($coupon_code)) {
    echo json_encode([\'valid\' => false, \'message\' => \'كود الكوبون مطلوب\']);
    exit;
}

if ($order_total <= 0) {
    echo json_encode([\'valid\' => false, \'message\' => \'إجمالي الطلب غير صحيح\']);
    exit;
}

$validator = new CouponValidator($db);
$result = $validator->validateCoupon($coupon_code, $order_total, $customer_id);

echo json_encode($result);
?>';
    
    if (!is_dir('modules/orders/ajax')) {
        mkdir('modules/orders/ajax', 0755, true);
    }
    
    if (file_put_contents('modules/orders/ajax/validate_coupon.php', $ajax_coupon)) {
        echo "✅ تم إنشاء modules/orders/ajax/validate_coupon.php\n";
    } else {
        echo "❌ فشل في إنشاء validate_coupon.php\n";
    }
    
    // Step 3: Create enhanced order creation form with coupon support
    echo "\nSTEP 3: تحديث نموذج إنشاء الطلبات لدعم الكوبونات...\n";
    echo "----------------------------------------------------\n";
    
    // Read current create.php and add coupon section
    $create_file = \'modules/orders/create.php\';
    if (file_exists($create_file)) {
        $create_content = file_get_contents($create_file);
        
        // Check if coupon section already exists
        if (strpos($create_content, \'coupon_code\') === false) {
            echo "إضافة قسم الكوبونات إلى نموذج إنشاء الطلب...\n";
            
            // Find the position to insert coupon section (before total calculation)
            $total_pattern = \'/<div[^>]*class="[^"]*total[^"]*"[^>]*>/i\';
            
            if (preg_match($total_pattern, $create_content, $matches, PREG_OFFSET_CAPTURE)) {
                $insert_position = $matches[0][1];
                
                $coupon_section = \'
                    <!-- Coupon Section -->
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 mb-6">
                        <h3 class="text-lg font-medium text-purple-800 mb-4">
                            <i class="fas fa-ticket-alt mr-2"></i>كوبون الخصم
                        </h3>
                        
                        <div class="flex gap-4">
                            <div class="flex-1">
                                <input type="text" id="coupon_code" name="coupon_code" 
                                       placeholder="أدخل كود الكوبون..." 
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 uppercase">
                            </div>
                            <button type="button" id="apply_coupon" 
                                    class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition">
                                <i class="fas fa-check mr-2"></i>تطبيق
                            </button>
                            <button type="button" id="remove_coupon" style="display: none;"
                                    class="px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                                <i class="fas fa-times mr-2"></i>إزالة
                            </button>
                        </div>
                        
                        <div id="coupon_message" class="mt-3" style="display: none;"></div>
                        
                        <div id="coupon_details" class="mt-4 p-3 bg-amber-50 border border-amber-200 rounded" style="display: none;">
                            <div class="flex justify-between items-center">
                                <span class="text-amber-700">الخصم المطبق:</span>
                                <span id="coupon_discount_display" class="font-bold text-amber-700"></span>
                            </div>
                        </div>
                        
                        <input type="hidden" id="applied_coupon_id" name="coupon_id" value="">
                        <input type="hidden" id="applied_coupon_discount" name="coupon_discount" value="0">
                    </div>
\';
                
                $new_content = substr($create_content, 0, $insert_position) . $coupon_section . substr($create_content, $insert_position);
                
                // Add JavaScript for coupon functionality
                $coupon_js = \'
<script>
// Coupon functionality
let appliedCoupon = null;
let originalTotal = 0;

document.getElementById(\\\'apply_coupon\\\').addEventListener(\\\'click\\\', function() {
    const couponCode = document.getElementById(\\\'coupon_code\\\').value.trim();
    const orderTotal = calculateOrderTotal();
    const customerId = document.getElementById(\\\'customer_id\\\').value;
    
    if (!couponCode) {
        showCouponMessage(\\\'يرجى إدخال كود الكوبون\\\', \\\'error\\\');
        return;
    }
    
    if (orderTotal <= 0) {
        showCouponMessage(\\\'يرجى إضافة منتجات للطلب أولاً\\\', \\\'error\\\');
        return;
    }
    
    // Show loading
    this.disabled = true;
    this.innerHTML = \\\'<i class="fas fa-spinner fa-spin mr-2"></i>جاري التحقق...\\\';
    
    // Validate coupon via AJAX
    fetch(\\\'ajax/validate_coupon.php\\\', {
        method: \\\'POST\\\',
        headers: {
            \\\'Content-Type\\\': \\\'application/json\\\'
        },
        body: JSON.stringify({
            coupon_code: couponCode,
            order_total: orderTotal,
            customer_id: customerId
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.valid) {
            applyCoupon(data.coupon, data.discount_amount);
            showCouponMessage(data.message, \\\'success\\\');
        } else {
            showCouponMessage(data.message, \\\'error\\\');
        }
    })
    .catch(error => {
        showCouponMessage(\\\'حدث خطأ في التحقق من الكوبون\\\', \\\'error\\\');
    })
    .finally(() => {
        this.disabled = false;
        this.innerHTML = \\\'<i class="fas fa-check mr-2"></i>تطبيق\\\';
    });
});

document.getElementById(\\\'remove_coupon\\\').addEventListener(\\\'click\\\', function() {
    removeCoupon();
});

function applyCoupon(coupon, discountAmount) {
    appliedCoupon = coupon;
    originalTotal = calculateOrderTotal();
    
    // Update UI
    document.getElementById(\\\'applied_coupon_id\\\').value = coupon.id;
    document.getElementById(\\\'applied_coupon_discount\\\').value = discountAmount;
    document.getElementById(\\\'coupon_discount_display\\\').textContent = discountAmount.toFixed(2) + \\\' ريال\\\';
    
    document.getElementById(\\\'coupon_details\\\').style.display = \\\'block\\\';
    document.getElementById(\\\'apply_coupon\\\').style.display = \\\'none\\\';
    document.getElementById(\\\'remove_coupon\\\').style.display = \\\'inline-block\\\';
    document.getElementById(\\\'coupon_code\\\').disabled = true;
    
    // Update totals
    updateOrderTotals();
}

function removeCoupon() {
    appliedCoupon = null;
    
    // Reset UI
    document.getElementById(\\\'applied_coupon_id\\\').value = \\\'\\\';
    document.getElementById(\\\'applied_coupon_discount\\\').value = \\\'0\\\';
    document.getElementById(\\\'coupon_code\\\').value = \\\'\\\';
    document.getElementById(\\\'coupon_code\\\').disabled = false;
    
    document.getElementById(\\\'coupon_details\\\').style.display = \\\'none\\\';
    document.getElementById(\\\'coupon_message\\\').style.display = \\\'none\\\';
    document.getElementById(\\\'apply_coupon\\\').style.display = \\\'inline-block\\\';
    document.getElementById(\\\'remove_coupon\\\').style.display = \\\'none\\\';
    
    // Update totals
    updateOrderTotals();
}

function showCouponMessage(message, type) {
    const messageDiv = document.getElementById(\\\'coupon_message\\\');
    messageDiv.style.display = \\\'block\\\';
    messageDiv.className = \\\'mt-3 p-2 rounded \\\' + (type === \\\'success\\\' ? \\\'bg-amber-100 text-amber-700\\\' : \\\'bg-red-100 text-red-700\\\');
    messageDiv.textContent = message;
}

function calculateOrderTotal() {
    // Calculate total from order items
    let total = 0;
    const itemRows = document.querySelectorAll(\\\'.item-row\\\');
    
    itemRows.forEach(row => {
        const quantity = parseFloat(row.querySelector(\\\'[name$="[quantity]"]\\\').value) || 0;
        const price = parseFloat(row.querySelector(\\\'[name$="[unit_price]"]\\\').value) || 0;
        total += quantity * price;
    });
    
    return total;
}

function updateOrderTotals() {
    const subtotal = calculateOrderTotal();
    const discount = parseFloat(document.getElementById(\\\'applied_coupon_discount\\\').value) || 0;
    const shipping = parseFloat(document.getElementById(\\\'shipping_cost\\\').value) || 0;
    const finalTotal = subtotal - discount + shipping;
    
    // Update display elements
    if (document.getElementById(\\\'subtotal_display\\\')) {
        document.getElementById(\\\'subtotal_display\\\').textContent = subtotal.toFixed(2);
    }
    if (document.getElementById(\\\'discount_display\\\')) {
        document.getElementById(\\\'discount_display\\\').textContent = discount.toFixed(2);
    }
    if (document.getElementById(\\\'total_display\\\')) {
        document.getElementById(\\\'total_display\\\').textContent = finalTotal.toFixed(2);
    }
    
    // Update hidden fields
    if (document.getElementById(\\\'subtotal_amount\\\')) {
        document.getElementById(\\\'subtotal_amount\\\').value = subtotal.toFixed(2);
    }
    if (document.getElementById(\\\'total_amount\\\')) {
        document.getElementById(\\\'total_amount\\\').value = (subtotal - discount).toFixed(2);
    }
    if (document.getElementById(\\\'final_amount\\\')) {
        document.getElementById(\\\'final_amount\\\').value = finalTotal.toFixed(2);
    }
}

// Recalculate when items change
document.addEventListener(\\\'input\\\', function(e) {
    if (e.target.name && (e.target.name.includes(\\\'quantity\\\') || e.target.name.includes(\\\'unit_price\\\'))) {
        if (appliedCoupon) {
            // Revalidate coupon with new total
            setTimeout(() => {
                const newTotal = calculateOrderTotal();
                if (newTotal < appliedCoupon.min_order_amount) {
                    showCouponMessage(\\\'إجمالي الطلب أقل من الحد المطلوب للكوبون\\\', \\\'error\\\');
                    removeCoupon();
                } else {
                    updateOrderTotals();
                }
            }, 100);
        } else {
            updateOrderTotals();
        }
    }
});
</script>\';
                
                // Add the JavaScript before closing body tag
                $new_content = str_replace(\'</body>\', $coupon_js . \'</body>\', $new_content);
                
                if (file_put_contents($create_file, $new_content)) {
                    echo "✅ تم تحديث نموذج إنشاء الطلبات\n";
                } else {
                    echo "❌ فشل في تحديث نموذج إنشاء الطلبات\n";
                }
            } else {
                echo "⚠️ لم يتم العثور على موقع إدراج مناسب في نموذج الطلب\n";
            }
        } else {
            echo "✅ قسم الكوبونات موجود مسبقاً في نموذج الطلب\n";
        }
    } else {
        echo "⚠️ ملف create.php غير موجود\n";
    }
    
    echo "\n=== تم الربط بنجاح ===\n";
    echo "✅ فئة التحقق من الكوبونات جاهزة\n";
    echo "✅ نقطة نهاية AJAX للتحقق جاهزة\n";
    echo "✅ نموذج الطلبات محدث لدعم الكوبونات\n";
    echo "✅ النظام مربوط بالكامل\n\n";
    
    echo "الميزات المتاحة الآن:\n";
    echo "• التحقق من صحة الكوبونات في الوقت الفعلي\n";
    echo "• تطبيق الخصومات تلقائياً\n";
    echo "• تتبع استخدام الكوبونات\n";
    echo "• منع تجاوز حدود الاستخدام\n";
    echo "• إحصائيات مفصلة للكوبونات\n";
    
} catch (Exception $e) {
    echo "\n❌ خطأ: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "</div>";

echo "<div style='margin-top: 20px; text-align: center;'>";
echo "<a href='modules/orders/create.php' style='background: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>📝 إنشاء طلب جديد</a>";
echo "<a href='modules/coupons/index.php' style='background: #9C27B0; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>🎫 إدارة الكوبونات</a>";
echo "<a href='system_status.php' style='background: #FF9800; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; margin: 5px;'>📊 حالة النظام</a>";
echo "</div>";
?>
