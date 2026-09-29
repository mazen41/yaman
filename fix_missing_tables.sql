-- ============================================
-- Fix Missing Tables - Run this in phpMyAdmin
-- ============================================

-- 1. Create role_permissions table
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

-- 2. Create permission_cache table
CREATE TABLE IF NOT EXISTS `permission_cache` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `cache_key` VARCHAR(255) NOT NULL,
  `cache_value` TEXT,
  `expires_at` TIMESTAMP NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `unique_cache` (`user_id`, `cache_key`),
  INDEX `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Create permission_audit_log table
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

-- 4. Seed default role permissions for manager role
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `can_view`, `can_add`, `can_edit`)
SELECT 
  2, -- manager role
  p.id,
  1, -- can_view
  CASE 
    WHEN p.page_key LIKE '%.create' THEN 1
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

-- 5. Seed default role permissions for employee role
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `can_view`, `can_add`, `can_edit`)
SELECT 
  3, -- employee role
  p.id,
  1, -- can_view only
  0, -- no add
  0  -- no edit
FROM permissions p
WHERE p.module_key IN ('customers', 'orders')
  AND p.page_key LIKE '%.index'
ON DUPLICATE KEY UPDATE 
  can_view = VALUES(can_view),
  can_add = VALUES(can_add),
  can_edit = VALUES(can_edit);

-- Done!
SELECT 'Missing tables created successfully!' as status;
