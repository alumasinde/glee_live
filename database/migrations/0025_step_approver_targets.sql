-- Migration: 0025_step_approver_targets
-- A workflow step may target named people instead of a role:
--   workflow_steps.approver_kind 'role'  = role_id + department scope (existing behaviour)
--                    'users' = the roster held in workflow_step_approvers
-- role_id becomes nullable because a 'users' step has no role. fallback_role_id still
-- covers either kind, so a roster whose people are all inactive or the requester does
-- not stall the workflow.

-- +migrate UP

ALTER TABLE `workflow_steps`
  ADD COLUMN `approver_kind` enum('role','users') NOT NULL DEFAULT 'role' AFTER `name`;

ALTER TABLE `workflow_steps`
  MODIFY COLUMN `role_id` bigint unsigned DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `workflow_step_approvers` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `workflow_step_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_wsa_step_user` (`workflow_step_id`,`user_id`),
  UNIQUE KEY `uk_wsa_tenant_id` (`id`,`tenant_id`),
  KEY `idx_wsa_tenant` (`tenant_id`),
  KEY `idx_wsa_step` (`tenant_id`,`workflow_step_id`),
  KEY `idx_wsa_user` (`tenant_id`,`user_id`),
  CONSTRAINT `fk_wsa_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wsa_step` FOREIGN KEY (`workflow_step_id`, `tenant_id`) REFERENCES `workflow_steps` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wsa_user` FOREIGN KEY (`user_id`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- +migrate DOWN

DELETE FROM `workflow_steps` WHERE `approver_kind` = 'users';

ALTER TABLE `workflow_steps`
  MODIFY COLUMN `role_id` bigint unsigned NOT NULL;

DROP TABLE IF EXISTS `workflow_step_approvers`;

ALTER TABLE `workflow_steps`
  DROP COLUMN `approver_kind`;
