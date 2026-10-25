-- Migration: 0021_notification_governance
-- P2 groundwork: email outbox retries, multi-channel notifications (SMS / push /
-- in-app), per-user notification preferences, approval delegation (OOO), and the
-- dead workflow_steps columns the engine no longer reads.
--
-- Drift-safe: glee_demo carries columns that no migration ever created
-- (users.phone, workflow_steps.allow_self_approval / approval_level), while the
-- test DB built from migrations does not. Every ALTER is therefore conditional.

-- +migrate UP

-- --- email_outbox: retry bookkeeping -----------------------------------------
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_outbox' AND COLUMN_NAME = 'attempts') = 0,
  'ALTER TABLE `email_outbox` ADD COLUMN `attempts` tinyint unsigned NOT NULL DEFAULT 0 AFTER `status`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_outbox' AND COLUMN_NAME = 'next_retry_at') = 0,
  'ALTER TABLE `email_outbox` ADD COLUMN `next_retry_at` datetime DEFAULT NULL AFTER `attempts`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_outbox' AND COLUMN_NAME = 'updated_at') = 0,
  'ALTER TABLE `email_outbox` ADD COLUMN `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER `created_at`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_outbox' AND INDEX_NAME = 'idx_email_outbox_retry') = 0,
  'ALTER TABLE `email_outbox` ADD KEY `idx_email_outbox_retry` (`status`, `next_retry_at`)', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- --- gatepass_approvals: escalation sweep bookkeeping -------------------------
-- bin/approval-escalation.php must not re-nudge the same approver every run.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gatepass_approvals' AND COLUMN_NAME = 'escalated_at') = 0,
  'ALTER TABLE `gatepass_approvals` ADD COLUMN `escalated_at` datetime DEFAULT NULL', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gatepass_approvals' AND COLUMN_NAME = 'escalation_count') = 0,
  'ALTER TABLE `gatepass_approvals` ADD COLUMN `escalation_count` tinyint unsigned NOT NULL DEFAULT 0', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- --- in-app notification feed -------------------------------------------------
