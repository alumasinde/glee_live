<?php

namespace App\Modules\Gatepass\Services;

use App\Core\Audit;
use App\Core\DB;
use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Badges\Repositories\BadgeRepository;
use App\Modules\Gatepass\DTOs\GatepassDTO;
use App\Modules\Gatepass\Repositories\GatepassItemRepository;
use App\Modules\Gatepass\Repositories\GatepassRepository;
use App\Modules\Gatepass\Repositories\GatepassStatusRepository;
use App\Modules\Gatepass\Repositories\ScanLogRepository;
use App\Modules\Settings\Repositories\GateRepository;
use App\Modules\Visits\Repositories\VisitRepository;
use InvalidArgumentException;
use PDO;
use RuntimeException;
use Throwable;

class GatepassService
{
    private PDO $db;
    private GatepassRepository $repo;
    private GatepassItemRepository $itemRepo;
    private GatepassStatusRepository $statusRepo;
    private ApprovalService $approvalService;
    private ScanLogRepository $scanLogs;
    private GateRepository $gates;
    private VisitRepository $visits;
    private BadgeRepository $badges;

    public function __construct()
    {
        $this->db              = DB::connect();
        $this->repo            = new GatepassRepository();
        $this->itemRepo        = new GatepassItemRepository();
        $this->statusRepo      = new GatepassStatusRepository();
        $this->approvalService = new ApprovalService();
        $this->scanLogs        = new ScanLogRepository();
        $this->gates           = new GateRepository();
        $this->visits          = new VisitRepository();
        $this->badges          = new BadgeRepository();
    }

    public function create(GatepassDTO $dto): int
    {
        try {
            $this->db->beginTransaction();

            $gatepassNumber = $this->generateGatepassNumber($dto->tenant_id);
            $statusId       = $this->resolveInitialStatus($dto);
            $departmentId   = $this->getUserDepartment($dto->tenant_id, $dto->created_by);

            $gatepassId = $this->repo->create([
                'tenant_id'           => $dto->tenant_id,
                'visit_id'            => $dto->visit_id,
                'gatepass_type_id'    => $dto->gatepass_type_id,
                'gatepass_number'     => $gatepassNumber,
                'status_id'           => $statusId,
                'purpose'             => $dto->purpose,
                'is_returnable'       => (int) $dto->is_returnable,
                'expected_return_date'=> $dto->expected_return_date,
                'needs_approval'      => (int) $dto->needs_approval,
                'created_by'          => $dto->created_by,
                'department_id'       => $departmentId,
            ]);

            $this->itemRepo->insertMany($dto->tenant_id, $gatepassId, $dto->items);

            if ($dto->needs_approval) {
                $workflowId = $this->repo->getWorkflowIdFromType(
                    $dto->tenant_id,
                    $dto->gatepass_type_id
                );

                if (!$workflowId) {
                    throw new RuntimeException('No workflow configured for this gatepass type.');
                }

                $this->approvalService->startWorkflow(
                    $dto->tenant_id,
                    $gatepassId,
                    $workflowId
                );
            }

            Audit::log('gatepass.created', 'gatepass', $gatepassId, [
                'target_label'    => $gatepassNumber,
                'gatepass_number' => $gatepassNumber,
                'needs_approval'  => $dto->needs_approval,
            ]);

            $this->db->commit();

            // startWorkflow ran inside this transaction, so its queued
            // notifications are only safe to send now that the commit landed.
            $this->approvalService->flushNotifications();

            return $gatepassId;

        } catch (Throwable $e) {
            $this->db->rollBack();
            $this->approvalService->discardNotifications();
            throw $e;
        }
    }

