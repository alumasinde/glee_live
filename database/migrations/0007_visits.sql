-- Migration: 0007_visits

-- +migrate UP

CREATE TABLE IF NOT EXISTS `visit_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vt_tenant_name` (`tenant_id`,`name`),
  UNIQUE KEY `uk_vt_tenant_id` (`id`,`tenant_id`),
  KEY `idx_vt_tenant` (`tenant_id`),
  CONSTRAINT `fk_vt_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `visit_statuses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vs_tenant_name` (`tenant_id`,`name`),
  UNIQUE KEY `uk_vs_tenant_code` (`tenant_id`,`code`),
  UNIQUE KEY `uk_vs_tenant_id` (`id`,`tenant_id`),
  KEY `idx_vs_tenant` (`tenant_id`),
  CONSTRAINT `fk_vs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `visits` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `department_id` bigint unsigned NOT NULL,
  `visitor_id` bigint unsigned NOT NULL,
  `host_user_id` bigint unsigned DEFAULT NULL,
  `visit_type_id` bigint unsigned DEFAULT NULL,
  `visit_status_id` bigint unsigned DEFAULT NULL,
  `purpose` text,
  `expected_in` datetime DEFAULT NULL,
  `expected_out` datetime DEFAULT NULL,
  `checkin_time` datetime DEFAULT NULL,
  `checkout_time` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT (now()),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_visit_tenant_id` (`id`,`tenant_id`),
  KEY `idx_visits_tenant` (`tenant_id`),
  KEY `idx_visit_lookup` (`tenant_id`,`visitor_id`),
  KEY `idx_visits_dept` (`tenant_id`,`department_id`),
  KEY `idx_visits_active` (`tenant_id`,`visit_status_id`,`checkin_time`),
  KEY `idx_visits_tenant_time` (`tenant_id`,`created_at` DESC),
  KEY `fk_visits_dept` (`department_id`,`tenant_id`),
  KEY `fk_visits_visitor` (`visitor_id`,`tenant_id`),
  KEY `fk_visits_host_user` (`host_user_id`,`tenant_id`),
  KEY `fk_visits_type` (`visit_type_id`,`tenant_id`),
  KEY `fk_visits_status` (`visit_status_id`,`tenant_id`),
  KEY `fk_visits_created_by` (`created_by`,`tenant_id`),
  CONSTRAINT `fk_visits_created_by` FOREIGN KEY (`created_by`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visits_dept` FOREIGN KEY (`department_id`, `tenant_id`) REFERENCES `departments` (`id`, `tenant_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_visits_host_user` FOREIGN KEY (`host_user_id`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visits_status` FOREIGN KEY (`visit_status_id`, `tenant_id`) REFERENCES `visit_statuses` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visits_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visits_type` FOREIGN KEY (`visit_type_id`, `tenant_id`) REFERENCES `visit_types` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_visits_visitor` FOREIGN KEY (`visitor_id`, `tenant_id`) REFERENCES `visitors` (`id`, `tenant_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `visit_badges` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `visit_id` bigint unsigned NOT NULL,
  `badge_code` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `printed_at` datetime DEFAULT NULL,
  `returned_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vb_tenant_code` (`tenant_id`,`badge_code`),
  KEY `idx_vb_visit` (`visit_id`),
  KEY `fk_vb_visit` (`visit_id`,`tenant_id`),
  KEY `idx_vb_tenant_active` (`tenant_id`,`is_active`),
  CONSTRAINT `fk_vb_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vb_visit` FOREIGN KEY (`visit_id`, `tenant_id`) REFERENCES `visits` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- +migrate DOWN

DROP TABLE IF EXISTS `visit_badges`;
DROP TABLE IF EXISTS `visits`;
DROP TABLE IF EXISTS `visit_statuses`;
DROP TABLE IF EXISTS `visit_types`;
