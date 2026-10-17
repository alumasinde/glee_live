-- Migration: 0013_scan_tokens
-- Adds opaque, non-enumerable scan tokens to gatepasses and visits so QR/barcode
-- scans resolve a record without exposing the sequential gatepass_number or visit id.
-- Tokens are 64-char hex (32 random bytes). Existing rows are backfilled.

-- +migrate UP

ALTER TABLE `gatepasses`
    ADD COLUMN `scan_token` CHAR(64) NULL DEFAULT NULL AFTER `gatepass_number`;

UPDATE `gatepasses`
SET `scan_token` = LOWER(SHA2(CONCAT(`id`, '-', RAND(), '-', UUID()), 256))
WHERE `scan_token` IS NULL;

ALTER TABLE `gatepasses`
    MODIFY COLUMN `scan_token` CHAR(64) NOT NULL,
    ADD UNIQUE INDEX `uq_gatepasses_scan_token` (`scan_token`);

ALTER TABLE `visits`
    ADD COLUMN `scan_token` CHAR(64) NULL DEFAULT NULL AFTER `visit_status_id`;

UPDATE `visits`
SET `scan_token` = LOWER(SHA2(CONCAT(`id`, '-', RAND(), '-', UUID()), 256))
WHERE `scan_token` IS NULL;

ALTER TABLE `visits`
    MODIFY COLUMN `scan_token` CHAR(64) NOT NULL,
    ADD UNIQUE INDEX `uq_visits_scan_token` (`scan_token`);

-- +migrate DOWN

ALTER TABLE `visits`
    DROP INDEX `uq_visits_scan_token`,
    DROP COLUMN `scan_token`;

ALTER TABLE `gatepasses`
    DROP INDEX `uq_gatepasses_scan_token`,
    DROP COLUMN `scan_token`;
