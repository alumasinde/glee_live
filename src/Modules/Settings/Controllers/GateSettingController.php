<?php

namespace App\Modules\Settings\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Settings\Services\GateService;
use RuntimeException;

class GateSettingController extends Controller
{
    public function __construct(
        private GateService $service
    ) {
        if (!Auth::check()) {
            Response::redirect('/login');
        }
    }

    public function index()
    {
        return $this->view('Settings::gates', [
            'gates'   => $this->service->all(),
            'created' => $_SESSION['flash']['gate_device'] ?? null,
            'error'   => $_GET['error'] ?? null,
        ]);
    }

    public function store(Request $request)
    {
        try {
            $result = $this->service->create(
                trim((string) $request->input('name', '')),
                trim((string) $request->input('code', ''))
            );

            // The plaintext key is shown once and never travels in a URL: query
            // strings end up in access logs, browser history and referrer headers.
            $_SESSION['flash'] = [
                'type'        => 'success',
                'message'     => 'Gate created.',
                'gate_device' => [$result['id'], $result['key']],
            ];

            return Response::redirect('/settings/gates');
        } catch (RuntimeException $e) {
            return Response::redirect('/settings/gates?error=' . urlencode($e->getMessage()));
        }
    }

    public function update(Request $request)
    {
        $id = (int) $request->input('id', 0);

        try {
            $this->service->update(
                $id,
                trim((string) $request->input('name', '')),
                trim((string) $request->input('code', '')),
                $request->input('is_active') !== null
            );

            return Response::redirect('/settings/gates');
        } catch (RuntimeException $e) {
            return Response::redirect('/settings/gates?error=' . urlencode($e->getMessage()));
        }
    }

    public function rotate(Request $request)
    {
        $id = (int) $request->input('id', 0);

        try {
            $key = $this->service->rotateKey($id);

            $_SESSION['flash'] = [
                'type'        => 'success',
                'message'     => 'Device key rotated.',
                'gate_device' => [$id, $key],
            ];

            return Response::redirect('/settings/gates');
        } catch (RuntimeException $e) {
            return Response::redirect('/settings/gates?error=' . urlencode($e->getMessage()));
        }
    }

    public function delete(Request $request)
    {
        $id = (int) $request->input('id', 0);

        try {
            $this->service->delete($id);

            return Response::redirect('/settings/gates');
        } catch (RuntimeException $e) {
            return Response::redirect('/settings/gates?error=' . urlencode($e->getMessage()));
        }
    }
}
