-- Migration: 0018_password_resets
-- Moves password-reset storage out of the users table into a dedicated
-- password_resets table (one row per token, single-use, tenant-scoped).
-- Supersedes users.reset_token / users.reset_expires, which are dropped here;
-- in-flight resets (1h lifetime) simply expire and users re-request.

-- +migrate UP

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `token` char(64) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `expires_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_password_resets_token` (`token`),
  KEY `idx_password_resets_user` (`tenant_id`,`user_id`),
  KEY `idx_password_resets_expires` (`expires_at`),
  CONSTRAINT `fk_password_resets_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_password_resets_user` FOREIGN KEY (`user_id`,`tenant_id`) REFERENCES `users` (`id`,`tenant_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

ALTER TABLE `users`
  DROP COLUMN `reset_token`,
  DROP COLUMN `reset_expires`;

-- +migrate DOWN

ALTER TABLE `users`
  ADD COLUMN `reset_token` varchar(255) DEFAULT NULL AFTER `updated_at`,
  ADD COLUMN `reset_expires` datetime DEFAULT NULL AFTER `reset_token`,
  ADD KEY `idx_users_reset_token` (`reset_token`),
  ADD KEY `idx_users_reset_expires` (`reset_expires`);

DROP TABLE IF EXISTS `password_resets`;
