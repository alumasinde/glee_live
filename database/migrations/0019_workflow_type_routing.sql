-- Migration: 0019_workflow_type_routing
-- Makes workflow→type routing single-path and deterministic:
--   1. Backfills workflow_gatepass_type from the legacy gatepass_types.workflow_id column.
--   2. De-duplicates types bound to several workflows (keeps the lowest workflow id).
--   3. Enforces one workflow per gatepass type with a unique key.
--   4. Drops the legacy column — it is never read at runtime and its
--      ON DELETE CASCADE FK would delete gatepass types when a workflow is removed.

-- +migrate UP

-- 1. Backfill from the legacy column (INSERT IGNORE relies on uniq_workflow_type).
INSERT IGNORE INTO `workflow_gatepass_type` (`tenant_id`, `workflow_id`, `gatepass_type_id`, `created_at`)
SELECT gt.`tenant_id`, gt.`workflow_id`, gt.`id`, NOW()
FROM `gatepass_types` gt
WHERE gt.`workflow_id` IS NOT NULL;

-- 2. De-duplicate: a type bound to several workflows had non-deterministic
--    routing (LIMIT 1 without ORDER BY); keep the lowest workflow id.
DELETE wgt
FROM `workflow_gatepass_type` wgt
INNER JOIN (
    SELECT `tenant_id`, `gatepass_type_id`, MIN(`workflow_id`) AS keep_id
    FROM `workflow_gatepass_type`
    GROUP BY `tenant_id`, `gatepass_type_id`
    HAVING COUNT(*) > 1
) d
  ON d.`tenant_id`        = wgt.`tenant_id`
 AND d.`gatepass_type_id` = wgt.`gatepass_type_id`
WHERE wgt.`workflow_id` <> d.keep_id;

-- 3. One workflow per type, per tenant.
ALTER TABLE `workflow_gatepass_type`
  ADD UNIQUE KEY `uk_wgt_tenant_type` (`tenant_id`, `gatepass_type_id`);

-- 4. Drop the legacy column with its FK and indexes.
ALTER TABLE `gatepass_types`
  DROP FOREIGN KEY `fk_gpt_workflow`,
  DROP KEY `idx_gpt_workflow`,
  DROP KEY `fk_gpt_workflow`,
  DROP COLUMN `workflow_id`;

-- +migrate DOWN

ALTER TABLE `gatepass_types`
  ADD COLUMN `workflow_id` bigint unsigned DEFAULT NULL AFTER `allowed_actions`,
  ADD KEY `idx_gpt_workflow` (`workflow_id`),
  ADD KEY `fk_gpt_workflow` (`workflow_id`, `tenant_id`),
  ADD CONSTRAINT `fk_gpt_workflow` FOREIGN KEY (`workflow_id`, `tenant_id`)
      REFERENCES `workflows` (`id`, `tenant_id`) ON DELETE CASCADE;

UPDATE `gatepass_types` gt
INNER JOIN (
    SELECT `tenant_id`, `gatepass_type_id`, MIN(`workflow_id`) AS workflow_id
    FROM `workflow_gatepass_type`
    GROUP BY `tenant_id`, `gatepass_type_id`
) w
   ON w.`tenant_id`        = gt.`tenant_id`
  AND w.`gatepass_type_id` = gt.`id`
SET gt.`workflow_id` = w.`workflow_id`;

ALTER TABLE `workflow_gatepass_type`
  DROP KEY `uk_wgt_tenant_type`;
