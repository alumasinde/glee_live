<?php

namespace App\Modules\Approval\Services;

use App\Core\Audit;
use App\Core\DB;
use PDO;
use RuntimeException;

/**
 * Workflow approval engine.
 *
 * Steps are resolved from configuration, not code paths:
 *   workflow_steps.approval_mode      'any' = first responder advances, 'all' = unanimous
 *   workflow_steps.approver_kind      'role' (role_id + scope) | 'users' (named roster)
 *   workflow_steps.department_source  'none' | 'step' (step's department_id) | 'gatepass' (requester's department)
 *   workflow_steps.is_mandatory       0 = step auto-skips when it resolves to zero approvers
 *
 * All mutations lock the workflow instance row first, so concurrent approvals on the
 * same instance serialize and can never leave a step stalled or double-advanced.
 */
class ApprovalService
{
    private PDO $db;
    private ApprovalNotifier $notifier;
    private DelegationService $delegations;

    public function __construct()
    {
        $this->db          = DB::connect();
        $this->notifier    = new ApprovalNotifier();
        $this->delegations = new DelegationService($this->db);
    }

    /* ============================================================
       START WORKFLOW
    ============================================================ */

    public function startWorkflow(int $tenantId, int $gatepassId, int $workflowId): void
    {
        $this->runExclusive(function () use ($tenantId, $gatepassId, $workflowId): void {

            $this->fetchOne("
                SELECT id FROM gatepasses
                WHERE id = ? AND tenant_id = ?
                FOR UPDATE
            ", [$gatepassId, $tenantId])
                ?? throw new RuntimeException("Gatepass not found.");

            $this->fetchOne("
                SELECT id FROM workflows
                WHERE id = ? AND tenant_id = ? AND is_active = 1
            ", [$workflowId, $tenantId])
                ?? throw new RuntimeException("Workflow not found or inactive.");

            if ($this->hasActiveWorkflow($tenantId, $gatepassId)) {
                throw new RuntimeException("Workflow already started.");
            }

            $firstStep = $this->fetchOne("
                SELECT step_order FROM workflow_steps
                WHERE workflow_id = ? AND tenant_id = ?
                ORDER BY step_order
                LIMIT 1
            ", [$workflowId, $tenantId])
                ?? throw new RuntimeException("Workflow has no steps.");

            $stmt = $this->db->prepare("
                INSERT INTO gatepass_workflow_instances
                (tenant_id, gatepass_id, workflow_id, current_step_order, status, started_at)
                VALUES (?, ?, ?, ?, 'in_progress', NOW())
            ");
            $stmt->execute([$tenantId, $gatepassId, $workflowId, (int) $firstStep['step_order']]);

            $instanceId = (int) $this->db->lastInsertId();

            // Freeze the step configuration so later admin edits cannot hijack this instance.
            $steps = $this->fetchAll("
                SELECT id, name, approver_kind, role_id, fallback_role_id, step_order,
                       is_mandatory, approval_mode, department_source, department_id
                FROM workflow_steps
                WHERE workflow_id = ? AND tenant_id = ?
                ORDER BY step_order
            ", [$workflowId, $tenantId]);

            // A named-people roster is frozen as ids; a role step keeps resolving
            // its members live, exactly as before.
            foreach ($steps as &$step) {
                $step['approver_user_ids'] = $step['approver_kind'] === 'users'
                    ? $this->stepApproverIds($tenantId, (int) $step['id'])
                    : [];
            }
            unset($step);

            if ($steps) {
                $this->db->prepare("
                    UPDATE gatepass_workflow_instances
                    SET step_snapshot = ?
                    WHERE id = ? AND tenant_id = ?
                ")->execute([json_encode($steps, JSON_UNESCAPED_UNICODE), $instanceId, $tenantId]);
            }

            Audit::system($tenantId, 'workflow.started', 'gatepass_workflow_instance', $instanceId, [
                'gatepass_id'  => $gatepassId,
                'workflow_id'  => $workflowId,
                'first_step'   => (int) $firstStep['step_order'],
            ]);

            $this->materializeStep($tenantId, $instanceId, (int) $firstStep['step_order']);
        });
    }

    /* ============================================================
       APPROVE
    ============================================================ */

    public function approve(int $approvalId, int $userId, ?string $comment = null): int
    {
        return $this->runExclusive(function () use ($approvalId, $userId, $comment): int {

            $pre = $this->fetchOne("
                SELECT workflow_instance_id, tenant_id
                FROM gatepass_approvals
                WHERE id = ? AND approver_user_id = ?
            ", [$approvalId, $userId])
                ?? throw new RuntimeException("Approval not found.");

            $tenantId   = (int) $pre['tenant_id'];
            $instanceId = (int) $pre['workflow_instance_id'];

            $instance = $this->lockInstance($instanceId, $tenantId);
            $approval = $this->lockCurrentStepApproval($approvalId, $userId, $instance);

            $this->db->prepare("
                UPDATE gatepass_approvals
                SET status = 'approved',
                    acted_at = NOW(),
                    comments = COALESCE(?, comments)
                WHERE id = ? AND tenant_id = ?
            ")->execute([$comment, $approvalId, $tenantId]);

            Audit::log('approval.approved', 'gatepass_approval', $approvalId, [
                'user_id'               => $userId,
                'workflow_instance_id'  => $instanceId,
                'gatepass_id'           => (int) $instance['gatepass_id'],
                'step_order'            => (int) $approval['step_order'],
                'comments'              => $comment,
            ]);

            $step = $this->resolveStepConfigById($instance, $tenantId, (int) $approval['workflow_step_id']);

            if ($step['approval_mode'] === 'any') {
                $this->cancelPendingForStep($instanceId, (int) $approval['workflow_step_id'], $tenantId);
            } elseif ($this->countPendingForStep($instanceId, (int) $approval['workflow_step_id'], $tenantId) > 0) {
                return $instanceId;
            }

            $this->materializeStep($tenantId, $instanceId, (int) $step['step_order'] + 1);

            return $instanceId;
        });
    }

    /* ============================================================
       REJECT
    ============================================================ */

    public function reject(int $approvalId, int $userId, string $comments): int
    {
        return $this->runExclusive(function () use ($approvalId, $userId, $comments): int {

            $pre = $this->fetchOne("
                SELECT workflow_instance_id, tenant_id
                FROM gatepass_approvals
                WHERE id = ? AND approver_user_id = ?
            ", [$approvalId, $userId])
                ?? throw new RuntimeException("Approval not found.");

            $tenantId   = (int) $pre['tenant_id'];
            $instanceId = (int) $pre['workflow_instance_id'];

            $instance = $this->lockInstance($instanceId, $tenantId);
            $approval = $this->lockCurrentStepApproval($approvalId, $userId, $instance);

            $this->db->prepare("
                UPDATE gatepass_approvals
                SET status = 'rejected',
                    acted_at = NOW(),
                    comments = ?
                WHERE id = ? AND tenant_id = ?
            ")->execute([$comments, $approvalId, $tenantId]);

            Audit::log('approval.rejected', 'gatepass_approval', $approvalId, [
                'user_id'               => $userId,
                'workflow_instance_id'  => $instanceId,
                'gatepass_id'           => (int) $instance['gatepass_id'],
                'step_order'            => (int) $approval['step_order'],
                'comments'              => $comments,
            ]);

            $this->cancelPendingForInstance($instanceId, $tenantId);

            $this->db->prepare("
                UPDATE gatepass_workflow_instances
                SET status = 'rejected',
                    completed_at = NOW()
                WHERE id = ? AND tenant_id = ?
            ")->execute([$instanceId, $tenantId]);

            $this->setGatepassStatus(
                $tenantId,
                (int) $instance['gatepass_id'],
                'rejected',
                'Rejected status not configured.'
            );

            $context = $this->requesterContext($tenantId, (int) $instance['gatepass_id']);
            if ($context) {
                $this->notifier->queueDecision($tenantId, $context, false, $comments);
            }

            return $instanceId;
        });
    }

    /* ============================================================
       REASSIGN (admin recovery of stuck pending approvals)
    ============================================================ */

    /**
     * Move a pending approval to another user (e.g. approver on leave or the
     * step role resolved to nobody). Notifies the new assignee.
     */
    public function reassign(int $approvalId, int $newUserId, int $tenantId): int
    {
        return $this->runExclusive(function () use ($approvalId, $newUserId, $tenantId): int {

            $pre = $this->fetchOne("
                SELECT workflow_instance_id, workflow_step_id, approver_user_id
                FROM gatepass_approvals
                WHERE id = ? AND tenant_id = ?
            ", [$approvalId, $tenantId])
                ?? throw new RuntimeException("Approval not found.");

            $instanceId = (int) $pre['workflow_instance_id'];
            $oldUserId  = (int) $pre['approver_user_id'];

            $instance = $this->lockInstance($instanceId, $tenantId);
            $approval = $this->lockCurrentStepApproval($approvalId, $oldUserId, $instance);

            $existing = $this->fetchOne("
                SELECT id FROM gatepass_approvals
                WHERE workflow_instance_id = ?
                  AND workflow_step_id = ?
                  AND approver_user_id = ?
                  AND status = 'pending'
            ", [$instanceId, (int) $approval['workflow_step_id'], $newUserId]);

            if ($existing) {
                throw new RuntimeException('That user is already a pending approver on this step.');
            }

            $context = $this->instanceNotificationContext($tenantId, $instanceId);

            $target = $this->fetchOne("
                SELECT id, email, first_name, last_name
                FROM users
                WHERE id = ? AND tenant_id = ? AND is_active = 1 AND id <> ?
            ", [$newUserId, $tenantId, (int) ($context['created_by'] ?? 0)]);

            if (!$target) {
                throw new RuntimeException('Reassign target must be an active user in this tenant and not the requester.');
            }

            $this->db->prepare("
                UPDATE gatepass_approvals
                SET approver_user_id = ?
                WHERE id = ? AND tenant_id = ? AND status = 'pending'
            ")->execute([$newUserId, $approvalId, $tenantId]);

            if ($context) {
                $this->notifier->queueStepPending(
                    $tenantId,
                    $approvalId,
                    $target,
                    $context,
                    (string) $approval['step_name']
                );
            }

            Audit::log('approval.reassigned', 'gatepass_approval', $approvalId, [
                'gatepass_id'          => (int) $instance['gatepass_id'],
                'workflow_instance_id' => $instanceId,
                'from_user_id'         => $oldUserId,
                'to_user_id'           => $newUserId,
                'target_label'         => sprintf('%d → %d', $oldUserId, $newUserId),
            ]);

            return $instanceId;
        });
    }

    /** Active tenant users eligible to be assigned a pending approval. */
    public function reassignCandidates(int $tenantId, int $requesterUserId, int $currentApproverId): array
    {
        return $this->fetchAll("
            SELECT u.id,
                   CONCAT(u.first_name, ' ', u.last_name) AS name,
                   u.email,
                   GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS roles
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id AND ur.tenant_id = u.tenant_id
            LEFT JOIN roles r ON r.id = ur.role_id AND r.tenant_id = ur.tenant_id
            WHERE u.tenant_id = ?
              AND u.is_active = 1
              AND u.id NOT IN (?, ?)
            GROUP BY u.id, name, u.email
            ORDER BY name
        ", [$tenantId, $requesterUserId, $currentApproverId]);
    }

    /* ============================================================
       ENGINE CORE
    ============================================================ */

    /**
     * Activate the step at $stepOrder: skip empty non-mandatory steps, finalize the
     * workflow past the last step, otherwise fan out pending approvals and move the
     * instance cursor. Caller must already hold the instance lock.
     */
    private function materializeStep(int $tenantId, int $instanceId, int $stepOrder): void
    {
        $instance = $this->instanceNotificationContext($tenantId, $instanceId);

        while (true) {

            $step = $this->resolveStepConfig($instance, $tenantId, $stepOrder);

            if (!$step) {
                $this->finalizeApproved($tenantId, $instanceId, (int) $instance['gatepass_id']);
                return;
            }

            $approvers = $this->resolveApprovers($tenantId, $step, (int) $instance['gatepass_department_id'], (int) ($instance['created_by'] ?? 0));

            if ($approvers) {
                $this->db->prepare("
                    UPDATE gatepass_workflow_instances
                    SET current_step_order = ?
                    WHERE id = ? AND tenant_id = ?
                ")->execute([$stepOrder, $instanceId, $tenantId]);

                $insert = $this->db->prepare("
                    INSERT INTO gatepass_approvals
                    (tenant_id, workflow_instance_id, workflow_step_id, approver_user_id, status)
                    VALUES (?, ?, ?, ?, 'pending')
                ");

                foreach ($approvers as $approver) {
                    $insert->execute([$tenantId, $instanceId, (int) $step['id'], (int) $approver['id']]);

                    $approvalRowId = (int) $this->db->lastInsertId();

                    if (!empty($approver['covers'])) {
                        Audit::system($tenantId, 'approval.delegated', 'gatepass_approval', $approvalRowId, [
                            'workflow_instance_id' => $instanceId,
                            'gatepass_id'          => (int) $instance['gatepass_id'],
                            'step_order'           => (int) $step['step_order'],
                            'from_user_id'         => (int) $approver['covers']['user_id'],
                            'to_user_id'           => (int) $approver['id'],
                            'delegate_ends_at'     => $approver['covers']['ends_at'],
                            'target_label'         => sprintf(
                                '%s → %s (delegation)',
                                (string) $approver['covers']['name'],
                                (string) ($approver['name'] ?? $approver['id'])
                            ),
                        ]);
                    }

                    $this->notifier->queueStepPending(
                        $tenantId,
                        $approvalRowId,
                        $approver,
                        $instance,
                        (string) $step['name']
                    );
                }

                return;
            }

            if ((int) $step['is_mandatory'] === 1) {
                throw new RuntimeException("Step '{$step['name']}' has no eligible approvers.");
            }

            Audit::system($tenantId, 'workflow.step_auto_skipped', 'workflow_step', (int) $step['id'], [
                'workflow_instance_id' => $instanceId,
                'gatepass_id'          => (int) $instance['gatepass_id'],
                'step_order'           => (int) $step['step_order'],
                'step_name'            => $step['name'],
                'reason'               => 'no_eligible_approvers',
            ]);

            $stepOrder++;
        }
    }

    /**
     * Approvers eligible to act on the step, excluding the requester (no
     * self-approval). A step either fans out to its role members (optionally
     * scoped to a department) or to the named people on its roster. When the
     * primary target resolves to nobody, step.fallback_role_id members are used,
     * then anyone covered by an active delegation is redirected.
     *
     * @return list<array{id:int, email:?string, first_name:?string, last_name:?string, name?:string, covers?:array}>
     */
    private function resolveApprovers(int $tenantId, array $step, int $gatepassDepartmentId, int $requesterUserId): array
    {
        $isRoster = ($step['approver_kind'] ?? 'role') === 'users';

        $primaryRoleId = !empty($step['role_id']) ? (int) $step['role_id'] : null;

        if ($isRoster) {
            $approvers = $this->approversForUsers(
                $tenantId,
                $this->stepApproverUserIds($tenantId, $step),
                $requesterUserId
            );
        } else {
            $approvers = $primaryRoleId === null
                ? []
                : $this->approversForRole($tenantId, $primaryRoleId, $step, $gatepassDepartmentId, $requesterUserId);
        }

        if (!$approvers && !empty($step['fallback_role_id'])) {
            $approvers = $this->approversForRole(
                $tenantId,
                (int) $step['fallback_role_id'],
                $step,
                $gatepassDepartmentId,
                $requesterUserId
            );
        }

        // A roster has no role context, so only a blanket (all-roles) delegation
        // can cover one of its people.
        return $this->applyDelegations($tenantId, $approvers, $isRoster ? null : $primaryRoleId, $requesterUserId);
    }

    /**
     * Swap approvers who are covered right now for their delegates. One row per
     * delegate even if several colleagues hand it to the same person, and never
     * to the requester (that would reintroduce self-approval). A covered user
     * whose delegate is the requester drops out entirely — that is the honest
     * signal that the step cannot be staffed, and is_mandatory decides whether
     * the workflow skips or errors.
     */
    private function applyDelegations(int $tenantId, array $approvers, ?int $roleId, int $requesterUserId): array
    {
        if (!$approvers) {
            return [];
        }

        $delegates = $this->delegations->activeDelegatesFor(
            $tenantId,
            array_column($approvers, 'id'),
            $roleId
        );

        if (!$delegates) {
            return $approvers;
        }

        $result = [];
        $taken  = [];

        foreach ($approvers as $approver) {
            $userId = (int) $approver['id'];

            if (!isset($delegates[$userId])) {
                $result[] = $approver;
                $taken[$userId] = true;
            }
        }

        foreach ($approvers as $approver) {
            $userId = (int) $approver['id'];

            if (!isset($delegates[$userId])) {
                continue;
            }

            $delegate = $delegates[$userId];
            $delegateId = (int) $delegate['delegate_user_id'];

            if ($delegateId === $requesterUserId || isset($taken[$delegateId])) {
                continue;
            }

            $taken[$delegateId] = true;

            $result[] = [
                'id'         => $delegateId,
                'email'      => $delegate['email'],
                'name'       => $delegate['delegate_name'],
                'first_name' => $delegate['delegate_name'],
                'last_name'  => '',
                'covers'     => [
                    'user_id'  => $userId,
                    'name'     => trim(($approver['first_name'] ?? '') . ' ' . ($approver['last_name'] ?? '')),
                    'ends_at'  => $delegate['ends_at'],
                    'note'     => $delegate['note'],
                ],
            ];
        }

        usort($result, static fn (array $a, array $b): int => (int) $a['id'] <=> (int) $b['id']);

        return $result;
    }

    private function approversForRole(int $tenantId, int $roleId, array $step, int $gatepassDepartmentId, int $requesterUserId): array
    {
        $query = "
            SELECT u.id, u.email, u.first_name, u.last_name
            FROM users u
            INNER JOIN user_roles ur ON ur.user_id = u.id AND ur.tenant_id = u.tenant_id
            WHERE ur.role_id = ?
              AND u.tenant_id = ?
              AND u.is_active = 1
              AND u.id <> ?
        ";
        $params = [$roleId, $tenantId, $requesterUserId];

        $departmentId = match ($step['department_source']) {
            'step'     => $step['department_id'] !== null ? (int) $step['department_id'] : null,
            'gatepass' => $gatepassDepartmentId ?: null,
            default    => null,
        };

        if ($departmentId !== null) {
            $query .= " AND u.department_id = ?";
            $params[] = $departmentId;
        }

        return $this->fetchAll($query, $params);
    }

    /**
     * The named people on a roster step. Still filtered to active accounts of
     * this tenant and never the requester, so a person who is deactivated after
     * being ticked simply drops out and the fallback role covers the step.
     */
    private function approversForUsers(int $tenantId, array $userIds, int $requesterUserId): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        if (!$userIds) {
            return [];
        }

        $in = implode(',', array_fill(0, count($userIds), '?'));

        return $this->fetchAll("
            SELECT id, email, first_name, last_name
            FROM users
            WHERE tenant_id = ?
              AND is_active = 1
              AND id <> ?
              AND id IN ($in)
            ORDER BY id
        ", array_merge([$tenantId, $requesterUserId], $userIds));
    }

    /** Roster ids from the frozen snapshot, or from the table for pre-snapshot instances. */
    private function stepApproverUserIds(int $tenantId, array $step): array
    {
        if (isset($step['approver_user_ids']) && is_array($step['approver_user_ids'])) {
            return array_map('intval', $step['approver_user_ids']);
        }

        return $this->stepApproverIds($tenantId, (int) $step['id']);
    }

    /** @return list<int> */
    private function stepApproverIds(int $tenantId, int $stepId): array
    {
        $rows = $this->fetchAll("
            SELECT user_id
            FROM workflow_step_approvers
            WHERE tenant_id = ? AND workflow_step_id = ?
            ORDER BY user_id
        ", [$tenantId, $stepId]);

        return array_map('intval', array_column($rows, 'user_id'));
    }

    private function lockInstance(int $instanceId, int $tenantId): array
    {
        $instance = $this->fetchOne("
            SELECT id, tenant_id, gatepass_id, workflow_id, current_step_order, status, step_snapshot
            FROM gatepass_workflow_instances
            WHERE id = ? AND tenant_id = ?
            FOR UPDATE
        ", [$instanceId, $tenantId])
            ?? throw new RuntimeException("Workflow instance not found.");

        if ($instance['status'] !== 'in_progress') {
            throw new RuntimeException("Workflow not active.");
        }

        return $instance;
    }

    /** Lock and re-validate the acting approval row (instance lock must be held). */
    private function lockCurrentStepApproval(int $approvalId, int $userId, array $instance): array
    {
        $approval = $this->fetchOne("
            SELECT ga.id, ga.workflow_step_id, ga.status, ws.step_order, ws.name AS step_name
            FROM gatepass_approvals ga
            INNER JOIN workflow_steps ws ON ws.id = ga.workflow_step_id AND ws.tenant_id = ga.tenant_id
            WHERE ga.id = ? AND ga.approver_user_id = ? AND ga.tenant_id = ?
            FOR UPDATE
        ", [$approvalId, $userId, (int) $instance['tenant_id']])
            ?? throw new RuntimeException("Approval not found.");

        if ($approval['status'] !== 'pending') {
            throw new RuntimeException("Already processed.");
        }

        if ((int) $approval['step_order'] !== (int) $instance['current_step_order']) {
            throw new RuntimeException("Invalid approval step.");
        }

        return $approval;
    }

    private function cancelPendingForStep(int $instanceId, int $stepId, int $tenantId): void
    {
        $this->db->prepare("
            UPDATE gatepass_approvals
            SET status = 'cancelled',
                acted_at = NOW()
            WHERE workflow_instance_id = ?
              AND workflow_step_id = ?
              AND tenant_id = ?
              AND status = 'pending'
        ")->execute([$instanceId, $stepId, $tenantId]);
    }

    private function cancelPendingForInstance(int $instanceId, int $tenantId): void
    {
        $this->db->prepare("
            UPDATE gatepass_approvals
            SET status = 'cancelled',
                acted_at = NOW()
            WHERE workflow_instance_id = ?
              AND tenant_id = ?
              AND status = 'pending'
        ")->execute([$instanceId, $tenantId]);
    }

    private function countPendingForStep(int $instanceId, int $stepId, int $tenantId): int
    {
        return (int) $this->fetchOne("
            SELECT COUNT(*) AS c
            FROM gatepass_approvals
            WHERE workflow_instance_id = ?
              AND workflow_step_id = ?
              AND tenant_id = ?
              AND status = 'pending'
        ", [$instanceId, $stepId, $tenantId])['c'];
    }

    private function finalizeApproved(int $tenantId, int $instanceId, int $gatepassId): void
    {
        $this->db->prepare("
            UPDATE gatepass_workflow_instances
            SET status = 'approved',
                completed_at = NOW()
            WHERE id = ? AND tenant_id = ?
        ")->execute([$instanceId, $tenantId]);

        Audit::system($tenantId, 'workflow.finalized', 'gatepass_workflow_instance', $instanceId, [
            'gatepass_id' => $gatepassId,
            'result'      => 'approved',
        ]);

        $this->setGatepassStatus(
            $tenantId,
            $gatepassId,
            'approved',
            'Approved status not configured.'
        );

        $context = $this->requesterContext($tenantId, $gatepassId);
        if ($context) {
            $this->notifier->queueDecision($tenantId, $context, true);
        }
    }

    /** Instance + gatepass + requester row used for step-pending notifications. */
    private function instanceNotificationContext(int $tenantId, int $instanceId): ?array
    {
        return $this->fetchOne("
            SELECT gwi.workflow_id, gwi.gatepass_id, gwi.step_snapshot,
                   g.department_id AS gatepass_department_id,
                   g.gatepass_number, g.purpose, g.created_by,
                   CONCAT(u.first_name, ' ', u.last_name) AS requested_by_name
            FROM gatepass_workflow_instances gwi
            INNER JOIN gatepasses g ON g.id = gwi.gatepass_id AND g.tenant_id = gwi.tenant_id
            LEFT JOIN users u ON u.id = g.created_by AND u.tenant_id = g.tenant_id
            WHERE gwi.id = ? AND gwi.tenant_id = ?
        ", [$instanceId, $tenantId]);
    }

    /**
     * Step config for the instance: the frozen snapshot when one exists, so admin
     * edits to workflow_steps after start cannot hijack in-flight requests.
     * Instances predating snapshots fall back to the live configuration.
     */
    private function resolveStepConfig(array $instance, int $tenantId, int $stepOrder): ?array
    {
        $steps = $this->decodedSnapshot($instance);

        if ($steps !== null) {
            foreach ($steps as $step) {
                if ((int) ($step['step_order'] ?? 0) === $stepOrder) {
                    return $step;
                }
            }
            return null;
        }

        return $this->fetchOne("
            SELECT id, name, approver_kind, role_id, fallback_role_id, step_order, is_mandatory,
                   approval_mode, department_source, department_id
            FROM workflow_steps
            WHERE workflow_id = ? AND tenant_id = ? AND step_order = ?
            LIMIT 1
        ", [(int) $instance['workflow_id'], $tenantId, $stepOrder]);
    }

    /** Same as resolveStepConfig, keyed by step id (used when a decision is recorded). */
    private function resolveStepConfigById(array $instance, int $tenantId, int $stepId): array
    {
        $steps = $this->decodedSnapshot($instance);

        if ($steps !== null) {
            foreach ($steps as $step) {
                if ((int) ($step['id'] ?? 0) === $stepId) {
                    return $step;
                }
            }
        }

        return $this->fetchOne("
            SELECT id, name, approver_kind, role_id, fallback_role_id, step_order, is_mandatory,
                   approval_mode, department_source, department_id
            FROM workflow_steps
            WHERE id = ? AND tenant_id = ?
            LIMIT 1
        ", [$stepId, $tenantId])
            ?? throw new RuntimeException('Workflow step configuration not found.');
    }

    /** @return array<int, array>|null null = no usable snapshot (use live config) */
    private function decodedSnapshot(array $instance): ?array
    {
        if (empty($instance['step_snapshot'])) {
            return null;
        }

        $steps = json_decode((string) $instance['step_snapshot'], true);

        return is_array($steps) && $steps !== [] ? $steps : null;
    }

    /** Requester + gatepass details needed to notify about the final decision. */
    private function requesterContext(int $tenantId, int $gatepassId): ?array
    {
        return $this->fetchOne("
            SELECT g.id AS gatepass_id, g.gatepass_number, g.purpose,
                   u.id AS user_id, u.email, u.first_name, u.last_name
            FROM gatepasses g
            INNER JOIN users u ON u.id = g.created_by AND u.tenant_id = g.tenant_id
            WHERE g.id = ? AND g.tenant_id = ?
        ", [$gatepassId, $tenantId]);
    }

    private function setGatepassStatus(int $tenantId, int $gatepassId, string $code, string $errorMessage): void
    {
        $status = $this->fetchOne("
            SELECT id
            FROM gatepass_statuses
            WHERE tenant_id = ?
              AND (code = ? OR LOWER(name) = ?)
            LIMIT 1
        ", [$tenantId, $code, $code]);

        if (!$status) {
            throw new RuntimeException($errorMessage);
        }

        $this->db->prepare("
            UPDATE gatepasses
            SET status_id = ?
            WHERE id = ? AND tenant_id = ?
        ")->execute([(int) $status['id'], $gatepassId, $tenantId]);
    }

    /**
     * Run $work inside a transaction that locks the instance for its whole duration,
     * joining an outer transaction when one is already open.
     */
    private function runExclusive(callable $work): mixed
    {
        $ownTransaction = !$this->db->inTransaction();

        try {
            if ($ownTransaction) {
                $this->db->beginTransaction();
            }

            $result = $work();

            if ($ownTransaction) {
                $this->db->commit();
                $this->notifier->flush();
            }

            return $result;
        } catch (\Throwable $e) {
            if ($ownTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ($ownTransaction) {
                $this->notifier->discard();
            }
            throw $e;
        }
    }

    /* ============================================================
       NOTIFICATION FLUSH (for outer-transaction owners)
    ============================================================ */

    /** Send notifications queued during a workflow start wrapped in an outer transaction. */
    public function flushNotifications(): void
    {
        $this->notifier->flush();
    }

    /** Drop queued notifications after an outer transaction rolled back. */
    public function discardNotifications(): void
    {
        $this->notifier->discard();
    }

    /** Notifications waiting to be flushed (observability for tests). */
    public function pendingNotificationCount(): int
    {
        return $this->notifier->pendingCount();
    }

    /* ============================================================
       QUERIES
    ============================================================ */

    public function hasActiveWorkflow(int $tenantId, int $gatepassId): bool
    {
        return (bool) $this->fetchOne("
            SELECT 1 AS ok
            FROM gatepass_workflow_instances
            WHERE tenant_id = ?
              AND gatepass_id = ?
              AND status = 'in_progress'
        ", [$tenantId, $gatepassId]);
    }

    /**
     * Approvals waiting on the acting user.
     *
     * $sort 'age' (default) puts the longest-waiting request first, which is the
     * order a work queue should be cleared in; 'recent' is newest-first.
     * $limit 0 means "everything" — callers that page use paginatePending().
     *
     * @return list<array>
     */
    public function getPendingForUser(int $tenantId, int $userId, string $sort = 'age', int $limit = 0, int $offset = 0): array
    {
        [$sql, $params] = $this->pendingQuery($tenantId, $userId, $sort);

        if ($limit > 0) {
            // Cast ints, so interpolating them cannot open an injection hole.
            $sql .= ' LIMIT ' . max(1, $limit) . ' OFFSET ' . max(0, $offset);
        }

        return $this->fetchAll($sql, $params);
    }

    public function countPending(int $tenantId, int $userId): int
    {
        return (int) $this->fetchOne("
            SELECT COUNT(*) AS c
            FROM gatepass_approvals ga
            INNER JOIN gatepass_workflow_instances gwi
                ON gwi.id = ga.workflow_instance_id
               AND gwi.tenant_id = ga.tenant_id
               AND gwi.status = 'in_progress'
            INNER JOIN workflow_steps ws
                ON ws.id = ga.workflow_step_id
               AND ws.tenant_id = ga.tenant_id
               AND ws.step_order = gwi.current_step_order
            WHERE ga.tenant_id = ?
              AND ga.approver_user_id = ?
              AND ga.status = 'pending'
        ", [$tenantId, $userId])['c'];
    }

    /**
     * One page of the pending queue plus the meta the paginator view needs.
     *
     * @return array{data: list<array>, meta: array{total:int, per_page:int, current_page:int, last_page:int, from:int, to:int}}
     */
    public function paginatePending(int $tenantId, int $userId, string $sort = 'age', int $page = 1, int $perPage = 20): array
    {
        $perPage      = max(5, min($perPage, 100));
        $total        = $this->countPending($tenantId, $userId);
        $lastPage     = max(1, (int) ceil($total / $perPage));
        $page         = max(1, min($page, $lastPage));
        $offset       = ($page - 1) * $perPage;
        $rows         = $this->getPendingForUser($tenantId, $userId, $sort, $perPage, $offset);

        return [
            'data' => $rows,
            'meta' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => $lastPage,
                'from'         => $total > 0 ? $offset + 1 : 0,
                'to'           => min($offset + $perPage, $total),
            ],
        ];
    }

    /** Hours an approval has been waiting, used to flag overdue items. */
    public function reminderHours(): int
    {
        return max(1, (int) (config('jobs.reminder_hours') ?? 24));
    }

    /** @return array{0: string, 1: array} SQL and its bound params */
    private function pendingQuery(int $tenantId, int $userId, string $sort): array
    {
        $order = $sort === 'recent'
            ? 'gwi.started_at DESC, ga.id DESC'
            : 'gwi.started_at ASC, ga.id ASC';

        $sql = "
            SELECT
                ga.id,
                gwi.id AS workflow_instance_id,
                g.id AS gatepass_id,
                g.gatepass_number,
                g.purpose,
                gt.name AS type_name,
                ws.name AS step_name,
                gwi.status AS workflow_status,
                ga.status AS approval_status,
                ga.escalation_count,
                CONCAT(u.first_name, ' ', u.last_name) AS requested_by_name,
                gwi.started_at AS created_at,
                TIMESTAMPDIFF(HOUR, gwi.started_at, NOW()) AS waiting_hours
            FROM gatepass_approvals ga
            INNER JOIN gatepass_workflow_instances gwi
                ON gwi.id = ga.workflow_instance_id
               AND gwi.tenant_id = ga.tenant_id
               AND gwi.status = 'in_progress'
            INNER JOIN gatepasses g
                ON g.id = gwi.gatepass_id
               AND g.tenant_id = gwi.tenant_id
            LEFT JOIN gatepass_types gt
                ON gt.id = g.gatepass_type_id
               AND gt.tenant_id = g.tenant_id
            INNER JOIN workflow_steps ws
                ON ws.id = ga.workflow_step_id
               AND ws.tenant_id = ga.tenant_id
               AND ws.step_order = gwi.current_step_order
            INNER JOIN users u
                ON u.id = g.created_by
               AND u.tenant_id = g.tenant_id
            WHERE ga.tenant_id = ?
              AND ga.approver_user_id = ?
              AND ga.status = 'pending'
            ORDER BY $order
        ";

        return [$sql, [$tenantId, $userId]];
    }

    public function findApproval(int $tenantId, int $approvalId, int $userId): ?array
    {
        $approval = $this->fetchOne("
            SELECT
                ga.id AS id,
                ga.status AS approval_status,
                ga.comments,
                ga.acted_at,
                gwi.id AS workflow_instance_id,
                gwi.status AS workflow_status,
                gwi.current_step_order,
                gwi.started_at AS created_at,
                ws.name AS step_name,
                g.id AS gatepass_id,
                g.gatepass_number,
                g.purpose,
                g.is_returnable,
                g.expected_return_date,
                gt.name AS type_name,
                gs.name AS gatepass_status,
                dep.name AS department_name,
                CONCAT(u.first_name, ' ', u.last_name) AS requested_by_name
            FROM gatepass_approvals ga
            INNER JOIN gatepass_workflow_instances gwi
                ON gwi.id = ga.workflow_instance_id
               AND gwi.tenant_id = ga.tenant_id
            INNER JOIN gatepasses g
                ON g.id = gwi.gatepass_id
               AND g.tenant_id = ga.tenant_id
            LEFT JOIN gatepass_types gt
                ON gt.id = g.gatepass_type_id
               AND gt.tenant_id = g.tenant_id
            LEFT JOIN gatepass_statuses gs
                ON gs.id = g.status_id
               AND gs.tenant_id = g.tenant_id
            LEFT JOIN departments dep
                ON dep.id = g.department_id
               AND dep.tenant_id = g.tenant_id
            INNER JOIN workflow_steps ws
                ON ws.id = ga.workflow_step_id
               AND ws.tenant_id = ga.tenant_id
            LEFT JOIN users u
                ON u.id = g.created_by
               AND u.tenant_id = g.tenant_id
            WHERE ga.id = ?
              AND ga.tenant_id = ?
              AND ga.approver_user_id = ?
            LIMIT 1
        ", [$approvalId, $tenantId, $userId]);

        if (!$approval) {
            return null;
        }

        return $this->attachDetail($approval, $tenantId);
    }

    /** Attach gatepass items and the full step trail to an approval row. */
    private function attachDetail(array $approval, int $tenantId): array
    {
        $approval['items'] = $this->fetchAll("
            SELECT item_name, description, quantity, serial_number, is_returnable, returned_quantity
            FROM gatepass_items
            WHERE gatepass_id = ?
              AND tenant_id = ?
            ORDER BY id
        ", [(int) $approval['gatepass_id'], $tenantId]);

        $approval['trail'] = $this->fetchAll("
            SELECT
                ws.step_order,
                ws.name AS step_name,
                ga.status,
                ga.comments,
                ga.acted_at,
                CONCAT(u.first_name, ' ', u.last_name) AS approver_name
            FROM gatepass_approvals ga
            INNER JOIN workflow_steps ws
                ON ws.id = ga.workflow_step_id
               AND ws.tenant_id = ga.tenant_id
            INNER JOIN users u
                ON u.id = ga.approver_user_id
               AND u.tenant_id = ga.tenant_id
            WHERE ga.workflow_instance_id = ?
              AND ga.tenant_id = ?
              AND ga.status <> 'cancelled'
            ORDER BY ws.step_order, ga.id
        ", [(int) $approval['workflow_instance_id'], $tenantId]);

        return $approval;
    }

    public function findUserApproval(int $tenantId, int $approvalId, int $userId): ?array
    {
        return $this->fetchOne("
            SELECT
                ga.id AS approval_id,
                ga.workflow_instance_id,
                ga.status AS approval_status,
                gwi.status AS workflow_status,
                gwi.current_step_order,
                ws.name AS step_name,
                g.id AS gatepass_id,
                g.gatepass_number,
                g.purpose,
                gt.name AS type_name,
                CONCAT(u.first_name, ' ', u.last_name) AS requested_by_name
            FROM gatepass_approvals ga
            INNER JOIN gatepass_workflow_instances gwi
                ON gwi.id = ga.workflow_instance_id
               AND gwi.tenant_id = ga.tenant_id
            INNER JOIN workflow_steps ws
                ON ws.id = ga.workflow_step_id
               AND ws.tenant_id = ga.tenant_id
            INNER JOIN gatepasses g
                ON g.id = gwi.gatepass_id
               AND g.tenant_id = gwi.tenant_id
            LEFT JOIN gatepass_types gt
                ON gt.id = g.gatepass_type_id
               AND gt.tenant_id = g.tenant_id
            LEFT JOIN users u
                ON u.id = g.created_by
               AND u.tenant_id = g.tenant_id
            WHERE ga.id = ?
              AND ga.approver_user_id = ?
              AND ga.tenant_id = ?
              AND ga.status = 'pending'
            LIMIT 1
        ", [$approvalId, $userId, $tenantId]);
    }

    /** Approval as seen by someone with approval.reassign (not the assignee). */
    public function findApprovalForReassign(int $tenantId, int $approvalId): ?array
    {
        $approval = $this->fetchOne("
            SELECT
                ga.id,
                ga.status AS approval_status,
                ga.approver_user_id,
                ga.workflow_step_id,
                ga.workflow_instance_id,
                gwi.status AS workflow_status,
                gwi.current_step_order,
                ws.name AS step_name,
                g.id AS gatepass_id,
                g.gatepass_number,
                g.purpose,
                g.is_returnable,
                g.expected_return_date,
                gt.name AS type_name,
                gs.name AS gatepass_status,
                dep.name AS department_name,
                g.created_by AS requester_id,
                CONCAT(u.first_name, ' ', u.last_name) AS requested_by_name,
                CONCAT(a.first_name, ' ', a.last_name) AS approver_name
            FROM gatepass_approvals ga
            INNER JOIN gatepass_workflow_instances gwi
                ON gwi.id = ga.workflow_instance_id
               AND gwi.tenant_id = ga.tenant_id
            INNER JOIN workflow_steps ws
                ON ws.id = ga.workflow_step_id
               AND ws.tenant_id = ga.tenant_id
            INNER JOIN gatepasses g
                ON g.id = gwi.gatepass_id
               AND g.tenant_id = gwi.tenant_id
            LEFT JOIN gatepass_types gt
                ON gt.id = g.gatepass_type_id
               AND gt.tenant_id = g.tenant_id
            LEFT JOIN gatepass_statuses gs
                ON gs.id = g.status_id
               AND gs.tenant_id = g.tenant_id
            LEFT JOIN departments dep
                ON dep.id = g.department_id
               AND dep.tenant_id = g.tenant_id
            LEFT JOIN users u
                ON u.id = g.created_by
               AND u.tenant_id = g.tenant_id
            LEFT JOIN users a
                ON a.id = ga.approver_user_id
               AND a.tenant_id = ga.tenant_id
            WHERE ga.id = ?
              AND ga.tenant_id = ?
            LIMIT 1
        ", [$approvalId, $tenantId]);

        if (!$approval) {
            return null;
        }

        return $this->attachDetail($approval, $tenantId);
    }

    /** All pending approvals of a step, for the reassign screen. */
    public function stepApprovals(int $tenantId, int $workflowStepId, int $instanceId): array
    {
        return $this->fetchAll("
            SELECT ga.id, ga.status, CONCAT(u.first_name, ' ', u.last_name) AS approver_name, u.email
            FROM gatepass_approvals ga
            LEFT JOIN users u ON u.id = ga.approver_user_id AND u.tenant_id = ga.tenant_id
            WHERE ga.tenant_id = ?
              AND ga.workflow_step_id = ?
              AND ga.workflow_instance_id = ?
              AND ga.status <> 'cancelled'
            ORDER BY ga.id
        ", [$tenantId, $workflowStepId, $instanceId]);
    }

    private function fetchOne(string $sql, array $params): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function fetchAll(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
