<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Gatepass\Services\GatepassService;
use InvalidArgumentException;

/**
 * A gatepass was deleted with a hard DELETE and no audit row, so a pass that had
 * already been through the gate (and its approvals/scan history, which cascade)
 * could disappear without a trace.
 */
final class GatepassDeleteGuardTest extends DatabaseTestCase
{
    private const TENANT = 1;
    private const NUMBER_PREFIX = 'DEL-GUARD-';

    private GatepassService $service;

    /** @var array<int, int> */
    private array $created = [];

    protected function setUp(): void
    {
        parent::setUp();

        // delete() opens its own transaction, so the fixture transaction this
        // base class starts has to be closed first.
        if ($this->db->inTransaction()) {
            $this->db->commit();
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }

        // Audit::log() needs an authenticated actor.
        $_SESSION['user'] = [
            'id'         => 1,
            'tenant_id'  => self::TENANT,
            'first_name' => 'Scan',
            'last_name'  => 'User',
            'email'      => 'scan.user@test.local',
        ];

        $this->service = new GatepassService();
    }

    protected function tearDown(): void
    {
        if ($this->created !== []) {
            $ids = implode(',', array_map('intval', $this->created));

            // audit_logs has no FK to gatepasses, so remove those rows by hand.
            $this->db->exec("DELETE FROM audit_logs WHERE action = 'gatepass.deleted' AND entity_id IN ({$ids})");
        }

        $this->db->exec(
            "DELETE FROM gatepasses WHERE tenant_id = " . self::TENANT
            . " AND gatepass_number LIKE '" . self::NUMBER_PREFIX . "%'"
        );
        $this->db->exec(
            "DELETE FROM workflows WHERE tenant_id = " . self::TENANT . " AND name LIKE 'Delete guard %'"
        );

        $_SESSION = [];

        parent::tearDown();
    }

    private function seedGatepass(string $number, ?string $actualIn = null): int
    {
        $typeId = (int) $this->db->query(
            'SELECT id FROM gatepass_types WHERE tenant_id = ' . self::TENANT . " AND type_code = 'EQUIP_OUT' LIMIT 1"
        )->fetchColumn();

        $deptId = (int) $this->db
            ->query('SELECT id FROM departments WHERE tenant_id = ' . self::TENANT . ' ORDER BY id LIMIT 1')
            ->fetchColumn();

        $stmt = $this->db->prepare("
            INSERT INTO gatepasses
                (tenant_id, gatepass_number, scan_token, status_id, purpose,
                 is_returnable, needs_approval, created_by, department_id,
                 gatepass_type_id, actual_in)
            VALUES
                (:tenant, :number, :token, :status, 'Delete guard test',
                 0, 0, 1, :dept, :type, :actual_in)
        ");

        $stmt->execute([
            ':tenant'    => self::TENANT,
            ':number'    => self::NUMBER_PREFIX . $number,
            ':token'     => bin2hex(random_bytes(16)),
            ':status'    => $this->gatepassStatusId(self::TENANT, $actualIn ? 'checked_in' : 'approved'),
            ':dept'      => $deptId,
            ':type'      => $typeId,
            ':actual_in' => $actualIn,
        ]);

        $id = (int) $this->db->lastInsertId();
        $this->created[] = $id;

        return $id;
    }

    private function addWorkflowInstance(int $gatepassId, string $status): void
    {
        $this->db->prepare('INSERT INTO workflows (tenant_id, name, description) VALUES (?, ?, ?)')
            ->execute([self::TENANT, 'Delete guard ' . bin2hex(random_bytes(4)), 'test']);

        $workflowId = (int) $this->db->lastInsertId();

        $this->db->prepare("
            INSERT INTO gatepass_workflow_instances (tenant_id, gatepass_id, workflow_id, status)
            VALUES (?, ?, ?, ?)
        ")->execute([self::TENANT, $gatepassId, $workflowId, $status]);
    }

    private function gatepassExists(int $id): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM gatepasses WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, self::TENANT]);

        return $stmt->fetchColumn() !== false;
    }

    public function testGatepassWithGateActivityCannotBeDeleted(): void
    {
        $id = $this->seedGatepass('ACTIVITY', '2026-11-01 08:00:00');

        $this->expectException(InvalidArgumentException::class);

        try {
            $this->service->delete(self::TENANT, $id);
        } finally {
            $this->assertTrue($this->gatepassExists($id), 'the row must survive a refused delete');
        }
    }

    public function testGatepassAwaitingApprovalCannotBeDeleted(): void
    {
        $id = $this->seedGatepass('PENDING');
        $this->addWorkflowInstance($id, 'in_progress');

        $this->expectException(InvalidArgumentException::class);

        try {
            $this->service->delete(self::TENANT, $id);
        } finally {
            $this->assertTrue($this->gatepassExists($id));
        }
    }

    public function testUnusedGatepassIsDeletedAndAudited(): void
    {
        $id = $this->seedGatepass('UNUSED');

        $this->assertTrue($this->service->delete(self::TENANT, $id));
        $this->assertFalse($this->gatepassExists($id));

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM audit_logs
            WHERE tenant_id = ? AND action = 'gatepass.deleted' AND entity_type = 'gatepass' AND entity_id = ?
        ");
        $stmt->execute([self::TENANT, $id]);

        $this->assertSame(1, (int) $stmt->fetchColumn(), 'deleting a gatepass must leave exactly one audit row');
    }
}
