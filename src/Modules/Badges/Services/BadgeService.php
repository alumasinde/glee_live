<?php

declare(strict_types=1);

namespace App\Modules\Badges\Services;

use App\Core\Audit;
use App\Core\DB;
use App\Modules\Badges\Repositories\BadgeRepository;
use App\Modules\Visits\Repositories\VisitRepository;
use App\Modules\Visitors\Repositories\VisitorRepository;
use PDO;
use Exception;

final class BadgeService
{
    private BadgeRepository $badgeRepo;
    private VisitRepository $visitRepo;
    private VisitorRepository $visitorRepo;
    private PDO $db;

    public function __construct()
    {
        $this->badgeRepo   = new BadgeRepository();
        $this->visitRepo   = new VisitRepository();
        $this->visitorRepo = new VisitorRepository();
        $this->db          = DB::connect();
    }

    /*
    |--------------------------------------------------------------------------
    | ISSUE BADGE (Transaction Safe)
    |--------------------------------------------------------------------------
    */
    public function issue(
        int $tenantId,
        int $visitId
    ): string {

        $visit = $this->visitRepo->find($tenantId, $visitId);

        if (!$visit) {
            throw new Exception('Visit not found.');
        }

        if (!$visit['checkin_time']) {
            throw new Exception('Visitor must be checked in first.');
        }

        if ($visit['checkout_time']) {
            throw new Exception('Cannot issue badge after checkout.');
        }

        $visitor = $this->visitorRepo->find(
            $tenantId,
            (int) $visit['visitor_id']
        );

        if ((int) $visitor['is_blacklisted'] === 1) {
            throw new Exception('Blacklisted visitors cannot receive badges.');
        }

        $badgeCode = $this->generateBadgeCode();

        $this->db->beginTransaction();

        try {

            $this->badgeRepo->issue(
                $tenantId,
                $visitId,
                $badgeCode
            );

            Audit::log(
                'visit.badge_issued',
                'visit',
                $visitId,
                [
                    'tenant_id' => $tenantId,
                    'badge_code'=> $badgeCode
                ]
            );

            $this->db->commit();

            return $badgeCode;

        } catch (\Throwable $e) {

            $this->db->rollBack();
            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RETURN BADGE
    |--------------------------------------------------------------------------
    */
    public function returnBadge(
        int $tenantId,
        int $visitId
    ): void {

        $this->badgeRepo->returnActiveBadge(
            $tenantId,
            $visitId
        );

        Audit::log(
            'visit.badge_returned',
            'visit',
            $visitId,
            ['tenant_id' => $tenantId]
        );
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE BADGE CODE
    |--------------------------------------------------------------------------
    */
    private function generateBadgeCode(): string
    {
        return 'BDG-' . strtoupper(bin2hex(random_bytes(4)));
    }
}