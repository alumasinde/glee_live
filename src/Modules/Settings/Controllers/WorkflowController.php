<?php

namespace App\Modules\Settings\Controllers;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use PDO;

class WorkflowController extends Controller
{
    private PDO $db;

    public function __construct()
    {
        // FIX: Use Auth helper instead of raw $_SESSION check
        if (! Auth::check()) {
            Response::redirect('/login');
        }

        $this->db = DB::connect();
        // FIX: Removed redundant setAttribute() — PDO::ERRMODE_EXCEPTION is
        // already set in config/database.php options. Calling it again here
        // is harmless but misleading.
    }

    private function tenantId(): int
    {
        // FIX: Use Tenant::require() instead of reading $_SESSION manually
        return Tenant::require();
    }

    /** Audit entry for a workflow configuration mutation. */
    private function logConfigChange(string $action, string $entityType, int $entityId, string $label, array $meta = []): void
    {
        Audit::log($action, $entityType, $entityId, $meta + ['target_label' => $label]);
    }

    private function findOrFail(int $tenantId, int $id): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM workflows
            WHERE id        = :id
              AND tenant_id = :tenant_id
            LIMIT 1
        ");

        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);

        $workflow = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $workflow) {
            Response::abort(404, 'Workflow not found.');
        }

        return $workflow;
    }

    /* =========================================================
     * INDEX
     * ========================================================= */

    public function index()
    {
        $tenantId = $this->tenantId();

        $stmt = $this->db->prepare("
            SELECT w.*,
                   (
                       SELECT COUNT(*)
                       FROM workflow_steps ws
                       WHERE ws.workflow_id = w.id
                         AND ws.tenant_id   = w.tenant_id
                   ) AS step_count
            FROM workflows w
            WHERE w.tenant_id = :tenant_id
            ORDER BY w.created_at DESC
        ");

        $stmt->execute([':tenant_id' => $tenantId]);

        return $this->view('Settings::Workflows/index', [
            'title'     => 'Workflows',
            'workflows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    /* =========================================================
     * CREATE / STORE
     * ========================================================= */

    public function create()
    {
        return $this->view('Settings::Workflows/create', [
            'title' => 'Create Workflow',
        ]);
    }

    public function store(Request $request)
    {
        $tenantId    = $this->tenantId();
        $name        = trim($request->input('name') ?? '');
        // FIX: schema — workflows.description is varchar(250) NOT NULL
        // Default to empty string to satisfy the NOT NULL constraint when omitted
        $description = trim($request->input('description') ?? '');

        if ($name === '') {
            Response::abort(422, 'Workflow name is required.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO workflows
                (tenant_id, name, description, is_active, created_at)
            VALUES
                (:tenant_id, :name, :description, 1, NOW())
        ");

        $stmt->execute([
            ':tenant_id'   => $tenantId,
            ':name'        => $name,
            ':description' => $description,
        ]);

        $this->logConfigChange('workflow.config_created', 'workflow', (int) $this->db->lastInsertId(), $name);

        return $this->redirect('/settings/workflows');
    }

    /* =========================================================
     * EDIT / UPDATE
     * ========================================================= */

    public function edit(Request $request, int $id)
    {
        $workflow = $this->findOrFail($this->tenantId(), $id);

        return $this->view('Settings::Workflows/edit', [
            'title'    => 'Edit Workflow',
            'workflow' => $workflow,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $tenantId = $this->tenantId();
        $workflow = $this->findOrFail($tenantId, $id);

        $name        = trim($request->input('name') ?? '');
        // FIX: same NOT NULL fix as store()
        $description = trim($request->input('description') ?? '');
        $isActive    = $request->input('is_active') ? 1 : 0;

        if ($name === '') {
            Response::abort(422, 'Workflow name is required.');
        }

        $stmt = $this->db->prepare("
            UPDATE workflows
            SET name        = :name,
                description = :description,
                is_active   = :is_active
            WHERE id        = :id
              AND tenant_id = :tenant_id
        ");

        $stmt->execute([
            ':name'        => $name,
            ':description' => $description,
            ':is_active'   => $isActive,
            ':id'          => $id,
            ':tenant_id'   => $tenantId,
        ]);

        $this->logConfigChange('workflow.config_updated', 'workflow', $id, $name, [
            'is_active' => $isActive,
            'was'       => [
                'name'      => $workflow['name'],
                'is_active' => (int) $workflow['is_active'],
            ],
        ]);

        return $this->redirect('/settings/workflows');
    }

    /* =========================================================
     * STEPS
     * ========================================================= */

    public function steps(Request $request, int $id)
    {
        $tenantId = $this->tenantId();
        $workflow = $this->findOrFail($tenantId, $id);

        $stmt = $this->db->prepare("
            SELECT ws.*, r.name AS role_name, fr.name AS fallback_role_name, d.name AS department_name
            FROM workflow_steps ws
            JOIN roles r
              ON r.id         = ws.role_id
             AND r.tenant_id  = ws.tenant_id
            LEFT JOIN roles fr
              ON fr.id        = ws.fallback_role_id
             AND fr.tenant_id = ws.tenant_id
            LEFT JOIN departments d
              ON d.id         = ws.department_id
             AND d.tenant_id  = ws.tenant_id
            WHERE ws.workflow_id = :workflow_id
              AND ws.tenant_id   = :tenant_id
            ORDER BY ws.step_order ASC
        ");

        $stmt->execute([':workflow_id' => $id, ':tenant_id' => $tenantId]);
        $steps = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $roleStmt = $this->db->prepare("
            SELECT id, name
            FROM roles
            WHERE tenant_id = :tenant_id
            ORDER BY name ASC
        ");

        $roleStmt->execute([':tenant_id' => $tenantId]);
        $roles = $roleStmt->fetchAll(PDO::FETCH_ASSOC);

        $deptStmt = $this->db->prepare("
            SELECT id, name
            FROM departments
            WHERE tenant_id = :tenant_id
              AND is_active = 1
            ORDER BY name ASC
        ");

        $deptStmt->execute([':tenant_id' => $tenantId]);
        $departments = $deptStmt->fetchAll(PDO::FETCH_ASSOC);

        return $this->view('Settings::Workflows/steps', [
            'title'       => 'Workflow Steps',
            'workflow'    => $workflow,
            'steps'       => $steps,
            'roles'       => $roles,
            'departments' => $departments,
        ]);
    }

    public function storeStep(Request $request, int $id)
    {
        $tenantId = $this->tenantId();
        $this->findOrFail($tenantId, $id);

        $data = $this->validateStepPayload($request, $tenantId);

        // schema — workflow_steps has no created_at column
        try {
            $stmt = $this->db->prepare("
                INSERT INTO workflow_steps
                    (tenant_id, workflow_id, step_order, name, role_id, fallback_role_id,
                     is_mandatory, approval_mode, department_source, department_id)
                VALUES
                    (:tenant_id, :workflow_id, :step_order, :name, :role_id, :fallback_role_id,
                     :is_mandatory, :approval_mode, :department_source, :department_id)
            ");

            $stmt->execute(array_merge($data, [
                ':tenant_id'   => $tenantId,
                ':workflow_id' => $id,
            ]));

            $this->logConfigChange('workflow.step_created', 'workflow_step', (int) $this->db->lastInsertId(), $data['name'], [
                'workflow_id' => $id,
                'step_order'  => $data['step_order'],
                'role_id'     => $data['role_id'],
            ]);
        } catch (\PDOException $e) {
            $this->rethrowDuplicateOrder($e, $id);
        }

        return $this->redirect("/settings/workflows/{$id}/steps");
    }

    /* =========================================================
     * UPDATE / DELETE STEP
     * ========================================================= */

    public function updateStep(Request $request, int $id, int $stepId)
    {
        $tenantId = $this->tenantId();
        $this->findOrFail($tenantId, $id);
        $step = $this->findStep($tenantId, $id, $stepId);

        $data = $this->validateStepPayload($request, $tenantId);

        try {
            $stmt = $this->db->prepare("
                UPDATE workflow_steps
                SET step_order        = :step_order,
                    name              = :name,
                    role_id           = :role_id,
                    fallback_role_id  = :fallback_role_id,
                    is_mandatory      = :is_mandatory,
                    approval_mode     = :approval_mode,
                    department_source = :department_source,
                    department_id     = :department_id
                WHERE id         = :step_id
                  AND workflow_id = :workflow_id
                  AND tenant_id  = :tenant_id
            ");

            $stmt->execute(array_merge($data, [
                ':step_id'     => $step['id'],
                ':workflow_id' => $id,
                ':tenant_id'   => $tenantId,
            ]));

            $this->logConfigChange('workflow.step_updated', 'workflow_step', (int) $step['id'], $data['name'], [
                'workflow_id' => $id,
                'step_order'  => $data['step_order'],
                'role_id'     => $data['role_id'],
                'was'         => [
                    'step_order'  => (int) $step['step_order'],
                    'name'        => $step['name'],
                    'role_id'     => (int) $step['role_id'],
                    'is_mandatory' => (int) $step['is_mandatory'],
                    'approval_mode' => $step['approval_mode'],
                ],
            ]);
        } catch (\PDOException $e) {
            $this->rethrowDuplicateOrder($e, $id);
        }

        return $this->redirect("/settings/workflows/{$id}/steps");
    }

    public function deleteStep(Request $request, int $id, int $stepId)
    {
        $tenantId = $this->tenantId();
        $this->findOrFail($tenantId, $id);
        $step = $this->findStep($tenantId, $id, $stepId);

        $counts = $this->db->prepare("
            SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending
            FROM gatepass_approvals
            WHERE workflow_step_id = :step_id
              AND tenant_id        = :tenant_id
        ");
        $counts->execute([':step_id' => $step['id'], ':tenant_id' => $tenantId]);
        $usage = $counts->fetch(PDO::FETCH_ASSOC);

        if ((int) ($usage['pending'] ?? 0) > 0) {
            Response::abort(422, sprintf(
                'Step "%s" has %d approval(s) awaiting action and cannot be deleted. Let them be processed first, or reassign them.',
                $step['name'],
                (int) $usage['pending']
            ));
        }

        if ((int) ($usage['total'] ?? 0) > 0) {
            Response::abort(422, sprintf(
                'Step "%s" has approval history and cannot be deleted (integrity protected). Reorder or rename it instead.',
                $step['name']
            ));
        }

        $stmt = $this->db->prepare("
            DELETE FROM workflow_steps
            WHERE id = :step_id AND workflow_id = :workflow_id AND tenant_id = :tenant_id
        ");
        $stmt->execute([':step_id' => $step['id'], ':workflow_id' => $id, ':tenant_id' => $tenantId]);

        $this->logConfigChange('workflow.step_deleted', 'workflow_step', (int) $step['id'], $step['name'], [
            'workflow_id' => $id,
            'step_order'  => (int) $step['step_order'],
            'role_id'     => (int) $step['role_id'],
        ]);

        return $this->redirect("/settings/workflows/{$id}/steps");
    }

    private function findStep(int $tenantId, int $workflowId, int $stepId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM workflow_steps
            WHERE id          = :step_id
              AND workflow_id = :workflow_id
              AND tenant_id   = :tenant_id
            LIMIT 1
        ");
        $stmt->execute([':step_id' => $stepId, ':workflow_id' => $workflowId, ':tenant_id' => $tenantId]);

        $step = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$step) {
            Response::abort(404, 'Step not found in this workflow.');
        }

        return $step;
    }

    /**
     * @return array{step_order:int, name:string, role_id:int, fallback_role_id:?int, is_mandatory:int,
     *               approval_mode:string, department_source:string, department_id:?int}
     */
    private function validateStepPayload(Request $request, int $tenantId): array
    {
        $stepOrder     = (int) $request->input('step_order');
        $stepName      = trim((string) ($request->input('step_name') ?? ''));
        $roleId        = (int) $request->input('role_id');
        $isMandatory   = $request->input('is_mandatory') ? 1 : 0;
        $approvalMode  = (string) $request->input('approval_mode', 'any');
        $deptSource    = (string) $request->input('department_source', 'none');

        if ($stepOrder <= 0 || $stepName === '' || $roleId <= 0) {
            Response::abort(422, 'Step order, name and role are required.');
        }

        if (!in_array($approvalMode, ['any', 'all'], true)) {
            Response::abort(422, 'Approval mode must be "any" or "all".');
        }

        if (!in_array($deptSource, ['none', 'step', 'gatepass'], true)) {
            Response::abort(422, 'Invalid department scope.');
        }

        $roleStmt = $this->db->prepare("
            SELECT id
            FROM roles
            WHERE id = :id AND tenant_id = :tenant_id
            LIMIT 1
        ");
        $roleStmt->execute([':id' => $roleId, ':tenant_id' => $tenantId]);

        if (!$roleStmt->fetch()) {
            Response::abort(403, 'Invalid role for this tenant.');
        }

        $departmentId = null;

        if ($deptSource === 'step') {
            $departmentId = (int) $request->input('department_id');

            if ($departmentId <= 0) {
                Response::abort(422, 'A department is required when the step is scoped to a fixed department.');
            }

            $deptStmt = $this->db->prepare("
                SELECT id
                FROM departments
                WHERE id = :id AND tenant_id = :tenant_id AND is_active = 1
                LIMIT 1
            ");
            $deptStmt->execute([':id' => $departmentId, ':tenant_id' => $tenantId]);

            if (!$deptStmt->fetch()) {
                Response::abort(403, 'Invalid department for this tenant.');
            }
        }

        // Optional fallback role used when the primary role resolves to no eligible approvers
        $fallbackRoleId = (int) $request->input('fallback_role_id');

        if ($fallbackRoleId > 0) {
            if ($fallbackRoleId === $roleId) {
                Response::abort(422, 'The fallback role must differ from the step role.');
            }

            $fallbackStmt = $this->db->prepare("
                SELECT id
                FROM roles
                WHERE id = :id AND tenant_id = :tenant_id
                LIMIT 1
            ");
            $fallbackStmt->execute([':id' => $fallbackRoleId, ':tenant_id' => $tenantId]);

            if (!$fallbackStmt->fetch()) {
                Response::abort(403, 'Invalid fallback role for this tenant.');
            }
        } else {
            $fallbackRoleId = null;
        }

        return [
            'step_order'        => $stepOrder,
            'name'              => $stepName,
            'role_id'           => $roleId,
            'fallback_role_id'  => $fallbackRoleId,
            'is_mandatory'      => $isMandatory,
            'approval_mode'     => $approvalMode,
            'department_source' => $deptSource,
            'department_id'     => $departmentId,
        ];
    }

    private function rethrowDuplicateOrder(\PDOException $e, int $workflowId): never
    {
        if ($e->getCode() !== '23000') {
            throw $e;
        }

        Response::abort(422, sprintf(
            'That step order is already used in this workflow. Use distinct positions (1, 2, 3…).'
        ));
    }

    /* =========================================================
     * ASSIGN WORKFLOW TO GATEPASS TYPE
     * ========================================================= */

    public function assign(Request $request, int $id)
    {
        $tenantId = $this->tenantId();
        $workflow = $this->findOrFail($tenantId, $id);

        $stmt = $this->db->prepare("
            SELECT id, name
            FROM gatepass_types
            WHERE tenant_id = :tenant_id
            ORDER BY name ASC
        ");

        $stmt->execute([':tenant_id' => $tenantId]);

        return $this->view('Settings::Workflows/assign', [
            'title'         => 'Assign Workflow',
            'workflow'      => $workflow,
            'gatepassTypes' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
    }

    public function storeAssignment(Request $request, int $id)
    {
        $tenantId       = $this->tenantId();
        $this->findOrFail($tenantId, $id);

        $gatepassTypeId = (int) $request->input('gatepass_type_id');

        if ($gatepassTypeId <= 0) {
            Response::abort(422, 'Gatepass type is required.');
        }

        // Validate type belongs to this tenant
        $typeStmt = $this->db->prepare("
            SELECT id, name
            FROM gatepass_types
            WHERE id        = :id
              AND tenant_id = :tenant_id
            LIMIT 1
        ");

        $typeStmt->execute([':id' => $gatepassTypeId, ':tenant_id' => $tenantId]);

        $type = $typeStmt->fetch(PDO::FETCH_ASSOC);

        if (!$type) {
            Response::abort(403, 'Invalid gatepass type.');
        }

        // One workflow per gatepass type — schema has UNIQUE KEY uk_wgt_tenant_type.
        // INSERT IGNORE would silently no-op a conflicting assignment, so check explicitly.
        $boundStmt = $this->db->prepare("
            SELECT wgt.workflow_id, w.name AS workflow_name
            FROM workflow_gatepass_type wgt
            INNER JOIN workflows w
                ON w.id         = wgt.workflow_id
               AND w.tenant_id  = wgt.tenant_id
            WHERE wgt.tenant_id        = :tenant_id
              AND wgt.gatepass_type_id = :gatepass_type_id
            LIMIT 1
        ");

        $boundStmt->execute([':tenant_id' => $tenantId, ':gatepass_type_id' => $gatepassTypeId]);
        $bound = $boundStmt->fetch(PDO::FETCH_ASSOC);

        if ($bound) {
            if ((int) $bound['workflow_id'] === $id) {
                return $this->redirect('/settings/workflows');
            }

            Response::abort(422, sprintf(
                'This gatepass type is already assigned to workflow "%s". Unassign it there first.',
                $bound['workflow_name']
            ));
        }

        $stmt = $this->db->prepare("
            INSERT INTO workflow_gatepass_type
                (tenant_id, workflow_id, gatepass_type_id, created_at)
            VALUES
                (:tenant_id, :workflow_id, :gatepass_type_id, NOW())
        ");

        $stmt->execute([
            ':tenant_id'        => $tenantId,
            ':workflow_id'      => $id,
            ':gatepass_type_id' => $gatepassTypeId,
        ]);

        $this->logConfigChange('workflow.assigned_to_type', 'workflow', $id, $type['name'], [
            'gatepass_type_id' => $gatepassTypeId,
        ]);

        return $this->redirect('/settings/workflows');
    }
}