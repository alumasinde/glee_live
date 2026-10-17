-- Migration: 0006_visitors

-- +migrate UP

CREATE TABLE IF NOT EXISTS `identification_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_idtype_tenant_name` (`tenant_id`,`name`),
  UNIQUE KEY `uk_idtype_tenant_id` (`id`,`tenant_id`),
  KEY `idx_idtypes_tenant` (`tenant_id`),
  CONSTRAINT `fk_idtypes_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `visitor_companies` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(255) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vc_tenant_name` (`tenant_id`,`name`),
  UNIQUE KEY `uk_vc_tenant_id` (`id`,`tenant_id`),
  KEY `idx_vc_tenant` (`tenant_id`),
  CONSTRAINT `fk_vc_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `visitors` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `first_name` varchar(120) NOT NULL,
  `last_name` varchar(120) NOT NULL,
  `id_type_id` bigint unsigned DEFAULT NULL,
  `id_number` varchar(100) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `company_id` bigint unsigned DEFAULT NULL,
  `risk_score` int NOT NULL DEFAULT '0',
  `is_blacklisted` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_by` bigint unsigned NOT NULL DEFAULT (0),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_visitor_tenant_id` (`id`,`tenant_id`),
  KEY `idx_visitors_tenant` (`tenant_id`),
  KEY `idx_visitor_phone` (`tenant_id`,`phone`),
  KEY `idx_visitors_name` (`tenant_id`,`last_name`,`first_name`),
  KEY `idx_visitor_id_lookup` (`tenant_id`,`id_type_id`,`id_number`),
  KEY `idx_visitor_blacklist` (`tenant_id`,`is_blacklisted`),
  KEY `fk_visitors_id_type` (`id_type_id`,`tenant_id`),
  KEY `fk_visitors_company` (`company_id`,`tenant_id`),
  CONSTRAINT `fk_visitors_company` FOREIGN KEY (`company_id`, `tenant_id`) REFERENCES `visitor_companies` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visitors_id_type` FOREIGN KEY (`id_type_id`, `tenant_id`) REFERENCES `identification_types` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visitors_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `visitor_watchlist` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `visitor_id` bigint unsigned NOT NULL,
  `severity` varchar(50) DEFAULT NULL,
  `reason` varchar(500) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vw_tenant` (`tenant_id`),
  KEY `idx_vw_visitor` (`visitor_id`),
  KEY `fk_vw_visitor` (`visitor_id`,`tenant_id`),
  KEY `idx_vw_tenant_severity` (`tenant_id`,`severity`),
  CONSTRAINT `fk_vw_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vw_visitor` FOREIGN KEY (`visitor_id`, `tenant_id`) REFERENCES `visitors` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- +migrate DOWN

DROP TABLE IF EXISTS `visitor_watchlist`;
DROP TABLE IF EXISTS `visitors`;
DROP TABLE IF EXISTS `visitor_companies`;
DROP TABLE IF EXISTS `identification_types`;
