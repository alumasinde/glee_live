<?php
declare(strict_types=1);

namespace Tests\Integration;

use App\Modules\Gatepass\Services\GatepassService;
use RuntimeException;

final class ScanFlowTest extends DatabaseTestCase
{
    private const TENANT = 1;

    private GatepassService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GatepassService();
    }

    private function seedGatepass(
        string $token,
        string $number,
        string $statusCode = 'approved',
        bool $returnable = false,
        ?string $actualIn = null
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO gatepasses
                (tenant_id, gatepass_number, scan_token, status_id, purpose,
                 is_returnable, needs_approval, created_by, department_id,
                 gatepass_type_id, actual_in)
            VALUES
                (:tenant, :number, :token, :status, 'Integration test',
                 :returnable, 0, 1, :dept, :type, :actual_in)
        ");

        $typeId = (int) $this->db->query(
            'SELECT id FROM gatepass_types WHERE tenant_id = ' . self::TENANT . " AND type_code = 'EQUIP_OUT' LIMIT 1"
        )->fetchColumn();

        $stmt->execute([
            ':tenant' => self::TENANT,
            ':number' => $number,
            ':token' => $token,
            ':status' => $this->gatepassStatusId(self::TENANT, $statusCode),
            ':returnable' => (int) $returnable,
            ':dept' => $this->firstDepartmentId(self::TENANT),
            ':type' => $typeId,
            ':actual_in' => $actualIn,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function firstDepartmentId(int $tenantId): int
    {
        return (int) $this->db
            ->query("SELECT id FROM departments WHERE tenant_id = {$tenantId} ORDER BY id LIMIT 1")
            ->fetchColumn();
    }

    private function seedVisit(string $token, bool $blacklisted = false): int
    {
        $this->db->prepare("
            INSERT INTO visitors (id, tenant_id, first_name, last_name, is_blacklisted)
            VALUES (1, :tenant, 'Jane', 'Doe', :bl)
        ")->execute([':tenant' => self::TENANT, ':bl' => (int) $blacklisted]);

        $stmt = $this->db->prepare("
            INSERT INTO visits (tenant_id, department_id, visitor_id, visit_status_id, scan_token, purpose)
            VALUES (:tenant, :dept, 1, :status, :token, 'Meeting')
        ");
        $stmt->execute([
            ':tenant' => self::TENANT,
            ':dept' => $this->firstDepartmentId(self::TENANT),
            ':status' => $this->visitStatusId(self::TENANT, 'scheduled'),
            ':token' => $token,
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function ageScanLogs(string $subjectType, int $subjectId): void
    {
        $stmt = $this->db->prepare("
            UPDATE scan_logs
            SET created_at = DATE_SUB(NOW(), INTERVAL 60 SECOND)
            WHERE subject_type = :type AND subject_id = :id
        ");
        $stmt->execute([':type' => $subjectType, ':id' => $subjectId]);
    }

    private function gateRow(int $id): array
    {
        return $this->db
            ->query("SELECT * FROM gatepasses WHERE id = {$id}")
            ->fetch();
    }

    private function visitRow(int $id): array
    {
        return $this->db
            ->query("SELECT * FROM visits WHERE id = {$id}")
            ->fetch();
    }

    public function testFirstTokenScanChecksGatepassIn(): void
    {
        $token = $this->newToken();
        $id = $this->seedGatepass($token, 'GP-SCAN-1');

        $result = $this->service->processScan(self::TENANT, $token, 1);

        $this->assertSame('checked_in', $result['action']);
        $this->assertSame('gatepass', $result['subjectType']);
        $this->assertSame($id, $result['subjectId']);
        $this->assertSame('GP-SCAN-1: checked in successfully.', $result['message']);

        $row = $this->gateRow($id);
        $this->assertNotNull($row['actual_in']);
        $this->assertSame($this->gatepassStatusId(self::TENANT, 'checked_in'), (int) $row['status_id']);
        $this->assertSame(1, (int) $row['checked_in_by']);
    }

    public function testScanWithGateRecordsGateContext(): void
    {
        $this->db->prepare("
            INSERT INTO gates (tenant_id, name, code, is_active)
            VALUES (:tenant, 'Main Gate', 'MAIN', 1)
        ")->execute([':tenant' => self::TENANT]);
        $gateId = (int) $this->db->lastInsertId();

        $token = $this->newToken();
        $id = $this->seedGatepass($token, 'GP-SCAN-GATE');

        $result = $this->service->processScan(self::TENANT, $token, null, $gateId, 'device');

        $this->assertSame('Main Gate', $result['gateName']);

        $row = $this->gateRow($id);
        $this->assertSame($gateId, (int) $row['last_in_gate_id']);
        $this->assertNull($row['checked_in_by'], 'device scans have no session user');

        $log = $this->db->query("SELECT * FROM scan_logs WHERE subject_type = 'gatepass' AND subject_id = {$id}")->fetch();
        $this->assertSame('device', $log['source']);
        $this->assertSame($gateId, (int) $log['gate_id']);
    }

    public function testInactiveGateIsRejected(): void
    {
        $this->db->prepare("
            INSERT INTO gates (tenant_id, name, code, is_active)
            VALUES (:tenant, 'Dead Gate', 'DEAD', 0)
        ")->execute([':tenant' => self::TENANT]);
        $gateId = (int) $this->db->lastInsertId();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid or inactive gate.');
        $this->service->processScan(self::TENANT, $this->newToken(), null, $gateId);
    }

    public function testRescanWithinWindowIsBlocked(): void
    {
        $token = $this->newToken();
        $id = $this->seedGatepass($token, 'GP-DUP');

        $this->service->processScan(self::TENANT, $token, 1);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('GP-DUP: already scanned moments ago.');
        $this->service->processScan(self::TENANT, $token, 1);
    }

    public function testSecondScanAfterWindowChecksOutNonReturnable(): void
    {
        $token = $this->newToken();
        $id = $this->seedGatepass($token, 'GP-CO');

        $this->service->processScan(self::TENANT, $token, 1);
        $this->ageScanLogs('gatepass', $id);

        $result = $this->service->processScan(self::TENANT, $token, 1);

        $this->assertSame('checked_out', $result['action']);
        $row = $this->gateRow($id);
        $this->assertNotNull($row['actual_out']);
        $this->assertSame($this->gatepassStatusId(self::TENANT, 'checked_out'), (int) $row['status_id']);
    }

    public function testReturnablePassScansToReturnedInsteadOfCheckedOut(): void
    {
        $token = $this->newToken();
        $id = $this->seedGatepass($token, 'GP-RET', statusCode: 'approved', returnable: true);

        $this->service->processScan(self::TENANT, $token, 1);
        $this->ageScanLogs('gatepass', $id);

        $result = $this->service->processScan(self::TENANT, $token, 1);

        $this->assertSame('returned', $result['action']);
        $this->assertSame('GP-RET: item returned successfully.', $result['message']);
        $row = $this->gateRow($id);
        $this->assertSame($this->gatepassStatusId(self::TENANT, 'returned'), (int) $row['status_id']);
        $this->assertNull($row['actual_out']);
    }

    public function testCompletedGatepassScanIsRejected(): void
    {
        $token = $this->newToken();
        $id = $this->seedGatepass($token, 'GP-DONE', actualIn: '2026-10-01 08:00:00');
        $this->db->exec("UPDATE gatepasses SET actual_out = '2026-10-01 17:00:00' WHERE id = {$id}");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('process already completed');
        $this->service->processScan(self::TENANT, $token, 1);
    }

    public function testUnknownCodeIsRejectedAndLogged(): void
    {
        $ghost = $this->newToken();

        try {
            $this->service->processScan(self::TENANT, $ghost, 1);
            $this->fail('Expected RuntimeException for unknown code.');
        } catch (RuntimeException $e) {
            $this->assertSame('Code not recognized.', $e->getMessage());
        }

        $log = $this->db->query("SELECT * FROM scan_logs WHERE action = 'rejected' AND detail LIKE 'Not found%'")->fetch();
        $this->assertNotFalse($log);
        $this->assertStringContainsString(substr($ghost, 0, 64), (string) $log['detail']);
    }

    public function testLegacyGatepassNumberStillResolves(): void
    {
        $token = $this->newToken();
        $this->seedGatepass($token, 'GP-LEGACY');

        $result = $this->service->processScan(self::TENANT, 'GP-LEGACY', 1);

        $this->assertSame('checked_in', $result['action']);
        $this->assertSame('GP-LEGACY', $result['label']);
    }

    public function testVisitTokenLifecycleCheckInThenOut(): void
    {
        $token = $this->newToken();
        $visitId = $this->seedVisit($token);

        $in = $this->service->processScan(self::TENANT, $token, 1);
        $this->assertSame('checked_in', $in['action']);
        $this->assertSame('visit', $in['subjectType']);
        $this->assertSame('Jane Doe', $in['label']);

        $row = $this->visitRow($visitId);
        $this->assertNotNull($row['checkin_time']);
        $this->assertSame($this->visitStatusId(self::TENANT, 'checked_in'), (int) $row['visit_status_id']);

        $this->ageScanLogs('visit', $visitId);

        $out = $this->service->processScan(self::TENANT, $token, 1);
        $this->assertSame('checked_out', $out['action']);
        $row = $this->visitRow($visitId);
        $this->assertNotNull($row['checkout_time']);
        $this->assertSame($this->visitStatusId(self::TENANT, 'checked_out'), (int) $row['visit_status_id']);
    }

    public function testBlacklistedVisitCannotCheckIn(): void
    {
        $token = $this->newToken();
        $this->seedVisit($token, blacklisted: true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Blacklisted visitors cannot check in.');
        $this->service->processScan(self::TENANT, $token, 1);
    }

    public function testCompletedVisitScanIsRejected(): void
    {
        $token = $this->newToken();
        $visitId = $this->seedVisit($token);
        $this->db->exec("UPDATE visits SET checkin_time = '2026-10-01 08:00:00', checkout_time = '2026-10-01 17:00:00' WHERE id = {$visitId}");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('visit already completed');
        $this->service->processScan(self::TENANT, $token, 1);
    }

    public function testVisitTokenOfAnotherTenantIsNotResolved(): void
    {
        $token = $this->newToken();
        $visitId = $this->seedVisit($token);

        // Second tenant owns the scan; the visit belongs to another tenant.
        $otherDept = (int) $this->db->query('SELECT id FROM departments WHERE tenant_id = 2 ORDER BY id LIMIT 1')->fetchColumn();
        $this->db->exec("INSERT INTO visitors (tenant_id, first_name, last_name) VALUES (2, 'Jane', 'Doe')");
        $otherVisitor = (int) $this->db->lastInsertId();
        $otherStatus = (int) $this->db->query(
            'SELECT vs2.id FROM visits v
             JOIN visit_statuses vs1 ON vs1.id = v.visit_status_id
             JOIN visit_statuses vs2 ON vs2.tenant_id = 2 AND vs2.code = vs1.code
             WHERE v.id = ' . $visitId
        )->fetchColumn();
        $this->db->exec("UPDATE visits SET tenant_id = 2, department_id = {$otherDept}, visitor_id = {$otherVisitor}, visit_status_id = {$otherStatus} WHERE id = {$visitId}");

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Code not recognized.');
        $this->service->processScan(self::TENANT, $token, 1);
    }
}
