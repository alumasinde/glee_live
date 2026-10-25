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
    /** Custom badge codes: readable, bounded, path-safe. */
    private const CODE_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9_-]{2,59}$/';

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
    | LIST BADGES (paginated, tenant scoped)
    |--------------------------------------------------------------------------
    */
    public function list(int $tenantId, array $params = []): array
    {
        return $this->badgeRepo->list($tenantId, $params);
    }

    /**
     * Option maps for the index filter bar.
     *
     * @return array{holders: array}
     */
    public function filterOptions(int $tenantId): array
    {
        return [
            'holders' => $this->badgeRepo->holderOptions($tenantId),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | ISSUANCE SCREEN CONTEXT (create form + per-visit form)
    |--------------------------------------------------------------------------
    */
    public function issuanceContext(int $tenantId, ?int $visitId = null): array
    {
        $context = [
            'candidates' => $this->badgeRepo->candidateVisits($tenantId),
            'visit'      => null,
        ];

        if ($visitId !== null && $visitId > 0) {
            $context['visit'] = $this->badgeRepo->visitForIssuance($tenantId, $visitId);
        }

        return $context;
    }

    /**
     * Single visit + holder snapshot for the issue-badge screen.
     */
    public function findVisit(int $tenantId, int $visitId): ?array
    {
        return $this->badgeRepo->visitForIssuance($tenantId, $visitId);
    }

    /**
     * Holder record (risk + blacklist visible on the issuance screen).
     */
    public function findVisitor(int $tenantId, int $visitorId): ?array
    {
        return $this->visitorRepo->find($tenantId, $visitorId);
    }

    /*
    |--------------------------------------------------------------------------
    | ISSUE BADGE (Transaction Safe)
    |--------------------------------------------------------------------------
    |
    | $badgeCode is optional: the form may carry a hand-written code (e.g. the
    | physical badge number printed on the card), otherwise a code is generated.
    */
    public function issue(
        int $tenantId,
        int $visitId,
        ?string $badgeCode = null
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

        if (!$visitor) {
            throw new Exception('Visitor record not found for this visit.');
        }

        if ((int) $visitor['is_blacklisted'] === 1) {
            throw new Exception('Blacklisted visitors cannot receive badges.');
        }

        if ($this->badgeRepo->hasActiveBadge($tenantId, $visitId)) {
            throw new Exception('This visit already has a badge out. Return it before issuing another.');
        }

        $badgeCode = $this->resolveBadgeCode($tenantId, $badgeCode);

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
                    'tenant_id'    => $tenantId,
                    'badge_code'   => $badgeCode,
                    'holder_id'    => (int) $visitor['id'],
                    'holder_name'  => trim((string) ($visitor['first_name'] ?? ''))
                        . ' ' . trim((string) ($visitor['last_name'] ?? '')),
                    'target_label' => $badgeCode,
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

        $badge = $this->badgeRepo->findActiveByVisit($tenantId, $visitId);

        if (!$badge) {
            throw new Exception('No active badge is out for this visit.');
        }

        $this->db->beginTransaction();

        try {

            $this->badgeRepo->returnActiveBadge(
                $tenantId,
                $visitId
            );

            Audit::log(
                'visit.badge_returned',
                'visit',
                $visitId,
                [
                    'tenant_id'    => $tenantId,
                    'badge_code'   => $badge['badge_code'],
                    'target_label' => $badge['badge_code'],
                ]
            );

            $this->db->commit();

        } catch (\Throwable $e) {

            $this->db->rollBack();
            throw $e;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | RESOLVE CODE (validate + de-duplicate)
    |--------------------------------------------------------------------------
    */
    private function resolveBadgeCode(int $tenantId, ?string $badgeCode): string
    {
        $requested = strtoupper(trim((string) $badgeCode));

        if ($requested === '') {
            return $this->generateBadgeCode($tenantId);
        }

        if (preg_match(self::CODE_PATTERN, $requested) !== 1) {
            throw new Exception(
                'Invalid badge code. Use 3-60 letters, digits, hyphens or underscores.'
            );
        }

        if ($this->badgeRepo->badgeCodeExists($tenantId, $requested)) {
            throw new Exception("Badge code {$requested} is already in use.");
        }

        return $requested;
    }

    /*
    |--------------------------------------------------------------------------
    | GENERATE BADGE CODE (unique per tenant)
    |--------------------------------------------------------------------------
    */
    private function generateBadgeCode(int $tenantId): string
    {
        // uk_vb_tenant_code is unique per tenant, so retry before inserting.
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $code = 'BDG-' . strtoupper(bin2hex(random_bytes(4)));

            if (!$this->badgeRepo->badgeCodeExists($tenantId, $code)) {
                return $code;
            }
        }

        throw new Exception('Could not find a free badge code. Issue one manually.');
    }
}
