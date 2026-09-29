-- ========================================
-- TAKSORIDE PERMISSIONS SYSTEM DATABASE
-- ========================================

-- 1. Create permissions table
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `module_name` VARCHAR(100) NOT NULL COMMENT 'Module name (orders, baskets, etc)',
  `module_name_ar` VARCHAR(100) NOT NULL COMMENT 'Arabic name',
  `module_route` VARCHAR(200) NOT NULL COMMENT 'URL path',
  `icon` VARCHAR(50) DEFAULT NULL COMMENT 'Icon class',
  `display_order` INT DEFAULT 0 COMMENT 'Order in sidebar',
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_module` (`module_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Create user permissions table (many-to-many)
CREATE TABLE IF NOT EXISTS `user_permissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `permission_id` INT NOT NULL,
  `can_view` TINYINT(1) DEFAULT 0 COMMENT 'Can view module',
  `can_create` TINYINT(1) DEFAULT 0 COMMENT 'Can create records',
  `can_edit` TINYINT(1) DEFAULT 0 COMMENT 'Can edit records',
  `can_delete` TINYINT(1) DEFAULT 0 COMMENT 'Can delete records',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_user_permission` (`user_id`, `permission_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`permission_id`) REFERENCES `permissions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Insert default modules/permissions
INSERT INTO `permissions` (`module_name`, `module_name_ar`, `module_route`, `icon`, `display_order`) VALUES
('dashboard', 'لوحة التحكم', '/index.php', 'fas fa-home', 1),
('orders', 'إدارة الطلبات', '/modules/orders/index.php', 'fas fa-shopping-cart', 2),
('baskets', 'سلال الشراء', '/modules/purchases/show_baskets.php', 'fas fa-shopping-basket', 3),
('purchases', 'مجموعات الشراء', '/modules/purchases/groups/index.php', 'fas fa-layer-group', 4),
('customers', 'إدارة العملاء', '/modules/customers/index.php', 'fas fa-users', 5),
('inventory', 'المخزون', '/modules/inventory/index.php', 'fas fa-boxes', 6),
('financial', 'الحسابات المالية', '/modules/financial/index.php', 'fas fa-dollar-sign', 7),
('reports', 'التقارير', '/modules/reports/index.php', 'fas fa-chart-bar', 8),
('settings', 'الإعدادات', '/modules/settings/index.php', 'fas fa-cog', 9),
('employees', 'إدارة الموظفين', '/modules/financial/employee_permissions.php', 'fas fa-user-tie', 10);

-- 4. Add is_admin column to users table if not exists
ALTER TABLE `users` 
ADD COLUMN IF NOT EXISTS `is_admin` TINYINT(1) DEFAULT 0 COMMENT '1 = Admin with all permissions';

-- 5. Create function to check user permission
DELIMITER $$
CREATE FUNCTION IF NOT EXISTS check_user_permission(
    p_user_id INT,
    p_module_name VARCHAR(100),
    p_action VARCHAR(20)
) RETURNS TINYINT(1)
DETERMINISTIC
BEGIN
    DECLARE has_permission TINYINT(1) DEFAULT 0;
    DECLARE is_admin_user TINYINT(1) DEFAULT 0;
    
    -- Check if user is admin
    SELECT is_admin INTO is_admin_user FROM users WHERE id = p_user_id LIMIT 1;
    
    IF is_admin_user = 1 THEN
        RETURN 1;
    END IF;
    
    -- Check specific permission
    SELECT 
        CASE p_action
            WHEN 'view' THEN can_view
            WHEN 'create' THEN can_create
            WHEN 'edit' THEN can_edit
            WHEN 'delete' THEN can_delete
            ELSE 0
        END INTO has_permission
    FROM user_permissions up
    JOIN permissions p ON up.permission_id = p.id
    WHERE up.user_id = p_user_id 
      AND p.module_name = p_module_name
      AND p.is_active = 1
    LIMIT 1;
    
    RETURN IFNULL(has_permission, 0);
END$$
DELIMITER ;

-- 6. Sample data: Give admin user all permissions
-- UPDATE users SET is_admin = 1 WHERE id = 1;

-- 7. Create index for performance
CREATE INDEX idx_user_permissions_user ON user_permissions(user_id);
CREATE INDEX idx_permissions_module ON permissions(module_name);
CREATE INDEX idx_permissions_active ON permissions(is_active);
