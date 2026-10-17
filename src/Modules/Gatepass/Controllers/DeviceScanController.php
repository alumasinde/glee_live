<?php

namespace App\Modules\Gatepass\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Gatepass\Services\GatepassService;
use App\Modules\Settings\Repositories\GateRepository;
use Throwable;

class DeviceScanController extends Controller
{
    public function __construct(
        private GateRepository $gates,
        private GatepassService $service
    ) {}

    public function scan(Request $request): never
    {
        $body = json_decode((string) file_get_contents('php://input'), true);

        if (!is_array($body)) {
            $body = $request->all();
        }

        $key  = trim((string) ($body['key'] ?? ''));
        $code = trim((string) ($body['code'] ?? ''));

        if ($key === '' || !preg_match('/^gk_[0-9a-f]{40}$/', $key)) {
            Response::json(['status' => 'unauthorized', 'message' => 'Invalid device key.'], 401);
        }

        $gate = $this->gates->findByKeyHash(hash('sha256', $key));

        if (!$gate) {
            Response::json(['status' => 'unauthorized', 'message' => 'Invalid device key.'], 401);
        }

        if ($code === '') {
            Response::json(['status' => 'error', 'message' => 'Missing code.'], 400);
        }

        try {
            $result = $this->service->processScan(
                (int) $gate['tenant_id'],
                $code,
                null,
                (int) $gate['id'],
                'device'
            );

            Response::json([
                'status'  => 'success',
                'action'  => $result['action'],
                'label'   => $result['label'],
                'gate'    => $result['gateName'],
                'message' => $result['message'],
            ]);
        } catch (Throwable $e) {
            $message = $e->getMessage();

            $http = match (true) {
                str_contains($message, 'not recognized')          => 404,
                str_contains($message, 'already')                 => 409,
                str_contains($message, 'Blacklisted')             => 403,
                str_contains($message, 'not allowed')             => 403,
                default                                           => 400,
            };

            Response::json([
                'status'  => 'rejected',
                'message' => $message,
            ], $http);
        }
    }
}
