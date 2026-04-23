<?php

namespace App\Modules\Gatepass\Services;

use App\Core\Audit;
use App\Core\DB;
use App\Modules\Approval\Services\ApprovalService;
use App\Modules\Gatepass\DTOs\GatepassDTO;
use App\Modules\Gatepass\Repositories\GatepassItemRepository;
use App\Modules\Gatepass\Repositories\GatepassRepository;
use App\Modules\Gatepass\Repositories\GatepassStatusRepository;
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

    public function __construct()
    {
        $this->db              = DB::connect();
        $this->repo            = new GatepassRepository();
        $this->itemRepo        = new GatepassItemRepository();
        $this->statusRepo      = new GatepassStatusRepository();
        $this->approvalService = new ApprovalService();
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
                'gatepass_number' => $gatepassNumber,
                'needs_approval'  => $dto->needs_approval,
            ]);

            $this->db->commit();

            return $gatepassId;

        } catch (Throwable $e) {
            $this->db->rollBack();
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

    public function checkIn(int $tenantId, int $gatepassId, int $userId): bool
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
        $approvedStatusId
    );

    if (!$success) {
        throw new RuntimeException(
            'Check-in failed. The gatepass may have been modified concurrently.'
        );
    }

    Audit::log('gatepass.checked_in', 'gatepass', $gatepassId, [
        'user_id'   => $userId,
        'timestamp' => $now,
    ]);

    return true;
}

public function checkOut(int $tenantId, int $gatepassId, int $userId): bool
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

    $timestamp = date('Y-m-d H:i:s');

    $checkedOutStatusId = $this->statusRepo->requireIdByCode($tenantId, 'CHECKED_OUT');

    $success = $this->repo->checkOut(
        $tenantId,
        $gatepassId,
        $userId,
        $timestamp,
        $checkedOutStatusId
    );

    if (!$success) {
        throw new RuntimeException(
            'Checkout failed. The gatepass may have been modified concurrently.'
        );
    }

    Audit::log('gatepass.checked_out', 'gatepass', $gatepassId, [
        'user_id'   => $userId,
        'timestamp' => $timestamp,
    ]);

    return true;
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

        $statusId = $this->statusRepo->requireIdByCode($tenantId, 'RETURNED');

        return $this->repo->updateStatus($tenantId, $gatepassId, $statusId);
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

    public function list(int $tenantId, int $userId, string $role): array
    {
        if ($role === 'admin' || $role === 'General Manager') {
            return $this->repo->findAllByTenant($tenantId);
        }

        $departmentId = $this->getUserDepartment($tenantId, $userId);

        return $this->repo->findAllByDepartment($tenantId, $departmentId);
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

        $parts[] = str_pad((string) $config['sequence'], (int) $config['padding'], '0', STR_PAD_LEFT);

        $config['sequence']++;

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

        return implode('-', $parts);
    }
}
