<?php

declare(strict_types=1);

namespace App\Modules\Visits\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\View;
use App\Core\Response;
use App\Modules\Visits\Services\VisitService;
use App\Modules\Visits\DTOs\VisitDTO;
use RuntimeException;

final class VisitController extends Controller
{
    private VisitService $service;

    public function __construct()
    {
        $this->service = new VisitService();
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
    | INDEX
    |--------------------------------------------------------------------------
    */
    public function index(): void
    {
        $user = $this->user();

        $visits = $this->service->activeVisits($user['tenant_id']);

        View::render('Visits::index', [
            'visits' => $visits
        ], 'app');
    }

    /*
    |--------------------------------------------------------------------------
    | STORE
    |--------------------------------------------------------------------------
    */

public function create(Request $request): void
{
    $user = $this->user();
    $tenantId = (int) $user['tenant_id'];

    // Get visitor_id from query string
    $visitorId = (int) $request->input('visitor_id');

    $visitor = null;

    if ($visitorId > 0) {
        $visitor = $this->service->getVisitor($tenantId, $visitorId);

        if (!$visitor) {
            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => 'Visitor not found.'
            ];

            header('Location: /visitors');
            exit;
        }
    }

    View::render('Visits::create', [
        'visitor'     => $visitor,
        'departments' => $this->service->getDepartments($tenantId),
        'hosts'       => $this->service->getHosts($tenantId),
        'visitTypes'  => $this->service->getVisitTypes($tenantId),
    ], 'app');
}

    public function store(Request $request): void
    {
        $user = $this->user();

        try {

            $dto = VisitDTO::fromArray(
                $request->all(),
                $user['tenant_id'],
                $user['id']
            );

            $this->service->create($dto);

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Visit created successfully.'
            ];

        } catch (RuntimeException $e) {

            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => $e->getMessage()
            ];
        }

        header('Location: /visits');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK IN
    |--------------------------------------------------------------------------
    */
    public function checkIn(Request $request, int $id): void
    {
        $user = $this->user();

        try {

            $this->service->checkIn(
                $user['tenant_id'],
                $id
            );

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Visitor checked in successfully.'
            ];

        } catch (RuntimeException $e) {

            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => $e->getMessage()
            ];
        }

        header('Location: /visits');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK OUT
    |--------------------------------------------------------------------------
    */
    public function checkOut(Request $request, int $id): void
    {
        $user = $this->user();

        try {

            $this->service->checkOut(
                $user['tenant_id'],
                $id
            );

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Visitor checked out successfully.'
            ];

        } catch (RuntimeException $e) {

            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => $e->getMessage()
            ];
        }

        header('Location: /visits');
        exit;
    }
}