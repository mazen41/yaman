<?php
session_start();

// Ensure the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

require_once '../../config/database.php';

$page_title = 'إدارة المدن';
$error_message = '';
$success_message = '';
$edit_mode = false;
$city_to_edit = null;

// Handle POST requests for Adding and Updating cities
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $city_name = trim($_POST['city_name'] ?? '');
    $shipping_cost = floatval($_POST['shipping_cost'] ?? 0);

    // Basic validation
    if (empty($city_name)) {
        $error_message = 'اسم المدينة مطلوب.';
    } else {
        // --- ADD a new city ---
        if ($_POST['action'] == 'add') {
            try {
                // Check if city already exists
                $check_stmt = $db->prepare("SELECT COUNT(*) FROM cities WHERE name = ?");
                $check_stmt->execute([$city_name]);
                if ($check_stmt->fetchColumn() > 0) {
                    $error_message = "المدينة '{$city_name}' موجودة بالفعل.";
                } else {
                    $stmt = $db->prepare("INSERT INTO cities (name, shipping_cost, is_active, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())");
                    $stmt->execute([$city_name, $shipping_cost]);
                    $success_message = "تمت إضافة المدينة '{$city_name}' بنجاح.";
                }
            } catch (PDOException $e) {
                $error_message = 'فشل في إضافة المدينة: ' . $e->getMessage();
            }
        }
        // --- UPDATE a city ---
        elseif ($_POST['action'] == 'update' && isset($_POST['city_id'])) {
            $city_id = intval($_POST['city_id']);
            try {
                // Check if the new name conflicts with another existing city
                $check_stmt = $db->prepare("SELECT COUNT(*) FROM cities WHERE name = ? AND id != ?");
                $check_stmt->execute([$city_name, $city_id]);
                if ($check_stmt->fetchColumn() > 0) {
                     $error_message = "لا يمكن التحديث، الاسم '{$city_name}' مستخدم لمدينة أخرى.";
                } else {
                    $stmt = $db->prepare("UPDATE cities SET name = ?, shipping_cost = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$city_name, $shipping_cost, $city_id]);
                    $success_message = "تم تحديث المدينة بنجاح.";
                }
            } catch (PDOException $e) {
                $error_message = 'فشل في تحديث المدينة: ' . $e->getMessage();
            }
        }
    }
}

// Handle GET requests for Editing and Toggling Status
if (isset($_GET['action'])) {
    $city_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // --- Prepare for EDITING a city ---
    if ($_GET['action'] == 'edit' && $city_id > 0) {
        $edit_stmt = $db->prepare("SELECT id, name, shipping_cost FROM cities WHERE id = ?");
        $edit_stmt->execute([$city_id]);
        $city_to_edit = $edit_stmt->fetch(PDO::FETCH_ASSOC);
        if ($city_to_edit) {
            $edit_mode = true;
        } else {
            $error_message = 'المدينة المطلوبة غير موجودة.';
        }
    }

    // --- TOGGLE active status ---
    if ($_GET['action'] == 'toggle_status' && $city_id > 0) {
        try {
            // First, get the current status
            $status_stmt = $db->prepare("SELECT is_active FROM cities WHERE id = ?");
            $status_stmt->execute([$city_id]);
            $current_status = $status_stmt->fetchColumn();

            // Toggle the status
            $new_status = $current_status == 1 ? 0 : 1;

            $toggle_stmt = $db->prepare("UPDATE cities SET is_active = ?, updated_at = NOW() WHERE id = ?");
            $toggle_stmt->execute([$new_status, $city_id]);

            $status_text = $new_status == 1 ? 'تفعيل' : 'إلغاء تفعيل';
            $success_message = "تم {$status_text} المدينة بنجاح.";

        } catch (PDOException $e) {
            $error_message = 'فشل تغيير حالة المدينة: ' . $e->getMessage();
        }
    }
}

