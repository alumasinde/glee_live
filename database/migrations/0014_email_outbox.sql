-- Migration: 0014_email_outbox
-- Outbox for every mail the system attempts (sent / failed / skipped), so
-- notifications are never silently lost and Phase 2 has an audit of sends.

-- +migrate UP

CREATE TABLE `email_outbox` (
    `id`         BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    `tenant_id`  BIGINT(20) UNSIGNED NOT NULL,
    `to_email`   VARCHAR(255) NOT NULL,
    `subject`    VARCHAR(500) NOT NULL DEFAULT '',
    `body`       MEDIUMTEXT NULL,
    `status`     ENUM('sent','failed','skipped') NOT NULL DEFAULT 'skipped',
    `error`      VARCHAR(500) NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_email_outbox_tenant_created` (`tenant_id`, `created_at`),
    CONSTRAINT `fk_email_outbox_tenant`
        FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- +migrate DOWN

DROP TABLE `email_outbox`;
