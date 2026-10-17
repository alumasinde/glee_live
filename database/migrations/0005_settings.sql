-- Migration: 0005_settings

-- +migrate UP

CREATE TABLE IF NOT EXISTS `tenant_settings` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `setting_key` varchar(150) NOT NULL,
  `config_json` json NOT NULL,
  `setting_value` json DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ts_tenant_key` (`tenant_id`,`setting_key`),
  KEY `idx_ts_tenant` (`tenant_id`),
  CONSTRAINT `fk_ts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- +migrate DOWN

DROP TABLE IF EXISTS `tenant_settings`;
