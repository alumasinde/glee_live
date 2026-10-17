<?php

namespace App\Modules\Gatepass\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\View;
use App\Modules\Gatepass\Services\GatepassService;
use App\Modules\Settings\Repositories\GateRepository;

class GateScanController extends Controller
{
    private GatepassService $service;
    private GateRepository $gates;

    public function __construct()
    {
        $this->service = new GatepassService();
        $this->gates   = new GateRepository();
    }

    private function user(): array
    {
        if (!isset($_SESSION['user'])) {
            header('Location: /login');
            exit;
        }

        return $_SESSION['user'];
    }

    public function index(): mixed
    {
        $user = $this->user();

        return View::render('Gatepass::scan', [
            'title'       => 'Scan Gatepass',
            'gates'       => $this->gates->all((int) $user['tenant_id']),
            'selected_gate' => $_SESSION['scan_gate_id'] ?? null,
        ], 'app');
    }

    public function process(Request $request): mixed
    {
        $user = $this->user();

        $code = trim((string) $request->input('gatepass_number', ''));
        $gateId = ($request->input('gate_id') !== null && (int) $request->input('gate_id') > 0)
            ? (int) $request->input('gate_id')
            : null;

        $_SESSION['scan_gate_id'] = $gateId;

        try {
            $result = $this->service->processScan(
                (int) $user['tenant_id'],
                $code,
                (int) $user['id'],
                $gateId
            );

            return $this->scanSuccess($result);
        } catch (\Throwable $e) {
            return $this->scanError($e->getMessage(), $user);
        }
    }

    private function scanSuccess(array $result): mixed
    {
        return View::render('Gatepass::scan_result', [
            'title'   => 'Scan Result',
            'message' => $result['message'],
            'action'  => $result['action'],
            'gate'    => $result['gateName'],
        ], 'app');
    }

    private function scanError(string $error, array $user): mixed
    {
        return View::render('Gatepass::scan', [
            'title'         => 'Scan Gatepass',
            'error'         => $error,
            'gates'         => $this->gates->all((int) $user['tenant_id']),
            'selected_gate' => $_SESSION['scan_gate_id'] ?? null,
        ], 'app');
    }
}
