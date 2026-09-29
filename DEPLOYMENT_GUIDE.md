# 🚀 دليل رفع نظام إدارة ياسين على السيرفر

## 📋 المتطلبات الأساسية

### على السيرفر:
- ✅ PHP 7.4 أو أحدث
- ✅ MySQL 5.7 أو MariaDB 10.3 أو أحدث
- ✅ Apache أو Nginx
- ✅ phpMyAdmin (اختياري)
- ✅ SSL Certificate (موصى به)

---

## 📦 الخطوة 1: تحضير الملفات

### 1.1 ضغط الملفات
```bash
# في مجلد المشروع
zip -r yassin-admin-system.zip yassin-admin-system/
# أو
tar -czf yassin-admin-system.tar.gz yassin-admin-system/
```

**أو استخدم برنامج WinRAR/7-Zip على Windows**

### 1.2 الملفات المطلوبة
```
yassin-admin-system/
├── config/
│   └── database.php          ⚠️ سيتم تعديله
├── includes/
├── modules/
├── assets/
├── setup/                    ⚠️ مهم للإعداد الأولي
├── index.php
└── .htaccess                 ⚠️ مهم للأمان
```

---

## 🌐 الخطوة 2: رفع الملفات على السيرفر

### الطريقة 1: باستخدام FTP/SFTP

#### باستخدام FileZilla:
1. افتح FileZilla
2. أدخل معلومات الاتصال:
   - Host: `ftp.yourdomain.com` أو `sftp.yourdomain.com`
   - Username: اسم المستخدم
   - Password: كلمة المرور
   - Port: 21 (FTP) أو 22 (SFTP)

3. ارفع المجلد إلى:
   ```
   /public_html/
   أو
   /www/
   أو
   /htdocs/
   ```

### الطريقة 2: باستخدام cPanel

1. سجل دخول إلى cPanel
2. اذهب إلى **File Manager**
3. افتح مجلد `public_html`
4. اضغط **Upload**
5. ارفع ملف ZIP
6. انقر بزر الماوس الأيمن → **Extract**

---

## 🗄️ الخطوة 3: إعداد قاعدة البيانات

### 3.1 إنشاء قاعدة البيانات

#### باستخدام phpMyAdmin:
1. افتح phpMyAdmin
2. اضغط **New** أو **Databases**
3. أدخل اسم القاعدة: `yassin_admin`
4. اختر Collation: `utf8mb4_unicode_ci`
5. اضغط **Create**

#### باستخدام cPanel:
1. اذهب إلى **MySQL Databases**
2. أنشئ قاعدة بيانات جديدة
3. أنشئ مستخدم جديد
4. أضف المستخدم للقاعدة مع **All Privileges**

### 3.2 استيراد البيانات (إذا كان لديك backup)

```sql
-- في phpMyAdmin
1. اختر القاعدة
2. اضغط Import
3. اختر ملف SQL
4. اضغط Go
```

---

## ⚙️ الخطوة 4: تكوين الإعدادات

### 4.1 تعديل ملف database.php

افتح: `/config/database.php`

```php
<?php
// ⚠️ عدّل هذه القيم حسب معلومات السيرفر

$host = 'localhost';              // عادة localhost
$dbname = 'yassin_admin';         // اسم قاعدة البيانات
$username = 'your_db_username';   // اسم مستخدم القاعدة
$password = 'your_db_password';   // كلمة مرور القاعدة

try {
    $db = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
```

### 4.2 إنشاء ملف .htaccess (للأمان)

في المجلد الرئيسي: `/.htaccess`

```apache
# تفعيل Rewrite Engine
RewriteEngine On

# منع الوصول للملفات الحساسة
<FilesMatch "\.(sql|log|md|txt)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# حماية مجلد config
<Directory "config">
    Order allow,deny
    Deny from all
</Directory>

# حماية مجلد setup بعد الإعداد
<Directory "setup">
    Order allow,deny
    Deny from all
</Directory>

# تفعيل HTTPS (إذا كان متوفر)
RewriteCond %{HTTPS} off
RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# منع عرض محتوى المجلدات
Options -Indexes

# حماية من XSS
<IfModule mod_headers.c>
    Header set X-XSS-Protection "1; mode=block"
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
</IfModule>
```

---

## 🔧 الخطوة 5: تشغيل سكريبتات الإعداد

### 5.1 افتح المتصفح واذهب إلى:

```
https://yourdomain.com/setup/
```

### 5.2 شغّل السكريبتات بالترتيب:

#### 1. إنشاء الجداول الأساسية:
```
https://yourdomain.com/setup/create_basket_tables.php
```

#### 2. إضافة عمود المجموعة:
```
https://yourdomain.com/setup/add_group_column_to_baskets.php
```

#### 3. إنشاء جدول سجل الحالات:
```
https://yourdomain.com/setup/create_order_history_table.php
```

#### 4. إنشاء مجموعات للسلال الموجودة:
```
https://yourdomain.com/setup/create_groups_for_existing_baskets.php
```

