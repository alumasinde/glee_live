<?php

namespace App\Modules\Approval\Services;

use App\Core\DB;
use PDO;
use RuntimeException;

/**
 * Workflow approval engine.
 *
 * Steps are resolved from configuration, not code paths:
 *   workflow_steps.approval_mode      'any' = first responder advances, 'all' = unanimous
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

    public function __construct()
    {
        $this->db       = DB::connect();
        $this->notifier = new ApprovalNotifier();
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

            $step = $this->fetchOne("
                SELECT ws.step_order, ws.approval_mode
                FROM workflow_steps ws
                WHERE ws.id = ? AND ws.tenant_id = ?
            ", [$approval['workflow_step_id'], $tenantId]);

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
            $this->lockCurrentStepApproval($approvalId, $userId, $instance);

            $this->db->prepare("
                UPDATE gatepass_approvals
                SET status = 'rejected',
                    acted_at = NOW(),
                    comments = ?
                WHERE id = ? AND tenant_id = ?
            ")->execute([$comments, $approvalId, $tenantId]);

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
       ENGINE CORE
    ============================================================ */

    /**
     * Activate the step at $stepOrder: skip empty non-mandatory steps, finalize the
     * workflow past the last step, otherwise fan out pending approvals and move the
     * instance cursor. Caller must already hold the instance lock.
     */
    private function materializeStep(int $tenantId, int $instanceId, int $stepOrder): void
    {
        $instance = $this->fetchOne("
            SELECT gwi.workflow_id, gwi.gatepass_id, g.department_id AS gatepass_department_id,
                   g.gatepass_number, g.purpose, g.created_by,
                   CONCAT(u.first_name, ' ', u.last_name) AS requested_by_name
            FROM gatepass_workflow_instances gwi
            INNER JOIN gatepasses g ON g.id = gwi.gatepass_id AND g.tenant_id = gwi.tenant_id
            LEFT JOIN users u ON u.id = g.created_by AND u.tenant_id = g.tenant_id
            WHERE gwi.id = ? AND gwi.tenant_id = ?
        ", [$instanceId, $tenantId]);

        while (true) {

            $step = $this->fetchOne("
                SELECT id, name, role_id, step_order, is_mandatory, department_source, department_id
                FROM workflow_steps
                WHERE workflow_id = ? AND tenant_id = ? AND step_order = ?
                LIMIT 1
            ", [$instance['workflow_id'], $tenantId, $stepOrder]);

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

                    $this->notifier->queueStepPending(
                        $tenantId,
                        (int) $this->db->lastInsertId(),
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

            $stepOrder++;
        }
    }

    /**
     * Approvers eligible to act on the step, excluding the requester (no
     * self-approval). @return array<int, array{id:int, email:?string, first_name:?string, last_name:?string}>
     */
    private function resolveApprovers(int $tenantId, array $step, int $gatepassDepartmentId, int $requesterUserId): array
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
        $params = [(int) $step['role_id'], $tenantId, $requesterUserId];

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

    private function lockInstance(int $instanceId, int $tenantId): array
    {
        $instance = $this->fetchOne("
            SELECT id, tenant_id, gatepass_id, workflow_id, current_step_order, status
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
            SELECT ga.id, ga.workflow_step_id, ga.status, ws.step_order
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

    /** Requester + gatepass details needed to notify about the final decision. */
    private function requesterContext(int $tenantId, int $gatepassId): ?array
    {
        return $this->fetchOne("
            SELECT g.id AS gatepass_id, g.gatepass_number, g.purpose,
                   u.email, u.first_name, u.last_name
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

    public function getPendingForUser(int $tenantId, int $userId): array
    {
        return $this->fetchAll("
            SELECT
                ga.id,
                gwi.id AS workflow_instance_id,
                g.id AS gatepass_id,
                g.gatepass_number,
                g.purpose,
                ws.name AS step_name,
                gwi.status AS workflow_status,
                ga.status AS approval_status,
                CONCAT(u.first_name, ' ', u.last_name) AS requested_by_name,
                gwi.started_at AS created_at
            FROM gatepass_approvals ga
            INNER JOIN gatepass_workflow_instances gwi
                ON gwi.id = ga.workflow_instance_id
               AND gwi.tenant_id = ga.tenant_id
               AND gwi.status = 'in_progress'
            INNER JOIN gatepasses g
                ON g.id = gwi.gatepass_id
               AND g.tenant_id = gwi.tenant_id
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
            ORDER BY gwi.started_at DESC
        ", [$tenantId, $userId]);
    }

    public function findApproval(int $tenantId, int $approvalId, int $userId): ?array
    {
        $approval = $this->fetchOne("
            SELECT
                ga.id AS id,
                ga.status AS approval_status,
                ga.comments,
                ga.acted_at,
                gwi.status AS workflow_status,
                gwi.started_at AS created_at,
                ws.name AS step_name,
                g.id AS gatepass_id,
                g.gatepass_number,
                g.purpose,
                CONCAT(u.first_name, ' ', u.last_name) AS requested_by_name
            FROM gatepass_approvals ga
            INNER JOIN gatepass_workflow_instances gwi
                ON gwi.id = ga.workflow_instance_id
               AND gwi.tenant_id = ga.tenant_id
            INNER JOIN gatepasses g
                ON g.id = gwi.gatepass_id
               AND g.tenant_id = ga.tenant_id
            INNER JOIN workflow_steps ws
                ON ws.id = ga.workflow_step_id
               AND ws.tenant_id = ga.tenant_id
            INNER JOIN users u
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

        $approval['items'] = $this->fetchAll("
            SELECT *
            FROM gatepass_items
            WHERE gatepass_id = ?
              AND tenant_id = ?
        ", [(int) $approval['gatepass_id'], $tenantId]);

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
                g.purpose
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
            WHERE ga.id = ?
              AND ga.approver_user_id = ?
              AND ga.tenant_id = ?
              AND ga.status = 'pending'
            LIMIT 1
        ", [$approvalId, $userId, $tenantId]);
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
