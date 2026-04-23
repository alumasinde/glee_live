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
        // FIX: Auth guard added — was missing
        if (! Auth::check()) {
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
        $id = (int) $request->input('id');

        if ($id <= 0) {
            // FIX: Response::json() is typed never — drop the return keyword
            Response::json(['message' => 'Invalid or missing gatepass type ID.'], 422);
        }

        $checkin  = (bool) (int) $request->input('checkin',  0);
        $checkout = (bool) (int) $request->input('checkout', 0);

        try {
            $this->service->updateActions($id, $checkin, $checkout);
        } catch (RuntimeException $e) {
            Response::json(['message' => $e->getMessage()], 404);
        } catch (\Throwable $e) {
            Response::json(['message' => 'An unexpected error occurred.'], 500);
        }

        Response::json(['success' => true]);
    }
}