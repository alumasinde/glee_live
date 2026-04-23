<?php

namespace App\Modules\Approval\Services;

use App\Core\DB;
use PDO;

class ApprovalService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    /* ============================================================
       APPROVE
    ============================================================ */
public function approve(int $approvalId, int $userId): int
{
    $this->db->beginTransaction();

    try {

        // 🔹 Lock approval row
        $stmt = $this->db->prepare("
            SELECT
                ga.id,
                ga.status AS approval_status,
                ga.workflow_instance_id,
                ga.workflow_step_id,

                gwi.workflow_id,
                gwi.current_step_order,
                gwi.status AS workflow_status,
                gwi.tenant_id,
                gwi.gatepass_id

            FROM gatepass_approvals ga

            INNER JOIN gatepass_workflow_instances gwi
                ON gwi.id = ga.workflow_instance_id

            WHERE ga.id = ?
              AND ga.approver_user_id = ?
            FOR UPDATE
        ");

        $stmt->execute([$approvalId, $userId]);
        $approval = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$approval) {
            throw new \RuntimeException("Approval not found.");
        }

        if ($approval['approval_status'] !== 'pending') {
            throw new \RuntimeException("Already processed.");
        }

        if ($approval['workflow_status'] !== 'in_progress') {
            throw new \RuntimeException("Workflow not active.");
        }

        $instanceId  = (int)$approval['workflow_instance_id'];
        $currentStep = (int)$approval['current_step_order'];

        // 🔹 Validate step matches current workflow step
        $stmt = $this->db->prepare("
            SELECT step_order
            FROM workflow_steps
            WHERE id = ?
              AND workflow_id = ?
        ");
        $stmt->execute([
            $approval['workflow_step_id'],
            $approval['workflow_id']
        ]);

        $stepOrder = (int)$stmt->fetchColumn();

        if ($stepOrder !== $currentStep) {
            throw new \RuntimeException("Invalid approval step.");
        }

        // 🔹 Mark approval approved
        $stmt = $this->db->prepare("
            UPDATE gatepass_approvals
            SET status = 'approved',
                acted_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$approvalId]);

        // 🔹 Check if other approvals still pending at this step
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM gatepass_approvals ga
            INNER JOIN workflow_steps ws
                ON ws.id = ga.workflow_step_id
            WHERE ga.workflow_instance_id = ?
              AND ws.step_order = ?
              AND ga.status = 'pending'
        ");
        $stmt->execute([$instanceId, $currentStep]);

        if ((int)$stmt->fetchColumn() > 0) {
            $this->db->commit();
            return $instanceId;
        }

        // 🔹 Move to next step
        $this->advanceToNextStep($instanceId);

        $this->db->commit();
        return $instanceId;

    } catch (\Throwable $e) {
        $this->db->rollBack();
        throw $e;
    }
}
    /* ============================================================
       REJECT
    ============================================================ */

