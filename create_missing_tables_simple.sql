-- Simple SQL to create missing tables
-- Copy and paste this ENTIRE file into phpMyAdmin

-- 1. role_permissions
DROP TABLE IF EXISTS `role_permissions`;
CREATE TABLE `role_permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(10) unsigned NOT NULL,
  `permission_id` int(10) unsigned NOT NULL,
  `can_view` tinyint(1) DEFAULT 0,
  `can_add` tinyint(1) DEFAULT 0,
  `can_edit` tinyint(1) DEFAULT 0,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_role_permission` (`role_id`,`permission_id`),
  KEY `idx_role_view` (`role_id`,`can_view`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. permission_cache
DROP TABLE IF EXISTS `permission_cache`;
CREATE TABLE `permission_cache` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `cache_key` varchar(255) NOT NULL,
  `cache_value` text DEFAULT NULL,
  `expires_at` timestamp NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_cache` (`user_id`,`cache_key`),
  KEY `idx_expires` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. permission_audit_log
DROP TABLE IF EXISTS `permission_audit_log`;
CREATE TABLE `permission_audit_log` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `role_id` int(10) unsigned DEFAULT NULL,
  `permission_id` int(10) unsigned DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `changed_by` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user` (`user_id`),
  KEY `idx_role` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert sample data for manager role (role_id = 2)
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `can_view`, `can_add`, `can_edit`) VALUES
(2, 1, 1, 1, 1),
(2, 2, 1, 1, 0),
(2, 3, 1, 0, 1),
(2, 4, 1, 1, 0);

-- Insert sample data for employee role (role_id = 3)
INSERT INTO `role_permissions` (`role_id`, `permission_id`, `can_view`, `can_add`, `can_edit`) VALUES
(3, 1, 1, 0, 0),
(3, 2, 1, 0, 0);

SELECT 'Tables created successfully!' as Result;
