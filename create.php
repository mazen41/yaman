<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/database.php';
require_once '../../includes/check_permissions.php';

// Check add permission
if (!hasPermission($_SESSION['user_id'], 'orders', 'add')) {
    $_SESSION['error_message'] = 'ليس لديك صلاحية لإنشاء طلب جديد';
    header('Location: index.php');
    exit();
}

// Get user role and admin status for permission checks
$user_role = $_SESSION['role'] ?? 'employee';
$is_super_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;

require_once '../../includes/auto_generate_helpers.php';
require_once 'discount_functions.php';

 $page_title = 'إنشاء طلب جديد';
 $error_message = '';
 $success_message = '';
 $creator_name = $_SESSION['username'] ?? 'المستخدم الحالي';

// Get discount rules
 $discount_rules = getAllDiscountRules($db);

// Get customers for dropdown with customer type information
 $customers_stmt = $db->prepare("
    SELECT c.id, c.name, c.customer_code, c.mobile_number, c.whatsapp_number, c.email, c.city_id, c.city_name, c.currency,
           ct.id as type_id, ct.name as type_name, ct.discount_percentage as type_discount
    FROM customers c
    LEFT JOIN customer_types ct ON c.customer_type_id = ct.id
    WHERE c.is_active = 1 
    ORDER BY c.name
");
 $customers_stmt->execute();
 $customers = $customers_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get products for dropdown
try {
    $products_stmt = $db->prepare("SELECT id, name, price FROM products WHERE is_active = 1 ORDER BY name");
    $products_stmt->execute();
    $products = $products_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If products table doesn't exist yet
    $products = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $customer_id = intval($_POST['customer_id'] ?? 0);
    $items = $_POST['items'] ?? [];
    $notes = trim($_POST['notes'] ?? '');
    $shipping_cost = floatval($_POST['shipping_cost'] ?? 0); 
    $expected_delivery_date = $_POST['expected_delivery_date'] ?? null;
    $order_link = trim($_POST['order_link'] ?? '');
    $additional_link = trim($_POST['additional_link'] ?? '');
    $notification_method = $_POST['notification_method'] ?? [];
    $coupon_code = trim($_POST['coupon_code'] ?? '');
    // START: New field for editable discount
    $automatic_discount_percentage = floatval($_POST['automatic_discount_percentage'] ?? 0);
    // END: New field

    // Backward compatibility for order links: if the old top-level fields are empty,
    // but the new per-item basket link fields are filled, use the first item's links.
    if (empty($order_link) && !empty($items) && !empty($items[0]['product_link'])) {
        $order_link = trim($items[0]['product_link']);
    }
    if (empty($additional_link) && !empty($items) && !empty($items[0]['additional_link'])) {
        $additional_link = trim($items[0]['additional_link']);
    }

    // Validation
    $errors = [];
    
    if (empty($customer_id)) {
        $errors[] = 'يرجى اختيار العميل';
    }
    
    if (empty($items) || !is_array($items) || count($items) === 0) {
        $errors[] = 'يرجى إضافة منتج واحد على الأقل';
    }
    
    
    if (!empty($errors)) {
        $error_message = implode(' • ', $errors);
    } else {
        try {
            $db->beginTransaction();
            
            // Generate unique order number (simple sequential: 1, 2, 3, ...)
            $order_number_stmt = $db->prepare("
                SELECT COALESCE(MAX(CAST(order_number AS UNSIGNED)), 0) + 1 as next_number
                FROM customer_orders 
                WHERE order_number REGEXP '^[0-9]+$'
            ");
            $order_number_stmt->execute();
            $next_number = (int)$order_number_stmt->fetchColumn();
            if ($next_number < 1) $next_number = 1;
            $order_number = (string)$next_number;
            
            // Double-check uniqueness (in case of race condition)
            $check_stmt = $db->prepare("SELECT COUNT(*) FROM customer_orders WHERE order_number = ?");
            $check_stmt->execute([$order_number]);
            if ($check_stmt->fetchColumn() > 0) {
                // If duplicate, increment
                $order_number = (string)($next_number + 1);
            }
            
            // Calculate subtotal (before discount)
            $subtotal_amount = 0;
            foreach ($items as $item) {
                $subtotal_amount += floatval($item['total'] ?? 0);
            }
            
            // Get customer type information (for display purposes only)
            $customer_stmt = $db->prepare("
                SELECT c.*, ct.name as type_name
                FROM customers c
                LEFT JOIN customer_types ct ON c.customer_type_id = ct.id
                WHERE c.id = ?
            ");
            $customer_stmt->execute([$customer_id]);
            $customer = $customer_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$customer) {
                $db->rollBack();
                $error_message = 'العميل المحدد غير صالح أو تم حذفه. يرجى تحديث الصفحة واختيار عميل آخر.';
                throw new Exception($error_message);
            }
            $customer_type_name = $customer['type_name'] ?? '';

            // IMPORTANT: Use the automatic_discount_percentage from the form (already set from POST on line 61)
            // This is calculated by JavaScript based on tiered discount rules
            // DO NOT overwrite it with customer type discount
            
            // START: Calculate automatic discount amount based on form input
            $automatic_discount_amount = round($subtotal_amount * ($automatic_discount_percentage / 100), 2);
            // END: Automatic discount calculation
            
            // Calculate discount using coupon
            $discount_info = [];
            $requires_approval = false;
            $coupon_id = null;
            
            if (!empty($coupon_code)) {
                // ... (Coupon logic remains unchanged) ...
                try {
                    $tableCheck = $db->query("SHOW TABLES LIKE 'coupons'");
                    if ($tableCheck->rowCount() == 0) {
                        $errors[] = 'نظام الكوبونات غير مفعل';
                        throw new Exception(implode(' • ', $errors));
                    }
                } catch (PDOException $e) {
                    $errors[] = 'نظام الكوبونات غير متاح';
                    throw new Exception(implode(' • ', $errors));
                }
                
                $coupon_stmt = $db->prepare("
                    SELECT * FROM coupons 
                    WHERE coupon_code = ? 
                    AND is_active = 1 
                    AND (valid_from IS NULL OR valid_from <= CURDATE())
                    AND (valid_to IS NULL OR valid_to >= CURDATE())
                    AND (usage_limit IS NULL OR used_count < usage_limit)
                ");
                $coupon_stmt->execute([$coupon_code]);
                $coupon = $coupon_stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($coupon) {
                    if ($subtotal_amount >= $coupon['min_order_amount']) {
                        $coupon_id = $coupon['id'];
                        $discount_info['discount_type'] = $coupon['discount_type'];
                        $discount_info['discount_value'] = $coupon['discount_value'];
                        
                        if ($coupon['discount_type'] === 'percentage') {
                            $discount_amount = round($subtotal_amount * ($coupon['discount_value'] / 100), 2);
                            if ($coupon['max_discount_amount'] && $discount_amount > $coupon['max_discount_amount']) {
                                $discount_amount = $coupon['max_discount_amount'];
                            }
                            $discount_info['discount_amount'] = $discount_amount;
                        } else {
                            $discount_info['discount_amount'] = min($coupon['discount_value'], $subtotal_amount);
                        }
                    } else {
                        $errors[] = 'الحد الأدنى للطلب لاستخدام هذا الكوبون هو ' . $coupon['min_order_amount'] . ' ريال';
                    }
                } else {
                    $errors[] = 'الكوبون غير صحيح أو منتهي الصلاحية';
                }
            } else {
                $discount_info['discount_type'] = null;
                $discount_info['discount_value'] = 0;
                $discount_info['discount_amount'] = 0;
            }
            
            if (!empty($errors)) {
                $error_message = implode(' • ', $errors);
                throw new Exception($error_message);
            }
            
            // MODIFIED: total_amount calculation with new discount
            $total_amount = $subtotal_amount - $automatic_discount_amount - $discount_info['discount_amount'];
            
            $final_amount = $total_amount + $shipping_cost;
            
            // DEBUG LOGGING: Log all discount calculations
            error_log("=== ORDER CREATION DEBUG ===");
            error_log("Order Number: $order_number");
            error_log("Customer: {$customer['name']} (ID: $customer_id)");
            error_log("Customer Type: $customer_type_name");
            error_log("Subtotal: $subtotal_amount");
            error_log("Automatic Discount %: $automatic_discount_percentage");
            error_log("Automatic Discount Amount: $automatic_discount_amount");
            error_log("Coupon Code: $coupon_code");
            error_log("Coupon Discount Amount: {$discount_info['discount_amount']}");
            error_log("Total Discount (Auto + Coupon): " . ($automatic_discount_amount + $discount_info['discount_amount']));
            error_log("Shipping Cost: $shipping_cost");
            error_log("Total Amount (after discounts): $total_amount");
            error_log("Final Amount: $final_amount");
            error_log("=== END DEBUG ===");
            
            $stmt = $db->prepare("
                INSERT INTO customer_orders (
                    order_number, customer_id, subtotal_amount, total_amount, final_amount, 
                    discount_type, discount_value, discount_amount, paid_amount,
                    status, shipping_cost, 
                    expected_delivery_date, order_link, additional_link, notes, requires_approval, created_by,
                    customer_type_id, customer_type_name, customer_type_discount,
                    automatic_discount_percentage, automatic_discount_amount, currency
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'new', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            // MODIFIED: Execute statement with the new discount value and structure
            // Calculate total discount for the discount_amount column (for display in lists)
            $total_discount_amount = $automatic_discount_amount + $discount_info['discount_amount'];
            
            error_log("Saving to database - discount_amount: $total_discount_amount");
            
            $stmt->execute([
                $order_number,
                $customer_id,
                $subtotal_amount,
                $total_amount,
                $final_amount,
                $discount_info['discount_type'], // Coupon type
                $discount_info['discount_value'], // Coupon value
                $total_discount_amount, // TOTAL discount (automatic + coupon) for display
                0, // paid_amount
                $shipping_cost,
                $expected_delivery_date ?: null,
                $order_link ?: null,
                $additional_link ?: null,
                $notes,
                $requires_approval ? 1 : 0,
                $_SESSION['user_id'],
                $customer['customer_type_id'] ?? null,
                $customer_type_name,
                0, // Set customer_type_discount to 0 as it's no longer used for calculation
                $automatic_discount_percentage, // NEW: Store the automatic discount percentage
                $automatic_discount_amount, // NEW: Store the automatic discount amount separately
                $customer['currency'] ?? 'YER' // Store customer currency
            ]);
            
            $order_id = $db->lastInsertId();
            
            // ... (rest of the PHP file remains the same) ...
            
            if ($coupon_id) {
                try {
                    $usage_stmt = $db->prepare("
                        INSERT INTO coupon_usage (coupon_id, order_id, customer_id, discount_amount)
                        VALUES (?, ?, ?, ?)
                    ");
                    $usage_stmt->execute([$coupon_id, $order_id, $customer_id, $discount_info['discount_amount']]);
                    
                    $update_stmt = $db->prepare("UPDATE coupons SET used_count = used_count + 1 WHERE id = ?");
                    $update_stmt->execute([$coupon_id]);
                } catch (PDOException $e) {
                    error_log('Coupon tracking failed: ' . $e->getMessage());
                }
            }
            
            $item_stmt = $db->prepare("
                INSERT INTO order_items (
                    order_id, product_name, quantity, unit_price, total_price, notes, product_link, product_status
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $item_counter = 1;
            foreach ($items as $item) {
                $product_name = trim($item['name'] ?? '');
                if (empty($product_name)) {
                    $product_name = 'منتج ' . (count($items) > 1 ? '#' . $item_counter : '');
                }
                $item_counter++;
                
                // Fix: Use correct field names that match the form
                $quantity = intval($item['item_count'] ?? $item['quantity'] ?? 1);
                $unit_price = floatval($item['price'] ?? 0);
                $total_price = floatval($item['total'] ?? 0); 
                $product_link = trim($item['product_link'] ?? $item['link'] ?? '');
                $item_notes = trim($item['notes'] ?? '');
                $product_status = 'available';
                
                $item_stmt->execute([
                    $order_id,
                    $product_name,
                    $quantity,
                    $unit_price,
                    $total_price,
                    $item_notes,
                    $product_link,
                    $product_status
                ]);
            }
            
            $status_stmt = $db->prepare("
                INSERT INTO order_status_history (
                    order_id, status, notes, created_by
                ) VALUES (?, 'new', 'تم إنشاء الطلب', ?)
            ");
            
            $status_stmt->execute([
                $order_id,
                $_SESSION['user_id']
            ]);
            
            if (!empty($notification_method)) {
                $customer_stmt = $db->prepare("SELECT name, mobile_number, whatsapp_number, email FROM customers WHERE id = ?");
                $customer_stmt->execute([$customer_id]);
                $customer_info = $customer_stmt->fetch(PDO::FETCH_ASSOC);
                
                $notification_stmt = $db->prepare("
                    INSERT INTO order_notifications (
                        order_id, notification_type, status, sent_to
                    ) VALUES (?, ?, 'pending', ?)
                ");
                
                foreach ($notification_method as $method) {
                    if ($method === 'whatsapp' && !empty($customer_info['whatsapp_number'])) {
                        $notification_stmt->execute([$order_id, 'whatsapp', $customer_info['whatsapp_number']]);
                    } elseif ($method === 'email' && !empty($customer_info['email'])) {
                        $notification_stmt->execute([$order_id, 'email', $customer_info['email']]);
                    }
                }
            }
            
            if (!empty($_FILES['order_images']['name'][0])) {
                $upload_dir = '../../uploads/orders/images/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $max_size = 5 * 1024 * 1024;
                
                foreach ($_FILES['order_images']['name'] as $key => $filename) {
                    if ($_FILES['order_images']['error'][$key] === UPLOAD_ERR_OK) {
                        $file_tmp = $_FILES['order_images']['tmp_name'][$key];
                        $file_size = $_FILES['order_images']['size'][$key];
                        $file_type = $_FILES['order_images']['type'][$key];
                        
                        if (!in_array($file_type, $allowed_types) || $file_size > $max_size) {
                            continue;
                        }
                        
                        $file_ext = pathinfo($filename, PATHINFO_EXTENSION);
                        $new_filename = 'order_' . $order_id . '_' . time() . '_' . $key . '.' . $file_ext;
                        $file_path = $upload_dir . $new_filename;
                        
                        if (move_uploaded_file($file_tmp, $file_path)) {
                            $image_stmt = $db->prepare("
                                INSERT INTO order_images 
                                (order_id, image_path, image_name, image_type, image_size, display_order, uploaded_by)
                                VALUES (?, ?, ?, ?, ?, ?, ?)
                            ");
                            $image_stmt->execute([
                                $order_id,
                                'uploads/orders/images/' . $new_filename,
                                $filename,
                                $file_type,
                                $file_size,
                                $key,
                                $_SESSION['user_id']
                            ]);
                        }
                    }
                }
            }
            
            try {
                $invoiceNumber = createInvoiceForOrder($db, $order_id, $_SESSION['user_id']);
                if ($invoiceNumber) {
                    error_log("Auto-generated invoice: $invoiceNumber for order: $order_number");
                }
            } catch (Exception $e) {
                error_log("Failed to auto-generate invoice: " . $e->getMessage());
            }
            
            $db->commit();
            $success_message = 'تم إنشاء الطلب بنجاح';
            header("Location: /modules/orders/index.php");
            exit();
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            
            // Detailed error logging
            error_log('[orders/create.php] Create order failed: ' . $e->getMessage());
            error_log('[orders/create.php] Stack trace: ' . $e->getTraceAsString());
            
            // Show detailed error in development
            if ($e instanceof PDOException) {
                error_log('[orders/create.php] PDO Error Code: ' . $e->getCode());
                error_log('[orders/create.php] PDO Error Info: ' . print_r($e->errorInfo, true));
                $error_message = 'خطأ في قاعدة البيانات: ' . $e->getMessage();
            } else {
                $error_message = 'خطأ: ' . $e->getMessage();
            }
        }
    }
}

include '../../includes/header.php';
?>

<style>
    /* Modern Card Design */
    .case-highlight { 
        background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        border: 1px solid #e2e8f0;
        border-radius: 16px;
        padding: 1rem; /* Adjusted for mobile */
        position: relative;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        transition: all 0.3s ease;
    }
    @media (min-width: 768px) {
        .case-highlight {
            padding: 1.5rem;
        }
    }
    .case-highlight:hover {
        border-color: #cbd5e1;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
        transform: translateY(-2px);
    }
    
    /* Section Headers */
    .section-header {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 12px;
        margin-bottom: 1.5rem;
        box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.3);
    }
    
    .dir-ltr { 
        direction: ltr; 
        text-align: left; 
    }
    
    /* Table Styles */
    .item-row {
        transition: all 0.2s ease;
        border-bottom: 1px solid #f1f5f9;
    }
    .item-row:hover {
        background-color: #f8fafc;
    }
    
    /* Notification Options */
    .notification-option {
        display: flex;
        align-items: center;
        padding: 0.75rem;
        border: 2px solid #e5e7eb;
        border-radius: 10px;
        margin-bottom: 0.75rem;
        cursor: pointer;
        transition: all 0.2s ease;
        background: white;
    }
    .notification-option:hover {
        background-color: #f0f9ff;
        border-color: #3b82f6;
    }
    .notification-option.selected {
        border-color: #3b82f6;
        background-color: #dbeafe;
        box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.2);
    }
    
    /* Customer Search Styles */
    #customerSearch {
        font-size: 1rem;
        padding: 0.75rem 2.5rem 0.75rem 1rem; /* Adjusted padding */
        transition: all 0.2s ease;
        border-radius: 12px;
        border: 2px solid #e2e8f0;
    }
    #customerSearch:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }
    #customerSearch.has-selection {
        background-color: #f0fdf4;
        border-color: #10b981;
        font-weight: 600;
    }
    
    /* Product Search Styles */
    .product-search-input {
        font-size: 0.875rem;
        padding: 0.625rem;
        border-radius: 8px;
        border: 2px solid #e2e8f0;
        transition: all 0.2s ease;
    }
    .product-search-input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    .product-search-results {
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        margin-top: 0.5rem;
        border-radius: 10px;
        overflow: hidden;
        z-index: 50;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.15);
    }
    
    /* Buttons */
    .btn-primary {
        background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        border-radius: 10px;
        padding: 0.75rem 1.5rem;
        font-weight: 600;
        transition: all 0.2s ease;
        box-shadow: 0 4px 6px -1px rgba(59, 130, 246, 0.3);
    }
    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 10px 15px -3px rgba(59, 130, 246, 0.4);
    }
    
    /* Input Fields */
    .form-input {
        width: 100%; /* Make inputs responsive by default */
        border-radius: 8px;
        border: 2px solid #e2e8f0;
        transition: all 0.2s ease;
        padding: 0.5rem 0.75rem; /* Standardized padding */
    }
    .form-input:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
    }
    
    /* Customer Type Badge */
    .customer-type-badge {
        display: inline-block;
        padding: 0.25rem 0.75rem;
        border-radius: 9999px;
        font-size: 0.75rem;
        font-weight: 600;
        margin-left: 0.5rem;
    }
    
    /* The portal script handles z-index, but let's keep some safe base styles */
    .search-results:not(.hidden) {
        display: block !important;
    }
    .product-search-container {
        position: relative;
    }
    
    /* Ensure parent containers don't clip dropdowns */
    .case-highlight, .container, .row, .col-md-12 {
        overflow: visible !important;
    }
    
    /* Mobile-responsive table */
    @media (max-width: 768px) {
        /* Hide table headers on mobile */
        .items-table thead {
            display: none;
        }
        
        /* Convert table rows to cards on mobile */
        .items-table tbody {
            display: block;
        }
        
        .items-table tbody tr {
            display: block;
            margin-bottom: 1rem;
            border: 2px solid #e5e7eb;
            border-radius: 12px;
            padding: 1rem;
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        
        .items-table tbody td {
            display: block;
            width: 100% !important;
            padding: 0.5rem 0 !important;
            border: none;
            text-align: right !important;
        }
        
        /* Add labels before each field on mobile */
        .items-table tbody td:before {
            content: attr(data-label);
            font-weight: 600;
            color: #4b5563;
            display: block;
            margin-bottom: 0.25rem;
            font-size: 0.875rem;
        }
        
        /* Hide the delete button label */
        .items-table tbody td:last-child:before {
            display: none;
        }
        
        /* Item number styling */
        .items-table tbody td:first-child {
            font-size: 1.25rem;
            font-weight: bold;
            color: #3b82f6;
            text-align: center !important;
            padding-bottom: 0.75rem !important;
            border-bottom: 1px solid #e5e7eb;
            margin-bottom: 0.5rem;
        }
        
        .items-table tbody td:first-child:before {
            content: 'السلة رقم ';
            font-size: 0.875rem;
            font-weight: 600;
            color: #6b7280;
        }
        
        /* Make inputs full width on mobile */
        .items-table tbody td input,
        .items-table tbody td select {
            width: 100% !important;
        }
        
        /* Delete button styling */
        .items-table tbody td:last-child {
            text-align: center !important;
            padding-top: 1rem !important;
            border-top: 1px solid #e5e7eb;
            margin-top: 0.5rem;
        }
        
        /* Tfoot responsive */
        .items-table tfoot tr {
            display: block;
            border-bottom: 1px solid #e5e7eb;
            padding: 0.75rem 0;
        }
        
        .items-table tfoot td {
            display: block;
            width: 100% !important;
            padding: 0.25rem 0 !important;
            text-align: right !important;
        }
        
        .items-table tfoot td[colspan] {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
    }
</style>

<div class="min-h-screen bg-gray-50 py-4 sm:py-6" dir="rtl">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-blue-700 shadow-xl rounded-2xl mb-6 sm:mb-8">
            <div class="px-4 py-4 sm:px-8 sm:py-6">
                <div class="flex flex-col sm:flex-row items-start sm:items-center sm:justify-between gap-4">
                    <div>
                        <h1 class="text-2xl sm:text-3xl font-bold text-white flex items-center">
                            <i class="fas fa-shopping-cart ml-3 text-blue-200"></i>
                            إنشاء طلب جديد
                        </h1>
                        <p class="text-blue-100 mt-2 text-sm sm:text-lg flex items-center">
                            <i class="fas fa-user-edit ml-2"></i>
                            يتم الإنشاء بواسطة: <span class="font-semibold mr-1"><?php echo htmlspecialchars($creator_name); ?></span>
                        </p>
                    </div>
                    <div class="w-full sm:w-auto">
                        <a href="index.php" class="w-full sm:w-auto inline-flex items-center justify-center px-4 py-2 sm:px-6 sm:py-3 bg-white text-blue-600 rounded-xl hover:bg-blue-50 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-1 font-semibold">
                            <i class="fas fa-arrow-right ml-2"></i>
                            العودة للقائمة
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($error_message): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6 text-sm">
            <i class="fas fa-exclamation-circle ml-2"></i>
            <?php echo $error_message; ?>
        </div>
        <?php endif; ?>
        
        <!-- Order Form -->
        <form method="POST" id="orderForm" enctype="multipart/form-data" class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-4 py-4 sm:px-6 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">بيانات الطلب</h2>
            </div>
            
            <div class="p-4 sm:p-6 space-y-6">
                <!-- Customer Selection -->
                <div class="case-highlight">
                    <label for="customerSearch" class="block text-sm font-medium text-gray-700 mb-2">
                        <i class="fas fa-user ml-2"></i>البحث باسم العميل *
                    </label>
                    <div class="relative">
                        <div class="relative">
                            <input type="text" id="customerSearch" class="form-input w-full pl-10" placeholder="ابحث بالاسم, الجوال, أو الكود..." autocomplete="off">
                            <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                        <div id="searchResults" class="absolute z-[9999] w-full bg-white border border-gray-300 rounded-lg shadow-lg mt-1 max-h-64 overflow-y-auto hidden">
                            <!-- Results populated by JS -->
                        </div>
                    </div>
                    
                    <select id="customer_id" name="customer_id" class="hidden" required>
                        <option value="">-- اختر العميل --</option>
                        <?php foreach ($customers as $customer): ?>
                        <option value="<?php echo $customer['id']; ?>" 
                                data-mobile="<?php echo htmlspecialchars($customer['mobile_number'] ?? ''); ?>"
                                data-whatsapp="<?php echo htmlspecialchars($customer['whatsapp_number'] ?? ''); ?>"
                                data-email="<?php echo htmlspecialchars($customer['email'] ?? ''); ?>"
                                data-city="<?php echo htmlspecialchars($customer['city_name'] ?? ''); ?>"
                                data-name="<?php echo htmlspecialchars($customer['name']); ?>"
                                data-code="<?php echo htmlspecialchars($customer['customer_code']); ?>"
                                data-type-id="<?php echo htmlspecialchars($customer['type_id'] ?? ''); ?>"
                                data-type-name="<?php echo htmlspecialchars($customer['type_name'] ?? ''); ?>"
                                data-type-discount="<?php echo htmlspecialchars($customer['type_discount'] ?? 0); ?>"
                                data-currency="<?php echo htmlspecialchars($customer['currency'] ?? 'YER'); ?>">
                            <?php echo htmlspecialchars($customer['name']) . ' (' . htmlspecialchars($customer['customer_code']) . ')'; ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    
                    <div id="customerDetails" class="mt-4 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3 text-sm hidden">
                        <div>
                            <span class="block font-medium text-gray-500">الجوال:</span>
                            <span id="customerMobile" class="text-gray-900"></span>
                        </div>
                        <div>
                            <span class="block font-medium text-gray-500">الواتساب:</span>
                            <span id="customerWhatsapp" class="text-gray-900"></span>
                        </div>
                        <div>
                            <span class="block font-medium text-gray-500">الإيميل:</span>
                            <span id="customerEmail" class="text-gray-900 break-words"></span>
                        </div>
                    </div>
                    
                    <div id="customerTypeDetails" class="mt-3 hidden">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-2">
                            <span class="text-sm font-medium text-blue-700">نوع العميل:</span>
                            <span id="customerTypeName" class="text-blue-900 font-semibold"></span>
                        </div>
                    </div>
                </div>
                
                <!-- Order Items -->
                <div class="case-highlight">
                    <div class="mb-4">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center mb-4">
                            <i class="fas fa-box ml-2 text-blue-600"></i>
                            منتجات الطلب
                        </h3>
                        
                        <div class="grid grid-cols-1 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">رابط السلة</label>
                                <input type="url" name="items[0][product_link]" class="form-input" placeholder="https://example.com/basket">
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">رابط إضافي</label>
                                <input type="url" name="items[0][additional_link]" class="form-input" placeholder="https://example.com/additional">
                            </div>
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">عدد القطع</label>
                                    <input type="number" name="items[0][item_count]" value="1" min="1" class="form-input item-quantity" oninput="updateTotals()">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">الإجمالي (ريال)</label>
                                    <input type="number" name="items[0][total]" value="0.00" min="0" step="0.01" class="form-input item-total-input" oninput="updateTotals()">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">ملاحظات</label>
                                <textarea name="items[0][notes]" class="form-input" rows="2" placeholder="أضف ملاحظات..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <div class="border-t border-gray-200 pt-4 mt-4">
                        <div id="itemsContainer" class="hidden"></div>
                        
                        <div class="bg-gray-50 rounded-lg p-4 space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="font-semibold text-gray-700">إجمالي القطع:</span>
                                <span id="totalQuantity" class="bg-gray-200 px-3 py-1 rounded-md font-bold">0</span>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <span class="font-semibold text-gray-700">المجموع قبل الخصم:</span>
                                <span class="font-bold text-gray-900">
                                    <span id="subtotalAmount" class="bg-blue-100 px-3 py-1 rounded-md">0.00</span> ريال
                                </span>
                            </div>

                            <div id="automaticDiscountRow" class="hidden flex justify-between items-center bg-purple-50 p-2 rounded">
                                <span class="font-semibold text-purple-700">الخصم التلقائي (%):</span>
                                <div class="flex items-center gap-2">
                                    <input type="number" name="automatic_discount_percentage" id="automaticDiscountPercentage" value="0" readonly class="form-input w-20 p-1 text-center dir-ltr font-semibold bg-gray-100 cursor-not-allowed">
                                    <span class="font-bold text-purple-700">
                                        <span id="automaticDiscountAmount" class="bg-purple-200 px-3 py-1 rounded-md">0.00</span> ريال
                                    </span>
                                </div>
                            </div>
                            
                            <div id="discountTierRow" class="hidden bg-indigo-50 p-2 rounded">
                                <div class="flex items-center justify-center gap-2 text-xs">
                                    <i class="fas fa-info-circle text-indigo-600"></i>
                                    <span class="font-medium text-indigo-700">منطق الخصم الجديد:</span>
                                    <span id="discountTierInfo" class="font-semibold text-indigo-900"></span>
                                </div>
                            </div>
                            
                            <div id="discountRow" class="hidden flex justify-between items-center bg-green-50 p-2 rounded">
                                <span class="font-semibold text-green-700">خصم الكوبون:</span>
                                <span class="font-bold text-green-700">
                                    <span id="discountDisplay" class="bg-green-200 px-3 py-1 rounded-md">0.00</span> ريال
                                </span>
                            </div>
                            
                            <div class="flex justify-between items-center bg-blue-50 p-2 rounded">
                                <span class="font-semibold text-blue-700">الإجمالي بعد الخصم:</span>
                                <span class="font-bold text-blue-700">
                                    <span id="totalAmount" class="bg-blue-200 px-3 py-1 rounded-md">0.00</span> ريال
                                </span>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <span class="font-semibold text-gray-700">تكلفة الشحن:</span>
                                <input type="number" name="shipping_cost" id="shippingCost" value="0" min="0" step="0.01" class="form-input w-32 p-2 text-right dir-ltr font-semibold">
                            </div>
                            
                            <div class="flex justify-between items-center bg-green-50 p-3 rounded border-t-2 border-green-300">
                                <span class="font-bold text-green-700 text-lg">الإجمالي النهائي:</span>
                                <span class="font-bold text-green-700 text-lg">
                                    <span id="finalTotal" class="bg-green-200 px-3 py-1 rounded-md">0.00</span> ريال
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Order Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="case-highlight">
                        <h3 class="text-lg font-medium text-gray-900 mb-4">تفاصيل الشحن والدفع</h3>
                        <div class="space-y-4">
                            <div>
                                <label for="coupon_code" class="block text-sm font-medium text-gray-700 mb-1"><i class="fas fa-ticket-alt ml-1 text-green-600"></i> كود الكوبون</label>
                                <div class="flex gap-2">
                                    <input type="text" id="coupon_code" name="coupon_code" class="form-input flex-1 uppercase" placeholder="أدخل الكود" style="text-transform: uppercase;">
                                    <button type="button" id="applyCouponBtn" class="px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition font-semibold">تطبيق</button>
                                </div>
                                <div id="couponMessage" class="mt-2 text-sm hidden"></div>
                                <div id="couponDetails" class="mt-2 p-2 bg-green-50 border border-green-300 rounded-lg hidden">
                                    <div class="flex items-center justify-between text-sm">
                                        <div>
                                            <span class="font-semibold text-green-800">✅ تم تطبيق:</span>
                                            <span id="appliedCouponCode" class="font-bold text-green-900"></span>
                                        </div>
                                        <button type="button" id="removeCouponBtn" class="text-red-600 hover:text-red-800 font-semibold"><i class="fas fa-times ml-1"></i> إزالة</button>
                                    </div>
                                    <div id="couponInfo" class="text-xs text-green-700 mt-1"></div>
                                </div>
                <!-- Notes -->
                <div>
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
                    <textarea id="notes" name="notes" rows="3" class="form-input"></textarea>
                </div>
                
                <!-- Order Images Upload -->
                <div class="case-highlight">
                    <h3 class="text-lg font-medium text-gray-900 mb-2"><i class="fas fa-images ml-2 text-purple-600"></i> صور الطلب</h3>
                    <p class="text-sm text-gray-600 mb-4">يمكنك رفع عدة صور للطلب (الحد الأقصى: 5 ميجا لكل صورة)</p>
                    <label class="flex justify-center w-full px-4 py-6 bg-white border-2 border-dashed border-gray-300 rounded-lg cursor-pointer hover:border-purple-500 hover:bg-purple-50 transition-all">
                        <div class="text-center">
                            <i class="fas fa-cloud-upload-alt text-3xl text-gray-400 mb-2"></i>
                            <p class="text-sm text-gray-600"><span class="font-semibold text-purple-600">اختر الصور</span> أو اسحبها هنا</p>
                            <p class="text-xs text-gray-500 mt-1">JPG, PNG, GIF</p>
                        </div>
                        <input type="file" id="order_images" name="order_images[]" multiple accept="image/*" class="hidden" onchange="previewImages(this)">
                    </label>
                    <div id="imagePreviewContainer" class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4 mt-4 hidden"></div>
                </div>
            </div>
            
            <div class="px-4 py-3 sm:px-6 bg-gray-50 border-t border-gray-200 flex justify-between">
                <button type="button" onclick="window.location.href='index.php'" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-lg hover:bg-gray-300 transition duration-200 font-semibold">
                    إلغاء
                </button>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200 font-semibold">
                    <i class="fas fa-save ml-2"></i>
                    حفظ الطلب
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Item Template removed - using simple inputs instead -->

<script>
    const customerSearch = document.getElementById('customerSearch');
    const customerSelect = document.getElementById('customer_id');
    const searchResults = document.getElementById('searchResults');
    const customerDetails = document.getElementById('customerDetails');
    const customerTypeDetails = document.getElementById('customerTypeDetails');
    const allCustomers = Array.from(customerSelect.options).slice(1);
    
    // Flag to prevent auto-calculation from overwriting manual input
    let isDiscountInputFocused = false;
    
    // Initialize customer type tracking variables at the top
    let appliedCoupon = null;
    let selectedCustomerType = null; // Track selected customer type name
    let selectedCustomerTypeId = null; // Track selected customer type ID
    let customerTypeDiscount = 0; // Track customer type discount percentage

    customerSearch.addEventListener('input', function() {
        const searchTerm = this.value.trim().toLowerCase();
        if (searchTerm.length === 0) {
            searchResults.classList.add('hidden');
            searchResults.innerHTML = '';
            return;
        }
        const filtered = allCustomers.filter(option => {
            const name = (option.dataset.name || '').toLowerCase();
            const code = (option.dataset.code || '').toLowerCase();
            const mobile = (option.dataset.mobile || '').toLowerCase();
            const whatsapp = (option.dataset.whatsapp || '').toLowerCase();
            return name.includes(searchTerm) || code.includes(searchTerm) || mobile.includes(searchTerm) || whatsapp.includes(searchTerm);
        });
        if (filtered.length > 0) {
            searchResults.innerHTML = filtered.map(option => {
                const typeBadge = option.dataset.typeName ? `<span class="customer-type-badge bg-gray-200 text-gray-700">${option.dataset.typeName}</span>` : '';
                const currencyBadge = option.dataset.currency ? `<span class="customer-type-badge bg-blue-100 text-blue-800 border border-blue-200 ml-1" style="font-size: 0.7rem;">${option.dataset.currency}</span>` : '';
                return `
                <div class="search-result-item p-3 hover:bg-blue-50 cursor-pointer border-b border-gray-100" data-customer-id="${option.value}" onclick="selectCustomer('${option.value}')">
                    <div class="font-semibold text-gray-900 text-sm flex items-center flex-wrap gap-1">
                        ${option.dataset.name} ${typeBadge} ${currencyBadge}
                    </div>
                    <div class="text-xs text-gray-600 mt-1">
                        <span class="inline-block ml-3">📱 ${option.dataset.mobile || 'N/A'}</span>
                        <span class="inline-block">#${option.dataset.code}</span>
                    </div>
                </div>`;
            }).join('');
            searchResults.classList.remove('hidden');
        } else {
            searchResults.innerHTML = '<div class="p-3 text-center text-sm text-gray-500">❌ لا توجد نتائج</div>';
            searchResults.classList.remove('hidden');
        }
    });

    function selectCustomer(customerId) {
        customerSelect.value = customerId;
        const selectedOption = customerSelect.options[customerSelect.selectedIndex];
        if (selectedOption && selectedOption.value) {
            customerSearch.value = selectedOption.dataset.name + ' (' + selectedOption.dataset.code + ')';
            customerSearch.classList.add('has-selection');
            searchResults.classList.add('hidden');
            
            // Store customer type ID for discount lookup
            const customerTypeId = selectedOption.dataset.typeId || '';
            selectedCustomerType = selectedOption.dataset.typeName || '';
            selectedCustomerTypeId = customerTypeId;
            
            // Trigger discount recalculation
            updateTotals();
        }
    }

    document.getElementById('customer_id').addEventListener('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        console.log('=== CUSTOMER SELECTION DEBUG ===');
        console.log('Selected value:', this.value);
        console.log('Selected option:', selectedOption);
        console.log('Dataset typeId:', selectedOption.dataset.typeId);
        console.log('Dataset typeName:', selectedOption.dataset.typeName);
        
        if (this.value) {
            document.getElementById('customerMobile').textContent = selectedOption.dataset.mobile || 'غير متوفر';
            document.getElementById('customerWhatsapp').textContent = selectedOption.dataset.whatsapp || 'غير متوفر';
            document.getElementById('customerEmail').textContent = selectedOption.dataset.email || 'غير متوفر';
            customerDetails.classList.remove('hidden');
            
            // Store customer type ID for discount lookup
            selectedCustomerTypeId = selectedOption.dataset.typeId || null;
            selectedCustomerType = selectedOption.dataset.typeName || null;
            
            console.log('Set selectedCustomerTypeId to:', selectedCustomerTypeId);
            console.log('Set selectedCustomerType to:', selectedCustomerType);
            
            if (selectedOption.dataset.typeName) {
                document.getElementById('customerTypeName').textContent = selectedOption.dataset.typeName;
                customerTypeDetails.classList.remove('hidden');
            } else {
                customerTypeDetails.classList.add('hidden');
            }
            document.getElementById('notification_whatsapp').disabled = !selectedOption.dataset.whatsapp;
            updateTotals();
        } else {
            customerDetails.classList.add('hidden');
            customerTypeDetails.classList.add('hidden');
            selectedCustomerTypeId = null;
            selectedCustomerType = null;
        }
    });

    let itemCount = 0;
    // Remove addItemBtn event listener since button was removed
    // document.getElementById('addItemBtn').addEventListener('click', addNewItem);

    function addNewItem() {
        const template = document.getElementById('itemRowTemplate').innerHTML;
        const container = document.getElementById('itemsContainer');
        const noItemsRow = document.getElementById('noItemsRow');
        if (noItemsRow) {
            noItemsRow.remove();
        }
        const newRow = document.createElement('tr');
        newRow.className = 'item-row align-top'; // Add the class to the tr element
        newRow.innerHTML = template.replace(/{index}/g, itemCount);
        container.appendChild(newRow);
        updateRowNumbers();
        initializeProductSearch(newRow);
        itemCount++;
        updateTotals();
    }

    function initializeProductSearch(row) {
        const searchInput = row.querySelector('.product-search-input');
        const searchResults = row.querySelector('.product-search-results');
        const productSelect = row.querySelector('.product-select');
        const priceInput = row.querySelector('.item-price');
        const nameInput = row.querySelector('.product-name');
        const allProducts = Array.from(productSelect.options).slice(1);

        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.trim().toLowerCase();
            searchResults.innerHTML = '';
            if (searchTerm.length === 0) {
                searchResults.classList.add('hidden');
                return;
            }
            const filtered = allProducts.filter(option => (option.dataset.name || option.textContent).toLowerCase().includes(searchTerm));
            if (filtered.length > 0) {
                searchResults.innerHTML = filtered.map(option => `
                    <div class="product-result-item p-2 hover:bg-blue-50 cursor-pointer" 
                         data-product-id="${option.value}"
                         data-product-name="${option.dataset.name || option.textContent}"
                         data-product-price="${option.dataset.price}">
                        <div class="font-medium text-sm">${option.dataset.name || option.textContent}</div>
                        <div class="text-xs text-gray-600">${parseFloat(option.dataset.price || 0).toFixed(2)} ريال</div>
                    </div>`).join('');
                searchResults.classList.remove('hidden');
                searchResults.querySelectorAll('.product-result-item').forEach(item => {
                    item.addEventListener('click', function() {
                        productSelect.value = this.dataset.productId;
                        searchInput.value = this.dataset.productName;
                        priceInput.value = this.dataset.productPrice;
                        nameInput.value = this.dataset.productName;
                        searchResults.classList.add('hidden');
                        updateRowTotal(priceInput);
                        row.querySelector('.item-quantity').focus();
                    });
                });
            } else {
                searchResults.innerHTML = '<div class="p-2 text-center text-xs">لا توجد نتائج</div>';
                searchResults.classList.remove('hidden');
            }
        });
        document.addEventListener('click', e => {
            if (!searchInput.contains(e.target)) searchResults.classList.add('hidden');
        });
        searchInput.focus();
    }

    function removeItem(button) {
        button.closest('tr').remove();
        if (document.querySelectorAll('#itemsContainer tr').length === 0) {
            document.getElementById('itemsContainer').innerHTML = '<tr id="noItemsRow"><td colspan="7" class="p-4 text-center text-gray-500">لا توجد سلال.</td></tr>';
        }
        updateRowNumbers();
        updateTotals();
    }
    
    function updateRowNumbers() {
        document.querySelectorAll('#itemsContainer .item-row').forEach((row, index) => {
            row.querySelector('.item-number').textContent = index + 1;
        });
    }

    function updateRowTotal(input) {
        // Price field removed - total is entered directly
        // Just trigger totals update when quantity changes
        updateTotals();
    }

    async function updateTotals() {
        let subtotal = 0;
        let totalQuantity = 0;
        
        // Get values from the simple input fields
        const totalInput = document.querySelector('input[name="items[0][total]"]');
        const quantityInput = document.querySelector('input[name="items[0][item_count]"]');
        
        if (totalInput && quantityInput) {
            subtotal = parseFloat(totalInput.value) || 0;
            totalQuantity = parseInt(quantityInput.value) || 0;
        }
        
        console.log('=== UPDATE TOTALS DEBUG ===');
        console.log('Subtotal:', subtotal);
        console.log('Total Quantity:', totalQuantity);
        console.log('Selected Customer Type ID:', selectedCustomerTypeId);
        console.log('Selected Customer Type Name:', selectedCustomerType);

        const discountInput = document.getElementById('automaticDiscountPercentage');
        
        // Determine which discount to apply
        let currentDiscountPercentage = 0;
        let tierInfo = '';
        
        if (!isDiscountInputFocused) {
            // Fetch discount from customer type tiers if customer is selected
            if (selectedCustomerTypeId && subtotal > 0) {
                try {
                    const url = `get_customer_discount.php?customer_type_id=${selectedCustomerTypeId}&amount=${subtotal}`;
                    console.log('Fetching discount from:', url);
                    
                    const response = await fetch(url);
                    const data = await response.json();
                    
                    console.log('Discount API response:', data);
                    
                    if (data.success) {
                        currentDiscountPercentage = data.discount_percentage;
                        tierInfo = data.tier_info;
                        discountInput.value = currentDiscountPercentage.toFixed(2);
                        console.log('Applied discount:', currentDiscountPercentage + '%');
                    } else {
                        currentDiscountPercentage = 0;
                        tierInfo = 'لا يوجد خصم';
                        discountInput.value = '0.00';
                        console.log('No discount applicable');
                    }
                } catch (error) {
                    console.error('Error fetching discount:', error);
                    currentDiscountPercentage = 0;
                    tierInfo = 'خطأ في تحميل الخصم';
                    discountInput.value = '0.00';
                }
            } else {
                currentDiscountPercentage = 0;
                tierInfo = subtotal > 0 ? 'يرجى اختيار عميل' : '';
                discountInput.value = '0.00';
                console.log('Skipping discount - Customer Type ID:', selectedCustomerTypeId, 'Subtotal:', subtotal);
            }
            
            // Update tier info display
            const discountTierRow = document.getElementById('discountTierRow');
            const discountTierInfo = document.getElementById('discountTierInfo');
            if (subtotal > 0 && tierInfo) {
                discountTierInfo.textContent = tierInfo;
                discountTierRow.classList.remove('hidden');
            } else {
                discountTierRow.classList.add('hidden');
            }
        } else {
            currentDiscountPercentage = parseFloat(discountInput.value) || 0;
            // Hide tier info when manually editing discount
            const discountTierRow = document.getElementById('discountTierRow');
            if (discountTierRow) {
                discountTierRow.classList.add('hidden');
            }
        }

        const automaticDiscountAmount = (subtotal * (currentDiscountPercentage / 100));

        const automaticDiscountRow = document.getElementById('automaticDiscountRow');
        // Show discount row only if discount > 0 and customer type is NOT 'عميل'
        if (currentDiscountPercentage > 0 && selectedCustomerType !== 'عميل' && subtotal > 0) {
            document.getElementById('automaticDiscountAmount').textContent = automaticDiscountAmount.toFixed(2);
            automaticDiscountRow.classList.remove('hidden');
        } else {
            automaticDiscountRow.classList.add('hidden');
        }
        
        let couponDiscountAmount = 0;
        if (appliedCoupon && subtotal > 0) {
            if (appliedCoupon.type === 'percentage' || appliedCoupon.type === 'percent') {
                couponDiscountAmount = subtotal * (appliedCoupon.value / 100);
                if (appliedCoupon.max_discount && couponDiscountAmount > appliedCoupon.max_discount) {
                    couponDiscountAmount = appliedCoupon.max_discount;
                }
            } else {
                couponDiscountAmount = Math.min(appliedCoupon.value, subtotal);
            }
            appliedCoupon.discount_amount = couponDiscountAmount;
            document.getElementById('discountDisplay').textContent = couponDiscountAmount.toFixed(2);
            document.getElementById('discountRow').classList.remove('hidden');
        } else {
            document.getElementById('discountRow').classList.add('hidden');
        }
        
        const totalAfterDiscount = subtotal - automaticDiscountAmount - couponDiscountAmount;
        const shippingCost = parseFloat(document.getElementById('shippingCost')?.value) || 0;
        const finalAmount = totalAfterDiscount + shippingCost;
        
        const totalQuantityEl = document.getElementById('totalQuantity');
        const subtotalAmountEl = document.getElementById('subtotalAmount');
        const totalAmountEl = document.getElementById('totalAmount');
        const finalTotalEl = document.getElementById('finalTotal');
        
        if (totalQuantityEl) totalQuantityEl.textContent = totalQuantity;
        if (subtotalAmountEl) subtotalAmountEl.textContent = subtotal.toFixed(2);
        if (totalAmountEl) totalAmountEl.textContent = totalAfterDiscount.toFixed(2);
        if (finalTotalEl) finalTotalEl.textContent = finalAmount.toFixed(2);
    }
    
    // Attach listeners
    const shippingCostEl = document.getElementById('shippingCost');
    if (shippingCostEl) {
        shippingCostEl.addEventListener('input', updateTotals);
    }

    document.getElementById('applyCouponBtn').addEventListener('click', function() {
        const couponCode = document.getElementById('coupon_code').value.trim().toUpperCase();
        const subtotal = parseFloat(document.getElementById('subtotalAmount').textContent) || 0;
        if (!couponCode) return showCouponMessage('يرجى إدخال كود الكوبون', 'error');
        if (subtotal === 0) return showCouponMessage('يرجى إضافة سلال أولاً', 'error');
        
        // This validation should be done via an AJAX call to a PHP script for security
        // For now, this is a placeholder. Replace 'validate_coupon.php' with your endpoint.
        fetch('validate_coupon.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `coupon_code=${encodeURIComponent(couponCode)}&subtotal=${subtotal}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                appliedCoupon = data.coupon;
                document.getElementById('coupon_code').value = data.coupon.code;
                showCouponDetails(data.coupon);
                updateTotals();
                showCouponMessage(data.message, 'success');
            } else {
                showCouponMessage(data.message, 'error');
                appliedCoupon = null;
                updateTotals();
            }
        })
        .catch(() => showCouponMessage('حدث خطأ في التحقق من الكوبون', 'error'));
    });

    document.getElementById('removeCouponBtn').addEventListener('click', function() {
        appliedCoupon = null;
        document.getElementById('coupon_code').value = '';
        document.getElementById('couponDetails').classList.add('hidden');
        document.getElementById('couponMessage').classList.add('hidden');
        updateTotals();
    });

    function showCouponMessage(message, type) {
        const messageDiv = document.getElementById('couponMessage');
        messageDiv.textContent = message;
        messageDiv.className = `mt-2 text-sm p-2 rounded-lg ${type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}`;
    }

    function showCouponDetails(coupon) {
        document.getElementById('appliedCouponCode').textContent = coupon.code;
        updateTotals(); // Recalculate to get discount amount
        let info = (coupon.type === 'percentage') ? `خصم ${coupon.value}% (توفير ${appliedCoupon.discount_amount.toFixed(2)} ريال)` : `خصم ${coupon.value} ريال`;
        document.getElementById('couponInfo').textContent = info;
        document.getElementById('couponDetails').classList.remove('hidden');
    }

    document.querySelectorAll('.notification-option').forEach(option => {
        option.addEventListener('click', function(e) {
            if (e.target.tagName !== 'INPUT') {
                const checkbox = this.querySelector('input[type="checkbox"]');
                if (!checkbox.disabled) {
                    checkbox.checked = !checkbox.checked;
                    this.classList.toggle('selected', checkbox.checked);
                }
            } else {
                 this.classList.toggle('selected', e.target.checked);
            }
        });
    });
    
    document.addEventListener('DOMContentLoaded', function() {
        const discountInput = document.getElementById('automaticDiscountPercentage');
        if (discountInput) {
            discountInput.addEventListener('focus', () => { isDiscountInputFocused = true; });
            discountInput.addEventListener('blur', () => { isDiscountInputFocused = false; });
            discountInput.addEventListener('input', updateTotals);
        }
        // Don't call addNewItem() - we're using simple inputs now
        updateTotals();
    });
    
    function previewImages(input) {
        const container = document.getElementById('imagePreviewContainer');
        container.innerHTML = '';
        if (input.files && input.files.length > 0) {
            container.classList.remove('hidden');
            for (const file of input.files) {
                if (file.size > 5 * 1024 * 1024 || !file.type.startsWith('image/')) continue;
                const reader = new FileReader();
                reader.onload = e => {
                    container.innerHTML += `<div class="relative group"><img src="${e.target.result}" class="w-full h-24 sm:h-32 object-cover rounded-lg border"></div>`;
                };
                reader.readAsDataURL(file);
            }
        } else {
            container.classList.add('hidden');
        }
    }
</script>

<script>
// PORTAL PATTERN FIX - Moves customer search dropdown to body to avoid z-index issues
(function() {
    'use strict';
    document.addEventListener('DOMContentLoaded', function() {
        const customerSearch = document.getElementById('customerSearch');
        const searchResults = document.getElementById('searchResults');
        if (!customerSearch || !searchResults) return;
        
        document.body.appendChild(searchResults);
        
        function positionDropdown() {
            if (searchResults.classList.contains('hidden')) return;
            const rect = customerSearch.getBoundingClientRect();
            const isRTL = document.dir === 'rtl';
            
            searchResults.style.position = 'fixed';
            searchResults.style.top = (rect.bottom + 4) + 'px';
            searchResults.style.width = rect.width + 'px';
            
            if (isRTL) {
                searchResults.style.right = (window.innerWidth - rect.right) + 'px';
                searchResults.style.left = 'auto';
            } else {
                searchResults.style.left = rect.left + 'px';
                searchResults.style.right = 'auto';
            }
        }
        
        const observer = new MutationObserver(positionDropdown);
        observer.observe(searchResults, { childList: true, subtree: true });

        const show = () => { searchResults.classList.remove('hidden'); positionDropdown(); };
        const hide = () => searchResults.classList.add('hidden');

        customerSearch.addEventListener('focus', show);
        customerSearch.addEventListener('input', show);
        
        window.addEventListener('scroll', positionDropdown, true);
        window.addEventListener('resize', positionDropdown);
        
        document.addEventListener('click', e => {
            if (!customerSearch.contains(e.target)) hide();
        });

        customerSearch.addEventListener('keydown', e => { if (e.key === 'Escape') hide(); });

        const originalSelectCustomer = window.selectCustomer;
        window.selectCustomer = function() {
            if (typeof originalSelectCustomer === 'function') {
                originalSelectCustomer.apply(this, arguments);
            }
            setTimeout(hide, 100);
        };
    });
})();
</script>

<script>
// Debug form submission
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('orderForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            console.log('=== ORDER FORM SUBMISSION DEBUG ===');
            
            // Get form data
            const formData = new FormData(form);
            
            // Log customer info
            console.log('Customer ID:', formData.get('customer_id'));
            
            // Log items
            const items = [];
            let itemIndex = 0;
            while (formData.get(`items[${itemIndex}][name]`)) {
                items.push({
                    name: formData.get(`items[${itemIndex}][name]`),
                    quantity: formData.get(`items[${itemIndex}][quantity]`),
                    price: formData.get(`items[${itemIndex}][price]`),
                    total: formData.get(`items[${itemIndex}][total]`)
                });
                itemIndex++;
            }
            console.log('Items:', items);
            console.log('Total items:', items.length);
            
            // Calculate totals for logging
            let subtotal = 0;
            items.forEach(item => {
                subtotal += parseFloat(item.total) || 0;
            });
            
            const automaticDiscountPct = parseFloat(formData.get('automatic_discount_percentage')) || 0;
            const automaticDiscountAmount = subtotal * (automaticDiscountPct / 100);
            const shippingCost = parseFloat(formData.get('shipping_cost')) || 0;
            
            console.log('--- DISCOUNT CALCULATIONS ---');
            console.log('Subtotal:', subtotal.toFixed(2), 'SAR');
            console.log('Automatic Discount %:', automaticDiscountPct.toFixed(2) + '%');
            console.log('Automatic Discount Amount:', automaticDiscountAmount.toFixed(2), 'SAR');
            console.log('Coupon Code:', formData.get('coupon_code') || 'None');
            console.log('Shipping Cost:', shippingCost.toFixed(2), 'SAR');
            
            const totalAfterDiscount = subtotal - automaticDiscountAmount;
            const finalAmount = totalAfterDiscount + shippingCost;
            
            console.log('--- FINAL AMOUNTS ---');
            console.log('Total after Discount:', totalAfterDiscount.toFixed(2), 'SAR');
            console.log('Final Amount:', finalAmount.toFixed(2), 'SAR');
            console.log('Expected in DB:');
            console.log('  subtotal_amount:', subtotal.toFixed(2));
            console.log('  discount_amount:', automaticDiscountAmount.toFixed(2));
            console.log('  automatic_discount_percentage:', automaticDiscountPct.toFixed(2));
            console.log('  automatic_discount_amount:', automaticDiscountAmount.toFixed(2));
            console.log('  final_amount:', finalAmount.toFixed(2));
            
            // Check for validation errors
            if (!formData.get('customer_id')) {
                console.error('ERROR: No customer selected!');
            }
            if (items.length === 0) {
                console.error('ERROR: No items added!');
            }
            
            console.log('=== END DEBUG ===');
        });
    }
});
</script>

<?php include '../../includes/footer.php'; ?>