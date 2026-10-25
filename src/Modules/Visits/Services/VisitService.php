<?php

declare(strict_types=1);

namespace App\Modules\Visits\Services;

use App\Core\Audit;
use App\Core\DB;
use App\Modules\Visits\Repositories\VisitRepository;
use App\Modules\Visits\DTOs\VisitDTO;
use App\Modules\Badges\Repositories\BadgeRepository;
use App\Modules\Visitors\Repositories\VisitorRepository;
use PDO;
use RuntimeException;
use Throwable;

final class VisitService
{
    private VisitRepository $visitRepo;
    private BadgeRepository $badgeRepo;
    private VisitorRepository $visitorRepo;
    private PDO $db;

    public function __construct()
    {
        $this->visitRepo   = new VisitRepository();
        $this->badgeRepo   = new BadgeRepository();
        $this->visitorRepo = new VisitorRepository();
        $this->db          = DB::connect();
    }

    /**
     * Status rows are per-tenant, so their ids can never be hardcoded — the
     * composite FK on (visit_status_id, tenant_id) rejects another tenant's id.
     */
    private function statusId(int $tenantId, string $code): int
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

        if (!$id) {
            throw new RuntimeException(strtoupper($code) . ' visit status not configured for this tenant.');
        }

        return (int) $id;
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE VISIT
    |--------------------------------------------------------------------------
    */
    public function create(VisitDTO $dto): int
    {
        // Ensure visitor exists
        $visitor = $this->visitorRepo->find(
            $dto->tenant_id,
            $dto->visitor_id
        );

        if (!$visitor) {
            throw new RuntimeException('Visitor not found.');
        }

        // Prevent multiple active visits
        $activeVisit = $this->visitRepo->findActiveByVisitor(
            $dto->tenant_id,
            $dto->visitor_id
        );

        if ($activeVisit) {
            throw new RuntimeException('Visitor already has an active visit.');
        }

        $this->db->beginTransaction();

        try {

            $visitId = $this->visitRepo->create([
                'tenant_id'       => $dto->tenant_id,
                'department_id'   => $dto->department_id,
                'visitor_id'      => $dto->visitor_id,
                'host_user_id'    => $dto->host_user_id,
                'visit_type_id'   => $dto->visit_type_id,
                'visit_status_id' => $this->statusId($dto->tenant_id, 'scheduled'),
                'purpose'         => $dto->purpose,
                'expected_in'     => $dto->expected_in,
                'expected_out'    => $dto->expected_out,
                'created_by'      => $dto->created_by,
            ]);

            Audit::log(
                'visit.created',
                'visit',
                $visitId,
                ['tenant_id' => $dto->tenant_id]
            );

            $this->db->commit();

            return $visitId;

        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK IN
    |--------------------------------------------------------------------------
    */
    public function checkIn(int $tenantId, int $visitId): void
    {
        $visit = $this->visitRepo->find($tenantId, $visitId);

        if (!$visit) {
            throw new RuntimeException('Visit not found.');
        }

        if ($visit['checkin_time']) {
            throw new RuntimeException('Visitor already checked in.');
        }

        if ($visit['checkout_time']) {
            throw new RuntimeException('Visit already completed.');
        }

        // Ensure visitor is not blacklisted
        $visitor = $this->visitorRepo->find(
            $tenantId,
            (int) $visit['visitor_id']
        );

        if (!$visitor) {
            throw new RuntimeException('Visitor not found.');
        }

        if ((int) $visitor['is_blacklisted'] === 1) {
            throw new RuntimeException(
                'Blacklisted visitors cannot check in.'
            );
        }

        $this->db->beginTransaction();

        try {

            $this->visitRepo->checkIn(
                $tenantId,
                $visitId,
                $this->statusId($tenantId, 'checked_in')
            );

            Audit::log(
                'visit.checkin',
                'visit',
                $visitId,
                ['tenant_id' => $tenantId]
            );

            $this->db->commit();

        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK OUT
    |--------------------------------------------------------------------------
    */
    public function checkOut(int $tenantId, int $visitId): void
    {
        $visit = $this->visitRepo->find($tenantId, $visitId);

        if (!$visit) {
            throw new RuntimeException('Visit not found.');
        }

        if (!$visit['checkin_time']) {
            throw new RuntimeException('Visitor not checked in.');
        }

        if ($visit['checkout_time']) {
            throw new RuntimeException('Visitor already checked out.');
        }

        if ($this->badgeRepo->hasActiveBadge($tenantId, $visitId)) {
            throw new RuntimeException(
                'Badge must be returned before checkout.'
            );
        }

        $this->db->beginTransaction();

        try {

            // Update visit
            $this->visitRepo->checkOut(
                $tenantId,
                $visitId,
                $this->statusId($tenantId, 'checked_out')
            );

            // Auto return badge
            $this->badgeRepo->returnActiveBadge(
                $tenantId,
                $visitId
            );

            Audit::log(
                'visit.checkout',
                'visit',
                $visitId,
                ['tenant_id' => $tenantId]
            );

            $this->db->commit();

        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVE VISITS
    |--------------------------------------------------------------------------
    */
    public function getVisitor(int $tenantId, int $visitorId): ?array
{
    return $this->visitorRepo->find($tenantId, $visitorId);
}

    public function activeVisits(int $tenantId): array
    {
        return $this->visitRepo->getActiveVisits($tenantId);
    }

    public function getDepartments(int $tenantId): array
{
    return $this->visitRepo->getDepartments($tenantId);
}

public function getHosts(int $tenantId): array
{
    return $this->visitRepo->getHosts($tenantId);
}

public function getVisitTypes(int $tenantId): array
{
    return $this->visitRepo->getVisitTypes($tenantId);
}

}