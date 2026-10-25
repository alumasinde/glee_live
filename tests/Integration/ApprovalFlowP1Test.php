<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Gatepass\Services\GatepassService;
use PDO;
use RuntimeException;

/**
 * Covers the P1 governance pass:
 *   - step.fallback_role_id approver resolution (migration 0020)
 *   - per-instance step_snapshot: in-flight workflows ignore later config edits
 *   - admin reassignment of pending approvals (+ audit trail)
 *   - audit trail for approve/reject decisions
 *   - server-side enforcement of gatepass type allowed_actions
 */
final class ApprovalFlowP1Test extends DatabaseTestCase
{
    private const TENANT = 1;

    private ApprovalService $service;
    private string $suffix;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ApprovalService();
        $this->suffix  = substr(bin2hex(random_bytes(5)), 0, 10);

        $actor         = $this->makeUser('act');
        $_SESSION      = ['user' => ['id' => $actor, 'tenant_id' => self::TENANT, 'first_name' => 'P1', 'last_name' => 'Actor']];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    /* =========================================================
       FALLBACK ROLE
    ========================================================= */

    public function testFallbackRoleSuppliesApproversWhenPrimaryRoleIsEmpty(): void
    {
        $primaryRole  = $this->makeRole('P1 Empty ' . $this->suffix);
        $fallbackRole = $this->makeRole('P1 Fallback ' . $this->suffix);

        $requester = $this->makeUser('req');
        $standby   = $this->makeUser('std');
        $this->grantRole($requester, $fallbackRole); // member but excluded as requester
        $this->grantRole($standby, $fallbackRole);

        $workflowId = $this->makeWorkflow();
        $stepId     = $this->insertStep($workflowId, 1, $primaryRole, true);
        $this->db->exec("UPDATE workflow_steps SET fallback_role_id = {$fallbackRole} WHERE id = {$stepId}");

        $typeId     = $this->makeType();
        $this->bindType($workflowId, $typeId);
        $gatepassId = $this->makeGatepass($requester, $typeId);

        $this->service->startWorkflow(self::TENANT, $gatepassId, $workflowId);

        $approvers = $this->db
            ->query("SELECT approver_user_id FROM gatepass_approvals WHERE tenant_id = " . self::TENANT)
            ->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame([(string) $standby], array_map('strval', $approvers), 'fallback role members fan out when the primary role is empty');
    }

    /* =========================================================
       STEP SNAPSHOT
    ========================================================= */

