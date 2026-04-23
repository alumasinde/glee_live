<?php

namespace App\Modules\Audit\Controllers;

use App\Core\DB;
use App\Modules\Audit\Repositories\AuditLogRepository;
use App\Modules\Audit\Services\AuditService;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * AuditController
 *
 * Exposes read-only endpoints for audit log inspection.
 * All routes must sit behind auth + admin/manager role middleware.
 *
 * Routes (register in your router):
 *   GET  /audit              → index()   – paginated list with filters
 *   GET  /audit/{id}         → show()    – single entry detail
 *   GET  /audit/export       → export()  – CSV download
 */
class AuditController
{
    private AuditService $service;

    public function __construct()
    {
        $db            = DB::connect();
        $repo          = new AuditLogRepository($db);
        $this->service = new AuditService($repo);
    }

    // -------------------------------------------------------------------------
    // GET /audit
    // -------------------------------------------------------------------------

    /**
     * Return a paginated, filterable list of audit log entries.
     *
     * Query params:
     *   page        int    (default 1)
     *   per_page    int    (default 50, max 200)
     *   user_id     int
     *   action      string  e.g. "user.login"
     *   entity_type string  e.g. "gatepass"
     *   entity_id   int
     *   date_from   string  Y-m-d
     *   date_to     string  Y-m-d
     */
    public function index(Request $request, Response $response): Response
    {
        $tenantId = (int) $request->getAttribute('tenant_id');
        $params   = $request->getQueryParams();

        $perPage = min((int) ($params['per_page'] ?? 50), 200);
        $page    = max((int) ($params['page']     ?? 1), 1);
        $offset  = ($page - 1) * $perPage;

        $filters = $this->extractFilters($params);
        $result  = $this->service->getLogs($tenantId, $filters, $perPage, $offset);

        $totalPages = $perPage > 0 ? (int) ceil($result['total'] / $perPage) : 1;

        return $this->json($response, [
            'data' => $result['items'],
            'meta' => [
                'total'       => $result['total'],
                'page'        => $page,
                'per_page'    => $perPage,
                'total_pages' => $totalPages,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /audit/{id}
    // -------------------------------------------------------------------------

    /**
     * Return full detail for a single audit log entry.
     * Metadata JSON is decoded so the client receives a proper object.
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $tenantId = (int) $request->getAttribute('tenant_id');
        $id       = (int) $args['id'];

        // Reuse findByTenant with an entity_id filter is impractical for PK lookup,
        // so we do a direct query here. The tenant_id guard prevents cross-tenant leakage.
        $db   = DB::connect();
        $stmt = $db->prepare("
            SELECT
                al.*,
                u.name  AS user_name,
                u.email AS user_email
            FROM audit_logs al
            LEFT JOIN users u ON u.id = al.user_id AND u.tenant_id = al.tenant_id
            WHERE al.id = :id AND al.tenant_id = :tenant_id
            LIMIT 1
        ");
        $stmt->execute([':id' => $id, ':tenant_id' => $tenantId]);
        $entry = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$entry) {
            return $this->json($response, ['error' => 'Audit log entry not found'], 404);
        }

        $entry['metadata'] = $entry['metadata'] ? json_decode($entry['metadata'], true) : null;

        return $this->json($response, ['data' => $entry]);
    }

    // -------------------------------------------------------------------------
    // GET /audit/export
    // -------------------------------------------------------------------------

    /**
     * Stream a CSV export of filtered audit logs (up to 10,000 rows).
     *
     * Accepts the same filter query params as index().
     */
    public function export(Request $request, Response $response): Response
    {
        $tenantId = (int) $request->getAttribute('tenant_id');
        $params   = $request->getQueryParams();
        $filters  = $this->extractFilters($params);

        $result = $this->service->getLogs($tenantId, $filters, 10000, 0);

        $body = $response->getBody();

        // CSV headers
        $columns = ['id', 'created_at', 'user_name', 'user_email', 'action', 'message',
                    'entity_type', 'entity_id', 'ip_address'];

        ob_start();
        $handle = fopen('php://output', 'w');
        fputcsv($handle, $columns);

        foreach ($result['items'] as $row) {
            fputcsv($handle, array_map(fn($col) => $row[$col] ?? '', $columns));
        }

        fclose($handle);
        $csv = ob_get_clean();
        $body->write($csv);

        $filename = 'audit-logs-' . date('Y-m-d') . '.csv';

        return $response
            ->withHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->withHeader('Content-Disposition', "attachment; filename=\"{$filename}\"")
            ->withBody($body);
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    private function extractFilters(array $params): array
    {
        $allowed = ['user_id', 'action', 'entity_type', 'entity_id', 'date_from', 'date_to'];
        $filters = [];

        foreach ($allowed as $key) {
            if (isset($params[$key]) && $params[$key] !== '') {
                $filters[$key] = $params[$key];
            }
        }

        return $filters;
    }

    private function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(
            json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        return $response
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }
}