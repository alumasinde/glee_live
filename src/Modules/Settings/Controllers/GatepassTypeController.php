<?php

namespace App\Modules\Settings\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Settings\Services\GatepassTypeService;
use RuntimeException;

class GatepassTypeController extends Controller
{
    public function __construct(
        private GatepassTypeService $service
    ) {
        if (!Auth::check()) {
            Response::redirect('/login');
        }
    }

    public function index()
    {
        return $this->view('Settings::gatepass-types', [
            'types' => $this->service->all(),
        ]);
    }

    public function update(Request $request)
    {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);

        if (!is_array($body)) {
            $body = [];
        }

        $id = (int) ($body['id'] ?? $request->input('id', 0));

        if ($id <= 0) {
            return Response::json([
                'message' => 'Invalid or missing gatepass type ID.'
            ], 422);
        }

        $checkin = !empty($body['checkin']);
        $checkout = !empty($body['checkout']);

        try {
            $this->service->updateActions($id, $checkin, $checkout);

            return Response::json(['success' => true]);

        } catch (RuntimeException $e) {
            return Response::json([
                'message' => $e->getMessage()
            ], 400);

        } catch (\Throwable $e) {
    Response::json([
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
    ], 500);
}
    }
}