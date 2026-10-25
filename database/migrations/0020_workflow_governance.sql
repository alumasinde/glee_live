-- Migration: 0020_workflow_governance
-- P1-5: workflow_steps.fallback_role_id — when a step's primary role resolves to
--       zero eligible approvers, its fallback role's members are used instead.
--       Plus the approval.reassign permission for admin reassignment of pending approvals.
-- P1-6: gatepass_workflow_instances.step_snapshot — the step configuration frozen
--       at workflow start, so later admin edits to workflow_steps cannot hijack
--       in-flight instances.

-- +migrate UP

ALTER TABLE `workflow_steps`
  ADD COLUMN `fallback_role_id` bigint unsigned DEFAULT NULL AFTER `role_id`,
  ADD KEY `fk_ws_fallback_role` (`fallback_role_id`, `tenant_id`),
  ADD CONSTRAINT `fk_ws_fallback_role` FOREIGN KEY (`fallback_role_id`, `tenant_id`)
    REFERENCES `roles` (`id`, `tenant_id`) ON DELETE RESTRICT;

ALTER TABLE `gatepass_workflow_instances`
  ADD COLUMN `step_snapshot` json DEFAULT NULL AFTER `current_step_order`;

INSERT IGNORE INTO `actions` (`name`) VALUES ('reassign');

INSERT IGNORE INTO `permissions` (`module_id`, `action_id`)
SELECT m.id, a.id
FROM `modules` m
JOIN `actions` a ON a.name = 'reassign'
WHERE m.name = 'approval';

-- Default holders: admins, plus existing approval.reject holders
-- (anyone trusted to decide is trusted to reroute a stuck approval).
INSERT IGNORE INTO `role_permissions` (`tenant_id`, `role_id`, `permission_id`)
SELECT r.tenant_id, r.id, p.id
FROM `roles` r
JOIN `permissions` p ON 1 = 1
JOIN `modules` m ON m.id = p.module_id AND m.name = 'approval'
JOIN `actions` a ON a.id = p.action_id AND a.name = 'reassign'
WHERE r.`name` = 'admin'
   OR r.id IN (
      SELECT rp.role_id
      FROM `role_permissions` rp
      JOIN `permissions` p2 ON p2.id = rp.permission_id
      JOIN `modules` m2 ON m2.id = p2.module_id AND m2.name = 'approval'
      JOIN `actions` a2 ON a2.id = p2.action_id AND a2.name = 'reject'
   );

-- +migrate DOWN

DELETE rp FROM `role_permissions` rp
JOIN `permissions` p ON p.id = rp.permission_id
JOIN `modules` m ON m.id = p.module_id AND m.name = 'approval'
JOIN `actions` a ON a.id = p.action_id AND a.name = 'reassign';

DELETE p FROM `permissions` p
JOIN `modules` m ON m.id = p.module_id AND m.name = 'approval'
JOIN `actions` a ON a.id = p.action_id AND a.name = 'reassign';

DELETE FROM `actions` WHERE `name` = 'reassign';

ALTER TABLE `gatepass_workflow_instances`
  DROP COLUMN `step_snapshot`;

ALTER TABLE `workflow_steps`
  DROP FOREIGN KEY `fk_ws_fallback_role`,
  DROP KEY `fk_ws_fallback_role`,
  DROP COLUMN `fallback_role_id`;
