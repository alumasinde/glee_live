-- Migration: 0015_returned_status
-- Seeds the RETURNED gatepass status for every tenant. The returnable scan
-- branch (GatepassService::markReturned) requires it and previously threw
-- because no tenant had this status code.

-- +migrate UP

INSERT IGNORE INTO `gatepass_statuses` (`tenant_id`, `name`, `code`)
SELECT t.`id`, 'Returned', 'RETURNED'
FROM `tenants` t
WHERE NOT EXISTS (
    SELECT 1 FROM `gatepass_statuses` gs
    WHERE gs.`tenant_id` = t.`id`
      AND gs.`code`      = 'RETURNED'
);

-- +migrate DOWN

DELETE gs
FROM `gatepass_statuses` gs
INNER JOIN `tenants` t ON t.`id` = gs.`tenant_id`
WHERE gs.`code` = 'RETURNED';
