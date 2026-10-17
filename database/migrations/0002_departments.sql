-- Migration: 0002_departments

-- +migrate UP

CREATE TABLE IF NOT EXISTS `departments` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `code` varchar(50) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_dept_tenant_code` (`tenant_id`,`code`),
  UNIQUE KEY `uk_dept_tenant_id` (`id`,`tenant_id`),
  KEY `idx_depts_tenant` (`tenant_id`),
  KEY `idx_depts_tenant_active` (`tenant_id`,`is_active`),
  CONSTRAINT `fk_depts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- +migrate DOWN

DROP TABLE IF EXISTS `departments`;
