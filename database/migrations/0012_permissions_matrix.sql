-- Migration: 0012_permissions_matrix
-- Rebuild the permission matrix from the canonical blueprint
-- (config/permissions.php): dedupe modules (gatepass/gatepasses, audit/audit_logs),
-- drop cartesian junk permissions, and re-grant sensible defaults per role name.
-- WARNING: all existing role_permissions grants are wiped and replaced with defaults.

-- +migrate UP

INSERT IGNORE INTO `actions` (`name`) VALUES
('access'),('view'),('view_all'),('create'),('update'),('delete'),
('approve'),('reject'),('checkin'),('checkout'),('scan'),('print'),
('check_in'),('check_out'),('export'),('blacklist'),('issue_badge'),
('assign'),('disable');

DELETE FROM `role_permissions`;

DELETE FROM `permissions`;

DELETE FROM `modules` WHERE `name` NOT IN (
    'dashboard','gatepass','approval','visitors','visits','reports',
    'audit','roles','users','settings','workflows','departments'
);

INSERT IGNORE INTO `modules` (`name`) VALUES
('dashboard'),('gatepass'),('approval'),('visitors'),('visits'),('reports'),
('audit'),('roles'),('users'),('settings'),('workflows'),('departments');

INSERT INTO `permissions` (`module_id`, `action_id`)
SELECT m.id, a.id
FROM (
    SELECT 'dashboard' AS module, 'access' AS action UNION ALL
    SELECT 'gatepass','view' UNION ALL
    SELECT 'gatepass','view_all' UNION ALL
    SELECT 'gatepass','create' UNION ALL
    SELECT 'gatepass','update' UNION ALL
    SELECT 'gatepass','delete' UNION ALL
    SELECT 'gatepass','checkin' UNION ALL
    SELECT 'gatepass','checkout' UNION ALL
    SELECT 'gatepass','scan' UNION ALL
    SELECT 'gatepass','print' UNION ALL
    SELECT 'approval','view' UNION ALL
    SELECT 'approval','approve' UNION ALL
    SELECT 'approval','reject' UNION ALL
    SELECT 'visitors','view' UNION ALL
    SELECT 'visitors','create' UNION ALL
    SELECT 'visitors','update' UNION ALL
    SELECT 'visitors','delete' UNION ALL
    SELECT 'visitors','blacklist' UNION ALL
    SELECT 'visitors','issue_badge' UNION ALL
    SELECT 'visits','view' UNION ALL
    SELECT 'visits','create' UNION ALL
    SELECT 'visits','check_in' UNION ALL
    SELECT 'visits','check_out' UNION ALL
    SELECT 'visits','export' UNION ALL
    SELECT 'reports','view' UNION ALL
    SELECT 'reports','export' UNION ALL
    SELECT 'audit','view' UNION ALL
    SELECT 'roles','view' UNION ALL
    SELECT 'roles','create' UNION ALL
    SELECT 'roles','update' UNION ALL
    SELECT 'roles','delete' UNION ALL
    SELECT 'roles','assign' UNION ALL
    SELECT 'users','view' UNION ALL
    SELECT 'users','create' UNION ALL
    SELECT 'users','update' UNION ALL
    SELECT 'users','disable' UNION ALL
    SELECT 'settings','view' UNION ALL
    SELECT 'settings','update' UNION ALL
    SELECT 'workflows','view' UNION ALL
    SELECT 'workflows','create' UNION ALL
    SELECT 'workflows','update' UNION ALL
    SELECT 'workflows','delete' UNION ALL
    SELECT 'departments','view' UNION ALL
    SELECT 'departments','create' UNION ALL
    SELECT 'departments','update' UNION ALL
    SELECT 'departments','delete'
) t
JOIN `modules` m ON m.name = t.module
JOIN `actions` a ON a.name = t.action;

-- admin: everything
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT r.tenant_id, r.id, p.id
FROM `roles` r
CROSS JOIN `permissions` p
WHERE r.`name` = 'admin';

-- General Manager: org-wide visibility, approvals, reports
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT r.tenant_id, r.id, p.id
FROM `roles` r
JOIN `permissions` p ON 1 = 1
JOIN `modules` m ON m.id = p.module_id
JOIN `actions` a ON a.id = p.action_id
WHERE r.`name` = 'General Manager'
  AND (m.name, a.name) IN (
    ('dashboard','access'),
    ('gatepass','view'),('gatepass','view_all'),('gatepass','create'),('gatepass','update'),
    ('approval','view'),('approval','approve'),('approval','reject'),
    ('visitors','view'),
    ('visits','view'),
    ('reports','view'),('reports','export'),
    ('audit','view')
  );

-- Department Head: own-department operations and approvals
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT r.tenant_id, r.id, p.id
FROM `roles` r
JOIN `permissions` p ON 1 = 1
JOIN `modules` m ON m.id = p.module_id
JOIN `actions` a ON a.id = p.action_id
WHERE r.`name` = 'Department Head'
  AND (m.name, a.name) IN (
    ('dashboard','access'),
    ('gatepass','view'),('gatepass','create'),('gatepass','update'),
    ('approval','view'),('approval','approve'),('approval','reject'),
    ('visitors','view'),
    ('visits','view'),
    ('reports','view')
  );

-- Security Officer: gate control
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT r.tenant_id, r.id, p.id
FROM `roles` r
JOIN `permissions` p ON 1 = 1
JOIN `modules` m ON m.id = p.module_id
JOIN `actions` a ON a.id = p.action_id
WHERE r.`name` = 'Security Officer'
  AND (m.name, a.name) IN (
    ('dashboard','access'),
    ('gatepass','view'),('gatepass','view_all'),('gatepass','checkin'),
    ('gatepass','checkout'),('gatepass','scan'),('gatepass','print'),
    ('visitors','view'),
    ('visits','view'),('visits','check_in'),('visits','check_out')
  );

-- Receptionist: visitor front-desk
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT r.tenant_id, r.id, p.id
FROM `roles` r
JOIN `permissions` p ON 1 = 1
JOIN `modules` m ON m.id = p.module_id
JOIN `actions` a ON a.id = p.action_id
WHERE r.`name` = 'Receptionist'
  AND (m.name, a.name) IN (
    ('dashboard','access'),
    ('gatepass','view'),('gatepass','create'),('gatepass','print'),
    ('visitors','view'),('visitors','create'),('visitors','update'),
    ('visitors','blacklist'),('visitors','issue_badge'),
    ('visits','view'),('visits','create')
  );

-- +migrate DOWN

DELETE FROM `role_permissions`;

DELETE FROM `permissions`;

DELETE FROM `actions` WHERE `name` IN ('view_all','scan','issue_badge');