CREATE TABLE IF NOT EXISTS `notifications` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `type` varchar(64) NOT NULL DEFAULT 'info',
  `title` varchar(255) NOT NULL,
  `body` text,
  `link` varchar(255) DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notif_inbox` (`tenant_id`,`user_id`,`read_at`,`created_at`),
  CONSTRAINT `fk_notif_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notif_user` FOREIGN KEY (`user_id`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --- SMS / push delivery attempts (mirrors email_outbox semantics) ------------
CREATE TABLE IF NOT EXISTS `notification_outbox` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `channel` enum('sms','push') NOT NULL,
  `recipient` varchar(255) DEFAULT NULL,
  `subject` varchar(500) NOT NULL DEFAULT '',
  `body` text,
  `status` enum('queued','sent','failed','skipped') NOT NULL DEFAULT 'queued',
  `error` varchar(500) DEFAULT NULL,
  `attempts` tinyint unsigned NOT NULL DEFAULT 0,
  `next_retry_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_notifout_dispatch` (`tenant_id`,`status`,`next_retry_at`),
  CONSTRAINT `fk_notifout_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_notifout_user` FOREIGN KEY (`user_id`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --- push registration tokens -------------------------------------------------
CREATE TABLE IF NOT EXISTS `notification_devices` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `platform` enum('ios','android','web') NOT NULL DEFAULT 'web',
  `token` varchar(255) NOT NULL,
  `last_seen_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_nd_token` (`tenant_id`,`token`),
  KEY `idx_nd_user` (`tenant_id`,`user_id`),
  CONSTRAINT `fk_nd_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_nd_user` FOREIGN KEY (`user_id`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --- per-user, per-event channel opt-ins --------------------------------------
-- No row = channel default (email and in-app on, sms and push off).
CREATE TABLE IF NOT EXISTS `notification_preferences` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `event_key` varchar(64) NOT NULL,
  `channel` enum('email','sms','push','in_app') NOT NULL,
  `enabled` tinyint(1) NOT NULL DEFAULT '1',
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_np_event_channel` (`tenant_id`,`user_id`,`event_key`,`channel`),
  CONSTRAINT `fk_np_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_np_user` FOREIGN KEY (`user_id`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --- approval delegation (OOO coverage) ---------------------------------------
-- role_id scopes the delegation to one approval role; NULL = every role.
CREATE TABLE IF NOT EXISTS `approval_delegates` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `delegate_user_id` bigint unsigned NOT NULL,
  `role_id` bigint unsigned DEFAULT NULL,
  `starts_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ends_at` datetime NOT NULL,
  `note` varchar(255) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_ad_active` (`tenant_id`,`user_id`,`ends_at`),
  KEY `idx_ad_delegate` (`tenant_id`,`delegate_user_id`,`ends_at`),
  CONSTRAINT `fk_ad_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ad_user` FOREIGN KEY (`user_id`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ad_delegate` FOREIGN KEY (`delegate_user_id`, `tenant_id`) REFERENCES `users` (`id`, `tenant_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ad_role` FOREIGN KEY (`role_id`, `tenant_id`) REFERENCES `roles` (`id`, `tenant_id`) ON DELETE RESTRICT,
  CONSTRAINT `chk_ad_not_self` CHECK (`user_id` <> `delegate_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- --- retire columns the workflow engine no longer reads -----------------------
-- Self-approval is now unconditionally blocked (ApprovalService::approversForRole)
-- and step behaviour is driven by approval_mode / department_source / is_mandatory.
SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'workflow_steps' AND COLUMN_NAME = 'allow_self_approval') = 1,
  'ALTER TABLE `workflow_steps` DROP COLUMN `allow_self_approval`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'workflow_steps' AND COLUMN_NAME = 'approval_level') = 1,
  'ALTER TABLE `workflow_steps` DROP COLUMN `approval_level`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- --- permissions ---------------------------------------------------------------
INSERT IGNORE INTO `actions` (`name`) VALUES ('delegate');

INSERT IGNORE INTO `modules` (`name`) VALUES ('notifications');

INSERT IGNORE INTO `permissions` (`module_id`, `action_id`)
SELECT m.id, a.id
FROM (
    SELECT 'approval' AS module, 'delegate' AS action UNION ALL
    SELECT 'notifications','view' UNION ALL
    SELECT 'notifications','update'
) t
JOIN `modules` m ON m.name = t.module
JOIN `actions` a ON a.name = t.action;

-- approval.delegate: same audience as approval.reassign
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT rp.tenant_id, rp.role_id, delegate_perm.id
FROM `role_permissions` rp
JOIN `permissions` src ON src.id = rp.permission_id
JOIN `modules` sm ON sm.id = src.module_id AND sm.name = 'approval'
JOIN `actions` sa ON sa.id = src.action_id AND sa.name = 'reassign'
CROSS JOIN (
    SELECT p.id
    FROM `permissions` p
    JOIN `modules` m ON m.id = p.module_id AND m.name = 'approval'
    JOIN `actions` a ON a.id = p.action_id AND a.name = 'delegate'
    LIMIT 1
) AS delegate_perm;

-- notifications.*: everyone who can reach the dashboard reads their own bell
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT rp.tenant_id, rp.role_id, notif_perm.id
FROM `role_permissions` rp
JOIN `permissions` src ON src.id = rp.permission_id
JOIN `modules` sm ON sm.id = src.module_id AND sm.name = 'dashboard'
JOIN `actions` sa ON sa.id = src.action_id AND sa.name = 'access'
CROSS JOIN (
    SELECT p.id
    FROM `permissions` p
    JOIN `modules` m ON m.id = p.module_id AND m.name = 'notifications'
    LIMIT 1
) AS notif_perm;

INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT rp.tenant_id, rp.role_id, notif_update_perm.id
FROM `role_permissions` rp
JOIN `permissions` src ON src.id = rp.permission_id
JOIN `modules` sm ON sm.id = src.module_id AND sm.name = 'dashboard'
JOIN `actions` sa ON sa.id = src.action_id AND sa.name = 'access'
CROSS JOIN (
    SELECT p.id
    FROM `permissions` p
    JOIN `modules` m ON m.id = p.module_id AND m.name = 'notifications'
    JOIN `actions` a ON a.id = p.action_id AND a.name = 'update'
    LIMIT 1
) AS notif_update_perm;

-- +migrate DOWN

DELETE rp FROM `role_permissions` rp
JOIN `permissions` p ON p.id = rp.permission_id
JOIN `modules` m ON m.id = p.module_id
WHERE (m.name = 'approval' AND p.action_id IN (SELECT id FROM `actions` WHERE `name` = 'delegate'))
   OR m.name = 'notifications';

DELETE p FROM `permissions` p
JOIN `modules` m ON m.id = p.module_id
WHERE (m.name = 'approval' AND p.action_id IN (SELECT id FROM `actions` WHERE `name` = 'delegate'))
   OR m.name = 'notifications';

DELETE FROM `modules` WHERE `name` = 'notifications';
DELETE FROM `actions` WHERE `name` = 'delegate';

DROP TABLE IF EXISTS `approval_delegates`;
DROP TABLE IF EXISTS `notification_preferences`;
DROP TABLE IF EXISTS `notification_devices`;
DROP TABLE IF EXISTS `notification_outbox`;
DROP TABLE IF EXISTS `notifications`;
