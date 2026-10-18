-- Migration: 0017_deepened_permissions
-- Endpoint-level permissions: granular per-report view/export keys,
-- visitors.unblacklist, audit.export, and dedicated gates + email modules
-- carved out of settings. Mirrors config/permissions.php.
-- Existing holders are migrated (settings.* -> gates.*/email.*,
-- reports.export -> export_*, reports.view -> view_*, visitors.blacklist ->
-- unblacklist, audit.view -> audit.export) BEFORE superseded pairs are dropped.

-- +migrate UP

INSERT IGNORE INTO `actions` (`name`) VALUES
('unblacklist'),
('view_gatepasses'),('view_visitors'),('view_visits'),('view_occupancy'),
('view_scans'),('view_departments'),
('export_gatepasses'),('export_visitors'),('export_visits'),
('export_occupancy'),('export_scans'),('export_departments'),
('rotate'),('test_send');

INSERT IGNORE INTO `modules` (`name`) VALUES
('gates'),('email');

-- New permission pairs
INSERT IGNORE INTO `permissions` (`module_id`, `action_id`)
SELECT m.id, a.id
FROM (
    SELECT 'reports' AS module, 'view_gatepasses' AS action UNION ALL
    SELECT 'reports','view_visitors' UNION ALL
    SELECT 'reports','view_visits' UNION ALL
    SELECT 'reports','view_occupancy' UNION ALL
    SELECT 'reports','view_scans' UNION ALL
    SELECT 'reports','view_departments' UNION ALL
    SELECT 'reports','export_gatepasses' UNION ALL
    SELECT 'reports','export_visitors' UNION ALL
    SELECT 'reports','export_visits' UNION ALL
    SELECT 'reports','export_occupancy' UNION ALL
    SELECT 'reports','export_scans' UNION ALL
    SELECT 'reports','export_departments' UNION ALL
    SELECT 'visitors','unblacklist' UNION ALL
    SELECT 'audit','export' UNION ALL
    SELECT 'gates','view' UNION ALL
    SELECT 'gates','create' UNION ALL
    SELECT 'gates','update' UNION ALL
    SELECT 'gates','delete' UNION ALL
    SELECT 'gates','rotate' UNION ALL
    SELECT 'email','view' UNION ALL
    SELECT 'email','update' UNION ALL
    SELECT 'email','test_send'
) t
JOIN `modules` m ON m.name = t.module
JOIN `actions` a ON a.name = t.action;

-- Migrate holders of the superseded umbrella keys to the granular keys
-- (covers custom roles; seed-role defaults are granted further below).

-- reports.export -> every reports.export_*
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT rp.tenant_id, rp.role_id, p.id
FROM `role_permissions` rp
JOIN `permissions` src ON src.id = rp.permission_id
JOIN `modules` sm ON sm.id = src.module_id AND sm.name = 'reports'
JOIN `actions` sa ON sa.id = src.action_id AND sa.name = 'export'
JOIN `permissions` p ON 1 = 1
JOIN `modules` tm ON tm.id = p.module_id AND tm.name = 'reports'
JOIN `actions` ta ON ta.id = p.action_id AND ta.name LIKE 'export\_%';

-- reports.view -> every reports.view_*
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT rp.tenant_id, rp.role_id, p.id
FROM `role_permissions` rp
JOIN `permissions` src ON src.id = rp.permission_id
JOIN `modules` sm ON sm.id = src.module_id AND sm.name = 'reports'
JOIN `actions` sa ON sa.id = src.action_id AND sa.name = 'view'
JOIN `permissions` p ON 1 = 1
JOIN `modules` tm ON tm.id = p.module_id AND tm.name = 'reports'
JOIN `actions` ta ON ta.id = p.action_id AND ta.name LIKE 'view\_%';

-- visitors.blacklist -> visitors.unblacklist
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT rp.tenant_id, rp.role_id, p.id
FROM `role_permissions` rp
JOIN `permissions` src ON src.id = rp.permission_id
JOIN `modules` sm ON sm.id = src.module_id AND sm.name = 'visitors'
JOIN `actions` sa ON sa.id = src.action_id AND sa.name = 'blacklist'
JOIN `permissions` p ON 1 = 1
JOIN `modules` tm ON tm.id = p.module_id AND tm.name = 'visitors'
JOIN `actions` ta ON ta.id = p.action_id AND ta.name = 'unblacklist';

-- audit.view -> audit.export
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT rp.tenant_id, rp.role_id, p.id
FROM `role_permissions` rp
JOIN `permissions` src ON src.id = rp.permission_id
JOIN `modules` sm ON sm.id = src.module_id AND sm.name = 'audit'
JOIN `actions` sa ON sa.id = src.action_id AND sa.name = 'view'
JOIN `permissions` p ON 1 = 1
JOIN `modules` tm ON tm.id = p.module_id AND tm.name = 'audit'
JOIN `actions` ta ON ta.id = p.action_id AND ta.name = 'export';

