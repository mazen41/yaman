# 🇾🇪 Yemen Phone Number System - نظام أرقام الهواتف اليمنية

## 📋 Overview - نظرة عامة

تم تحديث النظام بالكامل لدعم أرقام الهواتف اليمنية بصيغة موحدة ومعيارية.

---

## 📱 Phone Number Format - صيغة رقم الهاتف

### الصيغة القياسية:

**Mobile (الجوال):**
```
+967 777 123 456
```
- رمز الدولة: +967
- 9 أرقام
- يبدأ بالرقم 7

**Landline (الأرضي):**
```
+967 1 234 567
```
- رمز الدولة: +967
- 7 أرقام
- يبدأ بالأرقام 1-6

---

## 🏢 Yemen Mobile Operators - شركات الاتصالات اليمنية

### 1. Yemen Mobile (Sabafon) - سبأفون
**Prefixes:** 77X, 73X
- 770-779: سبأفون
- 730-739: سبأفون

### 2. MTN Yemen
**Prefixes:** 78X
- 780-789: MTN

### 3. Yemen Mobile (Y) - واي
**Prefixes:** 79X
- 790-799: واي

### 4. YOU (Aden Net) - يو
**Prefixes:** 70X
- 700-709: يو

### 5. HiTS-Unitel
**Prefixes:** 71X
- 710-719: HiTS

---

## 🛠️ Files Created - الملفات المنشأة

### 1. Phone Utilities Library
**File:** `includes/phone_utils.php`

**Functions:**
- `formatYemenPhone($phone)` - تنسيق الرقم
- `validateYemenPhone($phone)` - التحقق من صحة الرقم
- `getCleanYemenPhone($phone)` - الحصول على الرقم النظيف
- `formatPhoneForWhatsApp($phone)` - تنسيق للواتساب
- `getPhoneLink($phone, $whatsapp)` - رابط قابل للنقر
- `getYemenOperator($phone)` - معرفة الشركة
- `yemenPhoneInput($name, $value)` - حقل إدخال HTML

### 2. Database Migration Script
**File:** `setup/update_phone_numbers_yemen.php`

**What it does:**
- Updates all existing phone numbers
- Formats to Yemen standard
- Updates multiple tables:
  - customers
  - suppliers
  - users
  - shipping_companies

---

## 🚀 How to Use - كيفية الاستخدام

### Step 1: Update Existing Numbers
```
http://localhost/yassin-admin-system/setup/update_phone_numbers_yemen.php
```

This will:
- ✅ Convert all existing numbers to Yemen format
- ✅ Add +967 country code
- ✅ Format consistently
- ✅ Update all tables

### Step 2: Include in Your Files
```php
<?php
require_once 'includes/phone_utils.php';

// Format a phone number
$formatted = formatYemenPhone('777123456');
// Result: +967 777 123 456

// Validate
if (validateYemenPhone($phone)) {
    // Valid Yemen number
}

// Get WhatsApp link
echo getPhoneLink($phone, true);
// Result: <a href="https://wa.me/967777123456">...</a>
?>
```

### Step 3: Use in Forms
```php
<?php
// Simple input
echo yemenPhoneInput('mobile_number', $customer['mobile_number'], true, 'رقم الجوال');

// Or manual:
?>
<input type="tel" 
       name="mobile_number" 
       placeholder="+967 XXX XXX XXX"
       pattern="^(\+967|967|0)?[0-9]{7,9}$">
```

### Step 4: JavaScript Validation
```javascript
<?php echo getYemenPhoneValidationJS(); ?>

// Then use:
if (validateYemenPhone(phoneInput.value)) {
    // Valid
}

// Format on input
phoneInput.value = formatYemenPhone(phoneInput.value);
```

---

## 📊 Database Schema Updates

### Customers Table
```sql
mobile_number VARCHAR(20)    -- +967 777 123 456
whatsapp_number VARCHAR(20)  -- +967 777 123 456
phone VARCHAR(20)            -- +967 1 234 567
```

### Suppliers Table
```sql
phone VARCHAR(20)   -- +967 777 123 456
mobile VARCHAR(20)  -- +967 777 123 456
```

### Users Table
```sql
phone VARCHAR(20)   -- +967 777 123 456
```

### Shipping Companies Table
```sql
phone VARCHAR(20)   -- +967 777 123 456
mobile VARCHAR(20)  -- +967 777 123 456
```

---

## 🎯 Examples - أمثلة

### Example 1: Format Phone Number
```php
$phone = '0777123456';
$formatted = formatYemenPhone($phone);
// Output: +967 777 123 456
```

### Example 2: Validate Phone Number
```php
$phone = '+967 777 123 456';
if (validateYemenPhone($phone)) {
    echo 'Valid Yemen number';
}
```

