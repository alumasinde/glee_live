-- Migration: 0022_two_factor_and_login_lockout
-- Manages the users columns that existed in live databases as drift (phone,
-- MFA, lockout) so they are finally under migration control, and adds the
-- recovery-code store + audit retention bookkeeping the hardening phase needs.
--
-- Drift-safe: glee_demo already carries phone / mfa_secret / mfa_enabled /
-- mfa_enabled_at / failed_attempts / locked_until / tokens_invalid_before, while
-- a database built purely from migrations does not. Every ALTER is conditional.

-- +migrate UP

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'phone') = 0,
  'ALTER TABLE `users` ADD COLUMN `phone` varchar(20) DEFAULT NULL AFTER `email`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'mfa_secret') = 0,
  'ALTER TABLE `users` ADD COLUMN `mfa_secret` varchar(255) DEFAULT NULL AFTER `password`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'mfa_enabled') = 0,
  'ALTER TABLE `users` ADD COLUMN `mfa_enabled` tinyint(1) NOT NULL DEFAULT 0 AFTER `mfa_secret`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'mfa_enabled_at') = 0,
  'ALTER TABLE `users` ADD COLUMN `mfa_enabled_at` datetime DEFAULT NULL AFTER `mfa_enabled`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'mfa_recovery_codes') = 0,
  'ALTER TABLE `users` ADD COLUMN `mfa_recovery_codes` text NULL AFTER `mfa_enabled_at`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'failed_attempts') = 0,
  'ALTER TABLE `users` ADD COLUMN `failed_attempts` tinyint unsigned NOT NULL DEFAULT 0 AFTER `mfa_recovery_codes`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'locked_until') = 0,
  'ALTER TABLE `users` ADD COLUMN `locked_until` datetime DEFAULT NULL AFTER `failed_attempts`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'tokens_invalid_before') = 0,
  'ALTER TABLE `users` ADD COLUMN `tokens_invalid_before` datetime DEFAULT NULL AFTER `locked_until`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- +migrate DOWN

-- Columns are additive and pre-existed as drift in live databases; dropping them
-- here would destroy data on installs that created them outside migrations.
SELECT 1;
