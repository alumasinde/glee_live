-- Migration: 0016_gates

-- +migrate UP

CREATE TABLE IF NOT EXISTS `gates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `name` varchar(120) NOT NULL,
  `code` varchar(20) NOT NULL,
  `key_hash` char(64) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_gates_tenant_name` (`tenant_id`,`name`),
  UNIQUE KEY `uk_gates_tenant_code` (`tenant_id`,`code`),
  UNIQUE KEY `uk_gates_key_hash` (`key_hash`),
  KEY `idx_gates_tenant` (`tenant_id`),
  CONSTRAINT `fk_gates_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS `scan_logs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `gate_id` bigint unsigned DEFAULT NULL,
  `subject_type` enum('gatepass','visit') NOT NULL,
  `subject_id` bigint unsigned NOT NULL,
  `action` varchar(30) NOT NULL,
  `source` enum('manual','camera','device') NOT NULL DEFAULT 'manual',
  `user_id` bigint unsigned DEFAULT NULL,
  `detail` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sl_tenant_time` (`tenant_id`,`created_at`),
  KEY `idx_sl_gate` (`tenant_id`,`gate_id`),
  KEY `idx_sl_subject` (`tenant_id`,`subject_type`,`subject_id`),
  CONSTRAINT `fk_sl_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sl_gate` FOREIGN KEY (`gate_id`) REFERENCES `gates` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `gatepasses`
  ADD COLUMN `last_in_gate_id`  bigint unsigned DEFAULT NULL AFTER `scan_token`,
  ADD COLUMN `last_out_gate_id` bigint unsigned DEFAULT NULL AFTER `last_in_gate_id`;

ALTER TABLE `visits`
  ADD COLUMN `last_in_gate_id`  bigint unsigned DEFAULT NULL AFTER `scan_token`,
  ADD COLUMN `last_out_gate_id` bigint unsigned DEFAULT NULL AFTER `last_in_gate_id`;

-- +migrate DOWN

ALTER TABLE `visits`
  DROP COLUMN `last_in_gate_id`,
  DROP COLUMN `last_out_gate_id`;

ALTER TABLE `gatepasses`
  DROP COLUMN `last_in_gate_id`,
  DROP COLUMN `last_out_gate_id`;

DROP TABLE IF EXISTS `scan_logs`;
DROP TABLE IF EXISTS `gates`;
