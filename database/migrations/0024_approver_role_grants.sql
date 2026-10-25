-- Migration: 0024_approver_role_grants
-- The seeded workflow steps and the demo seeder both assign approval tasks to
-- Receptionist and Security Officer users, but 0012 granted those two roles no
-- approval permissions at all. The engine staffed the step, the assignee opened
-- /approvals and got a 403, so the mandatory step could never clear.
--
-- Grant the acting permissions for every tenant that has the roles.

-- +migrate UP

INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT r.tenant_id, r.id, p.id
FROM `roles` r
JOIN `permissions` p ON 1 = 1
JOIN `modules` m ON m.id = p.module_id
JOIN `actions` a ON a.id = p.action_id
WHERE r.`name` IN ('Receptionist', 'Security Officer')
  AND (m.name, a.name) IN (
    ('approval','view'),
    ('approval','approve'),
    ('approval','reject')
  );

-- +migrate DOWN

DELETE rp FROM `role_permissions` rp
JOIN `roles` r ON r.id = rp.role_id AND r.tenant_id = rp.tenant_id
JOIN `permissions` p ON p.id = rp.permission_id
JOIN `modules` m ON m.id = p.module_id
JOIN `actions` a ON a.id = p.action_id
WHERE r.`name` IN ('Receptionist', 'Security Officer')
  AND m.name = 'approval'
  AND a.name IN ('view', 'approve', 'reject');
