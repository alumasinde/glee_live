<?php

namespace App\Modules\Gatepass\Controllers;

use App\Core\Audit;
use App\Core\Controller;
use App\Core\DB;
use App\Core\Permission;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Gatepass\DTOs\GatepassDTO;
use App\Modules\Gatepass\Policies\GatepassPolicy;
use App\Modules\Gatepass\Repositories\GatepassTypeRepository;
use App\Modules\Gatepass\Services\GatepassService;
use InvalidArgumentException;
use RuntimeException;

class GatepassController extends Controller
{
    private GatepassService $service;
    private GatepassPolicy $policy;
    private GatepassTypeRepository $typeRepo;

    public function __construct()
    {
        $this->service  = new GatepassService();
        $this->policy   = new GatepassPolicy(new Permission(DB::connect()));
        $this->typeRepo = new GatepassTypeRepository();
    }

    private function user(): array
    {
        if (!isset($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }

        return $_SESSION['user'];
    }

    private function findGatepassOrFail(int $tenantId, mixed $id): array
    {
        $id = (int) $id;

        if ($id <= 0) {
            Response::abort(400, 'Invalid gatepass ID.');
        }

        $gatepass = $this->service->find($tenantId, $id);

        if (!$gatepass) {
            Response::abort(404, 'Gatepass not found.');
        }

        return $gatepass;
    }

    /* =========================================================
     * INDEX
     * ========================================================= */

    public function index(Request $request): mixed
    {
        $user   = $this->user();
        $search = trim((string) $request->input('q', ''));

        $gatepasses = $this->service->list(
            $user['tenant_id'],
            $user['id'],
            $search !== '' ? $search : null
        );

        foreach ($gatepasses as &$g) {
            $actions = $this->service->getAvailableActions($g);
            $g['can_checkin']  = $actions['can_checkin'];
            $g['can_checkout'] = $actions['can_checkout'];
            $g['can_edit']     = $this->policy->update($user, $g);
        }
        unset($g);

        Audit::log('gatepass.list_viewed', 'gatepass', null);

        View::render('Gatepass::index', [
            'title'      => 'Gatepasses',
            'gatepasses' => $gatepasses,
            'search'     => $search,
        ], 'app');

        return null;
    }

    /* =========================================================
     * CREATE
     * ========================================================= */

    public function create(): mixed
    {
        $user = $this->user();

        if (!$this->policy->create()) {
            Response::abort(403);
        }

        $visits = $this->service->getVisitsForTenant(
            $user['tenant_id'],
            $user['department_id'] ?? null
        );

        $types = $this->typeRepo->findAllByTenant($user['tenant_id']);

        View::render('Gatepass::create', [
            'title'  => 'Create Gatepass',
            'visits' => $visits,
            'types'  => $types,
        ], 'app');

        return null;
    }

    public function store(Request $request): never
    {
        $user = $this->user();

        if (!$this->policy->create()) {
            Response::abort(403);
        }

        try {
            $dto = new GatepassDTO(
                tenant_id: $user['tenant_id'],
                visit_id: (int) $request->input('visit_id'),
                gatepass_type_id: (int) $request->input('gatepass_type_id'),
                purpose: trim((string) $request->input('purpose', '')),
                is_returnable: (bool) $request->input('is_returnable'),
                expected_return_date: $request->input('expected_return_date'),
                needs_approval: (bool) $request->input('needs_approval'),
                created_by: $user['id'],
                items: $request->input('items', [])
            );

            $gatepassId = $this->service->create($dto);

            // create() already audit-logs gatepass.created with the real
            // gatepass number inside its transaction — logging here too
            // wrote two rows per pass.

            $_SESSION['flash'] = ['message' => 'Gatepass created successfully.', 'type' => 'success'];

        } catch (RuntimeException | InvalidArgumentException $e) {
            $_SESSION['flash'] = ['message' => $e->getMessage(), 'type' => 'danger'];
            header('Location: /gatepasses/create');
            exit;

        } catch (\Throwable $e) {
            error_log('Gatepass create failed: ' . $e->getMessage());

            $_SESSION['flash'] = ['message' => 'The gatepass could not be saved.', 'type' => 'danger'];
            header('Location: /gatepasses/create');
            exit;
        }

        header('Location: /gatepasses');
        exit;
    }

    /* =========================================================
     * SHOW
     * ========================================================= */

    public function show(Request $request, mixed $id): mixed
    {
        $user     = $this->user();
        $gatepass = $this->findGatepassOrFail($user['tenant_id'], $id);

        Audit::log(
            'gatepass.viewed',
            'gatepass',
            (int) $gatepass['id'],
            [
                'target_label' => $gatepass['gatepass_number'] ?? 'Unknown'
            ]
        );

        if (!$this->policy->view($user, $gatepass)) {
            Response::abort(403);
        }

        // Anyone allowed to open the pass can carry it to the gate, so the QR
        // follows pass visibility instead of a separate print permission.
        View::render('Gatepass::show', [
            'title'       => 'View Gatepass',
            'gatepass'    => $gatepass,
            'items'          => $gatepass['items'] ?? [],
            'approval_trail' => $this->service->approvalTrail((int) $user['tenant_id'], (int) $gatepass['id']),
            'can_edit'       => $this->policy->update($user, $gatepass),
            'qr_data_uri'    => \App\Core\QrCode::svgDataUri((string) $gatepass['scan_token']),
        ], 'app');

        return null;
    }

  public function edit(Request $request, int $id): void
{
    $user     = $this->user();
    $gatepass = $this->findGatepassOrFail($user['tenant_id'], $id);

    if (!$this->policy->update($user, $gatepass)) {
        Response::abort(403);
    }

    View::render('Gatepass::edit', [
        'title'    => 'Edit Gatepass',
        'gatepass' => $gatepass,
        'items'    => $gatepass['items'] ?? [],
    ], 'app');
}

public function update(Request $request, int $id): void
    {
        $user     = $this->user();
        $gatepass = $this->findGatepassOrFail($user['tenant_id'], $id);

        if (!$this->policy->update($user, $gatepass)) {
            Response::abort(403);
        }

        try {
            $dto = new GatepassDTO(
                tenant_id: $user['tenant_id'],
                visit_id: (int) $request->input('visit_id'),
                gatepass_type_id: (int) $request->input('gatepass_type_id'),
                purpose: trim((string) $request->input('purpose', '')),
                is_returnable: (bool) $request->input('is_returnable'),
                expected_return_date: $request->input('expected_return_date'),
                needs_approval: (bool) $request->input('needs_approval'),
                created_by: $gatepass['created_by'],
                items: $request->input('items', [])
            );

            $this->service->update($user['tenant_id'], (int) $id, $dto);

            Audit::log(
                'gatepass.updated',
                'gatepass',
                (int) $id,
                [
                    'target_label' => $gatepass['gatepass_number'] ?? 'Unknown'
                ]
            );

        } catch (InvalidArgumentException $e) {
            View::render('Gatepass::edit', [
                'title'    => 'Edit Gatepass',
                'error'    => $e->getMessage(),
                'gatepass' => $gatepass,
                'items'    => $gatepass['items'] ?? [],
            ], 'app');
       
        }

        header('Location: /gatepasses');
        exit;
    }

    /* =========================================================
     * CHECK IN / OUT
     * ========================================================= */

    public function checkIn(Request $request, int $id): never
    {
        $user = $this->user();

        // Keeps the 404 for a missing pass; checkIn() writes the audit row.
        $this->findGatepassOrFail($user['tenant_id'], $id);

        $this->service->checkIn($user['tenant_id'], $id, $user['id']);

        $_SESSION['flash'] = ['message' => 'Gatepass checked in successfully.', 'type' => 'success'];

        header('Location: /gatepasses');
        exit;
    }

    public function checkOut(Request $request, int $id): never
    {
        $user = $this->user();

        $this->findGatepassOrFail($user['tenant_id'], $id);

        $this->service->checkOut($user['tenant_id'], $id, $user['id']);

        $_SESSION['flash'] = ['message' => 'Gatepass checked out successfully.', 'type' => 'success'];

        header('Location: /gatepasses');
        exit;
    }

    /* =========================================================
     * DELETE
     * ========================================================= */

    public function delete(Request $request, int $id): never
    {
        $user = $this->user();

        if (!$this->policy->delete()) {
            Response::abort(403);
        }

        $this->findGatepassOrFail($user['tenant_id'], $id);

        try {
            // delete() writes the audit row itself, inside its transaction.
            $this->service->delete($user['tenant_id'], $id);
        } catch (RuntimeException | InvalidArgumentException $e) {
            $_SESSION['flash'] = ['message' => $e->getMessage(), 'type' => 'danger'];

            header('Location: /gatepasses/' . (int) $id);
            exit;
        }

        $_SESSION['flash'] = ['message' => 'Gatepass deleted successfully.', 'type' => 'success'];

        header('Location: /gatepasses');
        exit;
    }
}