### Example 3: WhatsApp Link
```php
$phone = '777123456';
echo getPhoneLink($phone, true);
// Output: <a href="https://wa.me/967777123456">
//           <i class="fab fa-whatsapp"></i> +967 777 123 456
//         </a>
```

### Example 4: Get Operator
```php
$phone = '777123456';
$operator = getYemenOperator($phone);
// Output: Yemen Mobile (Sabafon)
```

### Example 5: Clean Number for API
```php
$phone = '+967 777 123 456';
$clean = getCleanYemenPhone($phone);
// Output: 967777123456
```

---

## 🔧 Integration Guide - دليل التكامل

### In Customer Forms:
```php
<?php
require_once '../../includes/phone_utils.php';

// When displaying
echo formatYemenPhone($customer['mobile_number']);

// When saving
$mobile = getCleanYemenPhone($_POST['mobile_number']);
// Save to database

// Validation
if (!validateYemenPhone($_POST['mobile_number'])) {
    $error = 'رقم الهاتف غير صحيح';
}
?>
```

### In Order Forms:
```php
<?php
// Display customer phone
echo getPhoneLink($order['customer_mobile'], false);

// Display WhatsApp link
echo getPhoneLink($order['customer_whatsapp'], true);
?>
```

### In Reports:
```php
<?php
// Format all phone numbers
foreach ($customers as &$customer) {
    $customer['mobile_formatted'] = formatYemenPhone($customer['mobile_number']);
    $customer['operator'] = getYemenOperator($customer['mobile_number']);
}
?>
```

---

## ✅ Validation Rules - قواعد التحقق

### Mobile Numbers (9 digits):
- Must start with 7
- Format: 7XX XXX XXX
- Examples: 777123456, 780123456, 790123456

### Landline Numbers (7 digits):
- Must start with 1-6
- Format: X XXX XXX
- Examples: 1234567, 2345678

### Country Code:
- Always +967
- Can be entered as: +967, 967, or 0 (will be converted)

---

## 🎨 Display Examples - أمثلة العرض

### In Tables:
```html
<td>
    <i class="fas fa-phone text-blue-600"></i>
    +967 777 123 456
    <br>
    <small class="text-gray-500">Yemen Mobile (Sabafon)</small>
</td>
```

### With Links:
```html
<a href="tel:+967777123456" class="text-blue-600">
    <i class="fas fa-phone"></i> +967 777 123 456
</a>

<a href="https://wa.me/967777123456" class="text-green-600">
    <i class="fab fa-whatsapp"></i> WhatsApp
</a>
```

---

## 🔍 Testing - الاختبار

### Test Cases:
```php
// Test 1: Format with zeros
formatYemenPhone('0777123456');
// Expected: +967 777 123 456

// Test 2: Format with country code
formatYemenPhone('967777123456');
// Expected: +967 777 123 456

// Test 3: Format with +
formatYemenPhone('+967777123456');
// Expected: +967 777 123 456

// Test 4: Validate mobile
validateYemenPhone('777123456');
// Expected: true

// Test 5: Validate landline
validateYemenPhone('1234567');
// Expected: true

// Test 6: Invalid number
validateYemenPhone('123');
// Expected: false
```

---

## 📱 WhatsApp Integration

### Send Message:
```php
$phone = getCleanYemenPhone($customer['mobile_number']);
$message = urlencode('مرحباً بك في نظامنا');
$url = "https://wa.me/$phone?text=$message";

echo "<a href='$url' target='_blank'>إرسال رسالة واتساب</a>";
```

### WhatsApp Business API:
```php
$phone = getCleanYemenPhone($customer['mobile_number']);
// Use $phone with WhatsApp Business API
// Format: 967777123456 (no + or spaces)
```

---

## 🎉 Summary - الخلاصة

### What Was Done:
✅ Created phone utilities library
✅ Created database migration script
✅ Standardized format: +967 XXX XXX XXX
✅ Added validation functions
✅ Added formatting functions
✅ Added operator detection
✅ Added WhatsApp integration
✅ Added clickable links
✅ Added JavaScript validation

### Benefits:
✅ Consistent phone number format
✅ Easy validation
✅ WhatsApp integration ready
✅ Operator identification
✅ Clean database
✅ Better user experience
✅ International standard compliance

---

## 🚀 Next Steps

1. **Run Migration:**
   ```
   http://localhost/yassin-admin-system/setup/update_phone_numbers_yemen.php
   ```

2. **Include in All Forms:**
   ```php
   require_once 'includes/phone_utils.php';
   ```

3. **Update Display Code:**
   ```php
   echo formatYemenPhone($phone);
   ```

4. **Add Validation:**
   ```php
   if (!validateYemenPhone($phone)) {
       // Show error
   }
   ```

---

**🇾🇪 النظام جاهز للاستخدام مع أرقام الهواتف اليمنية!**
