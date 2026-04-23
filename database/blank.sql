-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               9.4.0 - MySQL Community Server - GPL
-- Server OS:                    Win64
-- HeidiSQL Version:             12.11.0.7065
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

-- Dumping structure for table glee_live.actions
CREATE TABLE IF NOT EXISTS `actions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_action_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=17412 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.audit_logs
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `action` varchar(120) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` bigint unsigned DEFAULT NULL,
  `metadata` json DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `message` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_audit_tenant` (`tenant_id`),
  KEY `idx_audit_tenant_time` (`tenant_id`,`created_at`),
  KEY `idx_audit_entity` (`tenant_id`,`entity_type`,`entity_id`),
  KEY `fk_audit_user` (`user_id`,`tenant_id`),
  CONSTRAINT `fk_audit_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=189 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.departments
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.gatepasses
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.gatepass_approvals
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
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.gatepass_items
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.gatepass_statuses
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
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.gatepass_types
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
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.gatepass_workflow_instances
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
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.identification_types
CREATE TABLE IF NOT EXISTS `identification_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_idtype_tenant_name` (`tenant_id`,`name`),
  UNIQUE KEY `uk_idtype_tenant_id` (`id`,`tenant_id`),
  KEY `idx_idtypes_tenant` (`tenant_id`),
  CONSTRAINT `fk_idtypes_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.modules
CREATE TABLE IF NOT EXISTS `modules` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_module_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=5548 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.permissions
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `action_id` bigint unsigned NOT NULL,
  `module_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_perm_action_module` (`action_id`,`module_id`),
  KEY `idx_perm_module` (`module_id`),
  CONSTRAINT `fk_perm_action` FOREIGN KEY (`action_id`) REFERENCES `actions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_perm_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=17530 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.roles
CREATE TABLE IF NOT EXISTS `roles` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_role_tenant_name` (`tenant_id`,`name`),
  UNIQUE KEY `uk_role_tenant_id` (`id`,`tenant_id`),
  KEY `idx_roles_tenant` (`tenant_id`),
  CONSTRAINT `fk_roles_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.role_permissions
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` bigint unsigned NOT NULL,
  `tenant_id` bigint unsigned NOT NULL,
  `permission_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `idx_rp_permission` (`permission_id`),
  KEY `idx_rp_tenant` (`tenant_id`),
  KEY `idx_rp_tenant_role` (`tenant_id`,`role_id`,`permission_id`),
  KEY `fk_rp_role` (`role_id`,`tenant_id`),
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.sessions
CREATE TABLE IF NOT EXISTS `sessions` (
  `token` char(43) NOT NULL,
  `data` blob NOT NULL,
  `expiry` timestamp(6) NOT NULL,
  PRIMARY KEY (`token`),
  KEY `expiry_idx` (`expiry`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.tenants
CREATE TABLE IF NOT EXISTS `tenants` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(120) NOT NULL,
  `logo` varchar(120) NOT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `country` varchar(120) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_tenant_code` (`code`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.tenant_settings
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
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `first_name` varchar(120) NOT NULL,
  `last_name` varchar(120) NOT NULL,
  `username` varchar(120) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `is_admin` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  `department_id` bigint unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_user_tenant_email` (`tenant_id`,`email`),
  UNIQUE KEY `uk_user_tenant_username` (`tenant_id`,`username`),
  UNIQUE KEY `uk_user_tenant_id` (`id`,`tenant_id`),
  KEY `idx_users_tenant` (`tenant_id`),
  KEY `idx_users_tenant_active` (`tenant_id`,`is_active`),
  KEY `idx_users_dept` (`department_id`),
  KEY `fk_users_dept` (`department_id`,`tenant_id`),
  KEY `idx_users_reset_token` (`reset_token`),
  KEY `idx_users_reset_expires` (`reset_expires`),
  KEY `idx_users_dept_tenant` (`tenant_id`,`department_id`,`is_active`),
  CONSTRAINT `fk_users_dept` FOREIGN KEY (`department_id`, `tenant_id`) REFERENCES `departments` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.user_otps
CREATE TABLE IF NOT EXISTS `user_otps` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `otp_code` varchar(10) NOT NULL,
  `purpose` varchar(50) NOT NULL DEFAULT 'login',
  `is_used` tinyint(1) NOT NULL DEFAULT '0',
  `expires_at` datetime NOT NULL,
  `used_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_otp_lookup` (`tenant_id`,`user_id`,`purpose`,`is_used`,`expires_at`),
  KEY `idx_otp_user` (`user_id`,`tenant_id`),
  CONSTRAINT `fk_otp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_otp_user` FOREIGN KEY (`user_id`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.user_roles
CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned NOT NULL,
  `tenant_id` bigint unsigned NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `idx_ur_role` (`role_id`),
  KEY `idx_ur_tenant` (`tenant_id`),
  KEY `fk_ur_user` (`user_id`,`tenant_id`),
  KEY `fk_ur_role` (`role_id`,`tenant_id`),
  KEY `idx_ur_tenant_user` (`tenant_id`,`user_id`,`role_id`),
  CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`, `tenant_id`) REFERENCES `roles` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.visitors
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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.visitor_companies
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.visitor_watchlist
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

-- Data exporting was unselected.

-- Dumping structure for table glee_live.visits
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.visit_badges
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.visit_statuses
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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.visit_types
CREATE TABLE IF NOT EXISTS `visit_types` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_vt_tenant_name` (`tenant_id`,`name`),
  UNIQUE KEY `uk_vt_tenant_id` (`id`,`tenant_id`),
  KEY `idx_vt_tenant` (`tenant_id`),
  CONSTRAINT `fk_vt_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.workflows
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.workflow_gatepass_type
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
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

-- Dumping structure for table glee_live.workflow_steps
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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- Data exporting was unselected.

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;
