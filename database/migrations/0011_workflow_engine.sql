-- Migration: 0011_workflow_engine
-- Turns the approval flow into a configurable engine:
--   workflow_steps.approval_mode     'any' (first responder) | 'all' (unanimous)
--   workflow_steps.department_source 'none' | 'step' (step's department_id) | 'gatepass' (requester's department)
--   gatepass_approvals.status gains 'cancelled' (siblings voided when a step/instance concludes)

-- +migrate UP

ALTER TABLE `workflow_steps`
  ADD COLUMN `approval_mode` enum('any','all') NOT NULL DEFAULT 'any' AFTER `is_mandatory`,
  ADD COLUMN `department_source` enum('none','step','gatepass') NOT NULL DEFAULT 'none' AFTER `approval_mode`;

ALTER TABLE `gatepass_approvals`
  MODIFY COLUMN `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending';

-- Preserve pre-engine behavior: unanimous steps, step 1 scoped to the requester's department.
UPDATE `workflow_steps` SET `approval_mode` = 'all';
UPDATE `workflow_steps` SET `department_source` = 'gatepass' WHERE `step_order` = 1;

-- +migrate DOWN

UPDATE `gatepass_approvals` SET `status` = 'pending' WHERE `status` = 'cancelled';

ALTER TABLE `gatepass_approvals`
  MODIFY COLUMN `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending';

ALTER TABLE `workflow_steps`
  DROP COLUMN `approval_mode`,
  DROP COLUMN `department_source`;