    public function testSnapshotFreezesRoleForInFlightInstances(): void
    {
        $roleA = $this->makeRole('P1 Snap A ' . $this->suffix);
        $roleB = $this->makeRole('P1 Snap B ' . $this->suffix);
        $roleC = $this->makeRole('P1 Snap C ' . $this->suffix);

        $requester = $this->makeUser('req');
        $step1User = $this->makeUser('s1u');
        $snapUser  = $this->makeUser('snaps');   // in roleB when the instance starts
        $hijack    = $this->makeUser('hijack');  // only in the live-edited roleC
        $this->grantRole($step1User, $roleA);
        $this->grantRole($snapUser, $roleB);
        $this->grantRole($hijack, $roleC);

        $workflowId = $this->makeWorkflow();
        $this->insertStep($workflowId, 1, $roleA, true);
        $step2Id      = $this->insertStep($workflowId, 2, $roleB, true);
        $typeId     = $this->makeType();
        $this->bindType($workflowId, $typeId);
        $gatepassId = $this->makeGatepass($requester, $typeId);

        $this->service->startWorkflow(self::TENANT, $gatepassId, $workflowId);

        $snapshot = $this->db
            ->query("SELECT step_snapshot FROM gatepass_workflow_instances WHERE tenant_id = " . self::TENANT . ' ORDER BY id DESC LIMIT 1')
            ->fetchColumn();
        $this->assertJson((string) $snapshot, 'startWorkflow must freeze the step config');

        // Admin re-points the live step at a different role while the request is in flight.
        $this->db->exec("UPDATE workflow_steps SET role_id = {$roleC} WHERE id = {$step2Id}");

        $this->service->approve($this->pendingApprovalId($step1User), $step1User);

        $step2Approvers = $this->db
            ->query("SELECT approver_user_id FROM gatepass_approvals ga
                     INNER JOIN workflow_steps ws ON ws.id = ga.workflow_step_id
                     WHERE ga.tenant_id = " . self::TENANT . " AND ws.step_order = 2")
            ->fetchAll(PDO::FETCH_COLUMN);

        $this->assertSame([(string) $snapUser], array_map('strval', $step2Approvers), 'step 2 fans out from the snapshot role, not the live edit');
        $this->assertNotContains((string) $hijack, array_map('strval', $step2Approvers));
    }

    public function testSnapshotApprovalModeWinsOverLiveEdit(): void
    {
        $roleA = $this->makeRole('P1 Mode A ' . $this->suffix);
        $roleB = $this->makeRole('P1 Mode B ' . $this->suffix);

        $requester = $this->makeUser('req');
        $first     = $this->makeUser('one');
        $second    = $this->makeUser('two');
        $third     = $this->makeUser('three');
        $this->grantRole($first, $roleA);
        $this->grantRole($second, $roleB);
        $this->grantRole($third, $roleB);

        $workflowId = $this->makeWorkflow();
        $step1      = $this->insertStep($workflowId, 1, $roleA, true);
        $step2      = $this->insertStep($workflowId, 2, $roleB, true);
        $this->db->exec("UPDATE workflow_steps SET approval_mode = 'all' WHERE id = {$step2}");

        $typeId     = $this->makeType();
        $this->bindType($workflowId, $typeId);
        $gatepassId = $this->makeGatepass($requester, $typeId);

        $this->service->startWorkflow(self::TENANT, $gatepassId, $workflowId);

        // Admin flips the unanimous step to 'any' in live config after the request started.
        $this->db->exec("UPDATE workflow_steps SET approval_mode = 'any' WHERE id = {$step2}");

        $this->service->approve($this->pendingApprovalId($first), $first);

        $instance = $this->db
            ->query("SELECT status FROM gatepass_workflow_instances WHERE tenant_id = " . self::TENANT . ' ORDER BY id DESC LIMIT 1')
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('in_progress', $instance['status'], 'snapshotted "all" mode still requires the second approver');

        $this->service->approve($this->pendingApprovalId($second), $second);
        $this->service->approve($this->pendingApprovalId($third), $third);

        $instance = $this->db
            ->query("SELECT status FROM gatepass_workflow_instances WHERE tenant_id = " . self::TENANT . ' ORDER BY id DESC LIMIT 1')
            ->fetch(PDO::FETCH_ASSOC);
        $this->assertSame('approved', $instance['status']);
    }

    public function testInstancesWithoutSnapshotStillReadLiveConfig(): void
    {
        $roleA = $this->makeRole('P1 Live A ' . $this->suffix);
        $roleB = $this->makeRole('P1 Live B ' . $this->suffix);

        $requester = $this->makeUser('req');
        $first     = $this->makeUser('one');
        $second    = $this->makeUser('two');
        $this->grantRole($first, $roleA);
        $this->grantRole($second, $roleB);

        $workflowId = $this->makeWorkflow();
        $this->insertStep($workflowId, 1, $roleA, true);
        $this->insertStep($workflowId, 2, $roleB, true);
        $typeId     = $this->makeType();
        $this->bindType($workflowId, $typeId);
        $gatepassId = $this->makeGatepass($requester, $typeId);

        $this->service->startWorkflow(self::TENANT, $gatepassId, $workflowId);

        // Simulate a pre-snapshot (legacy) instance.
        $this->db->exec("UPDATE gatepass_workflow_instances SET step_snapshot = NULL WHERE tenant_id = " . self::TENANT);

        $this->service->approve($this->pendingApprovalId($first), $first);

        $pending = (int) $this->db->query(
            'SELECT COUNT(*) FROM gatepass_approvals WHERE tenant_id = ' . self::TENANT . " AND status = 'pending'"
        )->fetchColumn();

        $this->assertSame(1, $pending, 'legacy instances fall back to live step config');

        $this->service->approve($this->pendingApprovalId($second), $second);

        $status = $this->db->query("SELECT status FROM gatepass_workflow_instances WHERE tenant_id = " . self::TENANT)->fetchColumn();
        $this->assertSame('approved', $status);
    }

    /* =========================================================
       REASSIGN + AUDIT
    ========================================================= */

    public function testReassignMovesPendingApprovalAndNotifiesNewApprover(): void
    {
        $roleId    = $this->makeRole('P1 Reassign ' . $this->suffix);
        $requester = $this->makeUser('req');
        $stuck     = $this->makeUser('stuck');
        $fresh     = $this->makeUser('fresh');
        // Only the stuck approver holds the role: if $fresh were in it too, the
        // engine would already have given them a pending row and reassign would
        // (correctly) refuse the duplicate.
        $this->grantRole($stuck, $roleId);

        [$workflowId, $typeId] = $this->makeWorkflowForType([$roleId]);
        $gatepassId = $this->makeGatepass($requester, $typeId);

        $this->service->startWorkflow(self::TENANT, $gatepassId, $workflowId);
        $this->service->discardNotifications();

        $approvalId = $this->pendingApprovalId($stuck, $gatepassId);

        $this->service->reassign($approvalId, $fresh, self::TENANT);

        $assignee = (int) $this->db->query("SELECT approver_user_id FROM gatepass_approvals WHERE id = {$approvalId}")->fetchColumn();
        $this->assertSame($fresh, $assignee);
        $this->assertSame(1, $this->service->pendingNotificationCount(), 'new assignee is notified');

        $logged = (int) $this->db->query(
            "SELECT COUNT(*) FROM audit_logs WHERE tenant_id = " . self::TENANT . " AND action = 'approval.reassigned' AND entity_id = {$approvalId}"
        )->fetchColumn();
        $this->assertSame(1, $logged);
    }

    public function testReassignRejectsRequesterAndActiveDuplicate(): void
    {
        $roleId    = $this->makeRole('P1 Rej ' . $this->suffix);
        $requester = $this->makeUser('req');
        $stuck     = $this->makeUser('stuck');
        $this->grantRole($requester, $roleId);
        $this->grantRole($stuck, $roleId);

        [$workflowId, $typeId] = $this->makeWorkflowForType([$roleId]);
        $gatepassId = $this->makeGatepass($requester, $typeId);

        $this->service->startWorkflow(self::TENANT, $gatepassId, $workflowId);

        // Requester is excluded from approvers, so only the stuck approver holds a pending row.
        $approvalId = $this->pendingApprovalId($stuck, $gatepassId);

        try {
            $this->service->reassign($approvalId, $requester, self::TENANT);
            $this->fail('Reassigning to the requester must be refused (no self-approval).');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('not the requester', $e->getMessage());
        }

        // A user who is already pending on the step cannot receive a second row.
        $second = $this->pendingApprovalId($stuck, $gatepassId);
        try {
            $this->service->reassign($second, $stuck, self::TENANT);
            $this->fail('Duplicate pending approver on the same step must be refused.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('already a pending approver', $e->getMessage());
        }
    }

    public function testDecisionsAreAuditLogged(): void
    {
        $roleA = $this->makeRole('P1 Audit A ' . $this->suffix);
        $roleB = $this->makeRole('P1 Audit B ' . $this->suffix);

        $requester = $this->makeUser('req');
        $first     = $this->makeUser('one');
        $second    = $this->makeUser('two');
        $this->grantRole($first, $roleA);
        $this->grantRole($second, $roleB);

        $workflowId = $this->makeWorkflow();
        $this->insertStep($workflowId, 1, $roleA, true);
        $this->insertStep($workflowId, 2, $roleB, true);
        $typeId     = $this->makeType();
        $this->bindType($workflowId, $typeId);

        $approvedGp = $this->makeGatepass($requester, $typeId);
        $rejectedGp = $this->makeGatepass($requester, $typeId);

        $this->service->startWorkflow(self::TENANT, $approvedGp, $workflowId);
        $this->service->approve($this->pendingApprovalId($first, $approvedGp), $first, 'looks fine');

        $this->service->startWorkflow(self::TENANT, $rejectedGp, $workflowId);
        $this->service->reject($this->pendingApprovalId($first, $rejectedGp), $first, 'missing paperwork');

        $actions = $this->db
            ->query("SELECT action FROM audit_logs WHERE tenant_id = " . self::TENANT)
            ->fetchAll(PDO::FETCH_COLUMN);

        $this->assertContains('approval.approved', $actions);
        $this->assertContains('approval.rejected', $actions);
        $this->assertContains('workflow.started', $actions);
    }

    /* =========================================================
       TYPE allowed_actions ENFORCEMENT
    ========================================================= */

    public function testCheckInBlockedWhenTypeDisallowsIt(): void
    {
        $this->requireColumn('gatepass_types', 'allowed_actions');

        $requester = $this->makeUser('req');
        $typeId    = $this->makeType();
        $this->db->exec("UPDATE gatepass_types SET allowed_actions = '{\"checkin\": false, \"checkout\": true}' WHERE id = {$typeId}");

        $gatepassId = $this->makeGatepass($requester, $typeId, 'approved');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not permitted');

        (new GatepassService())->checkIn(self::TENANT, $gatepassId, $requester);
    }

    public function testNullAllowedActionsDoesNotBlockCheckIn(): void
    {
        $this->requireColumn('gatepass_types', 'allowed_actions');

        $requester = $this->makeUser('req');
        $typeId    = $this->makeType(); // allowed_actions stays NULL

        $gatepassId = $this->makeGatepass($requester, $typeId, 'approved');

        $this->assertTrue((new GatepassService())->checkIn(self::TENANT, $gatepassId, $requester));
    }

    /* =========================================================
       FIXTURE HELPERS
    ========================================================= */

    private function requireColumn(string $table, string $column): void
    {
        $found = (int) $this->db->query("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '{$table}' AND COLUMN_NAME = '{$column}'
        ")->fetchColumn();

        if ($found === 0) {
            $this->markTestSkipped("{$table}.{$column} missing — run: php bin/migrate.php up --db={$_ENV['DB_DATABASE']}");
        }
    }

    private function makeWorkflow(): int
    {
        return $this->insert(
            "INSERT INTO workflows (tenant_id, name, description, is_active) VALUES (%d, 'P1 WF %s', '', 1)",
            self::TENANT,
            $this->suffix
        );
    }

    /** @param int[] $stepRoleIds @return array{0:int,1:int} [workflowId, typeId] */
    private function makeWorkflowForType(array $stepRoleIds): array
    {
        $workflowId = $this->makeWorkflow();

        foreach ($stepRoleIds as $i => $roleId) {
            $this->insertStep($workflowId, $i + 1, $roleId, true);
        }

        $typeId = $this->makeType();
        $this->bindType($workflowId, $typeId);

        return [$workflowId, $typeId];
    }

    private function insertStep(int $workflowId, int $order, int $roleId, bool $mandatory): int
    {
        return $this->insert(
            "INSERT INTO workflow_steps (tenant_id, workflow_id, name, role_id, step_order, is_mandatory) VALUES (%d, %d, 'Step %d', %d, %d, %d)",
            self::TENANT,
            $workflowId,
            $order,
            $roleId,
            $order,
            $mandatory ? 1 : 0
        );
    }

    private function makeType(): int
    {
        return $this->insert(
            "INSERT INTO gatepass_types (tenant_id, name, type_code, is_active) VALUES (%d, 'P1 Type %s', 'P1%s', 1)",
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

    private function makeUser(string $tag): int
    {
        return (int) $this->insert(
            "INSERT INTO users (tenant_id, email, password_hash, first_name, last_name, username, is_active, is_admin) VALUES (%d, '%s', 'x', 'P1', '%s', '%s', 1, 0)",
            self::TENANT,
            "p1.{$this->suffix}.{$tag}@test.local",
            ucfirst($tag),
            'p1' . $this->suffix . $tag
        );
    }

    private function grantRole(int $userId, int $roleId): void
    {
        $stmt = $this->db->prepare("INSERT INTO user_roles (user_id, role_id, tenant_id) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $roleId, self::TENANT]);
    }

    private function makeGatepass(int $createdBy, int $typeId, string $statusCode = 'pending'): int
    {
        $this->seq++;

        return $this->insert(
            "INSERT INTO gatepasses (tenant_id, gatepass_number, scan_token, gatepass_type_id, status_id, purpose, needs_approval, created_by) VALUES (%d, 'P1-%s-%d', '%s', %d, %d, 'integration', 1, %d)",
            self::TENANT,
            $this->suffix,
            $this->seq,
            bin2hex(random_bytes(32)),
            $typeId,
            $this->gatepassStatusId(self::TENANT, $statusCode),
            $createdBy
        );
    }

    private function pendingApprovalId(int $userId, ?int $gatepassId = null): int
    {
        $sql = "
            SELECT ga.id FROM gatepass_approvals ga
            INNER JOIN gatepass_workflow_instances gwi
                ON gwi.id = ga.workflow_instance_id AND gwi.tenant_id = ga.tenant_id
            WHERE ga.tenant_id = ? AND ga.approver_user_id = ? AND ga.status = 'pending'
        ";
        $args = [self::TENANT, $userId];

        if ($gatepassId !== null) {
            $sql .= ' AND gwi.gatepass_id = ?';
            $args[] = $gatepassId;
        }

        $stmt = $this->db->prepare($sql . ' ORDER BY ga.id LIMIT 1');
        $stmt->execute($args);
        $id = $stmt->fetchColumn();

        if ($id === false) {
            throw new RuntimeException("No pending approval for user {$userId}.");
        }

        return (int) $id;
    }

    private function insert(string $sql, mixed ...$args): int
    {
        $stmt = $this->db->prepare(sprintf($sql, ...$args));
        $stmt->execute();

        return (int) $this->db->lastInsertId();
    }
}
