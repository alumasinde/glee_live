-- Migration: 0008_workflows

-- +migrate UP

CREATE TABLE IF NOT EXISTS `workflows` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(150) NOT NULL,
  `description` varchar(250) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_wf_tenant_name` (`tenant_id`,`name`),
  UNIQUE KEY `uk_wf_tenant_id` (`id`,`tenant_id`),
  KEY `idx_wf_tenant` (`tenant_id`),
  KEY `idx_wf_tenant_active` (`tenant_id`,`is_active`),
  CONSTRAINT `fk_wf_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `workflow_steps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `workflow_id` bigint unsigned NOT NULL,
  `name` varchar(150) NOT NULL DEFAULT '',
  `role_id` bigint unsigned NOT NULL,
  `step_order` int NOT NULL,
  `is_mandatory` tinyint(1) NOT NULL DEFAULT '1',
  `department_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_ws_wf_order` (`workflow_id`,`step_order`),
  UNIQUE KEY `uk_ws_tenant_id` (`id`,`tenant_id`),
  KEY `idx_ws_tenant` (`tenant_id`),
  KEY `idx_ws_role` (`role_id`),
  KEY `idx_ws_dept` (`department_id`),
  KEY `fk_ws_workflow` (`workflow_id`,`tenant_id`),
  KEY `fk_ws_role` (`role_id`,`tenant_id`),
  KEY `fk_ws_dept` (`department_id`,`tenant_id`),
  CONSTRAINT `fk_ws_dept` FOREIGN KEY (`department_id`, `tenant_id`) REFERENCES `departments` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ws_role` FOREIGN KEY (`role_id`, `tenant_id`) REFERENCES `roles` (`id`, `tenant_id`) ON DELETE RESTRICT,
  CONSTRAINT `fk_ws_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ws_workflow` FOREIGN KEY (`workflow_id`, `tenant_id`) REFERENCES `workflows` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- +migrate DOWN

DROP TABLE IF EXISTS `workflow_steps`;
DROP TABLE IF EXISTS `workflows`;
