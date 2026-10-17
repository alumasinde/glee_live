-- Migration: 0009_gatepasses

-- +migrate UP

CREATE TABLE IF NOT EXISTS `gatepass_statuses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gs_tenant_name` (`tenant_id`,`name`),
  UNIQUE KEY `uk_gs_tenant_code` (`tenant_id`,`code`),
  UNIQUE KEY `uk_gs_tenant_id` (`id`,`tenant_id`),
  KEY `idx_gs_tenant` (`tenant_id`),
  CONSTRAINT `fk_gs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `gatepass_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `type_code` varchar(20) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `workflow_id` bigint unsigned DEFAULT NULL,
  `allowed_actions` json DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gpt_tenant_name` (`tenant_id`,`name`),
  UNIQUE KEY `uk_gpt_tenant_id` (`id`,`tenant_id`),
  KEY `idx_gpt_tenant` (`tenant_id`),
  KEY `idx_gpt_tenant_active` (`tenant_id`,`is_active`),
  KEY `idx_gpt_workflow` (`workflow_id`),
  KEY `fk_gpt_workflow` (`workflow_id`,`tenant_id`),
  CONSTRAINT `fk_gpt_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gpt_workflow` FOREIGN KEY (`workflow_id`, `tenant_id`) REFERENCES `workflows` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `gatepasses` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `visit_id` bigint unsigned DEFAULT NULL,
  `gatepass_type_id` bigint unsigned DEFAULT NULL,
  `gatepass_number` varchar(100) NOT NULL,
  `status_id` bigint unsigned DEFAULT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  `checked_in_by` bigint unsigned DEFAULT NULL,
  `checked_out_by` bigint unsigned DEFAULT NULL,
  `actual_in` datetime DEFAULT NULL,
  `actual_out` datetime DEFAULT NULL,
  `created_by` bigint unsigned DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `purpose` varchar(250) DEFAULT NULL,
  `is_returnable` tinyint(1) NOT NULL DEFAULT '0',
  `expected_return_date` datetime DEFAULT NULL,
  `actual_return_date` datetime DEFAULT NULL,
  `is_fully_returned` tinyint(1) NOT NULL DEFAULT '0',
  `needs_approval` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gatepass_tenant_number` (`tenant_id`,`gatepass_number`),
  UNIQUE KEY `uk_gatepass_tenant_id` (`id`,`tenant_id`),
  KEY `idx_gatepass_status` (`tenant_id`,`status_id`),
  KEY `idx_gatepass_type` (`tenant_id`,`gatepass_type_id`),
  KEY `idx_gatepasses_department` (`tenant_id`,`department_id`),
  KEY `idx_gatepass_created_by` (`tenant_id`,`created_by`),
  KEY `idx_gatepass_list` (`tenant_id`,`status_id`,`created_at` DESC),
  KEY `idx_gatepass_returnable` (`tenant_id`,`is_returnable`,`is_fully_returned`),
  KEY `fk_gatepass_visit` (`visit_id`,`tenant_id`),
  KEY `fk_gatepass_type` (`gatepass_type_id`,`tenant_id`),
  KEY `fk_gatepass_status` (`status_id`,`tenant_id`),
  KEY `fk_gatepass_department` (`department_id`,`tenant_id`),
  KEY `fk_gatepass_created_by` (`created_by`,`tenant_id`),
  KEY `fk_gatepass_checked_in_by` (`checked_in_by`),
  KEY `fk_gatepass_checked_out_by` (`checked_out_by`),
  CONSTRAINT `fk_gatepass_checked_in_by` FOREIGN KEY (`checked_in_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gatepass_checked_out_by` FOREIGN KEY (`checked_out_by`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gatepass_created_by` FOREIGN KEY (`created_by`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gatepass_department` FOREIGN KEY (`department_id`, `tenant_id`) REFERENCES `departments` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gatepass_status` FOREIGN KEY (`status_id`, `tenant_id`) REFERENCES `gatepass_statuses` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gatepass_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gatepass_type` FOREIGN KEY (`gatepass_type_id`, `tenant_id`) REFERENCES `gatepass_types` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gatepass_visit` FOREIGN KEY (`visit_id`, `tenant_id`) REFERENCES `visits` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `gatepass_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `gatepass_id` bigint unsigned NOT NULL,
  `item_name` varchar(255) NOT NULL,
  `description` text,
  `quantity` int NOT NULL DEFAULT '1',
  `serial_number` varchar(255) DEFAULT NULL,
  `is_returnable` tinyint(1) NOT NULL DEFAULT '0',
  `returned_quantity` int DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_gi_gatepass` (`gatepass_id`),
  KEY `fk_gi_tenant` (`tenant_id`),
  KEY `fk_gi_gatepass` (`gatepass_id`,`tenant_id`),
  KEY `idx_gi_serial` (`tenant_id`,`serial_number`),
  CONSTRAINT `fk_gi_gatepass` FOREIGN KEY (`gatepass_id`, `tenant_id`) REFERENCES `gatepasses` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gi_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `gatepass_workflow_instances` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `gatepass_id` bigint unsigned NOT NULL,
  `workflow_id` bigint unsigned NOT NULL,
  `current_step_order` int NOT NULL DEFAULT '1',
  `status` enum('in_progress','approved','rejected') NOT NULL DEFAULT 'in_progress',
  `started_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `completed_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gwi_gatepass` (`gatepass_id`),
  UNIQUE KEY `uk_gwi_tenant_id` (`id`,`tenant_id`),
  KEY `idx_gwi_tenant` (`tenant_id`),
  KEY `idx_gwi_status` (`tenant_id`,`status`),
  KEY `idx_gwi_lookup` (`tenant_id`,`status`,`gatepass_id`),
  KEY `fk_gwi_gatepass` (`gatepass_id`,`tenant_id`),
  KEY `fk_gwi_workflow` (`workflow_id`,`tenant_id`),
  CONSTRAINT `fk_gwi_gatepass` FOREIGN KEY (`gatepass_id`, `tenant_id`) REFERENCES `gatepasses` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gwi_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gwi_workflow` FOREIGN KEY (`workflow_id`, `tenant_id`) REFERENCES `workflows` (`id`, `tenant_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `workflow_gatepass_type` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `workflow_id` bigint unsigned NOT NULL,
  `gatepass_type_id` bigint unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_workflow_type` (`tenant_id`,`workflow_id`,`gatepass_type_id`),
  UNIQUE KEY `uk_wgt_tenant_id` (`id`,`tenant_id`),
  KEY `fk_wgt_workflow` (`workflow_id`,`tenant_id`),
  KEY `fk_wgt_gatepass_type` (`gatepass_type_id`,`tenant_id`),
  CONSTRAINT `fk_wgt_gatepass_type` FOREIGN KEY (`gatepass_type_id`, `tenant_id`) REFERENCES `gatepass_types` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wgt_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wgt_workflow` FOREIGN KEY (`workflow_id`, `tenant_id`) REFERENCES `workflows` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `gatepass_approvals` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `workflow_instance_id` bigint unsigned NOT NULL,
  `workflow_step_id` bigint unsigned NOT NULL,
  `approver_user_id` bigint unsigned NOT NULL,
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `comments` text,
  `acted_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ga_tenant` (`tenant_id`),
  KEY `idx_ga_status` (`tenant_id`,`status`),
  KEY `idx_ga_user_pending` (`approver_user_id`,`status`),
  KEY `idx_ga_lookup` (`tenant_id`,`approver_user_id`,`status`),
  KEY `idx_ga_instance_status` (`tenant_id`,`workflow_instance_id`,`status`),
  KEY `fk_ga_instance` (`workflow_instance_id`,`tenant_id`),
  KEY `fk_ga_step` (`workflow_step_id`,`tenant_id`),
  KEY `fk_ga_user` (`approver_user_id`,`tenant_id`),
  CONSTRAINT `fk_ga_instance` FOREIGN KEY (`workflow_instance_id`, `tenant_id`) REFERENCES `gatepass_workflow_instances` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ga_step` FOREIGN KEY (`workflow_step_id`, `tenant_id`) REFERENCES `workflow_steps` (`id`, `tenant_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ga_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ga_user` FOREIGN KEY (`approver_user_id`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- +migrate DOWN

DROP TABLE IF EXISTS `gatepass_approvals`;
DROP TABLE IF EXISTS `workflow_gatepass_type`;
DROP TABLE IF EXISTS `gatepass_workflow_instances`;
DROP TABLE IF EXISTS `gatepass_items`;
DROP TABLE IF EXISTS `gatepasses`;
DROP TABLE IF EXISTS `gatepass_types`;
DROP TABLE IF EXISTS `gatepass_statuses`;
