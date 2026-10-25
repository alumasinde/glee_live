<?php

declare(strict_types=1);

namespace App\Modules\Audit\Controllers;

use App\Core\Controller;
use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\Tenant;
use App\Modules\Audit\Repositories\AuditLogRepository;
use App\Modules\Audit\Services\AuditService;

class AuditController extends Controller
{
    private AuditService $service;

    public function __construct()
    {
        $this->service = new AuditService(new AuditLogRepository(DB::connect()));
    }

    private function tenantId(): int
    {
        $tenantId = Tenant::id();

        if (!$tenantId) {
            Response::abort(403);
        }

        return (int) $tenantId;
    }

    /*
    |--------------------------------------------------------------------------
    | AUDIT TRAIL  GET /audit
    |--------------------------------------------------------------------------
    */
    public function index(Request $request)
    {
        $query  = $request->all();
        $result = $this->service->listLogs($this->tenantId(), $query);

        return $this->view('Audit::index', [
            'title'    => 'Audit Trail',
            'entries'  => $result['data'],
            'meta'     => $result['meta'],
            'options'  => $this->service->filterOptions($this->tenantId()),
            'filters'  => [
                'action'      => trim((string) ($query['action'] ?? '')),
                'entity_type' => trim((string) ($query['entity_type'] ?? '')),
                'entity_id'   => (int) ($query['entity_id'] ?? 0),
                'user_id'     => (int) ($query['user_id'] ?? 0),
                'date_from'   => trim((string) ($query['date_from'] ?? '')),
                'date_to'     => trim((string) ($query['date_to'] ?? '')),
            ],
            'search'   => trim((string) ($query['search'] ?? $query['q'] ?? '')),
            'sort_by'  => trim((string) ($query['sort_by'] ?? 'created_at')),
            'sort_dir' => trim((string) ($query['sort_dir'] ?? 'desc')),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | SINGLE ENTRY + METADATA  GET /audit/{id}
    |--------------------------------------------------------------------------
    */
    public function show(Request $request, int $id)
    {
        $entry = $this->service->detail($this->tenantId(), $id);

        if (!$entry) {
            Response::abort(404, 'Audit entry not found.');
        }

        return $this->view('Audit::show', [
            'title' => 'Audit Entry #' . $id,
            'entry' => $entry,
        ]);
    }
}
