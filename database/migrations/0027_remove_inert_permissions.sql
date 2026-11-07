-- Migration: 0027_remove_inert_permissions
-- Drops four permissions that were seeded with the matrix but never enforced.
-- Nothing in the application checks them and no endpoint exists behind them:
--   gatepass.print   - the QR follows gatepass.view by design; there is no print action
--   visits.export    - visits export through /reports/visits/export (reports.export_visits)
--   users.disable    - no deactivation endpoint exists; edits go through users.update
--   workflows.delete - only workflow *steps* can be deleted, under workflows.update
-- Removing the grant rows keeps the role matrix honest; no capability is lost,
-- because none of these keys was ever consulted.

-- +migrate UP

DELETE rp FROM `role_permissions` rp
JOIN `permissions` p ON p.id = rp.permission_id
JOIN `modules` m ON m.id = p.module_id
JOIN `actions` a ON a.id = p.action_id
WHERE (m.name = 'gatepass'  AND a.name = 'print')
   OR (m.name = 'visits'    AND a.name = 'export')
   OR (m.name = 'users'     AND a.name = 'disable')
   OR (m.name = 'workflows' AND a.name = 'delete');

DELETE p FROM `permissions` p
JOIN `modules` m ON m.id = p.module_id
JOIN `actions` a ON a.id = p.action_id
WHERE (m.name = 'gatepass'  AND a.name = 'print')
   OR (m.name = 'visits'    AND a.name = 'export')
   OR (m.name = 'users'     AND a.name = 'disable')
   OR (m.name = 'workflows' AND a.name = 'delete');

-- +migrate DOWN

INSERT IGNORE INTO `permissions` (`module_id`, `action_id`)
SELECT m.id, a.id
FROM (
    SELECT 'gatepass' AS module, 'print' AS action UNION ALL
    SELECT 'visits','export' UNION ALL
    SELECT 'users','disable' UNION ALL
    SELECT 'workflows','delete'
) t
JOIN `modules` m ON m.name = t.module
JOIN `actions` a ON a.name = t.action;
