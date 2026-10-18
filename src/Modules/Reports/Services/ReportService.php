<?php

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Repositories\GatepassReportRepository;
use App\Modules\Reports\Repositories\OccupancyReportRepository;
use App\Modules\Reports\Repositories\VisitorReportRepository;
use App\Modules\Reports\Repositories\VisitReportRepository;
use App\Modules\Reports\Repositories\AuditReportRepository;
use App\Modules\Reports\Repositories\ScanLogReportRepository;
use App\Modules\Reports\Repositories\DepartmentReportRepository;

class ReportService
{
    private GatepassReportRepository $gatepass;
    private VisitorReportRepository $visitor;
    private VisitReportRepository $visit;
    private AuditReportRepository $audit;
    private OccupancyReportRepository $occupancy;
    private ScanLogReportRepository $scans;
    private DepartmentReportRepository $departments;

    public function __construct()
    {
        $this->gatepass    = new GatepassReportRepository();
        $this->visitor     = new VisitorReportRepository();
        $this->visit       = new VisitReportRepository();
        $this->audit       = new AuditReportRepository();
        $this->occupancy   = new OccupancyReportRepository();
        $this->scans       = new ScanLogReportRepository();
        $this->departments = new DepartmentReportRepository();
    }

    public function summary(int $tenantId): array
{
    return [
        'gatepasses_total' => $this->gatepass->count($tenantId),
        'visitors_total'   => $this->visitor->count($tenantId),
        'visits_total'     => $this->visit->count($tenantId),
        'audit_total'      => $this->audit->count($tenantId),
    ];
}
    public function gatepasses(int $tenantId, array $params = [])
    {
        return $this->gatepass->list($tenantId, $params);
    }

    public function visitors(int $tenantId, array $params = []): array
{
    return $this->visitor->list($tenantId, $params);
}

    public function visits(int $tenantId, array $params = [])
    {
        return $this->visit->list($tenantId, $params);
    }

    public function auditLogs(int $tenantId, array $params = [])
    {
        return $this->audit->list($tenantId, $params);
    }

    public function occupancy(int $tenantId): array
    {
        return $this->occupancy->summary($tenantId);
    }

    public function scans(int $tenantId, array $params = []): array
    {
        return $this->scans->list($tenantId, $params);
    }

    /**
     * Filter-bar option maps for the scan activity report, keyed by full column.
     */
    public function scanFilterOptions(int $tenantId): array
    {
        return [
            'sl.gate_id'     => $this->scans->gateOptions($tenantId),
            'sl.action'      => $this->scans->distinctOptions($tenantId, 'action'),
            'sl.source'      => $this->scans->distinctOptions($tenantId, 'source'),
            'sl.subject_type'=> $this->scans->distinctOptions($tenantId, 'subject_type'),
        ];
    }

    public function departments(int $tenantId): array
    {
        return $this->departments->summary($tenantId);
    }

    /**
     * Flat rows for the occupancy CSV: gates, gatepasses on site, visitors on site.
     */
    public function occupancyExportRows(int $tenantId): array
    {
        $summary = $this->occupancy->summary($tenantId);
        $rows    = [];

        foreach ($summary['gates'] as $gate) {
            $rows[] = [
                'section'    => 'Gate',
                'name'       => $gate['name'],
                'code'       => $gate['code'],
                'on_site'    => $gate['on_site'],
                'scans_today'=> $gate['scans_today'],
                'status'     => ((int) $gate['is_active'] === 1) ? 'Active' : 'Inactive',
            ];
        }

        foreach ($summary['gatepasses'] as $gp) {
            $rows[] = [
                'section'   => 'Gatepass',
                'name'      => $gp['gatepass_number'],
                'department'=> $gp['department_name'] ?? '',
                'holder'    => $gp['holder_name'] ?? '',
                'purpose'   => $gp['purpose'] ?? '',
                'gate_in'   => $gp['gate_name'] ?? '',
                'checked_in'=> $gp['actual_in'] ?? '',
                'returnable'=> ((int) $gp['is_returnable'] === 1) ? 'Yes' : 'No',
            ];
        }

        foreach ($summary['visits'] as $visit) {
            $rows[] = [
                'section'    => 'Visitor',
                'name'       => $visit['visitor_name'],
                'department' => $visit['department_name'] ?? '',
                'host'       => $visit['host_name'] ?? '',
                'purpose'    => $visit['purpose'] ?? '',
                'gate_in'    => $visit['gate_name'] ?? '',
                'arrived'    => $visit['checkin_time'] ?? '',
            ];
        }

        return $rows;
    }
}