<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Approval\Services\ApprovalService;
use PDO;
use RuntimeException;

/**
 * Covers migration 0025 (workflow_steps.approver_kind + workflow_step_approvers):
 *   - a 'users' step fans out to its named roster, minus the requester and inactive accounts
 *   - an empty roster falls back to step.fallback_role_id
 *   - a role step scoped to 'gatepass' resolves the requester's department only (HOD rule)
 *   - the roster is frozen into step_snapshot when the instance starts
 */
final class ApprovalStepTargetsTest extends DatabaseTestCase
{
    private const TENANT = 1;

    private ApprovalService $service;
    private string $suffix;
    private int $seq = 0;
    private int $deptSeq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ApprovalService();
        $this->suffix  = substr(bin2hex(random_bytes(5)), 0, 10);

        $actor        = $this->makeUser('act');
        $_SESSION     = ['user' => ['id' => $actor, 'tenant_id' => self::TENANT, 'first_name' => 'Targets', 'last_name' => 'Actor']];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testRosterStepFansOutToNamedPeopleWithoutRoles(): void
    {
        $requester = $this->makeUser('req');
        $first     = $this->makeUser('one');
        $second    = $this->makeUser('two');

        $workflowId = $this->makeWorkflow();
        $stepId     = $this->insertStep($workflowId, 1, null, true, 'users');
        $this->attach($stepId, $first);
        $this->attach($stepId, $second);
        $this->attach($stepId, $requester); // ticked by mistake — must still be excluded

        $typeId     = $this->makeType();
        $this->bindType($workflowId, $typeId);
        $this->startWorkflow($this->makeGatepass($requester, $typeId), $workflowId);

        $this->assertSame(
            $this->sortedIds($first, $second),
            $this->approvalUserIds(),
            'a named-people step signs to exactly its roster, requester excluded'
        );
    }

    public function testInactiveRosterMemberFallsBackToTheFallbackRole(): void
    {
        $requester = $this->makeUser('req');
        $onLeave   = $this->makeUser('gone');
        $standby   = $this->makeUser('std');

        $fallback = $this->makeRole('Targets Fallback ' . $this->suffix);
        $this->grantRole($standby, $fallback);
        $this->db->exec("UPDATE users SET is_active = 0 WHERE id = {$onLeave}");

        $workflowId = $this->makeWorkflow();
        $stepId     = $this->insertStep($workflowId, 1, null, true, 'users');
        $this->db->exec("UPDATE workflow_steps SET fallback_role_id = {$fallback} WHERE id = {$stepId}");
        $this->attach($stepId, $onLeave);
        $this->attach($stepId, $requester);

        $typeId     = $this->makeType();
        $this->bindType($workflowId, $typeId);
        $this->startWorkflow($this->makeGatepass($requester, $typeId), $workflowId);

        $this->assertSame(
            $this->sortedIds($standby),
            $this->approvalUserIds(),
            'a roster with no live member is covered by the fallback role'
        );
    }

    public function testHodRuleMatchesOnlyTheRequesterDepartmentHolder(): void
    {
        $hod = $this->makeRole('Targets HOD ' . $this->suffix);

        $finance = $this->makeDepartment();
        $it      = $this->makeDepartment();

        $requester  = $this->makeUser('req', $finance);
        $sameDept   = $this->makeUser('hodfin', $finance);
        $otherDept  = $this->makeUser('hodit', $it);
        $this->grantRole($sameDept, $hod);
        $this->grantRole($otherDept, $hod);
        $this->grantRole($requester, $hod);

        $workflowId = $this->makeWorkflow();
        $stepId     = $this->insertStep($workflowId, 1, $hod, true);
        $this->db->exec("UPDATE workflow_steps SET department_source = 'gatepass' WHERE id = {$stepId}");

        $typeId     = $this->makeType();
        $this->bindType($workflowId, $typeId);
        $this->startWorkflow($this->makeGatepass($requester, $typeId, $finance), $workflowId);

        $this->assertSame(
            $this->sortedIds($sameDept),
            $this->approvalUserIds(),
            "role + requester's department picks the HOD on the requester's own team"
        );
    }

