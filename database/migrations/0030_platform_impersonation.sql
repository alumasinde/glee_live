-- Migration: 0030_platform_impersonation
--
-- Support access is a time-boxed, session-bound grant: a platform operator can
-- view the tenant app as one active tenant user without knowing that user's
-- password. The platform session is never replaced, every start/end is audited,
-- and the row is the authority the request guard re-checks on each page.

-- +migrate UP

CREATE TABLE IF NOT EXISTS `platform_impersonations` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `platform_user_id` bigint unsigned NOT NULL,
  `tenant_id` bigint unsigned NOT NULL,
  `user_id` bigint unsigned NOT NULL,
  `token_hash` char(64) NOT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `started_at` datetime NOT NULL,
  `expires_at` datetime NOT NULL,
  `ended_at` datetime DEFAULT NULL,
  `ended_by_platform_user_id` bigint unsigned DEFAULT NULL,
  `end_reason` varchar(40) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_pi_token` (`token_hash`),
  KEY `idx_pi_active` (`ended_at`,`expires_at`),
  KEY `idx_pi_tenant` (`tenant_id`,`started_at`),
  CONSTRAINT `fk_pi_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- +migrate DOWN

DROP TABLE IF EXISTS `platform_impersonations`;
