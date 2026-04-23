<?php

namespace App\Modules\Gatepass\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\View;
use App\Modules\Gatepass\Services\GatepassService;

class GateScanController extends Controller
{
    private GatepassService $service;

    public function __construct()
    {
        $this->service = new GatepassService();
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
        return View::render('Gatepass::scan', ['title' => 'Scan Gatepass'], 'app');
    }

    public function process(Request $request): mixed
    {
        $user = $this->user();
        $code = trim((string) $request->input('gatepass_number', ''));

        if ($code === '') {
            return $this->scanError('Invalid QR code.');
        }

        try {
            $gatepass = $this->service->findByNumber($user['tenant_id'], $code);

            if (!$gatepass) {
                return $this->scanError('Gatepass not found.');
            }

            $actualIn     = $gatepass['actual_in'] ?? null;
            $actualOut    = $gatepass['actual_out'] ?? null;
            $isReturnable = (int) ($gatepass['is_returnable'] ?? 0) === 1;

            if (!$actualIn) {
                $this->service->checkIn($user['tenant_id'], (int) $gatepass['id'], $user['id']);
                return $this->scanSuccess('Checked in successfully.');
            }

            if ($isReturnable && $actualIn && !$actualOut) {
                $this->service->markReturned($user['tenant_id'], (int) $gatepass['id']);
                return $this->scanSuccess('Item returned successfully.');
            }

            if (!$actualOut) {
                $this->service->checkOut($user['tenant_id'], (int) $gatepass['id'], $user['id']);
                return $this->scanSuccess('Checked out successfully.');
            }

            return $this->scanError('Gatepass process already completed.');

        } catch (\Throwable $e) {
            return $this->scanError($e->getMessage());
        }
    }

    private function scanSuccess(string $message): mixed
    {
        return View::render('Gatepass::scan_result', [
            'title'   => 'Scan Result',
            'message' => $message,
        ], 'app');
    }

    private function scanError(string $error): mixed
    {
        return View::render('Gatepass::scan', [
            'title' => 'Scan Gatepass',
            'error' => $error,
        ], 'app');
    }
}
