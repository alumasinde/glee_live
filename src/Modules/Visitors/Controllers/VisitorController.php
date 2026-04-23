<?php

declare(strict_types=1);

namespace App\Modules\Visitors\Controllers;

use App\Core\View;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Visitors\Services\VisitorService;
use App\Modules\Visitors\DTOs\VisitorDTO;

final class VisitorController
{
    private VisitorService $service;

    public function __construct()
    {
        $this->service = new VisitorService();
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
    | LIST VISITORS
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $user = $this->user();

        $visitors = $this->service->list((int) $user['tenant_id']);

        return View::render('Visitors::index', [
            'visitors' => $visitors
        ], 'app');
    }

    /*
    |--------------------------------------------------------------------------
    | SHOW CREATE FORM
    |--------------------------------------------------------------------------
    */
    public function create(Request $request)
    {
        $user = $this->user();

        return View::render('Visitors::create', [
            'idTypes'   => $this->service->getIdentificationTypes((int) $user['tenant_id']),
            'companies' => $this->service->getCompanies((int) $user['tenant_id'])
        ], 'app');
    }

    /*
    |--------------------------------------------------------------------------
    | STORE VISITOR
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $user = $this->user();

        try {

            $data = $request->all();
            $data['created_by'] = (string) $user['id'];

            $dto = VisitorDTO::fromArray(
                $data,
                (int) $user['tenant_id']
            );

            $this->service->create($dto);

            $_SESSION['flash'] = [
                'type'    => 'success',
                'message' => 'Visitor created successfully.'
            ];

            header('Location: /visitors');
            exit;

        } catch (\Throwable $e) {

            return View::render('Visitors::create', [
                'error'     => $e->getMessage(),
                'idTypes'   => $this->service->getIdentificationTypes((int) $user['tenant_id']),
                'companies' => $this->service->getCompanies((int) $user['tenant_id'])
            ], 'app');
        }
    }

//SHOW EDIT FORM

public function edit(Request $request, int $id)
{
    $user = $this->user();
    $tenantId = (int)$user['tenant_id'];

    $visitor = $this->service->find($tenantId, $id);

    if (!$visitor) {
        Response::abort(404);
    }

    return View::render('Visitors::edit', [
        'visitor'  => $visitor,
        'idTypes'  => $this->service->getIdentificationTypes($tenantId),
        'companies'=> $this->service->getCompanies($tenantId),
    ], 'app');
}

//UPDATE VISITOR
public function update(Request $request, int $id)
{
    $user = $this->user();
    $tenantId = (int) $user['tenant_id'];

    try {

        $data = $request->all();

        $dto = VisitorDTO::fromArray(
            $data,
            $tenantId
        );

        $this->service->update($tenantId, $id, $dto);

        $_SESSION['flash'] = [
            'type'    => 'success',
            'message' => 'Visitor updated successfully.'
        ];

        header('Location: /visitors/' . $id);
        exit;

    } catch (\Throwable $e) {

        $_SESSION['flash'] = [
            'type'    => 'danger',
            'message' => $e->getMessage()
        ];

        header('Location: /visitors/' . $id . '/edit');
        exit;
    }
}
public function view(Request $request, int $id): void
{
    $user = $this->user();

    $visitor = $this->service->findWithVisits(
        $user['tenant_id'],
        $id
    );

    if (!$visitor) {
        http_response_code(404);
        echo "Visitor not found";
        return;
    }

    View::render('Visitors::view', [
        'visitor' => $visitor
    ], 'app');
}


// BlackList and Unblacklist
    public function blacklist(Request $request, int $visitorId): void
    {
        $user = $this->user();

        try {

            $this->service->blacklist(
                (int) $user['tenant_id'],
                $visitorId
            );

            $_SESSION['flash'] = [
                'type' => 'warning',
                'message' => 'Visitor has been blacklisted.'
            ];

        } catch (\Throwable $e) {

            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => $e->getMessage()
            ];
        }

        header('Location: /visitors');
        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | UNBLACKLIST VISITOR
    |--------------------------------------------------------------------------
    */
    public function unblacklist(Request $request, int $visitorId): void
    {
        $user = $this->user();

        try {

            $this->service->unblacklist(
                (int) $user['tenant_id'],
                $visitorId
            );

            $_SESSION['flash'] = [
                'type' => 'success',
                'message' => 'Visitor removed from blacklist.'
            ];

        } catch (\Throwable $e) {

            $_SESSION['flash'] = [
                'type' => 'danger',
                'message' => $e->getMessage()
            ];
        }

        header('Location: /visitors');
        exit;
    }
}