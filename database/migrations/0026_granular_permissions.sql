-- Migration: 0026_granular_permissions
-- Splits two over-broad keys into dedicated modules so a role can be granted
-- exactly the operation it performs:
--   visitors.issue_badge  -> badges.view / badges.issue / badges.return
--   settings.view|update  -> gatepass_types.view / gatepass_types.update
-- Existing holders are migrated BEFORE the superseded visitors.issue_badge
-- pair is dropped. Mirrors config/permissions.php.

-- +migrate UP

INSERT IGNORE INTO `actions` (`name`) VALUES ('issue'),('return');

INSERT IGNORE INTO `modules` (`name`) VALUES ('badges'),('gatepass_types');

INSERT IGNORE INTO `permissions` (`module_id`, `action_id`)
SELECT m.id, a.id
FROM (
    SELECT 'badges' AS module, 'view' AS action UNION ALL
    SELECT 'badges','issue' UNION ALL
    SELECT 'badges','return' UNION ALL
    SELECT 'gatepass_types','view' UNION ALL
    SELECT 'gatepass_types','update'
) t
JOIN `modules` m ON m.name = t.module
JOIN `actions` a ON a.name = t.action;

-- visitors.issue_badge -> every badges.* key, so no holder loses access
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT rp.tenant_id, rp.role_id, p.id
FROM `role_permissions` rp
JOIN `permissions` src ON src.id = rp.permission_id
JOIN `modules` sm ON sm.id = src.module_id AND sm.name = 'visitors'
JOIN `actions` sa ON sa.id = src.action_id AND sa.name = 'issue_badge'
JOIN `permissions` p ON 1 = 1
JOIN `modules` tm ON tm.id = p.module_id AND tm.name = 'badges';

-- settings.view -> gatepass_types.view
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT rp.tenant_id, rp.role_id, p.id
FROM `role_permissions` rp
JOIN `permissions` src ON src.id = rp.permission_id
JOIN `modules` sm ON sm.id = src.module_id AND sm.name = 'settings'
JOIN `actions` sa ON sa.id = src.action_id AND sa.name = 'view'
JOIN `permissions` p ON 1 = 1
JOIN `modules` tm ON tm.id = p.module_id AND tm.name = 'gatepass_types'
JOIN `actions` ta ON ta.id = p.action_id AND ta.name = 'view';

-- settings.update -> gatepass_types.view + gatepass_types.update
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT rp.tenant_id, rp.role_id, p.id
FROM `role_permissions` rp
JOIN `permissions` src ON src.id = rp.permission_id
JOIN `modules` sm ON sm.id = src.module_id AND sm.name = 'settings'
JOIN `actions` sa ON sa.id = src.action_id AND sa.name = 'update'
JOIN `permissions` p ON 1 = 1
JOIN `modules` tm ON tm.id = p.module_id AND tm.name = 'gatepass_types';

-- Drop the superseded key: every holder now has the granular equivalents.
DELETE rp FROM `role_permissions` rp
JOIN `permissions` p ON p.id = rp.permission_id
JOIN `modules` m ON m.id = p.module_id AND m.name = 'visitors'
JOIN `actions` a ON a.id = p.action_id AND a.name = 'issue_badge';

DELETE p FROM `permissions` p
JOIN `modules` m ON m.id = p.module_id AND m.name = 'visitors'
JOIN `actions` a ON a.id = p.action_id AND a.name = 'issue_badge';

-- +migrate DOWN

INSERT IGNORE INTO `permissions` (`module_id`, `action_id`)
SELECT m.id, a.id
FROM `modules` m
JOIN `actions` a ON a.name = 'issue_badge'
WHERE m.name = 'visitors';

INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT rp.tenant_id, rp.role_id, p.id
FROM `role_permissions` rp
JOIN `permissions` src ON src.id = rp.permission_id
JOIN `modules` sm ON sm.id = src.module_id AND sm.name = 'badges'
JOIN `actions` sa ON sa.id = src.action_id AND sa.name = 'issue'
JOIN `permissions` p ON 1 = 1
JOIN `modules` m ON m.id = p.module_id AND m.name = 'visitors'
JOIN `actions` a ON a.id = p.action_id AND a.name = 'issue_badge';

DELETE rp FROM `role_permissions` rp
JOIN `permissions` p ON p.id = rp.permission_id
JOIN `modules` m ON m.id = p.module_id AND m.name IN ('badges','gatepass_types');

DELETE p FROM `permissions` p
JOIN `modules` m ON m.id = p.module_id AND m.name IN ('badges','gatepass_types');
