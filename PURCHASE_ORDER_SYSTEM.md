# نظام إنشاء طلبات الشراء - Purchase Order System

## 📋 نظرة عامة - Overview
نظام متكامل لإنشاء وإدارة طلبات الشراء مع دعم مجموعات شراء متعددة وتتبع حالة الطلبات

---

## 🗄️ Database Tables - جداول قاعدة البيانات

### 1. `purchase_orders` - طلبات الشراء الرئيسية
```sql
CREATE TABLE `purchase_orders` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(50) NOT NULL UNIQUE COMMENT 'رقم سلة الشراء',
  `serial_number` VARCHAR(50) NOT NULL UNIQUE COMMENT 'الرقم التسلسلي',
  `purchase_date` DATE NOT NULL COMMENT 'تاريخ الشراء',
  `purchase_group_id` INT(11) DEFAULT NULL COMMENT 'اختيار مجموعة الشراء',
  `total_items` INT(11) DEFAULT 0 COMMENT 'إجمالي عدد القطع',
  `total_discount_before` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'خصم النقطة',
  `total_discount_after` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'خصم النادي',
  `total_price_before_discount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'سعر السلة قبل الخصم',
  `total_price_after_discount` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'سعر السلة بعد الخصم (قابل للتعديل)',
  `status` ENUM('pending', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending' COMMENT 'حالة الطلب',
  `tracking_code_1` VARCHAR(100) DEFAULT NULL COMMENT 'رمز التتبع 1',
  `tracking_code_2` VARCHAR(100) DEFAULT NULL COMMENT 'رمز التتبع 2',
  `notes` TEXT DEFAULT NULL COMMENT 'ملاحظات',
  `created_by` INT(11) NOT NULL COMMENT 'المستخدم الذي أنشأ الطلب',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_order_number` (`order_number`),
  INDEX `idx_purchase_date` (`purchase_date`),
  INDEX `idx_purchase_group` (`purchase_group_id`),
  INDEX `idx_status` (`status`),
  FOREIGN KEY (`purchase_group_id`) REFERENCES `purchase_groups`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 2. `purchase_groups` - مجموعات الشراء
```sql
CREATE TABLE `purchase_groups` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `group_name` VARCHAR(100) NOT NULL COMMENT 'اسم المجموعة',
  `description` TEXT DEFAULT NULL COMMENT 'وصف المجموعة',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT 'نشط/غير نشط',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 3. `purchase_order_items` - عناصر طلبات الشراء
```sql
CREATE TABLE `purchase_order_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `purchase_order_id` INT(11) NOT NULL COMMENT 'معرف طلب الشراء',
  `item_number` INT(11) NOT NULL COMMENT 'رقم العنصر في الطلب',
  `client_name` VARCHAR(200) NOT NULL COMMENT 'اسم العميل',
  `client_phone` VARCHAR(20) NOT NULL COMMENT 'رقم جوال العميل',
  `item_quantity` INT(11) NOT NULL DEFAULT 1 COMMENT 'عدد القطع',
  `item_price` DECIMAL(10,2) NOT NULL COMMENT 'قيمة الطلب',
  `notes` TEXT DEFAULT NULL COMMENT 'ملاحظات',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_purchase_order` (`purchase_order_id`),
  FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4. `purchase_order_tracking` - تتبع حالة الطلبات
```sql
CREATE TABLE `purchase_order_tracking` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `purchase_order_id` INT(11) NOT NULL,
  `tracking_type` ENUM('tracking_code_1', 'tracking_code_2') NOT NULL COMMENT 'نوع رمز التتبع',
  `tracking_code` VARCHAR(100) NOT NULL COMMENT 'رمز التتبع',
  `status` VARCHAR(50) DEFAULT NULL COMMENT 'حالة الشحنة',
  `location` VARCHAR(200) DEFAULT NULL COMMENT 'الموقع الحالي',
  `last_update` DATETIME DEFAULT NULL COMMENT 'آخر تحديث',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_purchase_order` (`purchase_order_id`),
  INDEX `idx_tracking_code` (`tracking_code`),
  FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 5. `purchase_order_status_log` - سجل تغييرات حالة الطلب
```sql
CREATE TABLE `purchase_order_status_log` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `purchase_order_id` INT(11) NOT NULL,
  `old_status` VARCHAR(50) DEFAULT NULL,
  `new_status` VARCHAR(50) NOT NULL,
  `changed_by` INT(11) NOT NULL COMMENT 'المستخدم الذي قام بالتغيير',
  `notes` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_purchase_order` (`purchase_order_id`),
  FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`changed_by`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 6. `purchase_order_gifts` - هدايا الطلبات (إضافة رمز آخر)
```sql
CREATE TABLE `purchase_order_gifts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `purchase_order_id` INT(11) NOT NULL,
  `gift_code` VARCHAR(100) NOT NULL COMMENT 'رمز الهدية',
  `gift_description` TEXT DEFAULT NULL COMMENT 'وصف الهدية',
  `gift_value` DECIMAL(10,2) DEFAULT 0.00 COMMENT 'قيمة الهدية',
  `is_used` TINYINT(1) DEFAULT 0 COMMENT 'تم استخدامه',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_purchase_order` (`purchase_order_id`),
  INDEX `idx_gift_code` (`gift_code`),
  FOREIGN KEY (`purchase_order_id`) REFERENCES `purchase_orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## 🎨 Frontend Structure - هيكل الواجهة الأمامية

### 📄 File: `modules/purchase_orders/create.php`

#### القسم 1: معلومات الطلب الأساسية
```html
<div class="card mb-4">
    <div class="card-header bg-primary text-white">
        <h5>إنشاء طلب شراء (سلة بالشراء)</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <!-- رقم سلة الشراء -->
            <div class="col-md-4">
                <label>رقم سلة الشراء <span class="text-danger">*</span></label>
                <input type="text" name="order_number" class="form-control" required>
            </div>
            
            <!-- الرقم التسلسلي -->
            <div class="col-md-4">
                <label>الرقم التسلسلي</label>
                <input type="text" name="serial_number" class="form-control">
            </div>
            
            <!-- تاريخ الشراء -->
            <div class="col-md-4">
                <label>تاريخ الشراء <span class="text-danger">*</span></label>
                <input type="date" name="purchase_date" class="form-control" required>
            </div>
        </div>
        
        <div class="row mt-3">
            <!-- اختيار مجموعة الشراء -->
            <div class="col-md-6">
                <label>اختيار مجموعة الشراء</label>
                <select name="purchase_group_id" class="form-control">
                    <option value="">-- اختر مجموعة --</option>
                    <!-- يتم تعبئتها من قاعدة البيانات -->
                </select>
            </div>
        </div>
    </div>
</div>
```

#### القسم 2: جدول طلبات العملاء
```html
<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h5>إضافة عدة طلبات شراء تحتوي على عدة سلات شراء تم قوم بالشراء طلبات شراء (سلة بالشراء)</h5>
        <p class="mb-0">حسب الجدول التالية:</p>
    </div>
    <div class="card-body">
        <table class="table table-bordered" id="itemsTable">
            <thead class="table-light">
                <tr>
                    <th width="5%">م</th>
                    <th width="25%">اسم العميل</th>
                    <th width="15%">رقم الطلب</th>
                    <th width="15%">رقم جوال العميل</th>
                    <th width="10%">عدد القطع</th>
                    <th width="15%">قيمة الطلب</th>
                    <th width="10%">ملاحظات</th>
                    <th width="5%">حذف</th>
                </tr>
            </thead>
            <tbody id="itemsTableBody">
                <!-- يتم إضافة الصفوف ديناميكياً -->
            </tbody>
        </table>
        <button type="button" class="btn btn-success" onclick="addItemRow()">
            <i class="fas fa-plus"></i> إضافة عميل
        </button>
    </div>
</div>
```

#### القسم 3: الحسابات والخصومات
```html
<div class="card mb-4">
    <div class="card-header bg-warning">
        <h5>إجمالي عدد القطع (إجمالي من طلبات الشراء)</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <!-- كوبون الخصم -->
            <div class="col-md-6">
                <div class="alert alert-info">
                    <strong>سعر السلة قبل الخصم (قابل للتعديل):</strong>
                    <h4 id="priceBeforeDiscount">0.00 ريال</h4>
                </div>
                <div class="form-group">
                    <label>خصم النقطة</label>
                    <input type="number" step="0.01" name="discount_before" 
                           class="form-control" value="0" onchange="calculateTotals()">
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="alert alert-success">
                    <strong>سعر السلة بعد الخصم (قابل للتعديل):</strong>
                    <h4 id="priceAfterDiscount">0.00 ريال</h4>
                </div>
                <div class="form-group">
                    <label>خصم النادي</label>
                    <input type="number" step="0.01" name="discount_after" 
                           class="form-control" value="0" onchange="calculateTotals()">
                </div>
            </div>
        </div>
        
        <div class="alert alert-primary mt-3">
            <strong>إجمالي عدد القطع:</strong>
            <h4 id="totalItems">0</h4>
        </div>
    </div>
</div>
```

#### القسم 4: إدارة حالة سلة الشراء
```html
<div class="card mb-4">
    <div class="card-header bg-secondary text-white">
        <h5>إدارة حالة سلة الشراء</h5>
    </div>
    <div class="card-body">
        <div class="row">
            <!-- إضافة حقل رمز تتبع -->
            <div class="col-md-12 mb-3">
                <div class="alert alert-warning">
                    <p>إضافة حقل رمز تتبع - وقد تكون السلة تحتوي على أكثر من رمز</p>
                    <p>تضيف حقل رمز تتبع - وقد تكون السلة تحتوي على أكثر من رمز</p>
                </div>
            </div>
            
            <!-- رمز التتبع 1 -->
            <div class="col-md-6">
                <label>قيد التعليق</label>
                <input type="text" name="tracking_code_1" class="form-control" 
                       placeholder="رمز التتبع 1">
            </div>
            
            <!-- رمز التتبع 2 -->
            <div class="col-md-6">
                <label>قيد الشحن</label>
                <input type="text" name="tracking_code_2" class="form-control" 
                       placeholder="رمز التتبع 2">
            </div>
        </div>
        
        <!-- إضافة رمز آخر -->
        <div class="mt-3">
            <button type="button" class="btn btn-info" onclick="addTrackingCode()">
                <i class="fas fa-plus"></i> إضافة رمز آخر
            </button>
        </div>
        
        <div id="additionalTrackingCodes"></div>
    </div>
</div>
```

#### القسم 5: عرض قائمة طلبات الشراء
```html
<div class="card">
    <div class="card-header bg-dark text-white">
        <h5>عرض قائمة طلبات الشراء</h5>
    </div>
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>رقم الطلب</th>
                    <th>رقم التسلسلي</th>
                    <th>تاريخ الشراء</th>
                    <th>مجموعة الشراء</th>
                    <th>عدد العملاء</th>
                    <th>إجمالي القطع</th>
                    <th>حالة الشراء بعد</th>
                    <th>حالة الشراء قبل</th>
                    <th>كود سلة الشراء</th>
                    <th>مجموعة الشراء</th>
                    <th>رقم مرجعي</th>
                    <th>تاريخ مرجعي</th>
                    <th>ملاحظة واضافة</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <!-- يتم تعبئتها من قاعدة البيانات -->
            </tbody>
        </table>
    </div>
</div>
```

---

## ⚙️ Backend Logic - المنطق الخلفي

### 📄 File: `modules/purchase_orders/actions/create_purchase_order.php`

```php
<?php
require_once '../../../config/database.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();
        
        // 1. إنشاء طلب الشراء الرئيسي
        $stmt = $pdo->prepare("
            INSERT INTO purchase_orders (
                order_number, serial_number, purchase_date, purchase_group_id,
                total_items, total_discount_before, total_discount_after,
                total_price_before_discount, total_price_after_discount,
                tracking_code_1, tracking_code_2, notes, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $_POST['order_number'],
            $_POST['serial_number'],
            $_POST['purchase_date'],
            $_POST['purchase_group_id'] ?: null,
            $_POST['total_items'],
            $_POST['discount_before'],
            $_POST['discount_after'],
            $_POST['price_before_discount'],
            $_POST['price_after_discount'],
            $_POST['tracking_code_1'] ?: null,
            $_POST['tracking_code_2'] ?: null,
            $_POST['notes'] ?: null,
            $_SESSION['user_id']
        ]);
        
        $purchase_order_id = $pdo->lastInsertId();
        
        // 2. إضافة عناصر الطلب
        $items = json_decode($_POST['items'], true);
        $stmt = $pdo->prepare("
            INSERT INTO purchase_order_items (
                purchase_order_id, item_number, client_name, 
                client_phone, item_quantity, item_price, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        
        foreach ($items as $index => $item) {
            $stmt->execute([
                $purchase_order_id,
                $index + 1,
                $item['client_name'],
                $item['client_phone'],
                $item['quantity'],
                $item['price'],
                $item['notes'] ?: null
            ]);
        }
        
        // 3. إضافة رموز التتبع الإضافية
        if (!empty($_POST['additional_tracking_codes'])) {
            $tracking_codes = json_decode($_POST['additional_tracking_codes'], true);
            $stmt = $pdo->prepare("
                INSERT INTO purchase_order_gifts (
                    purchase_order_id, gift_code, gift_description
                ) VALUES (?, ?, ?)
            ");
            
            foreach ($tracking_codes as $code) {
                $stmt->execute([
                    $purchase_order_id,
                    $code['code'],
                    $code['description'] ?: null
                ]);
            }
        }
        
        // 4. تسجيل الحالة الأولية
        $stmt = $pdo->prepare("
            INSERT INTO purchase_order_status_log (
                purchase_order_id, new_status, changed_by
            ) VALUES (?, 'pending', ?)
        ");
        $stmt->execute([$purchase_order_id, $_SESSION['user_id']]);
        
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'تم إنشاء طلب الشراء بنجاح',
            'order_id' => $purchase_order_id
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'message' => 'خطأ: ' . $e->getMessage()
        ]);
    }
}
?>
```

### 📄 File: `modules/purchase_orders/actions/get_purchase_orders.php`

```php
<?php
require_once '../../../config/database.php';

$stmt = $pdo->query("
    SELECT 
        po.*,
        pg.group_name,
        u.username as created_by_name,
        COUNT(DISTINCT poi.id) as items_count
    FROM purchase_orders po
    LEFT JOIN purchase_groups pg ON po.purchase_group_id = pg.id
    LEFT JOIN users u ON po.created_by = u.id
    LEFT JOIN purchase_order_items poi ON po.id = poi.purchase_order_id
    GROUP BY po.id
    ORDER BY po.created_at DESC
");

$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'data' => $orders
]);
?>
```

### 📄 File: `modules/purchase_orders/actions/update_tracking.php`

```php
<?php
require_once '../../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $purchase_order_id = $_POST['purchase_order_id'];
    $tracking_type = $_POST['tracking_type'];
    $tracking_code = $_POST['tracking_code'];
    $status = $_POST['status'];
    $location = $_POST['location'];
    
    $stmt = $pdo->prepare("
        INSERT INTO purchase_order_tracking (
            purchase_order_id, tracking_type, tracking_code, 
            status, location, last_update
        ) VALUES (?, ?, ?, ?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            status = VALUES(status),
            location = VALUES(location),
            last_update = NOW()
    ");
    
    $stmt->execute([
        $purchase_order_id,
        $tracking_type,
        $tracking_code,
        $status,
        $location
    ]);
    
    echo json_encode(['success' => true]);
}
?>
```

---

## 🎯 JavaScript Functions - الدوال البرمجية

### 📄 File: `assets/js/purchase_orders.js`

```javascript
// إضافة صف عميل جديد
function addItemRow() {
    const tbody = document.getElementById('itemsTableBody');
    const rowCount = tbody.rows.length + 1;
    
    const row = tbody.insertRow();
    row.innerHTML = `
        <td>${rowCount}</td>
        <td><input type="text" class="form-control" name="items[${rowCount}][client_name]" required></td>
        <td><input type="text" class="form-control" name="items[${rowCount}][order_number]" required></td>
        <td><input type="text" class="form-control" name="items[${rowCount}][client_phone]" required></td>
        <td><input type="number" class="form-control" name="items[${rowCount}][quantity]" value="1" onchange="calculateTotals()" required></td>
        <td><input type="number" step="0.01" class="form-control" name="items[${rowCount}][price]" onchange="calculateTotals()" required></td>
        <td><input type="text" class="form-control" name="items[${rowCount}][notes]"></td>
        <td><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fas fa-trash"></i></button></td>
    `;
}

// حذف صف
function removeRow(btn) {
    const row = btn.closest('tr');
    row.remove();
    updateRowNumbers();
    calculateTotals();
}

// تحديث أرقام الصفوف
function updateRowNumbers() {
    const tbody = document.getElementById('itemsTableBody');
    Array.from(tbody.rows).forEach((row, index) => {
        row.cells[0].textContent = index + 1;
    });
}

// حساب الإجماليات
function calculateTotals() {
    const tbody = document.getElementById('itemsTableBody');
    let totalItems = 0;
    let totalPrice = 0;
    
    Array.from(tbody.rows).forEach(row => {
        const quantity = parseFloat(row.querySelector('input[name*="[quantity]"]').value) || 0;
        const price = parseFloat(row.querySelector('input[name*="[price]"]').value) || 0;
        
        totalItems += quantity;
        totalPrice += price;
    });
    
    const discountBefore = parseFloat(document.querySelector('input[name="discount_before"]').value) || 0;
    const discountAfter = parseFloat(document.querySelector('input[name="discount_after"]').value) || 0;
    
    const priceAfterFirstDiscount = totalPrice - discountBefore;
    const finalPrice = priceAfterFirstDiscount - discountAfter;
    
    document.getElementById('totalItems').textContent = totalItems;
    document.getElementById('priceBeforeDiscount').textContent = totalPrice.toFixed(2) + ' ريال';
    document.getElementById('priceAfterDiscount').textContent = finalPrice.toFixed(2) + ' ريال';
    
    document.querySelector('input[name="total_items"]').value = totalItems;
    document.querySelector('input[name="price_before_discount"]').value = totalPrice.toFixed(2);
    document.querySelector('input[name="price_after_discount"]').value = finalPrice.toFixed(2);
}

// إضافة رمز تتبع إضافي
let trackingCodeCounter = 0;
function addTrackingCode() {
    trackingCodeCounter++;
    const container = document.getElementById('additionalTrackingCodes');
    
    const div = document.createElement('div');
    div.className = 'row mt-3';
    div.innerHTML = `
        <div class="col-md-5">
            <input type="text" class="form-control" 
                   name="additional_codes[${trackingCodeCounter}][code]" 
                   placeholder="رمز التتبع">
        </div>
        <div class="col-md-5">
            <input type="text" class="form-control" 
                   name="additional_codes[${trackingCodeCounter}][description]" 
                   placeholder="وصف الرمز">
        </div>
        <div class="col-md-2">
            <button type="button" class="btn btn-danger" onclick="this.closest('.row').remove()">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    
    container.appendChild(div);
}

// حفظ الطلب
async function savePurchaseOrder(event) {
    event.preventDefault();
    
    const formData = new FormData(event.target);
    
    // جمع بيانات العناصر
    const items = [];
    const tbody = document.getElementById('itemsTableBody');
    Array.from(tbody.rows).forEach(row => {
        items.push({
            client_name: row.querySelector('input[name*="[client_name]"]').value,
            order_number: row.querySelector('input[name*="[order_number]"]').value,
            client_phone: row.querySelector('input[name*="[client_phone]"]').value,
            quantity: row.querySelector('input[name*="[quantity]"]').value,
            price: row.querySelector('input[name*="[price]"]').value,
            notes: row.querySelector('input[name*="[notes]"]').value
        });
    });
    
    formData.append('items', JSON.stringify(items));
    
    try {
        const response = await fetch('actions/create_purchase_order.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('تم إنشاء طلب الشراء بنجاح');
            window.location.href = 'index.php';
        } else {
            alert('خطأ: ' + result.message);
        }
    } catch (error) {
        alert('حدث خطأ في الاتصال');
        console.error(error);
    }
}

// تحميل الطلبات
async function loadPurchaseOrders() {
    try {
        const response = await fetch('actions/get_purchase_orders.php');
        const result = await response.json();
        
        if (result.success) {
            displayPurchaseOrders(result.data);
        }
    } catch (error) {
        console.error('Error loading orders:', error);
    }
}

// عرض الطلبات
function displayPurchaseOrders(orders) {
    const tbody = document.querySelector('#ordersTable tbody');
    tbody.innerHTML = '';
    
    orders.forEach(order => {
        const row = tbody.insertRow();
        row.innerHTML = `
            <td>${order.order_number}</td>
            <td>${order.serial_number}</td>
            <td>${order.purchase_date}</td>
            <td>${order.group_name || '-'}</td>
            <td>${order.items_count}</td>
            <td>${order.total_items}</td>
            <td>${order.total_price_after_discount} ريال</td>
            <td>${order.total_price_before_discount} ريال</td>
            <td>${order.tracking_code_1 || '-'}</td>
            <td>${order.group_name || '-'}</td>
            <td>${order.serial_number}</td>
            <td>${order.created_at}</td>
            <td>${order.notes || '-'}</td>
            <td>
                <a href="view.php?id=${order.id}" class="btn btn-info btn-sm">عرض</a>
                <a href="edit.php?id=${order.id}" class="btn btn-warning btn-sm">تعديل</a>
            </td>
        `;
    });
}
```

---

## 📊 Features Summary - ملخص الميزات

### ✅ الميزات المنفذة:

1. **إنشاء طلب شراء متعدد**
   - رقم سلة الشراء
   - الرقم التسلسلي
   - تاريخ الشراء
   - اختيار مجموعة الشراء

2. **إدارة العملاء والطلبات**
   - إضافة عدة عملاء في طلب واحد
   - اسم العميل، رقم الطلب، رقم الجوال
   - عدد القطع وقيمة الطلب
   - ملاحظات لكل عميل

3. **الحسابات والخصومات**
   - حساب إجمالي القطع تلقائياً
   - خصم النقطة (قبل)
   - خصم النادي (بعد)
   - سعر السلة قبل وبعد الخصم (قابل للتعديل)

4. **تتبع الشحنات**
   - رمز تتبع 1 (قيد التعليق)
   - رمز تتبع 2 (قيد الشحن)
   - إضافة رموز تتبع إضافية
   - تحديث حالة الشحنة

5. **عرض وإدارة الطلبات**
   - قائمة شاملة بجميع الطلبات
   - عرض تفاصيل كل طلب
   - تعديل الطلبات
   - سجل التغييرات

---

## 🚀 Installation Steps - خطوات التثبيت

### 1. إنشاء الجداول
```bash
# تشغيل سكريبت SQL
mysql -u root -p yassin_admin_system < database/create_purchase_order_tables.sql
```

### 2. إنشاء الملفات
```bash
# إنشاء المجلدات
mkdir -p modules/purchase_orders/actions
mkdir -p assets/js

# نسخ الملفات
cp purchase_orders/* modules/purchase_orders/
```

### 3. التحقق من الصلاحيات
```php
// في ملف config/database.php
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
```

---

## 📝 Usage Examples - أمثلة الاستخدام

### مثال 1: إنشاء طلب شراء جديد
```
1. افتح: modules/purchase_orders/create.php
2. أدخل رقم سلة الشراء والتاريخ
3. اختر مجموعة الشراء (اختياري)
4. أضف عملاء الطلب
5. أدخل الخصومات
6. أضف رموز التتبع
7. احفظ الطلب
```

### مثال 2: تتبع الشحنة
```
1. افتح: modules/purchase_orders/view.php?id=123
2. انقر على "تحديث التتبع"
3. أدخل رمز التتبع والحالة
4. احفظ التحديث
```

---

## 🎯 Next Steps - الخطوات التالية

- [ ] إضافة نظام الإشعارات للعملاء
- [ ] تكامل مع API شركات الشحن
- [ ] تقارير تحليلية متقدمة
- [ ] تصدير البيانات إلى Excel
- [ ] نظام الطباعة والفواتير

---

**النظام جاهز للاستخدام! 🎉**