// Fetch all cities to display in the table
try {
    $cities_stmt = $db->prepare("SELECT id, name, shipping_cost, is_active, created_at FROM cities ORDER BY name ASC");
    $cities_stmt->execute();
    $cities = $cities_stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $cities = [];
    $error_message = 'فشل في تحميل قائمة المدن.';
}

$total_cities  = count($cities);
$active_cities = count(array_filter($cities, fn($c) => (int)$c['is_active'] === 1));

include '../../includes/header.php';
?>

<style>
    .cities-page {
        font-family: 'Cairo', 'Segoe UI', Tahoma, Arial, sans-serif;
        max-width: 1200px;
        margin: 0 auto;
        padding: 24px 20px 40px;
        color: #1f2937;
        display: flex;
        flex-direction: column;
        gap: 24px;
    }
    .cities-page .fa,
    .cities-page .fas,
    .cities-page .far,
    .cities-page .fab { font-family: 'Font Awesome 5 Free', 'Font Awesome 5 Brands'; }

    /* ----- Page header ----- */
    .cities-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 16px;
    }
    .cities-header h1 {
        margin: 0;
        font-size: 1.6rem;
        font-weight: 800;
        color: #111827;
    }
    .cities-header p { margin: 4px 0 0; color: #6b7280; font-size: 0.95rem; }
    .stat-chips { display: flex; gap: 12px; flex-wrap: wrap; }
    .stat-chip {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 12px;
        padding: 10px 16px;
        min-width: 110px;
        text-align: center;
    }
    .stat-chip .num { font-size: 1.35rem; font-weight: 800; color: #111827; line-height: 1.2; }
    .stat-chip .lbl { font-size: 0.8rem; color: #6b7280; margin-top: 2px; }

    /* ----- Cards ----- */
    .card {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 16px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        overflow: hidden;
    }
    .card-header {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 18px 24px;
        border-bottom: 1px solid #f1f5f9;
    }
    .card-header .icon-box {
        width: 40px; height: 40px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        background: #eff6ff; color: #2563eb;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .card-header.edit .icon-box { background: #fff7ed; color: #ea580c; }
    .card-header h2 { margin: 0; font-size: 1.1rem; font-weight: 700; color: #111827; }
    .card-body { padding: 24px; }
    .card-body.flush { padding: 0; }

    /* ----- Form ----- */
    .form-grid {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
        gap: 20px 24px;
    }
    .field label {
        display: block;
        font-weight: 600;
        font-size: 0.9rem;
        color: #374151;
        margin-bottom: 8px;
    }
    .form-control {
        width: 100%;
        height: 46px;
        padding: 0 14px;
        border: 1px solid #d1d5db;
        border-radius: 10px;
        font-size: 0.975rem;
        background: #fff;
        color: #111827;
        transition: border-color .15s, box-shadow .15s;
        box-sizing: border-box;
    }
    .form-control::placeholder { color: #9ca3af; }
    .form-control:focus {
        outline: none;
        border-color: #3b82f6;
        box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.15);
    }
    .form-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
        margin-top: 24px;
        padding-top: 20px;
        border-top: 1px solid #f1f5f9;
    }

    /* ----- Buttons ----- */
    .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        height: 42px;
        padding: 0 18px;
        border: 1px solid transparent;
        border-radius: 10px;
        font-size: 0.9rem;
        font-weight: 600;
        line-height: 1;
        white-space: nowrap;
        text-decoration: none;
        cursor: pointer;
        transition: background-color .15s, box-shadow .15s, transform .05s;
    }
    .btn:active { transform: translateY(1px); }
    .btn:focus-visible { outline: none; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.25); }
    .btn-primary   { background: #2563eb; color: #fff; }
    .btn-primary:hover { background: #1d4ed8; }
    .btn-warning   { background: #f97316; color: #fff; }
    .btn-warning:hover { background: #ea580c; }
    .btn-secondary { background: #f3f4f6; color: #374151; border-color: #e5e7eb; }
    .btn-secondary:hover { background: #e5e7eb; }
    .btn-success   { background: #16a34a; color: #fff; }
    .btn-success:hover { background: #15803d; }
    .btn-danger    { background: #fff; color: #dc2626; border-color: #fecaca; }
    .btn-danger:hover { background: #fef2f2; }
    .btn-sm { height: 34px; padding: 0 12px; font-size: 0.85rem; border-radius: 8px; }

    /* ----- Alerts ----- */
    .alert {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 14px 18px;
        border-radius: 12px;
        font-size: 0.95rem;
        font-weight: 600;
        border: 1px solid transparent;
    }
    .alert-success { background: #ecfdf5; border-color: #a7f3d0; color: #065f46; }
    .alert-error   { background: #fef2f2; border-color: #fecaca; color: #991b1b; }

    /* ----- Table ----- */
    .table-responsive { width: 100%; overflow-x: auto; }
    .cities-table { width: 100%; border-collapse: separate; border-spacing: 0; min-width: 760px; }
    .cities-table th,
    .cities-table td {
        padding: 14px 20px;
        text-align: right;
        border-bottom: 1px solid #f1f5f9;
        white-space: nowrap;
        vertical-align: middle;
        font-size: 0.925rem;
    }
    .cities-table th {
        background: #f9fafb;
        font-weight: 700;
        color: #6b7280;
        font-size: 0.8rem;
        text-transform: uppercase;
        letter-spacing: .03em;
    }
    .cities-table tbody tr:last-child td { border-bottom: none; }
    .cities-table tbody tr:hover { background: #f9fafb; }
    .cities-table td.num-col { color: #9ca3af; width: 60px; }
    .city-name { font-weight: 700; color: #111827; }
    .cost { font-weight: 700; color: #111827; font-variant-numeric: tabular-nums; }
    .cost small { color: #9ca3af; font-weight: 600; margin-right: 2px; }
    .date-col { color: #6b7280; }

    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 12px;
        border-radius: 999px;
        font-size: 0.8rem;
        font-weight: 700;
    }
    .status-badge::before {
        content: '';
        width: 7px; height: 7px;
        border-radius: 50%;
        background: currentColor;
    }
    .status-active   { background: #dcfce7; color: #166534; }
    .status-inactive { background: #fee2e2; color: #991b1b; }

    .row-actions { display: flex; gap: 8px; flex-wrap: nowrap; justify-content: flex-start; }

    .empty-state { text-align: center; padding: 48px 20px; color: #6b7280; }
    .empty-state i { font-size: 2.2rem; color: #d1d5db; margin-bottom: 12px; display: block; }
    .empty-state p { margin: 0; font-size: 0.95rem; }

    /* ----- Responsive ----- */
    @media (max-width: 720px) {
        .form-grid { grid-template-columns: 1fr; }
        .cities-page { padding: 16px 12px 32px; gap: 18px; }
        .card-body { padding: 18px; }
        .card-header { padding: 16px 18px; }
    }
</style>

<div class="cities-page" dir="rtl">

    <div class="cities-header">
        <div>
            <h1><?php echo $page_title; ?></h1>
            <p>إضافة المدن وتحديد تكلفة الشحن لكل مدينة</p>
        </div>
        <div class="stat-chips">
            <div class="stat-chip">
                <div class="num"><?php echo $total_cities; ?></div>
                <div class="lbl">إجمالي المدن</div>
            </div>
            <div class="stat-chip">
                <div class="num" style="color:#16a34a;"><?php echo $active_cities; ?></div>
                <div class="lbl">مفعّلة</div>
            </div>
        </div>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $success_message; ?></div>
    <?php endif; ?>
    <?php if ($error_message): ?>
        <div class="alert alert-error"><i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?></div>
    <?php endif; ?>

    <!-- Add / Edit City -->
    <div class="card" id="form-card">
        <div class="card-header <?php echo $edit_mode ? 'edit' : ''; ?>">
            <div class="icon-box">
                <i class="fas <?php echo $edit_mode ? 'fa-edit' : 'fa-plus'; ?>"></i>
            </div>
            <h2>
                <?php if ($edit_mode): ?>
                    تعديل مدينة: <?php echo htmlspecialchars($city_to_edit['name']); ?>
                <?php else: ?>
                    إضافة مدينة جديدة
                <?php endif; ?>
            </h2>
        </div>

        <form method="POST" action="cities.php" class="card-body">
            <input type="hidden" name="action" value="<?php echo $edit_mode ? 'update' : 'add'; ?>">
            <?php if ($edit_mode): ?>
                <input type="hidden" name="city_id" value="<?php echo $city_to_edit['id']; ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="field">
                    <label for="city_name">اسم المدينة</label>
                    <input type="text" id="city_name" name="city_name" class="form-control"
                           value="<?php echo htmlspecialchars($city_to_edit['name'] ?? ($_POST['city_name'] ?? '')); ?>"
                           placeholder="مثال: صنعاء، عدن..." required>
                </div>

                <div class="field">
                    <label for="shipping_cost">تكلفة الشحن</label>
                    <input type="number" step="0.01" id="shipping_cost" name="shipping_cost" class="form-control"
                           value="<?php echo htmlspecialchars($city_to_edit['shipping_cost'] ?? ($_POST['shipping_cost'] ?? '0')); ?>"
                           placeholder="0.00">
                </div>
            </div>

            <div class="form-actions">
                <?php if ($edit_mode): ?>
                    <button type="submit" class="btn btn-warning"><i class="fas fa-save"></i> تحديث المدينة</button>
                    <a href="cities.php" class="btn btn-secondary"><i class="fas fa-times"></i> إلغاء التعديل</a>
                <?php else: ?>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> إضافة المدينة</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Cities List -->
    <div class="card">
        <div class="card-header">
            <div class="icon-box"><i class="fas fa-list-ul"></i></div>
            <h2>قائمة المدن</h2>
        </div>

        <div class="card-body flush">
            <?php if (empty($cities)): ?>
                <div class="empty-state">
                    <i class="fas fa-city"></i>
                    <p>لا توجد مدن مضافة حالياً.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="cities-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>اسم المدينة</th>
                                <th>تكلفة الشحن</th>
                                <th>الحالة</th>
                                <th>تاريخ الإضافة</th>
                                <th>الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cities as $index => $city): ?>
                            <tr>
                                <td class="num-col"><?php echo $index + 1; ?></td>
                                <td class="city-name"><?php echo htmlspecialchars($city['name']); ?></td>
                                <td class="cost"><?php echo number_format($city['shipping_cost'], 2); ?></td>
                                <td>
                                    <?php if ($city['is_active']): ?>
                                        <span class="status-badge status-active">مفعل</span>
                                    <?php else: ?>
                                        <span class="status-badge status-inactive">غير مفعل</span>
                                    <?php endif; ?>
                                </td>
                                <td class="date-col"><?php echo date('Y-m-d', strtotime($city['created_at'])); ?></td>
                                <td>
                                    <div class="row-actions">
                                        <a href="cities.php?action=edit&id=<?php echo $city['id']; ?>#form-card" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i> تعديل
                                        </a>
                                        <?php if ($city['is_active']): ?>
                                            <a href="cities.php?action=toggle_status&id=<?php echo $city['id']; ?>" class="btn btn-sm btn-danger"
                                               onclick="return confirm('هل أنت متأكد من إلغاء تفعيل هذه المدينة؟')">
                                                <i class="fas fa-times-circle"></i> إلغاء التفعيل
                                            </a>
                                        <?php else: ?>
                                            <a href="cities.php?action=toggle_status&id=<?php echo $city['id']; ?>" class="btn btn-sm btn-success"
                                               onclick="return confirm('هل أنت متأكد من تفعيل هذه المدينة؟')">
                                                <i class="fas fa-check-circle"></i> تفعيل
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php include '../../includes/footer.php'; ?>
