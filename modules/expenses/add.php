<?php
session_start();

// Redirect to login if user is not authenticated
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Include necessary files
require_once '../../config/database.php';
require_once '../../includes/accounting_functions.php';

// Page setup
$page_title = 'إضافة مصروف جديد';
$error_message = '';
$success_message = '';

// Fetch active expense categories for the dropdown
$categories = $db->query("SELECT * FROM expense_categories WHERE is_active = 1 ORDER BY category_name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all active bank accounts for use in the bank transfer dropdown
try {
    $bank_accounts_stmt = $db->query("SELECT id, bank_name, account_number, current_balance, account_id FROM bank_accounts WHERE is_active = 1 ORDER BY bank_name");
    $bank_accounts = $bank_accounts_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If fetching fails, initialize as an empty array to prevent errors
    $bank_accounts = [];
    error_log("Database error fetching bank accounts: " . $e->getMessage());
}

// ===================================================================
// START: REPLACEMENT LOGIC TO CALCULATE TOTAL CASH BALANCE FROM TRANSACTIONS
// ===================================================================
try {
    // Step 1: Calculate total cash received from customer payments.
    $cash_in_stmt = $db->query("
        SELECT SUM(amount) as total_in 
        FROM customer_payments 
        WHERE payment_method = 'cash'
    ");
    $total_cash_in = $cash_in_stmt->fetch(PDO::FETCH_ASSOC)['total_in'] ?? 0;

    // Step 2: Calculate total cash paid out for approved expenses.
    $cash_out_stmt = $db->query("
        SELECT SUM(amount) as total_out 
        FROM expenses 
        WHERE payment_method = 'cash' AND status = 'approved'
    ");
    $total_cash_out = $cash_out_stmt->fetch(PDO::FETCH_ASSOC)['total_out'] ?? 0;
    
    // Step 3: Calculate the final cash balance.
    $cash_balance = $total_cash_in - $total_cash_out;

} catch (PDOException $e) {
    // In case of a database error during calculation, default to 0 and log the error.
    $cash_balance = 0;
    $error_message = "Could not calculate cash balance. Error: " . $e->getMessage();
    error_log("Database error dynamically calculating cash balance: " . $e->getMessage());
}
// ===================================================================
// END: REPLACEMENT LOGIC
// ===================================================================

// --- Orders with credit are now fetched via AJAX search (ajax_search_orders.php) ---
// No need to load all 587+ orders upfront.


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // --- Step 1: Sanitize and capture all form data ---
    $expense_date = !empty($_POST['expense_date']) ? $_POST['expense_date'] : date('Y-m-d');
    $category_id = $_POST['category_id'] ?? '';
    $amount = floatval($_POST['amount'] ?? 0);
    $currency = $_POST['currency'] ?? 'YER';
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $bank_account_id = ($payment_method === 'bank_transfer' && !empty($_POST['bank_account_id'])) ? intval($_POST['bank_account_id']) : null;
    $description = trim($_POST['description'] ?? '');
    $vendor_name = trim($_POST['vendor_name'] ?? '');
    $is_customer_refund = isset($_POST['is_customer_refund']) && $_POST['is_customer_refund'] == '1';
    
    // --- NEW: Capture specific order and customer for refund ---
    $refund_order_id = !empty($_POST['refund_order_id']) ? intval($_POST['refund_order_id']) : null;
    $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null; // From hidden input

    // --- Step 2: Perform basic validation ---
    if (empty($category_id)) {
        $error_message = 'يرجى اختيار فئة المصروف';
    } elseif ($amount <= 0) {
        $error_message = 'يرجى إدخال مبلغ صحيح وموجب';
    } elseif ($payment_method === 'bank_transfer' && empty($bank_account_id)) {
        $error_message = 'يرجى اختيار الحساب البنكي عند استخدام طريقة الدفع "تحويل بنكي"';
    } elseif ($is_customer_refund && empty($refund_order_id)) {
        $error_message = 'يرجى اختيار الطلب المراد رد المبلغ له';
    } else {
        try {
            // ===================================================================
            // START: BALANCE VALIDATION LOGIC
            // ===================================================================
            $account_name_for_error = '';
            if ($payment_method === 'cash') {
                $account_name_for_error = "الصندوق";
                if ($amount > $cash_balance) {
                     throw new Exception("الرصيد غير كافٍ في حساب {$account_name_for_error}. الرصيد الحالي: " . number_format($cash_balance, 2) . " والمبلغ المطلوب: " . number_format($amount, 2));
                }
            } else if ($payment_method === 'bank_transfer' && $bank_account_id) {
                $account_to_check_balance = null;
                foreach ($bank_accounts as $acc) {
                    if ($acc['id'] == $bank_account_id) {
                        $account_to_check_balance = $acc;
                        $account_name_for_error = "البنك ({$acc['bank_name']})";
                        break;
                    }
                }
                if ($account_to_check_balance) {
                    $current_balance = floatval($account_to_check_balance['current_balance']);
                    if ($amount > $current_balance) {
                        throw new Exception("الرصيد غير كافٍ في حساب {$account_name_for_error}. الرصيد الحالي: " . number_format($current_balance, 2) . " والمبلغ المطلوب: " . number_format($amount, 2));
                    }
                } else {
                    throw new Exception("لم يتم العثور على حساب الدفع المحدد للتحقق من الرصيد.");
                }
            }
            // ===================================================================
            // END: BALANCE VALIDATION LOGIC
            // ===================================================================

            $db->beginTransaction();

            // --- CRITICAL FIX/IMPROVEMENT: Override vendor_name for consistent refund description ---
            if ($is_customer_refund && $refund_order_id && $customer_id) {
                 $cust_stmt = $db->prepare("SELECT name, customer_code FROM customers WHERE id = ?");
                 $cust_stmt->execute([$customer_id]);
                 $customer_data = $cust_stmt->fetch(PDO::FETCH_ASSOC);
                 $customer_name_for_vendor = $customer_data['name'] ?? 'N/A';
                 $customer_code = $customer_data['customer_code'] ?? 'N/A';
                 
                 // Fetch order number for better description
                 $ord_stmt = $db->prepare("SELECT order_number FROM customer_orders WHERE id = ?");
                 $ord_stmt->execute([$refund_order_id]);
                 $order_number = $ord_stmt->fetchColumn() ?? $refund_order_id;

                 // Set descriptive vendor name
                 $vendor_name = "رد مبلغ/تسوية للعميل - " . $customer_name_for_vendor . " (#" . $customer_code . ")";
                 
                 // Set a default description if the user left it blank
                 if(empty($description)) {
                     $description = "رد مبلغ/تسوية رصيد للعميل " . $customer_name_for_vendor . " للطلب رقم " . $order_number;
                 }
            }
            // --- END CRITICAL FIX/IMPROVEMENT ---

            // --- Step 3: Insert the new expense record with 'pending' status ---
            $expense_number = 'EXP-' . date('ymd') . '-' . rand(100, 999);
            $stmt = $db->prepare("
                INSERT INTO expenses (
                    expense_number, expense_date, category_id, description,
                    amount, currency, payment_method, bank_account_id, vendor_name, customer_id,
                    status, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved', ?, NOW())
            ");
            // NOTE: Status is set to 'approved' by default in the original code. Using 'approved' here.
            $stmt->execute([
                $expense_number, $expense_date, $category_id, $description, $amount, $currency,
                $payment_method, $bank_account_id, $vendor_name, $customer_id, $_SESSION['user_id']
            ]);
            $expense_id = $db->lastInsertId();

            // --- Step 4: Handle customer refund logic by targeting a specific order ---
            if ($is_customer_refund && $refund_order_id && $customer_id && $amount > 0) {
                // First, verify the selected order actually has enough credit balance
                $order_stmt = $db->prepare("
                    SELECT (paid_amount - final_amount) AS credit_amount
                    FROM customer_orders
                    WHERE id = ? AND customer_id = ?
                ");
                $order_stmt->execute([$refund_order_id, $customer_id]);
                $order_credit = $order_stmt->fetch(PDO::FETCH_ASSOC);

                if (!$order_credit || !isset($order_credit['credit_amount'])) {
                    throw new Exception("الطلب المحدد للاسترداد غير صالح.");
                }

                $credit_amount_on_order = floatval($order_credit['credit_amount']);

                // Check if the refund amount is more than what is owed on this specific order
                if ($amount > $credit_amount_on_order) {
                    throw new Exception("مبلغ الاسترداد (" . number_format($amount) . ") أكبر من المبلغ المستحق على هذا الطلب (" . number_format($credit_amount_on_order, 2) . ").");
                }

                // Apply the refund to the specific order by reducing its `paid_amount`
                // This brings the order's balance closer to zero from a negative (credit) state.
                $db->prepare("UPDATE customer_orders SET paid_amount = paid_amount - ? WHERE id = ?")
                   ->execute([$amount, $refund_order_id]);
            }


            // --- Step 5: Update the corresponding account balance ---
            // The original logic here seems to assume the expense is immediately approved and affects the balance,
            // which aligns with the 'approved' status in the insert.
            if ($payment_method === 'bank_transfer' && $bank_account_id) {
                $db->prepare("UPDATE bank_accounts SET current_balance = current_balance - ? WHERE id = ?")
                  ->execute([$amount, $bank_account_id]);
            } elseif ($payment_method === 'cash') {
                // NOTE: This relies on a 'الصندوق' bank_account existing.
                // The dynamic cash balance calculation is already more robust.
                $db->prepare("UPDATE bank_accounts SET current_balance = current_balance - ? WHERE bank_name = 'الصندوق'")
                  ->execute([$amount]);
            }

            // --- Step 6: Create the corresponding accounting journal entry ---
            try {
                if ($is_customer_refund) {
                    // For a refund, we debit the A/R account (which has a credit balance) or a dedicated Refund/Suspense account.
                    // Using A/P as a placeholder for a liability/customer credit.
                    $debit_account_id = get_accounting_setting($db, 'default_accounts_receivable_id'); 
                } else {
                    $cat_stmt = $db->prepare("SELECT account_id FROM expense_categories WHERE id = ?");
                    $cat_stmt->execute([$category_id]);
                    $debit_account_id = $cat_stmt->fetchColumn();
                }

                if ($payment_method === 'bank_transfer' && $bank_account_id) {
                    $bank_stmt = $db->prepare("SELECT account_id FROM bank_accounts WHERE id = ?");
                    $bank_stmt->execute([$bank_account_id]); // Corrected: execute expects an array
                    $credit_account_id = $bank_stmt->fetchColumn();
                } else {
                    $credit_account_id = get_accounting_setting($db, 'default_cash_account_id');
                }

                $final_description = "مصروف: " . $description;
                if ($is_customer_refund && $customer_id) {
                     $cust_stmt = $db->prepare("SELECT name FROM customers WHERE id = ?");
                     $cust_stmt->execute([$customer_id]);
                     $customer_name = $cust_stmt->fetchColumn();
                     $final_description = "رد مبلغ/تسوية رصيد للعميل: " . $customer_name;
                }

                // Fallback check for missing account ID
                if (!$debit_account_id || !$credit_account_id) {
                    throw new Exception("فشل في تحديد الحسابات المحاسبية اللازمة (مدين: " . ($debit_account_id ?? 'N/A') . ", دائن: " . ($credit_account_id ?? 'N/A') . ")");
                }

                $entry_items = [
                    ['account_id' => $debit_account_id, 'type' => 'debit', 'amount' => $amount],
                    ['account_id' => $credit_account_id, 'type' => 'credit', 'amount' => $amount],
                ];

                create_journal_entry(
                    $db, $expense_date, $final_description, $entry_items,
                    'expenses', $expense_id, $_SESSION['user_id']
                );

            } catch (Exception $acc_e) {
                error_log("Accounting entry failed for Expense ID $expense_id: " . $acc_e->getMessage());
            }

            $db->commit();
            $success_message = 'تم إضافة المصروف بنجاح';
            if ($is_customer_refund && $customer_id) {
                $success_message .= ' وتم تحديث رصيد الطلب للعميل';
            }
            
            // Re-calculate or update cash balance display for immediate feedback
            if ($payment_method === 'cash') {
                $cash_balance -= $amount;
            }
            // Clear POST data to prevent resubmission
            $_POST = [];


        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error_message = 'حدث خطأ: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-8" dir="rtl">
    <div class="max-w-3xl mx-auto px-4">

        <!-- Page Header -->
        <div class="mb-8 flex justify-between items-center">
            <div>
                <h1 class="text-3xl font-bold text-gray-800">إضافة مصروف جديد</h1>
                <p class="text-gray-600 mt-2">تسجيل عملية صرف جديدة في النظام</p>
            </div>
            <a href="index.php" class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-bold transition-colors">
                <i class="fas fa-arrow-right ml-2"></i>
                عودة
            </a>
        </div>
        
        <!-- CASH BALANCE DISPLAY WIDGET -->
        <div class="bg-white rounded-xl shadow-lg p-4 mb-6 flex justify-between items-center border-r-4 border-teal-500">
            <div class="flex items-center">
                 <div class="bg-teal-100 p-3 rounded-full mr-4 ml-2">
                    <i class="fas fa-wallet fa-2x text-teal-600"></i>
                </div>
                <div>
                    <h3 class="font-bold text-gray-800 text-lg">رصيد الصندوق الحالي (محسوب)</h3>
                    <p class="text-gray-600 text-sm">المبلغ النقدي المتاح للصرف</p>
                </div>
            </div>
            <div class="text-left">
                <p class="text-2xl font-bold text-teal-700">
                    <?php echo number_format($cash_balance, 2); ?>
                </p>
                 <span class="text-xs text-gray-500">ريال يمني</span>
            </div>
        </div>

        <!-- Display Error/Success Messages -->
        <?php if ($error_message): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
                <strong class="font-bold">خطأ!</strong>
                <span class="block sm:inline"><?php echo $error_message; ?></span>
            </div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
                <strong class="font-bold">نجاح!</strong>
                <span class="block sm:inline"><?php echo $success_message; ?></span>
            </div>
        <?php endif; ?>

        <!-- Expense Form Card -->
        <div class="bg-white rounded-xl shadow-lg overflow-hidden">
            <div class="p-8">
                <form method="POST" action="">

                    <!-- Row 1: Date & Category -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">تاريخ المصروف <span class="text-red-500">*</span></label>
                            <input type="date" name="expense_date" value="<?php echo htmlspecialchars($_POST['expense_date'] ?? date('Y-m-d')); ?>" required
                                   class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">الفئة <span class="text-red-500">*</span></label>
                            <select name="category_id" id="category_id" required
                                    class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition-all">
                                <option value="">اختر الفئة...</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo $cat['id']; ?>" data-code="<?php echo htmlspecialchars($cat['category_code'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($cat['category_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <!-- Row 2: Amount, Currency, Payment Method -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">المبلغ <span class="text-red-500">*</span></label>
                            <input type="number" name="amount" step="0.01" min="0" required placeholder="0.00"
                                   class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">العملة <span class="text-red-500">*</span></label>
                            <select name="currency" required class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition-all">
                                <option value="YER" selected>ريال يمني (YER)</option>
                                <option value="USD">دولار أمريكي (USD)</option>
                                <option value="SAR">ريال سعودي (SAR)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-gray-700 mb-2">طريقة الدفع</label>
                            <select name="payment_method" id="payment_method" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition-all">
                                <option value="cash">نقدي</option>
                                <option value="bank_transfer">تحويل بنكي</option>
                            </select>
                        </div>
                    </div>

                    <!-- Bank Account Dropdown (Conditional) -->
                    <div class="mb-6" id="bank-account-wrapper" style="display:none;">
                        <label class="block text-sm font-bold text-gray-700 mb-2">الحساب البنكي المستخدم <span class="text-red-500">*</span></label>
                        <select name="bank_account_id" id="bank_account_id" class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition-all">
                            <option value="">اختر الحساب البنكي...</option>
                            <?php foreach ($bank_accounts as $acc): ?>
                                <?php if ($acc['bank_name'] !== 'الصندوق'): ?>
                                <option value="<?php echo $acc['id']; ?>">
                                    <?php echo htmlspecialchars($acc['bank_name'] . ' - ' . $acc['account_number'] . ' (الرصيد: ' . number_format($acc['current_balance'], 2) . ')'); ?>
                                </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Order Selection for Refund (Conditional) — searchable -->
                    <div class="mb-6 p-4 bg-amber-50 border border-amber-200 rounded-lg" id="customer-refund-wrapper" style="display:none;">
                        <input type="hidden" name="is_customer_refund" id="is_customer_refund" value="0">
                        <input type="hidden" name="customer_id" id="refund_customer_id" value="">
                        <input type="hidden" name="refund_order_id" id="refund_order_id" value="">

                        <label class="block text-sm font-bold text-amber-800 mb-2">
                            <i class="fas fa-search ml-1"></i>
                            ابحث عن الطلب باسم العميل أو رقم الطلب <span class="text-red-500">*</span>
                        </label>

                        <!-- Search input -->
                        <div class="relative" id="order-search-container">
                            <input type="text" id="order_search_input"
                                   placeholder="اكتب اسم العميل أو رقم الطلب..."
                                   autocomplete="off"
                                   class="w-full px-4 py-3 pr-10 rounded-lg border border-amber-300 bg-white focus:border-amber-500 focus:ring-2 focus:ring-amber-200 outline-none transition-all">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-amber-400" id="order-search-icon">
                                <i class="fas fa-search"></i>
                            </span>
                            <!-- Loading spinner (hidden by default) -->
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-amber-500 hidden" id="order-search-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                            </span>

                            <!-- Results dropdown -->
                            <div id="order-search-results"
                                 class="absolute z-50 w-full mt-1 bg-white border border-amber-200 rounded-lg shadow-xl max-h-64 overflow-y-auto hidden"
                                 style="direction:rtl;">
                            </div>
                        </div>

                        <!-- Selected order display -->
                        <div id="selected-order-display" class="hidden mt-3 p-3 bg-white border border-green-300 rounded-lg flex items-center justify-between">
                            <div>
                                <span class="text-sm font-bold text-green-800" id="selected-order-text"></span>
                            </div>
                            <button type="button" id="clear-selected-order"
                                    class="text-red-400 hover:text-red-600 transition-colors text-sm px-2 py-1 rounded hover:bg-red-50">
                                <i class="fas fa-times"></i> تغيير
                            </button>
                        </div>

                        <p class="text-xs text-amber-700 mt-2">
                            <i class="fas fa-info-circle"></i>
                            ابحث باسم العميل أو رقم الطلب — يعرض فقط الطلبات التي المبلغ المدفوع فيها أكبر من المبلغ النهائي.
                        </p>
                    </div>
                    
                    <!-- Vendor Name (Conditional) -->
                    <div class="mb-6" id="vendor-name-wrapper">
                        <label class="block text-sm font-bold text-gray-700 mb-2">اسم المستفيد / المورد</label>
                        <input type="text" name="vendor_name" id="vendor_name" placeholder="اسم الشخص أو الجهة المستفيدة"
                               class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition-all">
                    </div>

                    <!-- Description Textarea -->
                    <div class="mb-8">
                        <label class="block text-sm font-bold text-gray-700 mb-2">التفاصيل / الملاحظات</label>
                        <textarea name="description" rows="4" placeholder="أدخل تفاصيل المصروف..."
                                  class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:border-blue-500 focus:ring-2 focus:ring-blue-200 outline-none transition-all"></textarea>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end pt-4 border-t">
                        <button type="submit" class="bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 text-white px-8 py-3 rounded-lg font-bold shadow-lg transform hover:-translate-y-0.5 transition-all duration-200">
                            <i class="fas fa-save ml-2"></i>
                            حفظ المصروف
                        </button>
                    </div>

                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    // Get references to form elements
    const paymentSelect = document.getElementById('payment_method');
    const bankWrapper = document.getElementById('bank-account-wrapper');
    const bankSelect = document.getElementById('bank_account_id');
    const categorySelect = document.getElementById('category_id');
    const customerRefundWrapper = document.getElementById('customer-refund-wrapper');
    const vendorNameWrapper = document.getElementById('vendor-name-wrapper');
    const vendorNameInput = document.getElementById('vendor_name');
    const amountInput = document.querySelector('input[name="amount"]');
    const isCustomerRefundInput = document.getElementById('is_customer_refund');
    const refundCustomerIdInput = document.getElementById('refund_customer_id');
    const refundOrderIdInput = document.getElementById('refund_order_id');

    // Search elements
    const orderSearchInput = document.getElementById('order_search_input');
    const orderSearchResults = document.getElementById('order-search-results');
    const orderSearchIcon = document.getElementById('order-search-icon');
    const orderSearchSpinner = document.getElementById('order-search-spinner');
    const selectedOrderDisplay = document.getElementById('selected-order-display');
    const selectedOrderText = document.getElementById('selected-order-text');
    const clearSelectedOrder = document.getElementById('clear-selected-order');

    let searchTimeout = null;

    // ─── Bank field toggle ───────────────────────────────────────────────
    function toggleBankField() {
        if (paymentSelect.value === 'bank_transfer') {
            bankWrapper.style.display = 'block';
            bankSelect.required = true;
        } else {
            bankWrapper.style.display = 'none';
            bankSelect.required = false;
        }
    }
    paymentSelect.addEventListener('change', toggleBankField);
    toggleBankField();

    // ─── Category toggle (show/hide refund section) ─────────────────────
    function toggleRefundField() {
        const selectedOption = categorySelect.options[categorySelect.selectedIndex];
        const categoryCode = selectedOption ? selectedOption.getAttribute('data-code') : '';
        const categoryName = selectedOption ? selectedOption.text.toLowerCase() : '';
        const isRefundCategory = (categoryCode && (categoryCode.toUpperCase() === 'DAMAGED' || categoryCode.toUpperCase() === 'REFUND')) ||
                                 categoryName.includes('تالف') ||
                                 categoryName.includes('توالف') ||
                                 categoryName.includes('استرداد') ||
                                 categoryName.includes('رد');

        if (isRefundCategory) {
            customerRefundWrapper.style.display = 'block';
            vendorNameWrapper.style.display = 'none';
            vendorNameInput.required = false;
            isCustomerRefundInput.value = '1';
        } else {
            customerRefundWrapper.style.display = 'none';
            vendorNameWrapper.style.display = 'block';
            vendorNameInput.required = false;
            isCustomerRefundInput.value = '0';

            // Clear refund-specific fields
            clearOrderSelection();
            vendorNameInput.value = '';
            if (amountInput) amountInput.value = '';
        }
    }
    categorySelect.addEventListener('change', toggleRefundField);
    toggleRefundField();

    // ─── Order search via AJAX ──────────────────────────────────────────
    if (orderSearchInput) {
        orderSearchInput.addEventListener('input', function () {
            const query = this.value.trim();
            clearTimeout(searchTimeout);

            if (query.length < 1) {
                orderSearchResults.classList.add('hidden');
                orderSearchResults.innerHTML = '';
                return;
            }

            // Debounce 300ms
            searchTimeout = setTimeout(() => {
                performSearch(query);
            }, 300);
        });

        // Close results when clicking outside
        document.addEventListener('click', function (e) {
            if (!e.target.closest('#order-search-container')) {
                orderSearchResults.classList.add('hidden');
            }
        });

        // Re-open results on focus if there's text
        orderSearchInput.addEventListener('focus', function () {
            if (this.value.trim().length >= 1 && orderSearchResults.innerHTML.trim()) {
                orderSearchResults.classList.remove('hidden');
            }
        });
    }

    function performSearch(query) {
        // Show spinner, hide icon
        orderSearchIcon.classList.add('hidden');
        orderSearchSpinner.classList.remove('hidden');

        fetch('ajax_search_orders.php?q=' + encodeURIComponent(query))
            .then(res => res.json())
            .then(data => {
                orderSearchSpinner.classList.add('hidden');
                orderSearchIcon.classList.remove('hidden');
                renderResults(data);
            })
            .catch(err => {
                console.error('Search error:', err);
                orderSearchSpinner.classList.add('hidden');
                orderSearchIcon.classList.remove('hidden');
                orderSearchResults.innerHTML = '<div class="px-4 py-3 text-red-500 text-sm text-center">حدث خطأ في البحث</div>';
                orderSearchResults.classList.remove('hidden');
            });
    }

    function renderResults(data) {
        orderSearchResults.innerHTML = '';

        if (!data || data.length === 0) {
            orderSearchResults.innerHTML =
                '<div class="px-4 py-3 text-gray-400 text-sm text-center">' +
                '<i class="fas fa-inbox ml-1"></i> لا توجد نتائج مطابقة' +
                '</div>';
            orderSearchResults.classList.remove('hidden');
            return;
        }

        data.forEach(function (order) {
            const item = document.createElement('div');
            item.className = 'px-4 py-3 hover:bg-amber-50 cursor-pointer border-b border-gray-100 transition-colors';
            item.innerHTML =
                '<div class="flex justify-between items-center">' +
                '  <div>' +
                '    <span class="font-bold text-gray-800 text-sm">' + escapeHtml(order.customer_name) + '</span>' +
                (order.customer_code ? ' <span class="text-xs text-gray-400">(' + escapeHtml(order.customer_code) + ')</span>' : '') +
                '    <div class="text-xs text-gray-500 mt-0.5">طلب رقم: <span class="font-mono font-bold">' + escapeHtml(order.order_number) + '</span></div>' +
                '  </div>' +
                '  <div class="text-left">' +
                '    <span class="inline-block bg-green-100 text-green-800 text-xs font-bold px-2 py-1 rounded">' +
                      Number(order.credit_amount).toLocaleString('en') + ' ريال' +
                '    </span>' +
                '  </div>' +
                '</div>';

            item.addEventListener('click', function () {
                selectOrder(order);
            });

            orderSearchResults.appendChild(item);
        });

        orderSearchResults.classList.remove('hidden');
    }

    function selectOrder(order) {
        // Fill hidden fields
        refundOrderIdInput.value = order.order_id;
        refundCustomerIdInput.value = order.customer_id;

        // Fill amount
        if (amountInput) {
            amountInput.value = parseFloat(order.credit_amount).toFixed(2);
        }

        // Fill vendor name
        vendorNameInput.value = 'رد مبلغ/تسوية للعميل - ' + order.customer_name;

        // Show selected order display
        selectedOrderText.innerHTML =
            '<i class="fas fa-check-circle text-green-500 ml-1"></i> ' +
            escapeHtml(order.customer_name) +
            ' — طلب ' + escapeHtml(order.order_number) +
            ' — <span class="text-green-600">' + Number(order.credit_amount).toLocaleString('en') + ' ريال</span>';
        selectedOrderDisplay.classList.remove('hidden');

        // Hide search input and results
        orderSearchInput.value = '';
        orderSearchResults.classList.add('hidden');
        orderSearchInput.closest('#order-search-container').style.display = 'none';
    }

    function clearOrderSelection() {
        refundOrderIdInput.value = '';
        refundCustomerIdInput.value = '';
        if (amountInput) amountInput.value = '';
        vendorNameInput.value = '';
        selectedOrderDisplay.classList.add('hidden');
        selectedOrderText.innerHTML = '';
        orderSearchInput.value = '';
        orderSearchResults.classList.add('hidden');
        orderSearchResults.innerHTML = '';

        const container = document.getElementById('order-search-container');
        if (container) container.style.display = '';
    }

    // Clear button
    if (clearSelectedOrder) {
        clearSelectedOrder.addEventListener('click', function () {
            clearOrderSelection();
            orderSearchInput.focus();
        });
    }

    // ─── Form validation ────────────────────────────────────────────────
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (isCustomerRefundInput.value === '1' && !refundOrderIdInput.value) {
                e.preventDefault();
                alert('يرجى اختيار الطلب أولاً بالبحث عن اسم العميل أو رقم الطلب.');
                orderSearchInput.focus();
            }
        });
    }

    // ─── Utility ────────────────────────────────────────────────────────
    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }
});
</script>

<?php 
include '../../includes/footer.php'; 
?>