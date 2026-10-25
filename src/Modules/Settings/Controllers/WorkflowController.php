<?php

namespace App\Modules\Settings\Controllers;

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
        $tenantId    = $this->tenantId();
        $this->findOrFail($tenantId, $id);

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
            SELECT ws.*, r.name AS role_name
            FROM workflow_steps ws
            JOIN roles r
              ON r.id         = ws.role_id
             AND r.tenant_id  = ws.tenant_id
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

        return $this->view('Settings::Workflows/steps', [
            'title'    => 'Workflow Steps',
            'workflow' => $workflow,
            'steps'    => $steps,
            'roles'    => $roles,
        ]);
    }

    public function storeStep(Request $request, int $id)
    {
        $tenantId = $this->tenantId();
        $this->findOrFail($tenantId, $id);

        $stepOrder = (int) $request->input('step_order');
        $stepName  = trim($request->input('step_name') ?? '');
        $roleId    = (int) $request->input('role_id');

        if ($stepOrder <= 0 || $stepName === '' || $roleId <= 0) {
            Response::abort(422, 'All step fields are required.');
        }

        // Validate role belongs to this tenant
        $roleStmt = $this->db->prepare("
            SELECT id
            FROM roles
            WHERE id        = :id
              AND tenant_id = :tenant_id
            LIMIT 1
        ");

        $roleStmt->execute([':id' => $roleId, ':tenant_id' => $tenantId]);

        if (! $roleStmt->fetch()) {
            Response::abort(403, 'Invalid role for this tenant.');
        }

        // FIX: schema — workflow_steps has no created_at column; removed from INSERT
        $stmt = $this->db->prepare("
            INSERT INTO workflow_steps
                (tenant_id, workflow_id, step_order, name, role_id)
            VALUES
                (:tenant_id, :workflow_id, :step_order, :name, :role_id)
        ");

        $stmt->execute([
            ':tenant_id'   => $tenantId,
            ':workflow_id' => $id,
            ':step_order'  => $stepOrder,
            ':name'        => $stepName,
            ':role_id'     => $roleId,
        ]);

        return $this->redirect("/settings/workflows/{$id}/steps");
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
            SELECT id
            FROM gatepass_types
            WHERE id        = :id
              AND tenant_id = :tenant_id
            LIMIT 1
        ");

        $typeStmt->execute([':id' => $gatepassTypeId, ':tenant_id' => $tenantId]);

        if (! $typeStmt->fetch()) {
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

        return $this->redirect('/settings/workflows');
    }
}