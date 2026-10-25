<?php

declare(strict_types=1);

namespace App\Modules\Badges\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Badges\Services\BadgeService;

final class BadgeController extends Controller
{
    /** Screens an operator may be sent back to after an issue/return POST. */
    private const RETURN_TO = ['/badges', '/visits'];

    private BadgeService $service;

    public function __construct()
    {
        $this->service = new BadgeService();
    }

    /*
    |--------------------------------------------------------------------------
    | AUTH USER
    |--------------------------------------------------------------------------
    */
    private function user(): array
    {
        if (!isset($_SESSION['user'])) {
            Response::abort(403);
        }

        return $_SESSION['user'];
    }

    /*
    |--------------------------------------------------------------------------
    | LIST BADGES  GET /badges
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $user  = $this->user();
        $query = $request->all();

        $result = $this->service->list((int) $user['tenant_id'], $query);

        return $this->view('Badges::index', [
            'title'    => 'Badge Operations',
            'badges'   => $result['data'],
            'meta'     => $result['meta'],
            'filters'  => [
                'status'     => trim((string) ($query['status'] ?? '')),
                'visit_id'   => (int) ($query['visit_id'] ?? 0),
                'gatepass_id'=> (int) ($query['gatepass_id'] ?? 0),
                'holder_id'  => (int) ($query['holder_id'] ?? 0),
            ],
            'search'   => trim((string) ($query['q'] ?? $query['search'] ?? '')),
            'holders'  => $this->service->filterOptions((int) $user['tenant_id'])['holders'],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ISSUANCE FORM  GET /badges/create
    |--------------------------------------------------------------------------
    */
    public function create(Request $request)
    {
        $user    = $this->user();
        $visitId = (int) $request->input('visit_id', 0);

        $context = $this->service->issuanceContext((int) $user['tenant_id'], $visitId ?: null);

        return $this->view('Badges::create', [
            'title'      => 'Issue Visitor Badge',
            'candidates' => $context['candidates'],
            'visit'      => $context['visit'],
            'badgeCode'  => '',
            'visitId'    => $visitId,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | STORE BADGE (from the issuance form)  POST /badges
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $user     = $this->user();
        $tenantId = (int) $user['tenant_id'];

        $visitId   = (int) $request->input('visit_id', 0);
        $badgeCode = trim((string) $request->input('badge_code', ''));

        try {
            if ($visitId <= 0) {
                throw new \Exception('Select the visit the badge is being issued against.');
            }

            $code = $this->service->issue($tenantId, $visitId, $badgeCode !== '' ? $badgeCode : null);

            $_SESSION['flash'] = [
                'type'    => 'success',
                'message' => "Badge issued successfully. Code: {$code}",
            ];

            return $this->redirect('/badges');

        } catch (\Throwable $e) {

            $context = $this->service->issuanceContext($tenantId, $visitId ?: null);

            return $this->view('Badges::create', [
                'title'      => 'Issue Visitor Badge',
                'error'      => $e->getMessage(),
                'candidates' => $context['candidates'],
                'visit'      => $context['visit'],
                'badgeCode'  => $badgeCode,
                'visitId'    => $visitId,
            ]);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | PER-VISIT ISSUANCE SCREEN  GET /badges/{visitId}/issue
    |--------------------------------------------------------------------------
    */
    public function issueForm(Request $request, int $visitId)
    {
        $user  = $this->user();
        $visit = $this->service->findVisit((int) $user['tenant_id'], $visitId);

        if (!$visit) {
            Response::abort(404, 'Visit not found.');
        }

        $visitor = $this->service->findVisitor(
            (int) $user['tenant_id'],
            (int) $visit['visitor_id']
        );

        return $this->view('Badges::issue-badge', [
            'title'   => 'Issue Visitor Badge',
            'visit'   => $visit,
            'visitor' => $visitor ?? [],
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | ISSUE BADGE  POST /badges/{visitId}/issue
    |--------------------------------------------------------------------------
    */
    public function issue(Request $request, int $visitId): void
    {
        $user = $this->user();

        try {

            $badgeCode = $this->service->issue(
                (int) $user['tenant_id'],
                $visitId,
                trim((string) $request->input('badge_code', '')) ?: null
            );

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => "Badge issued successfully. Code: {$badgeCode}"
            ];

        } catch (\Throwable $e) {

            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => $e->getMessage()
            ];
        }

        $this->redirect($this->returnTo($request));
    }

    /*
    |--------------------------------------------------------------------------
    | RETURN BADGE  POST /badges/{visitId}/return
    |--------------------------------------------------------------------------
    */
    public function return(Request $request, int $visitId): void
    {
        $user = $this->user();

        try {

            $this->service->returnBadge(
                (int) $user['tenant_id'],
                $visitId
            );

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Badge returned successfully.'
            ];

        } catch (\Throwable $e) {

            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => $e->getMessage()
            ];
        }

        $this->redirect($this->returnTo($request));
    }

    /*
    |--------------------------------------------------------------------------
    | WHERE DO WE LAND AFTER A POST? (whitelisted — never trust user input)
    |--------------------------------------------------------------------------
    */
    private function returnTo(Request $request): string
    {
        $target = trim((string) $request->input('redirect_to', ''));

        return in_array($target, self::RETURN_TO, true) ? $target : '/visits';
    }
}
