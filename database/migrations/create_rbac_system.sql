-- ============================================
-- Complete RBAC System Migration
-- ============================================

-- 1. Create roles table (if not exists)
CREATE TABLE IF NOT EXISTS `roles` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) UNIQUE NOT NULL,
  `display_name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_name` (`name`),
  INDEX `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create permissions table (enhanced)
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `module_key` VARCHAR(50) NOT NULL,
  `module_name` VARCHAR(100) NOT NULL,
  `page_key` VARCHAR(100) NOT NULL,
  `page_name` VARCHAR(100) NOT NULL,
  `route_path` VARCHAR(255),
  `sidebar_icon` VARCHAR(50),
  `sort_order` INT DEFAULT 0,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_page` (`module_key`, `page_key`),
  INDEX `idx_module` (`module_key`),
  INDEX `idx_active` (`is_active`),
  INDEX `idx_sort` (`sort_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create role_permissions table (pivot with actions)
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `role_id` INT UNSIGNED NOT NULL,
  `permission_id` INT UNSIGNED NOT NULL,
  `can_view` TINYINT(1) DEFAULT 0,
  `can_add` TINYINT(1) DEFAULT 0,
  `can_edit` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_role_permission` (`role_id`, `permission_id`),
  INDEX `idx_role_view` (`role_id`, `can_view`),
  INDEX `idx_role_add` (`role_id`, `can_add`),
  INDEX `idx_role_edit` (`role_id`, `can_edit`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign keys separately (safer)
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
                  WHERE CONSTRAINT_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'role_permissions' 
                  AND CONSTRAINT_NAME = 'fk_rp_role');
SET @sql = IF(@fk_exists = 0, 
              'ALTER TABLE role_permissions ADD CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE', 
              'SELECT "FK role already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
                  WHERE CONSTRAINT_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'role_permissions' 
                  AND CONSTRAINT_NAME = 'fk_rp_permission');
SET @sql = IF(@fk_exists = 0, 
              'ALTER TABLE role_permissions ADD CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE', 
              'SELECT "FK permission already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 4. Add role_id to users table (if not exists)
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `role_id` INT UNSIGNED NULL AFTER `email`,
ADD INDEX IF NOT EXISTS `idx_role` (`role_id`);

-- Add foreign key if not exists
SET @fk_exists = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS 
                  WHERE CONSTRAINT_SCHEMA = DATABASE() 
                  AND TABLE_NAME = 'users' 
                  AND CONSTRAINT_NAME = 'fk_users_role');

SET @sql = IF(@fk_exists = 0, 
              'ALTER TABLE users ADD CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE SET NULL', 
              'SELECT "FK already exists"');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 5. Seed default roles
INSERT INTO `roles` (`name`, `display_name`, `description`) VALUES
('super_admin', 'مدير النظام', 'صلاحيات كاملة لجميع الأقسام'),
('manager', 'مدير', 'صلاحيات إدارية للأقسام الرئيسية'),
('employee', 'موظف', 'صلاحيات محدودة للعمليات اليومية'),
('accountant', 'محاسب', 'صلاحيات مالية ومحاسبية'),
('sales', 'مبيعات', 'صلاحيات إدارة الطلبات والعملاء')
ON DUPLICATE KEY UPDATE display_name = VALUES(display_name);

-- 6. Seed permissions for all modules
INSERT INTO `permissions` (`module_key`, `module_name`, `page_key`, `page_name`, `route_path`, `sidebar_icon`, `sort_order`) VALUES
-- Customers Module
('customers', 'إدارة العملاء', 'customers.index', 'قائمة العملاء', '/modules/customers/index.php', 'fas fa-users', 10),
('customers', 'إدارة العملاء', 'customers.create', 'إضافة عميل', '/modules/customers/create.php', NULL, 11),
('customers', 'إدارة العملاء', 'customers.edit', 'تعديل عميل', '/modules/customers/edit.php', NULL, 12),
('customers', 'إدارة العملاء', 'customers.view', 'عرض تفاصيل العميل', '/modules/customers/view_enhanced.php', NULL, 13),

-- Orders Module
('orders', 'إدارة الطلبات', 'orders.index', 'قائمة الطلبات', '/modules/orders/index.php', 'fas fa-shopping-cart', 20),
('orders', 'إدارة الطلبات', 'orders.create', 'إنشاء طلب', '/modules/orders/create.php', NULL, 21),
('orders', 'إدارة الطلبات', 'orders.edit', 'تعديل طلب', '/modules/orders/edit.php', NULL, 22),

-- Financial Module
('financial', 'المالية', 'financial.dashboard', 'لوحة المالية', '/modules/financial/index.php', 'fas fa-chart-line', 30),
('financial', 'المالية', 'financial.reports', 'التقارير المالية', '/modules/reports/index.php', NULL, 31),
('financial', 'المالية', 'financial.invoices', 'الفواتير', '/modules/invoices/index.php', NULL, 32),
('financial', 'المالية', 'financial.payments', 'المدفوعات', '/modules/payments/index.php', NULL, 33),

-- Purchases Module
('purchases', 'المشتريات', 'purchases.index', 'قائمة المشتريات', '/modules/purchases/index.php', 'fas fa-shopping-basket', 40),
('purchases', 'المشتريات', 'purchases.create', 'إضافة مشتريات', '/modules/purchases/create.php', NULL, 41),
('purchases', 'المشتريات', 'purchases.groups', 'مجموعات الشراء', '/modules/purchases/groups/index.php', NULL, 42),

-- Expenses Module
('expenses', 'المصروفات', 'expenses.index', 'قائمة المصروفات', '/modules/expenses/index.php', 'fas fa-money-bill-wave', 50),
('expenses', 'المصروفات', 'expenses.create', 'إضافة مصروف', '/modules/expenses/create.php', NULL, 51),

-- Reports Module
('reports', 'التقارير', 'reports.index', 'التقارير والطباعة', '/modules/reports/index.php', 'fas fa-file-alt', 60),
('reports', 'التقارير', 'reports.profit_loss', 'الأرباح والخسائر', '/modules/reports/profit_loss.php', NULL, 61),
('reports', 'التقارير', 'reports.balance_sheet', 'الميزانية العمومية', '/modules/reports/balance_sheet.php', NULL, 62),

-- Settings Module
('settings', 'الإعدادات', 'settings.users', 'إدارة المستخدمين', '/modules/users/index.php', 'fas fa-cog', 70),
('settings', 'الإعدادات', 'settings.permissions', 'إدارة الصلاحيات', '/modules/financial/employee-permissions.php', NULL, 71)

ON DUPLICATE KEY UPDATE 
  module_name = VALUES(module_name),
  page_name = VALUES(page_name),
  route_path = VALUES(route_path),
  sidebar_icon = VALUES(sidebar_icon),
  sort_order = VALUES(sort_order);

-- 7. Seed default role permissions (Manager role example)
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `can_view`, `can_add`, `can_edit`)
SELECT 
  (SELECT id FROM roles WHERE name = 'manager'),
  p.id,
  1, -- can_view
  CASE 
    WHEN p.page_key LIKE '%.create' THEN 1
    WHEN p.page_key LIKE '%.edit' THEN 0
    ELSE 0
  END, -- can_add
  CASE 
    WHEN p.page_key LIKE '%.edit' THEN 1
    WHEN p.page_key LIKE '%.index' THEN 1
    ELSE 0
  END -- can_edit
