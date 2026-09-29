<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Include database connection
try {
    require_once 'config/database.php';
} catch (Exception $e) {
    die('خطأ في الاتصال بقاعدة البيانات: ' . $e->getMessage());
}

// Initialize variables for form data and errors
$success_message = '';
$error_message = '';

// Get current user data
$user_id = $_SESSION['user_id'];
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        die('لم يتم العثور على بيانات المستخدم');
    }
} catch (PDOException $e) {
    die('خطأ في استرجاع بيانات المستخدم: ' . $e->getMessage());
}

// Set default values for potentially missing fields
if (!isset($user['email'])) $user['email'] = '';
if (!isset($user['phone'])) $user['phone'] = '';
if (!isset($user['last_login'])) $user['last_login'] = null;
if (!isset($user['created_at'])) $user['created_at'] = date('Y-m-d H:i:s');
if (!isset($user['updated_at'])) $user['updated_at'] = $user['created_at'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate and sanitize input
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Basic validation
    if (empty($full_name) || empty($email)) {
        $error_message = 'الاسم والبريد الإلكتروني مطلوبان';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'البريد الإلكتروني غير صالح';
    } else {
        // Check if email already exists for another user
        $check_stmt = $db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $check_stmt->execute([$email, $user_id]);
        if ($check_stmt->rowCount() > 0) {
            $error_message = 'البريد الإلكتروني مستخدم بالفعل';
        } else {
            // Update profile information
            try {
                $db->beginTransaction();
                
                // Update basic info
                $update_stmt = $db->prepare("UPDATE users SET full_name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                $update_stmt->execute([$full_name, $email, $phone, $user_id]);
                
                // Update password if requested
                if (!empty($current_password) && !empty($new_password)) {
                    // Verify current password
                    if (!password_verify($current_password, $user['password'])) {
                        $error_message = 'كلمة المرور الحالية غير صحيحة';
                        $db->rollBack();
                    } 
                    // Check if new passwords match
                    elseif ($new_password !== $confirm_password) {
                        $error_message = 'كلمة المرور الجديدة وتأكيدها غير متطابقين';
                        $db->rollBack();
                    }
                    // Check password strength
                    elseif (strlen($new_password) < 8) {
                        $error_message = 'كلمة المرور الجديدة يجب أن تكون 8 أحرف على الأقل';
                        $db->rollBack();
                    } 
                    else {
                        // Hash new password and update
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $pwd_stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $pwd_stmt->execute([$hashed_password, $user_id]);
                    }
                }
                
                if (empty($error_message)) {
                    $db->commit();
                    $success_message = 'تم تحديث الملف الشخصي بنجاح';
                    
                    // Update session data
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $email;
                    
                    // Refresh user data
                    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
                    $stmt->execute([$user_id]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            } catch (PDOException $e) {
                $db->rollBack();
                $error_message = 'حدث خطأ أثناء تحديث البيانات: ' . $e->getMessage();
            }
        }
    }
}

// Set page title
$page_title = 'الملف الشخصي';

// Include header
include 'includes/header.php';
?>

<style>
    .dir-ltr {
        direction: ltr;
        text-align: left;
    }
    .dir-rtl {
        direction: rtl;
        text-align: right;
    }
    
    /* Fix for form inputs */
    .form-input {
        width: 100%;
        padding: 0.5rem 0.75rem;
        border: 1px solid #d1d5db;
        border-radius: 0.375rem;
        box-shadow: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    }
    .form-input:focus {
        outline: none;
        border-color: #C7A46D;
        box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2);
    }
</style>

<div class="bg-gray-50 py-6 px-4 sm:px-6 lg:px-8" dir="rtl">
    <!-- Header -->
    <div class="bg-white shadow rounded-lg mb-6">
        <div class="px-6 py-4">
            <h1 class="text-2xl font-bold text-gray-900">الملف الشخصي</h1>
            <p class="text-gray-600 mt-1">إدارة معلومات حسابك الشخصي</p>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (!empty($success_message)): ?>
    <div class="bg-amber-100 border-r-4 border-amber-500 text-amber-700 p-4 mb-6 rounded-md">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="mr-3">
                <p class="font-medium"><?php echo $success_message; ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
    <div class="bg-red-100 border-r-4 border-red-500 text-red-700 p-4 mb-6 rounded-md">
        <div class="flex">
            <div class="flex-shrink-0">
                <i class="fas fa-exclamation-circle"></i>
            </div>
            <div class="mr-3">
                <p class="font-medium"><?php echo $error_message; ?></p>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Profile Information -->
        <div class="md:col-span-2">
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">معلومات الملف الشخصي</h2>
                </div>
                <div class="p-6">
                    <form method="POST" action="profile.php">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="col-span-2">
                                <label for="full_name" class="block text-sm font-medium text-gray-700 mb-1">الاسم الكامل</label>
                                <input type="text" name="full_name" id="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                                    class="form-input block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring focus:ring-green-200 focus:ring-opacity-50" required>
                            </div>
                            
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-1">البريد الإلكتروني</label>
                                <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" 
                                    class="form-input block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring focus:ring-green-200 focus:ring-opacity-50" required>
                            </div>
                            
                            <div>
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">رقم الهاتف</label>
                                <input type="text" name="phone" id="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" 
                                    class="form-input block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring focus:ring-green-200 focus:ring-opacity-50"
                                    placeholder="966xxxxxxxxx">
                                <p class="text-xs text-gray-500 mt-1">مثال: 966500000000</p>
                            </div>
                            
                            <div class="col-span-2">
                                <label for="role" class="block text-sm font-medium text-gray-700 mb-1">الدور</label>
                                <input type="text" id="role" value="<?php echo $user['role'] === 'admin' ? 'مدير النظام' : ($user['role'] === 'manager' ? 'مدير' : 'موظف'); ?>" 
                                    class="form-input block w-full rounded-md bg-gray-50 border-gray-300 shadow-sm" readonly>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-amber-600 text-white border border-amber-700 rounded-md hover:bg-amber-700 hover:shadow-md transition-all duration-200 font-medium">
                                <i class="fas fa-save ml-2"></i>
                                حفظ التغييرات
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- User Info Card -->
        <div>
            <div class="bg-white shadow rounded-lg overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">معلومات الحساب</h2>
                </div>
                <div class="p-6">
                    <div class="flex flex-col items-center">
                        <div class="w-24 h-24 rounded-full bg-amber-600 flex items-center justify-center text-white text-3xl font-bold mb-4">
                            <?php echo substr($user['full_name'], 0, 1); ?>
                        </div>
                        <h3 class="text-xl font-semibold text-gray-800"><?php echo htmlspecialchars($user['full_name']); ?></h3>
                        <p class="text-sm text-gray-500 mt-1"><?php echo $user['role'] === 'admin' ? 'مدير النظام' : ($user['role'] === 'manager' ? 'مدير' : 'موظف'); ?></p>
                        
                        <?php if (isset($user['email']) && !empty($user['email'])): ?>
                        <p class="text-sm text-gray-600 mt-2">
                            <i class="fas fa-envelope ml-1"></i>
                            <?php echo htmlspecialchars($user['email']); ?>
                        </p>
                        <?php endif; ?>
                        
                        <?php if (isset($user['phone']) && !empty($user['phone'])): ?>
                        <p class="text-sm text-gray-600 mt-1">
                            <i class="fas fa-phone ml-1"></i>
                            <?php echo htmlspecialchars($user['phone']); ?>
                        </p>
                        <?php endif; ?>
                        
                        <div class="mt-6 w-full">
                            <div class="flex justify-between py-3 border-b border-gray-100">
                                <span class="text-sm text-gray-500">آخر تسجيل دخول</span>
                                <span class="text-sm font-medium text-gray-800"><?php echo isset($user['last_login']) && $user['last_login'] ? date('Y-m-d H:i', strtotime($user['last_login'])) : 'غير متوفر'; ?></span>
                            </div>
                            <div class="flex justify-between py-3 border-b border-gray-100">
                                <span class="text-sm text-gray-500">تاريخ الإنشاء</span>
                                <span class="text-sm font-medium text-gray-800"><?php echo isset($user['created_at']) ? date('Y-m-d', strtotime($user['created_at'])) : date('Y-m-d'); ?></span>
                            </div>
                            <div class="flex justify-between py-3">
                                <span class="text-sm text-gray-500">آخر تحديث</span>
                                <span class="text-sm font-medium text-gray-800"><?php 
                                    if (isset($user['updated_at']) && $user['updated_at']) {
                                        echo date('Y-m-d', strtotime($user['updated_at']));
                                    } elseif (isset($user['created_at']) && $user['created_at']) {
                                        echo date('Y-m-d', strtotime($user['created_at']));
                                    } else {
                                        echo date('Y-m-d');
                                    }
                                ?></span>
                            </div>
                            
                            <?php if ($user['role'] === 'admin'): ?>
                            <div class="mt-4 pt-4 border-t border-gray-100">
                                <details class="text-xs">
                                    <summary class="text-gray-500 cursor-pointer hover:text-gray-700">معلومات النظام</summary>
                                    <div class="mt-2 bg-gray-50 p-2 rounded text-gray-600 overflow-auto max-h-48 dir-ltr text-left">
                                        <p><strong>User ID:</strong> <?php echo $user_id; ?></p>
                                        <p><strong>Username:</strong> <?php echo htmlspecialchars($user['username'] ?? ''); ?></p>
                                        <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email'] ?? ''); ?></p>
                                        <p><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone'] ?? ''); ?></p>
                                        <p><strong>Role:</strong> <?php echo htmlspecialchars($user['role'] ?? ''); ?></p>
                                        <p><strong>Last Login:</strong> <?php echo $user['last_login'] ?? 'Not set'; ?></p>
                                        <p><strong>Created:</strong> <?php echo $user['created_at'] ?? 'Not set'; ?></p>
                                        <p><strong>Updated:</strong> <?php echo $user['updated_at'] ?? 'Not set'; ?></p>
                                        <p><strong>Session Data:</strong></p>
                                        <pre class="bg-gray-100 p-1 mt-1 text-xs overflow-auto"><?php print_r($_SESSION); ?></pre>
                                    </div>
                                </details>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Change Password Card -->
            <div class="bg-white shadow rounded-lg overflow-hidden mt-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">تغيير كلمة المرور</h2>
                </div>
                <div class="p-6">
                    <form method="POST" action="profile.php">
                        <!-- Keep the existing fields but hidden -->
                        <input type="hidden" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>">
                        <input type="hidden" name="email" value="<?php echo htmlspecialchars($user['email']); ?>">
                        <input type="hidden" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                        
                        <div class="space-y-4">
                            <div>
                                <label for="current_password" class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور الحالية</label>
                                <input type="password" name="current_password" id="current_password" 
                                    class="form-input block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring focus:ring-green-200 focus:ring-opacity-50" required>
                            </div>
                            
                            <div>
                                <label for="new_password" class="block text-sm font-medium text-gray-700 mb-1">كلمة المرور الجديدة</label>
                                <input type="password" name="new_password" id="new_password" 
                                    class="form-input block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring focus:ring-green-200 focus:ring-opacity-50" required>
                                <p class="text-xs text-gray-500 mt-1">يجب أن تكون 8 أحرف على الأقل</p>
                            </div>
                            
                            <div>
                                <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">تأكيد كلمة المرور</label>
                                <input type="password" name="confirm_password" id="confirm_password" 
                                    class="form-input block w-full rounded-md border-gray-300 shadow-sm focus:border-amber-500 focus:ring focus:ring-green-200 focus:ring-opacity-50" required>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" class="inline-flex items-center px-5 py-2.5 bg-gray-700 text-white border border-gray-800 rounded-md hover:bg-gray-800 hover:shadow-md transition-all duration-200 font-medium w-full justify-center">
                                <i class="fas fa-key ml-2"></i>
                                تغيير كلمة المرور
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
