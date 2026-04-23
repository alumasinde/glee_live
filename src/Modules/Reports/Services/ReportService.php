<?php

namespace App\Modules\Reports\Services;

use App\Modules\Reports\Repositories\GatepassReportRepository;
use App\Modules\Reports\Repositories\VisitorReportRepository;
use App\Modules\Reports\Repositories\VisitReportRepository;
use App\Modules\Reports\Repositories\AuditReportRepository;

class ReportService
{
    private GatepassReportRepository $gatepass;
    private VisitorReportRepository $visitor;
    private VisitReportRepository $visit;
    private AuditReportRepository $audit;

    public function __construct()
    {
        $this->gatepass = new GatepassReportRepository();
        $this->visitor  = new VisitorReportRepository();
        $this->visit    = new VisitReportRepository();
        $this->audit    = new AuditReportRepository();
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
}