public function reject(int $approvalId, int $userId, string $comments): int
{
    $this->db->beginTransaction();

    try {

        // 🔹 Lock approval row
        $stmt = $this->db->prepare("
            SELECT 
                ga.id,
                ga.status,
                ga.workflow_instance_id,
                gwi.status AS workflow_status,
                gwi.tenant_id,
                gwi.gatepass_id
            FROM gatepass_approvals ga
            INNER JOIN gatepass_workflow_instances gwi
                ON ga.workflow_instance_id = gwi.id
            WHERE ga.id = ?
              AND ga.approver_user_id = ?
            FOR UPDATE
        ");

        $stmt->execute([$approvalId, $userId]);
        $approval = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$approval) {
            throw new \RuntimeException("Approval not found.");
        }

        if ($approval['status'] !== 'pending') {
            throw new \RuntimeException("Already processed.");
        }

        if ($approval['workflow_status'] !== 'in_progress') {
            throw new \RuntimeException("Workflow not active.");
        }

        $instanceId = (int)$approval['workflow_instance_id'];
        $tenantId   = (int)$approval['tenant_id'];
        $gatepassId = (int)$approval['gatepass_id'];

        // 🔹 Mark approval rejected
        $stmt = $this->db->prepare("
            UPDATE gatepass_approvals
            SET status = 'rejected',
                acted_at = NOW(),
                comments = ?
            WHERE id = ?
        ");
        $stmt->execute([$comments, $approvalId]);

        // 🔹 Mark workflow rejected
        $stmt = $this->db->prepare("
            UPDATE gatepass_workflow_instances
            SET status = 'rejected',
                completed_at = NOW()
            WHERE id = ?
              AND tenant_id = ?
        ");
        $stmt->execute([$instanceId, $tenantId]);

        // 🔹 Get Rejected status_id safely
        $stmt = $this->db->prepare("
            SELECT id
            FROM gatepass_statuses
            WHERE tenant_id = ?
              AND name = 'Rejected'
            LIMIT 1
        ");
        $stmt->execute([$tenantId]);

        $statusId = $stmt->fetchColumn();

        if (!$statusId) {
            throw new \RuntimeException("Rejected status not configured.");
        }

        // 🔹 Update gatepass status
        $stmt = $this->db->prepare("
            UPDATE gatepasses
            SET status_id = ?
            WHERE id = ?
              AND tenant_id = ?
        ");
        $stmt->execute([$statusId, $gatepassId, $tenantId]);

        $this->db->commit();
        return $instanceId;

    } catch (\Throwable $e) {
        $this->db->rollBack();
        throw $e;
    }
}
    /* ============================================================
       ADVANCE STEP
    ============================================================ */

    private function advanceToNextStep(int $instanceId): void
{
    $stmt = $this->db->prepare("
        SELECT *
        FROM gatepass_workflow_instances
        WHERE id = ?
        FOR UPDATE
    ");
    $stmt->execute([$instanceId]);
    $instance = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$instance) {
        throw new \RuntimeException("Workflow instance not found.");
    }

    $currentStep = (int)$instance['current_step_order'];
    $nextStep = $currentStep + 1;

    // Check if next step exists
    $stmt = $this->db->prepare("
        SELECT id
        FROM workflow_steps
        WHERE workflow_id = ?
          AND step_order = ?
        LIMIT 1
    ");
    $stmt->execute([$instance['workflow_id'], $nextStep]);
    $step = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$step) {

        // FINAL STEP → APPROVED
        $this->db->prepare("
            UPDATE gatepass_workflow_instances
            SET status = 'approved',
                completed_at = NOW()
            WHERE id = ?
        ")->execute([$instanceId]);

        $this->db->prepare("
            UPDATE gatepasses
            SET status_id = (
                SELECT id
                FROM gatepass_statuses
                WHERE tenant_id = ?
                  AND name = 'Approved'
                LIMIT 1
            )
            WHERE id = ?
        ")->execute([
            $instance['tenant_id'],
            $instance['gatepass_id']
        ]);

        return;
    }

    // Move forward
    $this->db->prepare("
        UPDATE gatepass_workflow_instances
        SET current_step_order = ?
        WHERE id = ?
    ")->execute([$nextStep, $instanceId]);

    $this->createApprovalsForStep(
        (int)$instance['tenant_id'],
        $instanceId,
        $nextStep
    );
}
    /* ============================================================
       CREATE APPROVALS
    ============================================================ */

    private function createApprovalsForStep(int $tenantId, int $instanceId, int $stepOrder): void
{
    // 1️⃣ Fetch step info
    $stmt = $this->db->prepare("
        SELECT ws.id AS step_id, ws.role_id, ws.step_order
        FROM workflow_steps ws
        INNER JOIN gatepass_workflow_instances gwi
            ON gwi.workflow_id = ws.workflow_id
        WHERE gwi.id = ?
          AND ws.step_order = ?
        LIMIT 1
    ");
    $stmt->execute([$instanceId, $stepOrder]);
    $step = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$step) {
        throw new \RuntimeException("Workflow step not found.");
    }

    // 2️⃣ Fetch gatepass to get department
    $stmt = $this->db->prepare("
        SELECT gatepass_id
        FROM gatepass_workflow_instances
        WHERE id = ?
    ");
    $stmt->execute([$instanceId]);
    $instance = $stmt->fetch(PDO::FETCH_ASSOC);

    $stmt = $this->db->prepare("
        SELECT department_id
        FROM gatepasses
        WHERE id = ?
    ");
    $stmt->execute([$instance['gatepass_id']]);
    $departmentId = (int)$stmt->fetchColumn();

    // 3️⃣ Get users with role (and department for step 1)
    $query = "
        SELECT u.id
        FROM users u
        INNER JOIN user_roles ur ON ur.user_id = u.id
        WHERE ur.role_id = ?
          AND u.tenant_id = ?
          AND u.is_active = 1
    ";

    $params = [$step['role_id'], $tenantId];

    if ($stepOrder === 1) {
        // Only for Step 1, filter by department
        $query .= " AND u.department_id = ?";
        $params[] = $departmentId;
    }

    $stmt = $this->db->prepare($query);
    $stmt->execute($params);

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!$users) {
        throw new \RuntimeException("No users found for this role/department.");
    }

    // 4️⃣ Create approvals
    foreach ($users as $user) {
        $this->db->prepare("
            INSERT INTO gatepass_approvals
            (tenant_id, workflow_instance_id, workflow_step_id, approver_user_id, status)
            VALUES (?, ?, ?, ?, 'pending')
        ")->execute([
            $tenantId,
            $instanceId,
            $step['step_id'],
            $user['id']
        ]);
    }
}

    /* ============================================================
       START WORKFLOW
    ============================================================ */

    public function startWorkflow(
        int $tenantId,
        int $gatepassId,
        int $workflowId
    ): void {

        if ($this->hasActiveWorkflow($tenantId, $gatepassId)) {
            throw new \Exception("Workflow already started.");
        }

        $stmt = $this->db->prepare("
            INSERT INTO gatepass_workflow_instances
            (tenant_id, gatepass_id, workflow_id, current_step_order, status, started_at)
            VALUES (?, ?, ?, 1, 'in_progress', NOW())
        ");
        $stmt->execute([
            $tenantId,
            $gatepassId,
            $workflowId
        ]);

        $instanceId = (int)$this->db->lastInsertId();

        $this->createApprovalsForStep($tenantId, $instanceId, 1);
    }

    /* ============================================================
       HELPERS
    ============================================================ */

    public function hasActiveWorkflow(int $tenantId, int $gatepassId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM gatepass_workflow_instances
            WHERE tenant_id = ?
            AND gatepass_id = ?
            AND status = 'in_progress'
        ");
        $stmt->execute([$tenantId, $gatepassId]);

        return (bool)$stmt->fetchColumn();
    }

   public function getPendingForUser(int $tenantId, int $userId): array
{
    $stmt = $this->db->prepare("
        SELECT DISTINCT
            ga.id AS id,
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
        INNER JOIN gatepasses g
            ON g.id = gwi.gatepass_id
           AND g.tenant_id = gwi.tenant_id
        INNER JOIN workflow_steps ws
            ON ws.id = ga.workflow_step_id
        INNER JOIN user_roles ur
            ON ur.role_id = ws.role_id
           AND ur.user_id = :user_id
        INNER JOIN users u
            ON u.id = g.created_by
        WHERE ga.tenant_id = :tenant_id
          AND ga.status = 'pending'
        ORDER BY gwi.started_at DESC
    ");

    $stmt->execute([
        ':tenant_id' => $tenantId,
        ':user_id'   => $userId
    ]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


public function findApproval(int $tenantId, int $approvalId, int $userId): ?array
{
    $stmt = $this->db->prepare("
        SELECT 
            ga.id AS id,
            ga.status AS approval_status,
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
        INNER JOIN gatepasses g
            ON g.id = gwi.gatepass_id
        INNER JOIN workflow_steps ws
            ON ws.id = ga.workflow_step_id
        INNER JOIN users u
            ON u.id = g.created_by
        WHERE ga.id = :approval_id
          AND ga.tenant_id = :tenant_id
          AND ga.approver_user_id = :user_id
        LIMIT 1
    ");

    $stmt->execute([
        ':approval_id' => $approvalId,
        ':tenant_id'   => $tenantId,
        ':user_id'     => $userId,
    ]);

    $approval = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$approval) {
        return null;
    }

    // Fetch gatepass items
    $itemsStmt = $this->db->prepare("
        SELECT *
        FROM gatepass_items
        WHERE gatepass_id = :gatepass_id
          AND tenant_id = :tenant_id
    ");

    $itemsStmt->execute([
        ':gatepass_id' => $approval['gatepass_id'],
        ':tenant_id'   => $tenantId
    ]);

    $approval['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

    return $approval;
}
public function findUserApproval(int $tenantId, int $approvalId, int $userId): ?array
{
    $stmt = $this->db->prepare("
        SELECT ga.*, 
               g.gatepass_number,
               g.purpose
        FROM gatepass_approvals ga
        INNER JOIN gatepass_workflow_instances gwi
            ON gwi.id = ga.workflow_instance_id
        INNER JOIN gatepasses g
            ON g.id = gwi.gatepass_id
        WHERE ga.id = ?
          AND ga.approver_user_id = ?
          AND ga.tenant_id = ?
          AND ga.status = 'pending'
        LIMIT 1
    ");

    $stmt->execute([$approvalId, $userId, $tenantId]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
}