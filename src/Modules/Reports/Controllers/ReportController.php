<?php

namespace App\Modules\Reports\Controllers;

use App\Core\Controller;
use App\Core\View;
use App\Core\Request;
use App\Modules\Reports\Services\ReportService;

class ReportController extends Controller
{
    private ReportService $service;

    public function __construct()
    {
        $this->service = new ReportService();
    }

    private function tenantId(): int
    {
        return (int) $_SESSION['user']['tenant_id'];
    }

    public function index()
{
    $tenantId = $this->tenantId();

    $summary = $this->service->summary($tenantId);

    return View::render('Reports::index', [
        'title'   => 'Reports Dashboard',
        'summary' => $summary
    ], 'app');
}
    public function gatepasses(Request $request)
    {
        $data = $this->service->gatepasses(
            $this->tenantId(),
            $request->all()
        );

        return View::render('Reports::gatepasses', [
            'title'        => 'Gatepass Report',
            'data'         => $data,
            'export_perm'  => 'reports.export_gatepasses'
        ], 'app');
    }

    public function exportGatepasses(Request $request)
    {
        $result = $this->service->gatepasses(
            $this->tenantId(),
            $this->exportParams($request)
        );

        $this->streamCsv('gatepasses', $result['data']);
    }

    public function visitors(Request $request)
    {
        $data = $this->service->visitors(
            $this->tenantId(),
            $request->all()
        );

        return View::render('Reports::visitors', [
            'title'        => 'Visitor Report',
            'data'         => $data,
            'export_perm'  => 'reports.export_visitors'
        ], 'app');
    }

    public function exportVisitors(Request $request)
    {
        $result = $this->service->visitors(
            $this->tenantId(),
            $this->exportParams($request)
        );

        $this->streamCsv('visitors', $result['data']);
    }

    public function visits(Request $request)
    {
        $data = $this->service->visits(
            $this->tenantId(),
            $request->all()
        );

        return View::render('Reports::visits', [
            'title'        => 'Visit Report',
            'data'         => $data,
            'export_perm'  => 'reports.export_visits'
        ], 'app');
    }

    public function occupancy()
    {
        return View::render('Reports::occupancy', [
            'title'       => 'Occupancy',
            'data'        => $this->service->occupancy($this->tenantId()),
            'export_perm' => 'reports.export_occupancy',
        ], 'app');
    }

    public function exportOccupancy()
    {
        $this->streamCsv('occupancy', $this->service->occupancyExportRows($this->tenantId()));
    }

    public function scanActivity(Request $request)
    {
        $data = $this->service->scans(
            $this->tenantId(),
            $request->all()
        );

        return View::render('Reports::scans', [
            'title'          => 'Scan Activity',
            'data'           => $data,
            'filters'        => $this->service->scanFilterOptions($this->tenantId()),
            'export_perm'    => 'reports.export_scans',
        ], 'app');
    }

    public function exportScanActivity(Request $request)
    {
        $result = $this->service->scans(
            $this->tenantId(),
            $this->exportParams($request)
        );

        $this->streamCsv('scan-activity', $result['data']);
    }

    public function departments()
    {
        return View::render('Reports::departments', [
            'title'       => 'Department Activity',
            'rows'        => $this->service->departments($this->tenantId()),
            'export_perm' => 'reports.export_departments',
        ], 'app');
    }

    public function exportDepartments()
    {
        $this->streamCsv('department-activity', $this->service->departments($this->tenantId()));
    }

    public function exportVisits(Request $request)
    {
        $result = $this->service->visits(
            $this->tenantId(),
            $this->exportParams($request)
        );

        $this->streamCsv('visits', $result['data']);
    }

    public function auditLogs(Request $request)
    {
        $data = $this->service->auditLogs(
            $this->tenantId(),
            $request->all()
        );

        return View::render('Reports::audit', [
            'title'        => 'Audit Logs',
            'data'         => $data,
            'export_perm'  => 'audit.export'
        ], 'app');
    }

    public function exportAuditLogs(Request $request)
    {
        $result = $this->service->auditLogs(
            $this->tenantId(),
            $this->exportParams($request)
        );

        $this->streamCsv('audit-logs', $result['data']);
    }

    /* =========================================================
     * EXPORT HELPERS
     * ========================================================= */

    private function exportParams(Request $request): array
    {
        return array_merge($request->all(), [
            'page'     => 1,
            'per_page' => 10000
        ]);
    }

    private function streamCsv(string $filenameBase, array $rows): never
    {
        $headers = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                $headers[$key] = true;
            }
        }
        $columns = array_keys($headers);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        $filename = $filenameBase . '-' . date('Y-m-d') . '.csv';
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $out = fopen('php://output', 'w');

        if ($columns !== []) {
            fputcsv($out, $columns);
        }

        foreach ($rows as $row) {
            $line = array_map(function ($key) use ($row) {
                $value = $row[$key] ?? '';

                if (is_array($value)) {
                    $value = json_encode($value);
                }

                $value = (string) $value;

                // Spreadsheet formula injection guard
                if ($value !== '' && str_contains('=+-@', $value[0])) {
                    $value = "'" . $value;
                }

                return $value;
            }, $columns);

            fputcsv($out, $line);
        }

        fclose($out);
        exit;
    }
}