    public function update(int $tenantId, int $id, GatepassDTO $dto): bool
    {
        try {
            $this->db->beginTransaction();

            $updated = $this->repo->update($tenantId, $id, [
                'visit_id'            => $dto->visit_id,
                'gatepass_type_id'    => $dto->gatepass_type_id,
                'purpose'             => $dto->purpose,
                'is_returnable'       => (int) $dto->is_returnable,
                'expected_return_date'=> $dto->expected_return_date,
                'needs_approval'      => (int) $dto->needs_approval,
            ]);

            if (!$updated) {
                throw new InvalidArgumentException('Gatepass update failed or no changes were made.');
            }

            $this->itemRepo->deleteByGatepass($tenantId, $id);
            $this->itemRepo->insertMany($tenantId, $id, $dto->items);

            $this->db->commit();

            return true;

        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function delete(int $tenantId, int $id): bool
    {
        try {
            $this->db->beginTransaction();

            $this->itemRepo->deleteByGatepass($tenantId, $id);

            if (!$this->repo->delete($tenantId, $id)) {
                throw new InvalidArgumentException('Gatepass not found or already deleted.');
            }

            $this->db->commit();

            return true;

        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function checkIn(int $tenantId, int $gatepassId, ?int $userId, ?int $gateId = null): bool
{
    $gatepass = $this->repo->findById($tenantId, $gatepassId);

    if (!$gatepass) {
        throw new RuntimeException('Gatepass not found.');
    }

    if (!empty($gatepass['actual_in'])) {
        throw new RuntimeException('Gatepass already checked in.');
    }

    $eligibility = GatepassWorkflow::eligibility($gatepass);

    if (!$eligibility['checkin_eligible']) {
        throw new RuntimeException('Check-in not allowed in current state.');
    }

    $this->assertTypeActionAllowed($gatepass, 'checkin');

    $now = date('Y-m-d H:i:s');

    $checkedInStatusId = $this->statusRepo->requireIdByCode($tenantId, 'CHECKED_IN');
    $checkedOutStatusId = $this->statusRepo->requireIdByCode($tenantId, 'CHECKED_OUT');
    $approvedStatusId = $this->statusRepo->requireIdByCode($tenantId, 'APPROVED');

    $success = $this->repo->checkIn(
        $tenantId,
        $gatepassId,
        $userId,
        $now,
        $checkedInStatusId,
        $checkedOutStatusId,
        $approvedStatusId,
        $gateId
    );

    if (!$success) {
        throw new RuntimeException(
            'Check-in failed. The gatepass may have been modified concurrently.'
        );
    }

    Audit::log('gatepass.checked_in', 'gatepass', $gatepassId, [
        'user_id'      => $userId,
        'timestamp'    => $now,
        'target_label' => (string) $gatepass['gatepass_number'],
    ]);

    return true;
}

public function checkOut(int $tenantId, int $gatepassId, ?int $userId, ?int $gateId = null): bool
{
    $gatepass = $this->repo->findById($tenantId, $gatepassId);

    if (!$gatepass) {
        throw new RuntimeException('Gatepass not found.');
    }

    if (!empty($gatepass['actual_out'])) {
        throw new RuntimeException('Gatepass already checked out.');
    }

    $eligibility = GatepassWorkflow::eligibility($gatepass);

    if (!$eligibility['checkout_eligible']) {
        throw new RuntimeException('Checkout not allowed in current state.');
    }

    $this->assertTypeActionAllowed($gatepass, 'checkout');

    $timestamp = date('Y-m-d H:i:s');

    $checkedOutStatusId = $this->statusRepo->requireIdByCode($tenantId, 'CHECKED_OUT');

    $success = $this->repo->checkOut(
        $tenantId,
        $gatepassId,
        $userId,
        $timestamp,
        $checkedOutStatusId,
        $gateId
    );

    if (!$success) {
        throw new RuntimeException(
            'Checkout failed. The gatepass may have been modified concurrently.'
        );
    }

    Audit::log('gatepass.checked_out', 'gatepass', $gatepassId, [
        'user_id'      => $userId,
        'timestamp'    => $timestamp,
        'target_label' => (string) $gatepass['gatepass_number'],
    ]);

    return true;
}

    /**
     * Enforce the gatepass type's allowed_actions JSON for gate operations.
     * Missing/NULL config or an absent key means "allowed"; only explicit
     * false blocks the action.
     */
    private function assertTypeActionAllowed(array $gatepass, string $action): void
    {
        $raw = $gatepass['allowed_actions'] ?? null;
        if (!$raw) {
            return;
        }

        $allowed = json_decode((string) $raw, true);
        if (!is_array($allowed) || !array_key_exists($action, $allowed) || $allowed[$action] !== false) {
            return;
        }

        $labels = ['checkin' => 'Check-in', 'checkout' => 'Checkout', 'return' => 'Return'];
        $label = $labels[$action] ?? ucfirst($action);

        throw new RuntimeException(sprintf(
            '%s is not permitted for the "%s" gatepass type.',
            $label,
            $gatepass['gatepass_type_name'] ?? 'unknown'
        ));
    }

    public function markReturned(int $tenantId, int $gatepassId): bool
    {
        $gatepass = $this->repo->findById($tenantId, $gatepassId);
        if (!$gatepass) {
            throw new RuntimeException('Gatepass not found.');
        }

        if (!$gatepass['is_returnable']) {
            throw new RuntimeException('Gatepass is not returnable.');
        }

        if (!empty($gatepass['actual_return_date'])) {
            throw new RuntimeException('Gatepass already returned.');
        }

        $this->assertTypeActionAllowed($gatepass, 'return');

        $statusId = $this->statusRepo->requireIdByCode($tenantId, 'RETURNED');

        if (!$this->repo->markReturned($tenantId, $gatepassId, date('Y-m-d H:i:s'), $statusId)) {
            throw new RuntimeException('Return not allowed in current state.');
        }

        // The gate has taken the items back, so the quantities the reporting
        // and overdue queries read have to move with the status.
        $this->itemRepo->markAllReturned($tenantId, $gatepassId);

        Audit::log('gatepass.returned', 'gatepass', $gatepassId, [
            'target_label' => (string) $gatepass['gatepass_number'],
        ]);

        return true;
    }

    public function find(int $tenantId, int $id): ?array
    {
        $gatepass = $this->repo->findById($tenantId, $id);

        if (!$gatepass) {
            return null;
        }

        $gatepass['items'] = $this->itemRepo->findByGatepass($tenantId, $id);

        return $gatepass;
    }

    public function findByNumber(int $tenantId, string $number): ?array
    {
        $number = trim($number);

        if ($number === '') {
            return null;
        }

        $gatepass = $this->repo->findByNumber($tenantId, $number);

        if (!$gatepass) {
            return null;
        }

        $gatepass['items'] = $this->itemRepo->findByGatepass($tenantId, (int) $gatepass['id']);

        return $gatepass;
    }

    /*
    |--------------------------------------------------------------------------
    | SCAN
    | Single state machine shared by typed entry, camera QR and gate devices.
    | Accepts a 64-hex scan_token (gatepass OR visit pass) or a legacy
    | gatepass_number. Returns action details; throws RuntimeException with a
    | guard message on rejection (rejections are written to scan_logs).
    |--------------------------------------------------------------------------
    */
    public function processScan(
        int     $tenantId,
        string  $code,
        ?int    $userId = null,
        ?int    $gateId = null,
        string  $source = 'manual'
    ): array {
        $code = trim($code);

        if ($code === '') {
            throw new RuntimeException('Invalid QR code.');
        }

        $gateName = null;

        if ($gateId !== null) {
            $gate = $this->gates->findActive($tenantId, $gateId);

            if (!$gate) {
                throw new RuntimeException('Invalid or inactive gate.');
            }

            $gateName = (string) $gate['name'];
        }

        $isToken = preg_match('/^[0-9a-fA-F]{64}$/', $code) === 1;

        if ($isToken) {
            $subject = $this->findScanSubject($tenantId, strtolower($code));
        } else {
            $gatepass = $this->repo->findByNumber($tenantId, $code);
            $subject  = $gatepass !== null
                ? ['type' => 'gatepass', 'row' => $gatepass]
                : null;
        }

        if ($subject === null) {
            $this->scanLogs->record($tenantId, $gateId, 'gatepass', 0, 'rejected', $source, $userId, 'Not found: ' . substr($code, 0, 64));
            throw new RuntimeException('Code not recognized.');
        }

        if ($this->recentDuplicateScan($tenantId, $subject['type'], (int) $subject['row']['id'])) {
            $row = $subject['row'];

            $label = $subject['type'] === 'gatepass'
                ? (string) ($row['gatepass_number'] ?? 'Gatepass')
                : (trim(($row['visitor_first'] ?? '') . ' ' . ($row['visitor_last'] ?? '')) ?: 'Visitor');

            throw new RuntimeException("{$label}: already scanned moments ago.");
        }

        return $subject['type'] === 'visit'
            ? $this->scanVisit($tenantId, $subject['row'], $userId, $gateId, $source, $gateName)
            : $this->scanGatepass($tenantId, $subject['row'], $userId, $gateId, $source, $gateName);
    }

    private function findScanSubject(int $tenantId, string $token): ?array
    {
        $visit = $this->visits->findByTokenGlobal($token);

        if ($visit !== null && (int) $visit['tenant_id'] === $tenantId) {
            return ['type' => 'visit', 'row' => $visit];
        }

        $gatepass = $this->repo->findByToken($tenantId, $token);

        return $gatepass !== null ? ['type' => 'gatepass', 'row' => $gatepass] : null;
    }

    private function scanGatepass(
        int   $tenantId,
        array $gatepass,
        ?int  $userId,
        ?int  $gateId,
        string $source,
        ?string $gateName
    ): array {
        $id     = (int) $gatepass['id'];
        $number = (string) ($gatepass['gatepass_number'] ?? 'Gatepass');

        $transition = function (string $action, string $message) use ($tenantId, $gateId, $source, $userId, $number, $id, $gateName): array {
            $this->scanLogs->record($tenantId, $gateId, 'gatepass', $id, $action, $source, $userId);

            return $this->scanResult('gatepass', $id, $number, $action, $gateName, $message);
        };

        if (empty($gatepass['actual_in'])) {
            $this->checkIn($tenantId, $id, $userId, $gateId);
            return $transition('checked_in', "{$number}: checked in successfully.");
        }

        if ((int) ($gatepass['is_returnable'] ?? 0) === 1) {
            if (!empty($gatepass['actual_return_date'])) {
                $this->rejectScan($tenantId, $gateId, 'gatepass', $id, $source, $userId, "{$number}: item already returned.");
            }

            $this->markReturned($tenantId, $id);
            return $transition('returned', "{$number}: item returned successfully.");
        }

        if (empty($gatepass['actual_out'])) {
            $this->checkOut($tenantId, $id, $userId, $gateId);
            return $transition('checked_out', "{$number}: checked out successfully.");
        }

        $this->rejectScan($tenantId, $gateId, 'gatepass', $id, $source, $userId, "{$number}: process already completed.");
    }

    private function scanVisit(
        int   $tenantId,
        array $visit,
        ?int  $userId,
        ?int  $gateId,
        string $source,
        ?string $gateName
    ): array {
        $id    = (int) $visit['id'];
        $label = trim(($visit['visitor_first'] ?? '') . ' ' . ($visit['visitor_last'] ?? ''));
        $label = $label !== '' ? $label : 'Visitor';

        if (!empty($visit['checkout_time'])) {
            $this->rejectScan($tenantId, $gateId, 'visit', $id, $source, $userId, "{$label}: visit already completed.");
        }

        $transition = fn (string $action): array => $this->scanResult(
            'visit', $id, $label, $action, $gateName,
            "{$label}: " . str_replace('_', ' ', $action) . " successfully."
        );

        if (empty($visit['checkin_time'])) {
            if ((int) ($visit['is_blacklisted'] ?? 0) === 1) {
                $this->rejectScan($tenantId, $gateId, 'visit', $id, $source, $userId, 'Blacklisted visitors cannot check in.');
            }

            $this->visits->checkIn($tenantId, $id, $this->visitStatusId($tenantId, 'CHECKED_IN'), $gateId);
            $this->scanLogs->record($tenantId, $gateId, 'visit', $id, 'checked_in', $source, $userId);
            $this->auditScan($tenantId, $userId, 'visit.checkin', 'visit', $id, $gateId, $source);

            return $transition('checked_in');
        }

        if ($this->badges->hasActiveBadge($id, $tenantId)) {
            $this->rejectScan($tenantId, $gateId, 'visit', $id, $source, $userId, 'Badge must be returned before checkout.');
        }

        $this->visits->checkOut($tenantId, $id, $this->visitStatusId($tenantId, 'CHECKED_OUT'), $gateId);
        $this->badges->returnActiveBadge($tenantId, $id);
        $this->scanLogs->record($tenantId, $gateId, 'visit', $id, 'checked_out', $source, $userId);
        $this->auditScan($tenantId, $userId, 'visit.checkout', 'visit', $id, $gateId, $source);

        return $transition('checked_out');
    }

    private function scanResult(
        string $subjectType,
        int    $subjectId,
        string $label,
        string $action,
        ?string $gateName,
        string $message
    ): array {
        return [
            'subjectType' => $subjectType,
            'subjectId'   => $subjectId,
            'label'       => $label,
            'action'      => $action,
            'gateName'    => $gateName,
            'message'     => $message,
        ];
    }

    private function recentDuplicateScan(int $tenantId, string $type, int $id): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM scan_logs
            WHERE tenant_id    = :tenant_id
              AND subject_type = :type
              AND subject_id   = :id
              AND action <> 'rejected'
              AND created_at > DATE_SUB(NOW(), INTERVAL 30 SECOND)
            LIMIT 1
        ");

        $stmt->execute([':tenant_id' => $tenantId, ':type' => $type, ':id' => $id]);

        return $stmt->fetchColumn() !== false;
    }

    private function rejectScan(
        int $tenantId,
        ?int $gateId,
        string $type,
        int $id,
        string $source,
        ?int $userId,
        string $message
    ): never {
        $this->scanLogs->record($tenantId, $gateId, $type, $id, 'rejected', $source, $userId, $message);

        throw new RuntimeException($message);
    }

    private function visitStatusId(int $tenantId, string $code): int
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM visit_statuses
            WHERE tenant_id = :tenant_id
              AND UPPER(code) = :code
            LIMIT 1
        ");

        $stmt->execute([':tenant_id' => $tenantId, ':code' => strtoupper($code)]);
        $id = $stmt->fetchColumn();

        if ($id === false) {
            throw new RuntimeException("Visit status '{$code}' not configured for this tenant.");
        }

        return (int) $id;
    }

    private function auditScan(
        int $tenantId,
        ?int $userId,
        string $action,
        string $entityType,
        int $entityId,
        ?int $gateId,
        string $source
    ): void {
        $metadata = [
            'gate_id' => $gateId,
            'source'  => $source,
            'user_id' => $userId,
        ];

        if ($userId !== null) {
            Audit::log($action, $entityType, $entityId, $metadata);
        } else {
            Audit::system($tenantId, $action, $entityType, $entityId, $metadata);
        }
    }

    public function list(int $tenantId, int $userId, ?string $search = null): array
    {
        if (can('gatepass.view_all')) {
            return $this->repo->findAllByTenant($tenantId, $search);
        }

        $departmentId = $this->getUserDepartment($tenantId, $userId);

        return $this->repo->findAllByDepartment($tenantId, $departmentId, $search);
    }
        


    public function getAvailableActions(array $gatepass): array
    {
        $eligibility = GatepassWorkflow::eligibility($gatepass);

        return [
            'can_checkin'  => $eligibility['checkin_eligible'],
            'can_checkout' => $eligibility['checkout_eligible'],
        ];
    }

    public function getVisitsForTenant(int $tenantId, ?int $departmentId): array
    {
        $sql = "
            SELECT
                v.id,
                v.purpose,
                v.expected_in,
                v.expected_out,
                v.checkin_time,
                vs.name AS status_name,
                CONCAT(vis.first_name, ' ', vis.last_name) AS visitor_name,
                vis.phone,
                vis.id_number,
                vis.company_id
            FROM visits v
            INNER JOIN visitors vis
                ON vis.id = v.visitor_id AND vis.tenant_id = v.tenant_id
            INNER JOIN visit_statuses vs
                ON vs.id = v.visit_status_id AND vs.tenant_id = v.tenant_id
            WHERE v.tenant_id   = :tenant_id
              AND vs.code       = 'CHECKED_IN'
              AND v.checkout_time IS NULL
        ";

        $params = [':tenant_id' => $tenantId];

        if ($departmentId !== null) {
            $sql .= ' AND v.department_id = :department_id';
            $params[':department_id'] = $departmentId;
        }

        $sql .= ' ORDER BY v.checkin_time DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function resolveInitialStatus(GatepassDTO $dto): int
    {
        $code = $dto->needs_approval ? 'PENDING' : 'APPROVED';

        return $this->statusRepo->requireIdByCode($dto->tenant_id, $code);
    }

    private function getUserDepartment(int $tenantId, int $userId): int
    {
        $stmt = $this->db->prepare("
            SELECT department_id
            FROM users
            WHERE tenant_id = :tenant_id
              AND id        = :id
            LIMIT 1
        ");

        $stmt->execute([':tenant_id' => $tenantId, ':id' => $userId]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !(int) $row['department_id']) {
            throw new RuntimeException('User department not configured.');
        }

        return (int) $row['department_id'];
    }

    private function generateGatepassNumber(int $tenantId): string
    {
        $stmt = $this->db->prepare("
            SELECT config_json
            FROM tenant_settings
            WHERE tenant_id   = :tenant_id
              AND setting_key = 'gatepass_numbering'
            FOR UPDATE
        ");

        $stmt->execute([':tenant_id' => $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new RuntimeException('Gatepass numbering not configured for this tenant.');
        }

        $config = json_decode($row['config_json'], true) ?? [];

        $config = array_merge([
            'prefix'        => 'GP',
            'include_year'  => true,
            'include_month' => false,
            'padding'       => 4,
            'reset_yearly'  => true,
            'current_year'  => (int) date('Y'),
            'sequence'      => 1,
        ], $config);

        $year  = date('Y');
        $month = date('m');

        if ($config['reset_yearly'] && (string) $config['current_year'] !== $year) {
            $config['sequence']     = 1;
            $config['current_year'] = (int) $year;
        }

        $parts = [];

        if (!empty($config['prefix'])) {
            $parts[] = $config['prefix'];
        }

        if (!empty($config['include_year'])) {
            $parts[] = $year;
        }

        if (!empty($config['include_month'])) {
            $parts[] = $month;
        }

        $parts = [];

        if (!empty($config['prefix'])) {
            $parts[] = $config['prefix'];
        }

        if (!empty($config['include_year'])) {
            $parts[] = $year;
        }

        if (!empty($config['include_month'])) {
            $parts[] = $month;
        }

        $sequence = (int) $config['sequence'];

        // The stored counter can fall behind the rows that already exist — an
        // import, a hand-inserted pass, or a create that committed and then had
        // its counter update skipped. uk_gatepass_tenant_number would reject the
        // insert with a raw duplicate-key error, so walk past taken numbers.
        $exists = $this->db->prepare("
            SELECT 1 FROM gatepasses
            WHERE tenant_id       = :tenant_id
              AND gatepass_number = :gatepass_number
            LIMIT 1
        ");

        $taken = true;
        $gatepassNumber = '';

        for ($attempt = 0; $attempt < 100 && $taken; $attempt++) {
            $gatepassNumber = implode('-', array_merge(
                $parts,
                [str_pad((string) $sequence, (int) $config['padding'], '0', STR_PAD_LEFT)]
            ));

            $exists->execute([':tenant_id' => $tenantId, ':gatepass_number' => $gatepassNumber]);
            $taken = (bool) $exists->fetchColumn();

            if ($taken) {
                $sequence++;
            }
        }

        if ($taken) {
            throw new RuntimeException('Unable to allocate a free gatepass number for this tenant.');
        }

        $config['sequence'] = $sequence + 1;

        $update = $this->db->prepare("
            UPDATE tenant_settings
            SET config_json = :config_json
            WHERE tenant_id   = :tenant_id
              AND setting_key = 'gatepass_numbering'
        ");

        $update->execute([
            ':config_json' => json_encode($config),
            ':tenant_id'   => $tenantId,
        ]);

        return $gatepassNumber;
    }
}
