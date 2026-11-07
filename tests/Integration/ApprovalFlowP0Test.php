<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Gatepass\Repositories\GatepassRepository;
use PDOException;
use RuntimeException;

/**
 * Covers the P0 hardening pass:
 *   - requester excluded from approver fan-out (no self-approval)
 *   - deterministic one-workflow-per-type routing (migration 0019)
 *   - approval-lifecycle notifications queued in-transaction, flushed post-commit
 */
final class ApprovalFlowP0Test extends DatabaseTestCase
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
    }

    /* =========================================================
       SELF-APPROVAL EXCLUSION
    ========================================================= */

    public function testRequesterIsExcludedFromStepApprovers(): void
    {
        $roleId  = $this->makeRole('P0 Approver ' . $this->suffix);
        $requester = $this->makeUser('req');
        $other     = $this->makeUser('oth');
        $this->grantRole($requester, $roleId);
        $this->grantRole($other, $roleId);

        [$workflowId, $typeId] = $this->makeWorkflowForType([$roleId]);
        $gatepassId = $this->makeGatepass($requester, $typeId);

        $this->service->startWorkflow(self::TENANT, $gatepassId, $workflowId);

        $approvers = $this->db
            ->query("SELECT approver_user_id FROM gatepass_approvals WHERE tenant_id = " . self::TENANT)
            ->fetchAll(\PDO::FETCH_COLUMN);

        $this->assertContains((string) $other, array_map('strval', $approvers));
        $this->assertNotContains((string) $requester, array_map('strval', $approvers));
    }

    public function testMandatoryStepWithOnlyRequesterEligibleThrows(): void
    {
        $roleId    = $this->makeRole('P0 Sole ' . $this->suffix);
        $requester = $this->makeUser('req');
        $this->grantRole($requester, $roleId);

        [$workflowId, $typeId] = $this->makeWorkflowForType([$roleId], mandatory: true);
        $gatepassId = $this->makeGatepass($requester, $typeId);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no eligible approvers');

        $this->service->startWorkflow(self::TENANT, $gatepassId, $workflowId);
    }

    public function testNonMandatoryStepWithOnlyRequesterEligibleAutoSkips(): void
    {
        $firstRole  = $this->makeRole('P0 First ' . $this->suffix);
        $skipRole   = $this->makeRole('P0 Skip ' . $this->suffix);
        $requester  = $this->makeUser('req');
        $firstApprover = $this->makeUser('oth');
        $this->grantRole($requester, $skipRole);
        $this->grantRole($firstApprover, $firstRole);

        $workflowId = $this->insert("INSERT INTO workflows (tenant_id, name, description, is_active) VALUES (%d, 'P0 WF %s', '', 1)", self::TENANT, $this->suffix);
        $this->insertStep($workflowId, 1, $firstRole, true);
        $this->insertStep($workflowId, 2, $skipRole, false);
        $typeId     = $this->makeType();
        $this->bindType($workflowId, $typeId);

        $gatepassId = $this->makeGatepass($requester, $typeId);
        $this->service->startWorkflow(self::TENANT, $gatepassId, $workflowId);

        $approvalId = $this->pendingApprovalId($firstApprover);
        $this->service->approve($approvalId, $firstApprover, 'ok');

        $instance = $this->db->query(
            "SELECT status FROM gatepass_workflow_instances WHERE tenant_id = " . self::TENANT
        )->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('approved', $instance['status'], 'step 2 should auto-skip and finalize');
    }

    /* =========================================================
       ROUTING: ONE WORKFLOW PER TYPE
    ========================================================= */

    public function testSecondWorkflowForSameTypeIsRejectedByUniqueKey(): void
    {
        $this->requireJunctionUniqueKey();

        $roleA = $this->makeRole('P0 Route A ' . $this->suffix);
        $roleB = $this->makeRole('P0 Route B ' . $this->suffix);
        $wfA   = $this->insert("INSERT INTO workflows (tenant_id, name, description, is_active) VALUES (%d, 'P0 WFA %s', '', 1)", self::TENANT, $this->suffix);
        $wfB   = $this->insert("INSERT INTO workflows (tenant_id, name, description, is_active) VALUES (%d, 'P0 WFB %s', '', 1)", self::TENANT, $this->suffix);
        $type  = $this->makeType();

        $this->bindType($wfA, $type);

        try {
            $this->bindType($wfB, $type);
            $this->fail('Expected unique key uk_wgt_tenant_type to block a second workflow for the same type.');
        } catch (PDOException $e) {
            $this->assertSame('23000', $e->getCode());
        }
    }

    public function testLegacyWorkflowIdColumnIsDropped(): void
    {
        $found = $this->db->query("
            SELECT COUNT(*) FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'gatepass_types'
              AND COLUMN_NAME  = 'workflow_id'
        ")->fetchColumn();

        $this->assertEquals(0, (int) $found, 'gatepass_types.workflow_id must be dropped by migration 0019');
    }

    public function testRoutingResolvesThroughJunctionOnly(): void
    {
        $roleId = $this->makeRole('P0 Route ' . $this->suffix);
        [$workflowId, $typeId] = $this->makeWorkflowForType([$roleId]);

        $resolved = (new GatepassRepository())->getWorkflowIdFromType(self::TENANT, $typeId);

        $this->assertSame($workflowId, $resolved);
    }

    /* =========================================================
       NOTIFICATIONS
    ========================================================= */

    public function testOnlyApproversWithValidEmailAreNotified(): void
    {
        $roleId  = $this->makeRole('P0 Notify ' . $this->suffix);
        $requester = $this->makeUser('req');
        $withMail    = $this->makeUser('oth');
        $noMail      = $this->makeUser('nol');
        $this->db->exec('UPDATE users SET email = \'\' WHERE id = ' . $noMail);
        foreach ([$requester, $withMail, $noMail] as $uid) {
            $this->grantRole($uid, $roleId);
        }

        [$workflowId, $typeId] = $this->makeWorkflowForType([$roleId]);
        $gatepassId = $this->makeGatepass($requester, $typeId);

        $this->service->startWorkflow(self::TENANT, $gatepassId, $workflowId);

        $pending = (int) $this->db->query(
            'SELECT COUNT(*) FROM gatepass_approvals WHERE tenant_id = ' . self::TENANT
        )->fetchColumn();

        $this->assertSame(2, $pending, 'both eligible approvers get pending rows');
        $this->assertSame(1, $this->service->pendingNotificationCount(), 'only the approver with an email is notified');
    }

    public function testDecisionEmailQueuedOnFinalApprovalAndRejection(): void
    {
        $roleId  = $this->makeRole('P0 Decide ' . $this->suffix);
        $requester = $this->makeUser('req');
        $approver  = $this->makeUser('oth');
        $this->grantRole($approver, $roleId);

        [$workflowId, $typeId] = $this->makeWorkflowForType([$roleId]);
        $gatepassId = $this->makeGatepass($requester, $typeId);

        $this->service->startWorkflow(self::TENANT, $gatepassId, $workflowId);
        $this->service->approve($this->pendingApprovalId($approver), $approver);

        $this->assertSame(2, $this->service->pendingNotificationCount(), 'approver notice + requester approved notice');

        // Second pass: rejection must queue the requester decision too.
        $gatepassId2 = $this->makeGatepass($requester, $typeId);
        $this->service->startWorkflow(self::TENANT, $gatepassId2, $workflowId);
        $before = $this->service->pendingNotificationCount();
        $this->service->reject($this->pendingApprovalId($approver, $gatepassId2), $approver, 'not justified');

        $this->assertSame($before + 1, $this->service->pendingNotificationCount(), 'requester rejected notice');
    }

    public function testFlushWritesEmailOutboxRows(): void
    {
        $outbox = (int) $this->db->query("
            SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'email_outbox'
        ")->fetchColumn();

        if ($outbox === 0) {
            $this->markTestSkipped('email_outbox missing — run: php bin/migrate.php up --db=' . $_ENV['DB_DATABASE']);
        }

        $roleId   = $this->makeRole('P0 Flush ' . $this->suffix);
        $requester = $this->makeUser('req');
        $approver  = $this->makeUser('oth');
        $this->grantRole($approver, $roleId);

        [$workflowId, $typeId] = $this->makeWorkflowForType([$roleId]);
        $gatepassId = $this->makeGatepass($requester, $typeId);

        $this->service->startWorkflow(self::TENANT, $gatepassId, $workflowId);
        $this->service->flushNotifications();

        $this->assertSame(0, $this->service->pendingNotificationCount(), 'flush empties the queue');

        $rows = $this->db
            ->query("SELECT to_email, status FROM email_outbox WHERE tenant_id = " . self::TENANT)
            ->fetchAll(\PDO::FETCH_ASSOC);

        $this->assertCount(1, $rows);
        $this->assertSame($this->emailFor('oth'), $rows[0]['to_email']);
        // Mail is disabled in test tenants, so Mailer records the attempt as skipped.
        $this->assertSame('skipped', $rows[0]['status']);
    }

    public function testDiscardDropsQueuedNotifications(): void
    {
        $roleId   = $this->makeRole('P0 Discard ' . $this->suffix);
        $requester = $this->makeUser('req');
        $approver  = $this->makeUser('oth');
        $this->grantRole($approver, $roleId);

        [$workflowId, $typeId] = $this->makeWorkflowForType([$roleId]);
        $gatepassId = $this->makeGatepass($requester, $typeId);

        $this->service->startWorkflow(self::TENANT, $gatepassId, $workflowId);
        $this->service->discardNotifications();

        $this->assertSame(0, $this->service->pendingNotificationCount());
    }

    /* =========================================================
       NON-CONTIGUOUS STEP ORDER
    ========================================================= */

    /**
     * Steps 1 and 3 with no step 2: the engine used to advance by +1, find no
     * step, and treat that as the end of the workflow — approving step 1
     * finalised the gatepass while step 3 never ran.
     */
    public function testStepOrderGapDoesNotFinaliseTheWorkflow(): void
    {
        $firstRole  = $this->makeRole('Gap First ' . $this->suffix);
        $secondRole = $this->makeRole('Gap Second ' . $this->suffix);

        $requester      = $this->makeUser('req');
        $firstApprover  = $this->makeUser('oth');
        $secondApprover = $this->makeUser('gap');

        $this->grantRole($firstApprover, $firstRole);
        $this->grantRole($secondApprover, $secondRole);

        $workflowId = $this->insert(
            "INSERT INTO workflows (tenant_id, name, description, is_active) VALUES (%d, 'Gap WF %s', '', 1)",
            self::TENANT,
            $this->suffix
        );

        $this->insertStep($workflowId, 1, $firstRole, true);
        $this->insertStep($workflowId, 3, $secondRole, true); // order 2 deliberately missing

        $typeId = $this->makeType();
        $this->bindType($workflowId, $typeId);

        $gatepassId = $this->makeGatepass($requester, $typeId);
        $this->service->startWorkflow(self::TENANT, $gatepassId, $workflowId);

        $this->service->approve($this->pendingApprovalId($firstApprover, $gatepassId), $firstApprover, 'ok');

        $stmt = $this->db->prepare(
            'SELECT status, current_step_order FROM gatepass_workflow_instances WHERE tenant_id = ? AND gatepass_id = ? LIMIT 1'
        );
        $stmt->execute([self::TENANT, $gatepassId]);
        $instance = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertSame('in_progress', $instance['status'], 'a numbering gap must not end the workflow');
        $this->assertSame(3, (int) $instance['current_step_order'], 'the next configured step must be materialised');

        // Step 3 really has a live approver waiting on it.
        $this->service->approve($this->pendingApprovalId($secondApprover, $gatepassId), $secondApprover, 'ok');

        $stmt->execute([self::TENANT, $gatepassId]);
        $this->assertSame('approved', $stmt->fetch(\PDO::FETCH_ASSOC)['status']);
    }

    /* =========================================================
       FIXTURE HELPERS (reference rows by id returned here, never hardcoded)
    ========================================================= */

    private function requireJunctionUniqueKey(): void
    {
        $found = (int) $this->db->query("
            SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME   = 'workflow_gatepass_type'
              AND INDEX_NAME   = 'uk_wgt_tenant_type'
        ")->fetchColumn();

        if ($found === 0) {
            $this->markTestSkipped('Migration 0019 not applied — run: php bin/migrate.php up --db=' . $_ENV['DB_DATABASE']);
        }
    }

    /** @param int[] $stepRoleIds @return array{0:int,1:int} [workflowId, typeId] */
    private function makeWorkflowForType(array $stepRoleIds, bool $mandatory = true): array
    {
        $workflowId = $this->insert(
            "INSERT INTO workflows (tenant_id, name, description, is_active) VALUES (%d, 'P0 WF %s', '', 1)",
            self::TENANT,
            $this->suffix
        );

        foreach ($stepRoleIds as $i => $roleId) {
            $this->insertStep($workflowId, $i + 1, $roleId, $mandatory);
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
            "INSERT INTO gatepass_types (tenant_id, name, type_code, is_active) VALUES (%d, 'P0 Type %s', 'P0%s', 1)",
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
        $id = $this->insert(
            "INSERT INTO users (tenant_id, email, password_hash, first_name, last_name, username, is_active, is_admin) VALUES (%d, '%s', 'x', 'P0', '%s', '%s', 1, 0)",
            self::TENANT,
            $this->emailFor($tag),
            ucfirst($tag),
            'p0' . $this->suffix . $tag
        );

        return (int) $id;
    }

    private function emailFor(string $tag): string
    {
        return "p0.{$this->suffix}.{$tag}@test.local";
    }

    private function grantRole(int $userId, int $roleId): void
    {
        $stmt = $this->db->prepare("INSERT INTO user_roles (user_id, role_id, tenant_id) VALUES (?, ?, ?)");
        $stmt->execute([$userId, $roleId, self::TENANT]);
    }

    private function makeGatepass(int $createdBy, int $typeId): int
    {
        $this->seq++;

        return $this->insert(
            "INSERT INTO gatepasses (tenant_id, gatepass_number, scan_token, gatepass_type_id, status_id, purpose, needs_approval, created_by) VALUES (%d, 'P0-%s-%d', '%s', %d, %d, 'integration', 1, %d)",
            self::TENANT,
            $this->suffix,
            $this->seq,
            bin2hex(random_bytes(32)),
            $typeId,
            $this->gatepassStatusId(self::TENANT, 'pending'),
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
