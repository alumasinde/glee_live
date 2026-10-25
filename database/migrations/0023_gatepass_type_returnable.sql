-- Migration: 0023_gatepass_type_returnable
-- Gives gatepass_types the is_returnable column the settings screen has shown
-- since the beginning but never stored: GatepassTypeRepository::map() read a
-- column that does not exist in this table, so every row rendered "No" and the
-- Configure modal had nothing to write. Also makes allowed_actions explicit and
-- adds the return key, which the return scan now checks like check-in/out.
--
-- Semantics are unchanged on purpose. assertTypeActionAllowed() treats a
-- NULL/empty config or an absent key as ALLOWED and blocks only on an explicit
-- false, so the backfill below alters no behaviour — it only stops the screen
-- from contradicting the engine.
--
-- Drift-safe: installs that already carry the column are left alone.

-- +migrate UP

SET @sql = IF((SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gatepass_types' AND COLUMN_NAME = 'is_returnable') = 0,
  'ALTER TABLE `gatepass_types` ADD COLUMN `is_returnable` tinyint(1) NOT NULL DEFAULT 0 AFTER `allowed_actions`', 'SELECT 1');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- Equipment that leaves the site is the one type that always comes back.
UPDATE `gatepass_types` SET `is_returnable` = 1 WHERE `type_code` IN ('EQUIP_OUT', 'ASSET_OUT');

-- Spell out the permissive default for types that were never configured, so an
-- admin sees the same answer the gate gives.
UPDATE `gatepass_types`
   SET `allowed_actions` = '{"checkin":true,"checkout":true,"return":true}'
 WHERE `allowed_actions` IS NULL
    OR TRIM(`allowed_actions`) = '';

-- Add the return key to configs written before it existed.
UPDATE `gatepass_types`
   SET `allowed_actions` = JSON_INSERT(`allowed_actions`, '$."return"', TRUE)
 WHERE JSON_VALID(`allowed_actions`)
   AND NOT JSON_CONTAINS_PATH(`allowed_actions`, 'one', '$."return"');

-- +migrate DOWN

-- The column goes; the normalised allowed_actions payloads are left as they are
-- because explicit true and absent mean the same thing to the engine.
ALTER TABLE `gatepass_types` DROP COLUMN `is_returnable`;
