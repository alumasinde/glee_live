-- Migration: 0028_user_deactivation
-- Re-introduces users.disable, now that a real endpoint exists behind it
-- (POST /settings/users/{id}/deactivate and .../activate), and grants it to
-- every role that can already edit users: deactivating is the reversible,
-- history-preserving counterpart of an edit, so the same people should hold it.

-- +migrate UP

INSERT IGNORE INTO `modules` (`name`) VALUES ('users');
INSERT IGNORE INTO `actions` (`name`) VALUES ('disable');

INSERT IGNORE INTO `permissions` (`module_id`, `action_id`)
SELECT m.id, a.id
FROM `modules` m
JOIN `actions` a ON a.name = 'disable'
WHERE m.name = 'users';

-- Propagate to existing roles that hold users.update.
INSERT IGNORE INTO `role_permissions` (`role_id`, `tenant_id`, `permission_id`)
SELECT DISTINCT rp.role_id, rp.tenant_id, dp.id
FROM `role_permissions` rp
JOIN `permissions` up ON up.id = rp.permission_id
JOIN `modules` um ON um.id = up.module_id AND um.name = 'users'
JOIN `actions` ua ON ua.id = up.action_id AND ua.name = 'update'
CROSS JOIN `permissions` dp
JOIN `modules` dm ON dm.id = dp.module_id AND dm.name = 'users'
JOIN `actions` da ON da.id = dp.action_id AND da.name = 'disable';

-- +migrate DOWN

DELETE rp FROM `role_permissions` rp
JOIN `permissions` p ON p.id = rp.permission_id
JOIN `modules` m ON m.id = p.module_id AND m.name = 'users'
JOIN `actions` a ON a.id = p.action_id AND a.name = 'disable';

DELETE p FROM `permissions` p
JOIN `modules` m ON m.id = p.module_id AND m.name = 'users'
JOIN `actions` a ON a.id = p.action_id AND a.name = 'disable';