-- settings.view -> gates.view + email.view
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT rp.tenant_id, rp.role_id, p.id
FROM `role_permissions` rp
JOIN `permissions` src ON src.id = rp.permission_id
JOIN `modules` sm ON sm.id = src.module_id AND sm.name = 'settings'
JOIN `actions` sa ON sa.id = src.action_id AND sa.name = 'view'
JOIN `permissions` p ON 1 = 1
JOIN `modules` tm ON tm.id = p.module_id AND tm.name IN ('gates','email')
JOIN `actions` ta ON ta.id = p.action_id AND ta.name = 'view';

-- settings.update -> all gates + email endpoint keys
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT rp.tenant_id, rp.role_id, p.id
FROM `role_permissions` rp
JOIN `permissions` src ON src.id = rp.permission_id
JOIN `modules` sm ON sm.id = src.module_id AND sm.name = 'settings'
JOIN `actions` sa ON sa.id = src.action_id AND sa.name = 'update'
JOIN `permissions` p ON 1 = 1
JOIN `modules` tm ON tm.id = p.module_id AND tm.name IN ('gates','email')
JOIN `actions` ta ON ta.id = p.action_id;

-- Seed-role defaults (INSERT IGNORE: idempotent alongside holder migration)

-- admin: everything
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT r.tenant_id, r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.`name` = 'admin';

-- General Manager: full reports + audit export
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT r.tenant_id, r.id, p.id
FROM `roles` r
JOIN `permissions` p ON 1 = 1
JOIN `modules` m ON m.id = p.module_id AND m.name = 'reports'
JOIN `actions` a ON a.id = p.action_id
WHERE r.`name` = 'General Manager';

-- Department Head: departmental report views (no exports)
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT r.tenant_id, r.id, p.id
FROM `roles` r
JOIN `permissions` p ON 1 = 1
JOIN `modules` m ON m.id = p.module_id AND m.name = 'reports'
JOIN `actions` a ON a.id = p.action_id AND a.name IN (
    'view','view_gatepasses','view_visits','view_occupancy','view_scans','view_departments'
)
WHERE r.`name` = 'Department Head';

-- Security Officer: gate visibility + scan activity report
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT r.tenant_id, r.id, p.id
FROM `roles` r
JOIN `permissions` p ON 1 = 1
JOIN `modules` m ON m.id = p.module_id
JOIN `actions` a ON a.id = p.action_id
WHERE r.`name` = 'Security Officer'
  AND (m.name, a.name) IN (
    ('gates','view'),
    ('reports','view'),('reports','view_scans')
  );

-- Receptionist: un/Blacklist parity
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT r.tenant_id, r.id, p.id
FROM `roles` r
JOIN `permissions` p ON 1 = 1
JOIN `modules` m ON m.id = p.module_id AND m.name = 'visitors'
JOIN `actions` a ON a.id = p.action_id AND a.name = 'unblacklist'
WHERE r.`name` = 'Receptionist';

-- Drop the superseded umbrella pair (grants cascade-delete with it;
-- holders were expanded to the granular keys above).
DELETE p FROM `permissions` p
JOIN `modules` m ON m.id = p.module_id AND m.name = 'reports'
JOIN `actions` a ON a.id = p.action_id AND a.name = 'export';

-- +migrate DOWN

-- Restore the umbrella reports.export pair
INSERT IGNORE INTO `permissions` (`module_id`, `action_id`)
SELECT m.id, a.id
FROM `modules` m
JOIN `actions` a ON a.name = 'export'
WHERE m.name = 'reports';

-- Re-grant the umbrella to roles holding any granular export
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT rp.tenant_id, rp.role_id, p.id
FROM `role_permissions` rp
JOIN `permissions` src ON src.id = rp.permission_id
JOIN `modules` sm ON sm.id = src.module_id AND sm.name = 'reports'
JOIN `actions` sa ON sa.id = src.action_id AND sa.name LIKE 'export\_%'
JOIN `permissions` p ON 1 = 1
JOIN `modules` tm ON tm.id = p.module_id AND tm.name = 'reports'
JOIN `actions` ta ON ta.id = p.action_id AND ta.name = 'export';

-- Delete the new pairs and their grants (cascades), then the new rows
DELETE p FROM `permissions` p
JOIN `modules` m ON m.id = p.module_id
JOIN `actions` a ON a.id = p.action_id
WHERE (m.name = 'reports' AND a.name IN (
        'view_gatepasses','view_visitors','view_visits','view_occupancy',
        'view_scans','view_departments',
        'export_gatepasses','export_visitors','export_visits',
        'export_occupancy','export_scans','export_departments'))
   OR (m.name = 'visitors' AND a.name = 'unblacklist')
   OR (m.name = 'audit' AND a.name = 'export');

DELETE FROM `modules` WHERE `name` IN ('gates','email');

DELETE FROM `actions` WHERE `name` IN (
    'unblacklist',
    'view_gatepasses','view_visitors','view_visits','view_occupancy',
    'view_scans','view_departments',
    'export_gatepasses','export_visitors','export_visits',
    'export_occupancy','export_scans','export_departments',
    'rotate','test_send'
);