FROM permissions p
WHERE p.module_key IN ('customers', 'orders', 'financial')
ON DUPLICATE KEY UPDATE 
  can_view = VALUES(can_view),
  can_add = VALUES(can_add),
  can_edit = VALUES(can_edit);

-- 8. Seed employee role permissions (limited access)
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `can_view`, `can_add`, `can_edit`)
SELECT 
  (SELECT id FROM roles WHERE name = 'employee'),
  p.id,
  1, -- can_view
  CASE 
    WHEN p.page_key IN ('orders.create', 'customers.create') THEN 1
    ELSE 0
  END, -- can_add
  0 -- can_edit (no edit rights)
FROM permissions p
WHERE p.module_key IN ('customers', 'orders')
  AND p.page_key NOT LIKE '%settings%'
ON DUPLICATE KEY UPDATE 
  can_view = VALUES(can_view),
  can_add = VALUES(can_add),
  can_edit = VALUES(can_edit);

-- 9. Create permission cache table for performance
CREATE TABLE IF NOT EXISTS `permission_cache` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `cache_key` VARCHAR(255) NOT NULL,
  `cache_value` TEXT,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_cache` (`user_id`, `cache_key`),
  INDEX `idx_expires` (`expires_at`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Create audit log for permission changes
CREATE TABLE IF NOT EXISTS `permission_audit_log` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED,
  `role_id` INT UNSIGNED,
  `permission_id` INT UNSIGNED,
  `action` VARCHAR(50) NOT NULL,
  `old_value` TEXT,
  `new_value` TEXT,
  `changed_by` INT UNSIGNED,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_user` (`user_id`),
  INDEX `idx_role` (`role_id`),
  INDEX `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Migration Complete!
-- ============================================
