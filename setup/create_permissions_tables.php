<?php
require_once '../config/database.php';

try {
    // Create permissions table
    $db->exec("
        CREATE TABLE IF NOT EXISTS permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            permission_name VARCHAR(100) NOT NULL UNIQUE,
            permission_key VARCHAR(100) NOT NULL UNIQUE,
            module VARCHAR(50) NOT NULL,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Create user_permissions table
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_permissions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            permission_id INT NOT NULL,
            granted_by INT NOT NULL,
            granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
            FOREIGN KEY (granted_by) REFERENCES users(id),
            UNIQUE KEY unique_user_permission (user_id, permission_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    
    // Insert default permissions
    $permissions = [
        // Financial Module
        ['إدارة الحسابات المالية', 'financial.accounts.manage', 'financial', 'إضافة وتعديل وحذف الحسابات المالية'],
        ['عرض الحسابات المالية', 'financial.accounts.view', 'financial', 'عرض قائمة الحسابات المالية'],
        ['إدارة المعاملات المالية', 'financial.transactions.manage', 'financial', 'إضافة وتعديل وحذف المعاملات المالية'],
        ['عرض المعاملات المالية', 'financial.transactions.view', 'financial', 'عرض قائمة المعاملات المالية'],
        ['عرض التقارير المالية', 'financial.reports.view', 'financial', 'الوصول إلى التقارير المالية'],
        ['تصدير التقارير المالية', 'financial.reports.export', 'financial', 'تصدير التقارير المالية'],
        
        // Inventory Module
        ['إدارة المخزون', 'inventory.manage', 'inventory', 'إضافة وتعديل وحذف المنتجات'],
        ['عرض المخزون', 'inventory.view', 'inventory', 'عرض قائمة المنتجات'],
        ['إدارة الموردين', 'suppliers.manage', 'inventory', 'إدارة الموردين'],
        
        // Sales Module
        ['إدارة المبيعات', 'sales.manage', 'sales', 'إضافة وتعديل المبيعات'],
        ['عرض المبيعات', 'sales.view', 'sales', 'عرض قائمة المبيعات'],
        ['حذف المبيعات', 'sales.delete', 'sales', 'حذف المبيعات'],
        
        // Purchases Module
        ['إدارة المشتريات', 'purchases.manage', 'purchases', 'إضافة وتعديل المشتريات'],
        ['عرض المشتريات', 'purchases.view', 'purchases', 'عرض قائمة المشتريات'],
        ['حذف المشتريات', 'purchases.delete', 'purchases', 'حذف المشتريات'],
        
        // Customers Module
        ['إدارة العملاء', 'customers.manage', 'customers', 'إضافة وتعديل وحذف العملاء'],
        ['عرض العملاء', 'customers.view', 'customers', 'عرض قائمة العملاء'],
        
        // Users & Permissions
        ['إدارة المستخدمين', 'users.manage', 'users', 'إضافة وتعديل وحذف المستخدمين'],
        ['إدارة الصلاحيات', 'permissions.manage', 'users', 'منح وإزالة الصلاحيات'],
        ['عرض المستخدمين', 'users.view', 'users', 'عرض قائمة المستخدمين'],
        
        // Settings
        ['إدارة الإعدادات', 'settings.manage', 'settings', 'تعديل إعدادات النظام'],
    ];
    
    $stmt = $db->prepare("
        INSERT IGNORE INTO permissions (permission_name, permission_key, module, description)
        VALUES (?, ?, ?, ?)
    ");
    
    foreach ($permissions as $permission) {
        $stmt->execute($permission);
    }
    
    echo "✅ تم إنشاء جداول الصلاحيات بنجاح!<br>";
    echo "✅ تم إضافة " . count($permissions) . " صلاحية افتراضية<br>";
    
} catch (PDOException $e) {
    echo "❌ خطأ: " . $e->getMessage();
}
?>
