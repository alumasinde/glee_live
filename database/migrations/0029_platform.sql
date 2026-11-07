-- Migration: 0029_platform
--
-- Introduces the platform (provider) plane:
--   platform_users        separate identity for provider staff — never a tenant user
--   platform_settings     global feature flags + platform-restricted permissions
--   platform_audit_logs   append-only audit of provider actions
--   tenant_modules        per-tenant module entitlements (missing row = module default on)
--   modules.*             metadata that makes the existing permission catalogue
--                         also act as the feature catalogue: core / depends_on / order

-- +migrate UP

CREATE TABLE IF NOT EXISTS `platform_users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_superadmin` tinyint(1) NOT NULL DEFAULT '1',
  `last_login_at` datetime DEFAULT NULL,
  `tokens_invalid_before` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_platform_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `platform_settings` (
  `setting_key` varchar(150) NOT NULL,
  `config_json` json NOT NULL,
  `updated_by` bigint unsigned DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `platform_audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `platform_user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `subject_type` varchar(60) DEFAULT NULL,
  `subject_id` bigint unsigned DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pal_action` (`action`),
  KEY `idx_pal_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `tenant_modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `module_name` varchar(120) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT '1',
  `updated_by` bigint unsigned DEFAULT NULL,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tm_tenant_module` (`tenant_id`,`module_name`),
  KEY `idx_tm_tenant` (`tenant_id`),
  CONSTRAINT `fk_tm_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `modules`
  ADD COLUMN `is_core` tinyint(1) NOT NULL DEFAULT '0' AFTER `name`,
  ADD COLUMN `depends_on` varchar(120) DEFAULT NULL AFTER `is_core`,
  ADD COLUMN `sort_order` int NOT NULL DEFAULT '0' AFTER `depends_on`;

-- Modules a tenant can never lose: losing settings/roles/users would lock the
-- tenant out of its own administration, and audit is a compliance control.
UPDATE `modules` SET `is_core` = 1, `sort_order` = 0
 WHERE `name` IN ('dashboard', 'settings', 'roles', 'users', 'departments', 'audit');

-- Dependency graph derived from the schema (visits.visitor_id is NOT NULL,
-- visit_badges.visit_id is NOT NULL, approvals attach to a gatepass).
UPDATE `modules` SET `depends_on` = 'visitors', `sort_order` = 20 WHERE `name` = 'visits';
UPDATE `modules` SET `depends_on` = 'visits',   `sort_order` = 21 WHERE `name` = 'visitors';
UPDATE `modules` SET `depends_on` = 'visits',   `sort_order` = 22 WHERE `name` = 'badges';
UPDATE `modules` SET `sort_order` = 10 WHERE `name` = 'gatepass';
UPDATE `modules` SET `depends_on` = 'gatepass', `sort_order` = 11 WHERE `name` = 'gatepass_types';
UPDATE `modules` SET `depends_on` = 'gatepass', `sort_order` = 12 WHERE `name` = 'approval';
UPDATE `modules` SET `depends_on` = 'approval', `sort_order` = 73 WHERE `name` = 'workflows';
UPDATE `modules` SET `sort_order` = 30 WHERE `name` = 'notifications';
UPDATE `modules` SET `sort_order` = 40 WHERE `name` = 'reports';
UPDATE `modules` SET `sort_order` = 60 WHERE `name` = 'roles';
UPDATE `modules` SET `sort_order` = 61 WHERE `name` = 'users';
UPDATE `modules` SET `sort_order` = 62 WHERE `name` = 'departments';
UPDATE `modules` SET `sort_order` = 70 WHERE `name` = 'settings';
UPDATE `modules` SET `sort_order` = 71 WHERE `name` = 'gates';
UPDATE `modules` SET `sort_order` = 72 WHERE `name` = 'email';

-- +migrate DOWN

ALTER TABLE `modules`
  DROP COLUMN `sort_order`,
  DROP COLUMN `depends_on`,
  DROP COLUMN `is_core`;

DROP TABLE IF EXISTS `tenant_modules`;
DROP TABLE IF EXISTS `platform_audit_logs`;
DROP TABLE IF EXISTS `platform_settings`;
DROP TABLE IF EXISTS `platform_users`;