---

## 🔐 الخطوة 6: الأمان

### 6.1 حذف أو حماية مجلد setup

**الطريقة 1: الحذف (موصى به)**
```bash
rm -rf setup/
```

**الطريقة 2: الحماية بكلمة مرور**

أنشئ ملف `/setup/.htaccess`:
```apache
AuthType Basic
AuthName "Restricted Access"
AuthUserFile /path/to/.htpasswd
Require valid-user
```

### 6.2 تعيين صلاحيات الملفات

```bash
# للمجلدات
chmod 755 -R yassin-admin-system/

# للملفات
find yassin-admin-system/ -type f -exec chmod 644 {} \;

# ملف config (قراءة فقط)
chmod 400 config/database.php

# مجلدات الرفع (إذا وجدت)
chmod 777 uploads/
```

### 6.3 تفعيل SSL

في cPanel:
1. اذهب إلى **SSL/TLS**
2. اختر **Let's Encrypt** (مجاني)
3. اختر الدومين
4. اضغط **Install**

---

## ✅ الخطوة 7: الاختبار

### 7.1 اختبر الصفحات الأساسية:

```
✅ https://yourdomain.com/
✅ https://yourdomain.com/login.php
✅ https://yourdomain.com/modules/orders/index.php
✅ https://yourdomain.com/modules/purchases/groups/index.php
```

### 7.2 اختبر الوظائف:

- ✅ تسجيل الدخول
- ✅ عرض الطلبات
- ✅ عرض مجموعات الشراء
- ✅ إنشاء سلة جديدة
- ✅ طباعة الفاتورة

---

## 🐛 حل المشاكل الشائعة

### المشكلة 1: خطأ 500 Internal Server Error

**الحل:**
```bash
# تحقق من ملف .htaccess
# تحقق من صلاحيات الملفات
# تحقق من error_log
tail -f /path/to/error_log
```

### المشكلة 2: لا يمكن الاتصال بقاعدة البيانات

**الحل:**
```php
// تحقق من معلومات الاتصال في config/database.php
// تأكد من أن المستخدم لديه صلاحيات
// جرب الاتصال من phpMyAdmin
```

### المشكلة 3: الصفحات لا تعمل (404)

**الحل:**
```apache
# تأكد من تفعيل mod_rewrite
# في .htaccess أضف:
RewriteEngine On
RewriteBase /
```

### المشكلة 4: مشاكل الترميز (العربية)

**الحل:**
```sql
-- في phpMyAdmin
ALTER DATABASE yassin_admin CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- لكل جدول
ALTER TABLE table_name CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

## 📊 الخطوة 8: النسخ الاحتياطي

### 8.1 نسخ احتياطي لقاعدة البيانات

```bash
# يومياً
mysqldump -u username -p yassin_admin > backup_$(date +%Y%m%d).sql

# أو من cPanel → phpMyAdmin → Export
```

### 8.2 نسخ احتياطي للملفات

```bash
# أسبوعياً
tar -czf backup_files_$(date +%Y%m%d).tar.gz yassin-admin-system/
```

### 8.3 جدولة النسخ الاحتياطي (Cron Job)

في cPanel → Cron Jobs:
```bash
# كل يوم الساعة 2 صباحاً
0 2 * * * /usr/bin/mysqldump -u username -p'password' yassin_admin > /backups/db_$(date +\%Y\%m\%d).sql
```

---

## 📈 الخطوة 9: التحسينات (اختياري)

### 9.1 تفعيل Caching

في `.htaccess`:
```apache
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpg "access plus 1 year"
    ExpiresByType image/jpeg "access plus 1 year"
    ExpiresByType image/gif "access plus 1 year"
    ExpiresByType image/png "access plus 1 year"
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
</IfModule>
```

### 9.2 تفعيل Gzip

```apache
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
</IfModule>
```

---

## 📞 الدعم

إذا واجهت أي مشاكل:

1. تحقق من ملف `error_log` في السيرفر
2. تحقق من Console في المتصفح (F12)
3. تأكد من جميع الصلاحيات صحيحة
4. تأكد من معلومات قاعدة البيانات صحيحة

---

## ✅ Checklist النهائي

- [ ] رفع الملفات على السيرفر
- [ ] إنشاء قاعدة البيانات
- [ ] تعديل ملف database.php
- [ ] إنشاء ملف .htaccess
- [ ] تشغيل سكريبتات الإعداد
- [ ] حماية/حذف مجلد setup
- [ ] تعيين صلاحيات الملفات
- [ ] تفعيل SSL
- [ ] اختبار جميع الوظائف
- [ ] إعداد النسخ الاحتياطي

---

## 🎉 تم!

الآن نظامك يعمل على السيرفر بنجاح!

**الرابط:**
```
https://yourdomain.com
```

**تسجيل الدخول:**
- استخدم بيانات المستخدم الموجودة في قاعدة البيانات

---

**📅 تاريخ آخر تحديث:** 2025-10-14
**📝 الإصدار:** 1.0
