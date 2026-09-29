<?php
session_start();

// Prevent browser caching to ensure users always get the latest version
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/database.php';

$page_title = 'إضافة دفعة جديدة';
$error_message = '';
$success_message = '';

// Fetch invoice and customer data
$invoice_id = isset($_GET['invoice_id']) ? intval($_GET['invoice_id']) : 0;
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id']) : 0;

if ($invoice_id <= 0 && $customer_id <= 0) {
    header('Location: ../customers/index.php');
    exit();
}

$invoice = null;
$customer = null;
$remaining_amount = 0;
$unpaid_invoices = [];

if ($invoice_id > 0) {
    $stmt = $db->prepare("SELECT ci.*, c.name as customer_name, c.id as customer_id FROM customer_invoices ci JOIN customers c ON ci.customer_id = c.id WHERE ci.id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$invoice) { header('Location: ../customers/index.php'); exit(); }
    $customer_id = $invoice['customer_id'];
    $paid_stmt = $db->prepare("SELECT SUM(amount) FROM customer_payments WHERE invoice_id = ?");
    $paid_stmt->execute([$invoice_id]);
    $total_paid = $paid_stmt->fetchColumn() ?? 0;
    $remaining_amount = $invoice['total_amount'] - $total_paid;
}

if ($customer_id > 0) {
    $stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$customer) { header('Location: ../customers/index.php'); exit(); }
    if ($invoice_id <= 0) {
        $invoices_stmt = $db->prepare("SELECT ci.* FROM customer_invoices ci WHERE ci.customer_id = ? AND ci.status IN ('pending', 'partially_paid') ORDER BY ci.due_date ASC");
        $invoices_stmt->execute([$customer_id]);
        $unpaid_invoices = $invoices_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

try {
    $bank_stmt = $db->query("SELECT id, bank_name, account_number, account_holder_name, currency FROM bank_accounts WHERE is_active = 1 ORDER BY bank_name");
    $bank_accounts = $bank_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $bank_accounts = [];
    $error_message = 'فشل في تحميل الحسابات البنكية. قد تحتاج إلى إنشاء جدول bank_accounts.';
}


// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment_invoice_id = isset($_POST['invoice_id']) ? intval($_POST['invoice_id']) : 0;
    $payment_customer_id = isset($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
    $amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
    $currency = $_POST['currency'] ?? 'SAR';
    $payment_method = $_POST['payment_method'] ?? 'cash';
    $bank_account_id = ($payment_method === 'transfer' && isset($_POST['bank_account_id'])) ? intval($_POST['bank_account_id']) : null;
    $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
    $reference_number = $_POST['reference_number'] ?? '';
    $notes = $_POST['notes'] ?? '';
    
    if ($payment_customer_id <= 0) {
        $error_message = 'يرجى اختيار العميل';
    } elseif ($amount <= 0) {
        $error_message = 'يرجى إدخال مبلغ صحيح';
    } elseif ($payment_method === 'transfer' && empty($bank_account_id)) {
        $error_message = 'يرجى اختيار الحساب البنكي عند استخدام طريقة التحويل البنكي.';
    } else {
        try {
            $db->beginTransaction();

            // ***** START: NEW VALIDATION LOGIC *****
            // Check if payment amount exceeds the remaining balance for a specific invoice
            if ($payment_invoice_id > 0) {
                // 1. Get the invoice's total amount
                $invoice_check_stmt = $db->prepare("SELECT total_amount FROM customer_invoices WHERE id = ?");
                $invoice_check_stmt->execute([$payment_invoice_id]);
                $invoice_total = $invoice_check_stmt->fetchColumn();

                if ($invoice_total === false) {
                    throw new Exception('الفاتورة المحددة غير موجودة.');
                }

                // 2. Calculate the total amount already paid for this invoice
                $paid_check_stmt = $db->prepare("SELECT SUM(amount) FROM customer_payments WHERE invoice_id = ?");
                $paid_check_stmt->execute([$payment_invoice_id]);
                $total_paid = $paid_check_stmt->fetchColumn() ?? 0;

                // 3. Calculate the current remaining amount
                $current_remaining = $invoice_total - $total_paid;

                // 4. Check if the new payment amount exceeds the remaining amount
                // Use a small tolerance (0.001) for floating point comparisons
                // Only validate if remaining amount is positive (to allow payments/adjustments on credit notes)
                if ($current_remaining > 0 && $amount > ($current_remaining + 0.001)) {
                    throw new Exception(
                        'لا يمكن إضافة الدفعة. المبلغ المدفوع (' . number_format($amount, 2) . 
                        ') أكبر من المبلغ المتبقي للفاتورة (' . number_format($current_remaining, 2) . ').'
                    );
                }
            }
            // ***** END: NEW VALIDATION LOGIC *****


            $receipt_path_for_db = null;
            // Handle receipt image upload
            if (isset($_FILES['receipt_image']) && $_FILES['receipt_image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['receipt_image'];
                $upload_dir = '../../uploads/receipts/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0775, true);
                }

                $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];

                if (in_array($file_ext, $allowed_exts) && $file['size'] <= 5000000) { // Max 5MB
                    $new_filename = 'receipt_' . time() . '_' . uniqid() . '.' . $file_ext;
                    $upload_path = $upload_dir . $new_filename;

                    if (move_uploaded_file($file['tmp_name'], $upload_path)) {
                        $receipt_path_for_db = 'uploads/receipts/' . $new_filename;
                    } else {
                        throw new Exception('فشل في رفع صورة الإيصال.');
                    }
                } else {
                    throw new Exception('الملف المرفق غير صالح. يرجى رفع صورة (JPG, PNG) أو ملف PDF بحجم أقصى 5 ميجا.');
                }
            }

            // Generate payment number
            $payment_number = 'PAY-' . date('Ymd') . '-' . uniqid();
            
            // Insert statement with new fields
            $stmt = $db->prepare("
                INSERT INTO customer_payments 
                (payment_number, customer_id, invoice_id, amount, currency, payment_method, bank_account_id, payment_date, reference_number, notes, receipt_image_path, created_by, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $stmt->execute([
                $payment_number,
                $payment_customer_id,
                $payment_invoice_id > 0 ? $payment_invoice_id : null,
                $amount,
                $currency,
                $payment_method,
                $bank_account_id,
                $payment_date,
                $reference_number,
                $notes,
                $receipt_path_for_db,
                $_SESSION['user_id']
            ]);
            
            $payment_id = $db->lastInsertId();
            
            // Update invoice status logic
            if ($payment_invoice_id > 0) {
                $invoice_stmt = $db->prepare("SELECT total_amount FROM customer_invoices WHERE id = ?");
                $invoice_stmt->execute([$payment_invoice_id]);
                $invoice_total_after_payment = $invoice_stmt->fetchColumn();
                
                $paid_stmt = $db->prepare("SELECT SUM(amount) FROM customer_payments WHERE invoice_id = ?");
                $paid_stmt->execute([$payment_invoice_id]);
                $total_paid_after_payment = $paid_stmt->fetchColumn() ?? 0;
                
                $new_status = ($total_paid_after_payment >= $invoice_total_after_payment) ? 'paid' : 'partially_paid';
                
                $update_stmt = $db->prepare("UPDATE customer_invoices SET status = ?, updated_at = NOW() WHERE id = ?");
                $update_stmt->execute([$new_status, $payment_invoice_id]);
            }

            // Update bank account balance for transfer payments
            if ($payment_method === 'transfer' && !empty($bank_account_id)) {
                // We assume bank_accounts.current_balance holds the running balance.
                // Use COALESCE to safely handle NULL.
                $bank_update_stmt = $db->prepare("UPDATE bank_accounts SET current_balance = COALESCE(current_balance, 0) + ? WHERE id = ?");
                $bank_update_stmt->execute([$amount, $bank_account_id]);
            }

            $db->commit();
            $success_message = 'تم إضافة الدفعة بنجاح';
            
            header("Location: view.php?id=$payment_id&success=" . urlencode($success_message));
            exit();
            
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $error_message = 'حدث خطأ أثناء إضافة الدفعة: ' . $e->getMessage();
        }
    }
}

include '../../includes/header.php';
?>

<div class="min-h-screen bg-gray-50 py-6" dir="rtl">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="bg-white shadow rounded-lg mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">إضافة دفعة جديدة</h1>
                        <p class="text-gray-600 mt-1">
                            <?php if ($invoice) echo "للفاتورة: " . htmlspecialchars($invoice['invoice_number']); elseif ($customer) echo "للعميل: " . htmlspecialchars($customer['name']); ?>
                        </p>
                    </div>
                    <div>
                        <?php if ($invoice): ?>
                        <a href="../invoices/view.php?id=<?php echo $invoice_id; ?>" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition duration-200"><i class="fas fa-arrow-right ml-2"></i> العودة للفاتورة</a>
                        <?php elseif ($customer): ?>
                        <a href="../customers/view_enhanced.php?id=<?php echo $customer_id; ?>&tab=payments" class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition duration-200"><i class="fas fa-arrow-right ml-2"></i> العودة للعميل</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($error_message): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg mb-6"><i class="fas fa-exclamation-circle ml-2"></i> <?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg mb-6"><i class="fas fa-check-circle ml-2"></i> <?php echo $success_message; ?></div>
        <?php endif; ?>

        <div class="bg-white shadow rounded-lg overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">بيانات الدفعة</h2>
            </div>
            
            <form method="POST" class="p-6" enctype="multipart/form-data">
                <input type="hidden" name="customer_id" value="<?php echo $customer_id; ?>">
                
                <?php if ($invoice): ?>
                <div class="mb-6 p-4 bg-blue-50 rounded-lg">
                    <h3 class="text-md font-semibold text-blue-800 mb-2">معلومات الفاتورة</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600">رقم الفاتورة: <span class="font-medium text-gray-900"><?php echo htmlspecialchars($invoice['invoice_number']); ?></span></p>
                            <p class="text-sm text-gray-600">المبلغ الإجمالي: <span class="font-medium text-gray-900"><?php echo number_format($invoice['total_amount'], 2); ?> ريال</span></p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600">المبلغ المتبقي: <span class="font-medium text-red-600"><?php echo number_format($remaining_amount, 2); ?> ريال</span></p>
                        </div>
                    </div>
                    <input type="hidden" name="invoice_id" value="<?php echo $invoice_id; ?>">
                </div>
                <?php elseif (!empty($unpaid_invoices)): ?>
                <div class="mb-6">
                    <label for="invoice_id" class="block text-sm font-medium text-gray-700 mb-1">اختر الفاتورة (اختياري)</label>
                    <select id="invoice_id" name="invoice_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- دفع بدون ربط بفاتورة --</option>
                        <?php foreach ($unpaid_invoices as $unpaid_invoice): 
                            $paid_stmt = $db->prepare("SELECT SUM(amount) FROM customer_payments WHERE invoice_id = ?");
                            $paid_stmt->execute([$unpaid_invoice['id']]);
                            $inv_remaining = $unpaid_invoice['total_amount'] - ($paid_stmt->fetchColumn() ?? 0);
                        ?>
                        <option value="<?php echo $unpaid_invoice['id']; ?>">
                            <?php echo htmlspecialchars($unpaid_invoice['invoice_number']); ?> - المتبقي: <?php echo number_format($inv_remaining, 2); ?> ريال
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="amount" class="block text-sm font-medium text-gray-700 mb-1">المبلغ <span class="text-red-600">*</span></label>
                        <div class="relative">
                            <input type="number" id="amount" name="amount" step="0.01" min="0.01" value="<?php echo ($invoice && $remaining_amount > 0) ? number_format($remaining_amount, 2, '.', '') : ''; ?>" class="w-full pl-24 pr-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <div class="absolute inset-y-0 left-0 flex items-center">
                                <select name="currency" id="currency" class="h-full pl-3 pr-8 bg-gray-50 border-r border-gray-300 rounded-l-md text-gray-700 text-sm focus:outline-none font-bold cursor-pointer hover:bg-gray-100">
                                    <option value="SAR" selected>SAR</option>
                                    <option value="USD">USD</option>
                                    <option value="YER">YER</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <label for="payment_method" class="block text-sm font-medium text-gray-700 mb-1">طريقة الدفع <span class="text-red-600">*</span></label>
                        <select id="payment_method" name="payment_method" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="cash">نقدي</option>
                            <option value="transfer">تحويل بنكي</option>
                            <option value="credit_card">بطاقة ائتمانية</option>
                            <option value="check">شيك</option>
                            <option value="other">أخرى</option>
                        </select>
                    </div>
                </div>

                <div id="bankAccountDiv" class="mt-6" style="display: none;">
                    <label for="bank_account_id" class="block text-sm font-medium text-gray-700 mb-1">الحساب البنكي المستلم <span class="text-red-600">*</span></label>
                    <select id="bank_account_id" name="bank_account_id" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">-- اختر الحساب البنكي --</option>
                        <?php foreach ($bank_accounts as $account): ?>
                            <option value="<?php echo $account['id']; ?>" data-currency="<?php echo htmlspecialchars($account['currency']); ?>">
                                <?php echo htmlspecialchars($account['bank_name'] . ' - ' . $account['account_holder_name'] . ' (' . $account['currency'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <div>
                        <label for="payment_date" class="block text-sm font-medium text-gray-700 mb-1">تاريخ الدفع <span class="text-red-600">*</span></label>
                        <input type="date" id="payment_date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>
                    
                    <div>
                        <label for="reference_number" class="block text-sm font-medium text-gray-700 mb-1">رقم المرجع</label>
                        <input type="text" id="reference_number" name="reference_number" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="رقم التحويل أو رقم الشيك">
                    </div>
                </div>
                
                <div class="mt-6">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">ملاحظات</label>
                    <textarea id="notes" name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="أي ملاحظات إضافية حول الدفعة"></textarea>
                </div>

                <div class="mt-6">
                    <label for="receipt_image" class="block text-sm font-medium text-gray-700 mb-1">إيصال الدفع (اختياري)</label>
                    <input type="file" id="receipt_image" name="receipt_image" accept="image/jpeg,image/png,application/pdf" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">صورة أو ملف PDF بحجم أقصى 5 ميجا.</p>
                </div>
                
                <div class="flex justify-end mt-8 border-t pt-6">
                    <button type="submit" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-200"><i class="fas fa-save ml-2"></i> حفظ الدفعة</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethodSelect = document.getElementById('payment_method');
    const bankAccountContainer = document.getElementById('bankAccountDiv');
    const bankAccountSelect = document.getElementById('bank_account_id');
    const currencySelect = document.getElementById('currency');
    const form = document.querySelector('form');
    let isSubmitting = false;

    function filterBankAccounts() {
        const selectedCurrency = currencySelect.value;
        const options = bankAccountSelect.querySelectorAll('option');
        let hasVisibleOptions = false;
        
        options.forEach(opt => {
            if (opt.value === "") return; // Skip default option
            
            const accountCurrency = opt.getAttribute('data-currency');
            // Show option if currency matches OR if account has no currency (legacy support)
            if (accountCurrency === selectedCurrency || !accountCurrency || accountCurrency === 'SAR') {
                opt.style.display = 'block';
                opt.disabled = false;
                hasVisibleOptions = true;
            } else {
                opt.style.display = 'none';
                opt.disabled = true;
            }
        });
        
        // Reset selection if the currently selected option is now hidden
        const selectedOption = bankAccountSelect.options[bankAccountSelect.selectedIndex];
        if (selectedOption && selectedOption.value !== "" && selectedOption.style.display === 'none') {
            bankAccountSelect.value = "";
        }
    }

    currencySelect.addEventListener('change', filterBankAccounts);
    // Run filter on load
    filterBankAccounts();

    function toggleBankAccountField() {
        if (paymentMethodSelect.value === 'transfer') {
            bankAccountContainer.style.display = 'block';
            bankAccountSelect.required = true;
        } else {
            bankAccountContainer.style.display = 'none';
            bankAccountSelect.required = false;
        }
    }

    paymentMethodSelect.addEventListener('change', toggleBankAccountField);
    toggleBankAccountField();
    
    // Prevent double submission
    form.addEventListener('submit', function(e) {
        if (isSubmitting) {
            e.preventDefault();
            return false;
        }
        isSubmitting = true;
        // Disable submit button
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin ml-2"></i> جاري الحفظ...';
        }
    });
});
</script>

<?php include '../../includes/footer.php'; ?>```