    public function testRosterIsFrozenIntoTheInstanceSnapshot(): void
    {
        $roleA = $this->makeRole('Targets Snap A ' . $this->suffix);
        $first = $this->makeUser('one');
        $this->grantRole($first, $roleA);

        $requester = $this->makeUser('req');
        $original  = $this->makeUser('orig');
        $hijack    = $this->makeUser('hijack');

        $workflowId = $this->makeWorkflow();
        $this->insertStep($workflowId, 1, $roleA, true);
        $rosterStep = $this->insertStep($workflowId, 2, null, true, 'users');
        $this->attach($rosterStep, $original);

        $typeId     = $this->makeType();
        $this->bindType($workflowId, $typeId);
        $gatepassId = $this->makeGatepass($requester, $typeId);
        $this->startWorkflow($gatepassId, $workflowId);

        // Admin swaps the roster while the request is in flight.
        $this->db->exec("DELETE FROM workflow_step_approvers WHERE workflow_step_id = {$rosterStep}");
        $this->attach($rosterStep, $hijack);

        $this->service->approve($this->pendingApprovalId($first, $gatepassId), $first);

        $step2 = $this->db
            ->query("SELECT ga.approver_user_id FROM gatepass_approvals ga
                     INNER JOIN workflow_steps ws ON ws.id = ga.workflow_step_id AND ws.tenant_id = ga.tenant_id
                     WHERE ga.tenant_id = " . self::TENANT . " AND ws.step_order = 2")
            ->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame($this->sortedIds($original), array_map('intval', $step2), 'step 2 honours the roster frozen at start');
        $this->assertNotContains((string) $hijack, array_map('strval', $step2));
    }

    /* =========================================================
       helpers
    ========================================================= */

    private function startWorkflow(int $gatepassId, int $workflowId): void
    {
        $this->service->startWorkflow(self::TENANT, $gatepassId, $workflowId);
    }

    /** @return list<int> */
    private function approvalUserIds(): array
    {
        $ids = $this->db
            ->query("SELECT approver_user_id FROM gatepass_approvals WHERE tenant_id = " . self::TENANT)
            ->fetchAll(PDO::FETCH_COLUMN);

        return $this->sortedIds(...array_map('intval', $ids));
    }

    /** @return list<int> */
    private function sortedIds(int ...$ids): array
    {
        sort($ids);

        return $ids;
    }

    private function pendingApprovalId(int $userId, int $gatepassId): int
    {
        $stmt = $this->db->prepare("
            SELECT ga.id FROM gatepass_approvals ga
            INNER JOIN gatepass_workflow_instances gwi
                ON gwi.id = ga.workflow_instance_id AND gwi.tenant_id = ga.tenant_id
            WHERE ga.tenant_id = ? AND ga.approver_user_id = ? AND ga.status = 'pending' AND gwi.gatepass_id = ?
            ORDER BY ga.id LIMIT 1
        ");
        $stmt->execute([self::TENANT, $userId, $gatepassId]);
        $id = $stmt->fetchColumn();

        if ($id === false) {
            throw new RuntimeException("No pending approval for user {$userId}.");
        }

        return (int) $id;
    }

    private function attach(int $stepId, int $userId): void
    {
        $this->insert(
            "INSERT INTO workflow_step_approvers (tenant_id, workflow_step_id, user_id) VALUES (%d, %d, %d)",
            self::TENANT,
            $stepId,
            $userId
        );
    }

    private function makeWorkflow(): int
    {
        return $this->insert(
            "INSERT INTO workflows (tenant_id, name, description, is_active) VALUES (%d, 'Targets WF %s', '', 1)",
            self::TENANT,
            $this->suffix
        );
    }

    private function insertStep(int $workflowId, int $order, ?int $roleId, bool $mandatory, string $kind = 'role'): int
    {
        return $this->insert(
            "INSERT INTO workflow_steps (tenant_id, workflow_id, name, approver_kind, role_id, step_order, is_mandatory)
             VALUES (%d, %d, 'Step %d', '%s', %s, %d, %d)",
            self::TENANT,
            $workflowId,
            $order,
            $kind,
            $roleId === null ? 'NULL' : (string) $roleId,
            $order,
            $mandatory ? 1 : 0
        );
    }

    private function makeType(): int
    {
        return $this->insert(
            "INSERT INTO gatepass_types (tenant_id, name, type_code, is_active) VALUES (%d, 'Targets Type %s', 'TG%s', 1)",
            self::TENANT,
            $this->suffix,
            strtoupper($this->suffix)
        );
    }

    private function bindType(int $workflowId, int $typeId): int
    {
        return $this->insert(
            "INSERT INTO workflow_gatepass_type (tenant_id, workflow_id, gatepass_type_id) VALUES (%d, %d, %d)",
            self::TENANT,
            $workflowId,
            $typeId
        );
    }

    private function makeRole(string $name): int
    {
        $stmt = $this->db->prepare("INSERT INTO roles (tenant_id, name) VALUES (?, ?)");
        $stmt->execute([self::TENANT, $name]);

        return (int) $this->db->lastInsertId();
    }

    private function makeDepartment(): int
    {
        $this->deptSeq++;

        return $this->insert(
            "INSERT INTO departments (tenant_id, name, code, is_active) VALUES (%d, 'Targets Dept %s-%d', '%s%d', 1)",
            self::TENANT,
            $this->suffix,
            $this->deptSeq,
            strtoupper($this->suffix),
            $this->deptSeq
        );
    }

    private function makeUser(string $tag, ?int $departmentId = null): int
    {
        return (int) $this->insert(
            "INSERT INTO users (tenant_id, email, password_hash, first_name, last_name, username, is_active, is_admin, department_id)
             VALUES (%d, '%s', 'x', 'Targets', '%s', '%s', 1, 0, %s)",
            self::TENANT,
            "targets.{$this->suffix}.{$tag}@test.local",
            ucfirst($tag),
            'tg' . $this->suffix . $tag,
            $departmentId === null ? 'NULL' : (string) $departmentId
        );
    }

    private function grantRole(int $userId, int $roleId): void
    {
        $stmt = $this->db->prepare("INSERT INTO user_roles (user_id, role_id, tenant_id) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $roleId, self::TENANT]);
    }

    private function makeGatepass(int $createdBy, int $typeId, ?int $departmentId = null): int
    {
        $this->seq++;

        return $this->insert(
            "INSERT INTO gatepasses (tenant_id, gatepass_number, scan_token, gatepass_type_id, status_id, department_id, purpose, needs_approval, created_by)
             VALUES (%d, 'TG-%s-%d', '%s', %d, %d, %s, 'integration', 1, %d)",
            self::TENANT,
            $this->suffix,
            $this->seq,
            bin2hex(random_bytes(32)),
            $typeId,
            $this->gatepassStatusId(self::TENANT, 'pending'),
            $departmentId === null ? 'NULL' : (string) $departmentId,
            $createdBy
        );
    }

    private function insert(string $sql, mixed ...$args): int
    {
        $stmt = $this->db->prepare(sprintf($sql, ...$args));
        $stmt->execute();

        return (int) $this->db->lastInsertId();
    }
}
