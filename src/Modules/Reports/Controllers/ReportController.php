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
            'title' => 'Gatepass Report',
            'data'  => $data
        ], 'app');
    }

    public function visitors(Request $request)
    {
        $data = $this->service->visitors(
            $this->tenantId(),
            $request->all()
        );

        return View::render('Reports::visitors', [
            'title' => 'Visitor Report',
            'data'  => $data
        ], 'app');
    }

    public function visits(Request $request)
    {
        $data = $this->service->visits(
            $this->tenantId(),
            $request->all()
        );

        return View::render('Reports::visits', [
            'title' => 'Visit Report',
            'data'  => $data
        ], 'app');
    }

    public function auditLogs(Request $request)
    {
        $data = $this->service->auditLogs(
            $this->tenantId(),
            $request->all()
        );

        return View::render('Reports::audit', [
            'title' => 'Audit Logs',
            'data'  => $data
        ], 'app');
    }
}