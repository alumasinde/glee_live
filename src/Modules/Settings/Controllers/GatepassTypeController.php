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
        $return = !empty($body['return']);

        // Absent key = older form/JSON client that knows nothing about the flag:
        // leave whatever the row says instead of silently switching it off.
        $isReturnable = array_key_exists('returnable', $body)
            ? !empty($body['returnable'])
            : null;

        try {
            $this->service->updateConfig($id, $checkin, $checkout, $return, $isReturnable);

            return Response::json(['success' => true]);

        } catch (\PDOException $e) {
            error_log(sprintf('GatepassTypeController::update hit a DB constraint for type %d: %s', $id, $e->getMessage()));

            return Response::json([
                'message' => 'Gatepass type could not be saved.'
            ], 400);

        } catch (RuntimeException $e) {
            return Response::json([
                'message' => $e->getMessage()
            ], 400);

        } catch (\Throwable $e) {
            // Never leak file/line of internal errors to the client
            error_log(sprintf('GatepassTypeController::update failed for type %d: %s', $id, $e->getMessage()));

            return Response::json([
                'message' => 'Server error while updating the gatepass type.'
            ], 500);
        }
    }
}