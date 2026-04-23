<?php

namespace App\Modules\Settings\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Modules\Settings\Services\DepartmentService;
use RuntimeException;

class DepartmentController extends Controller
{
    public function __construct(
        private DepartmentService $service
    ) {
        if (!Auth::check()) {
            Response::redirect('/login');
        }
    }

    public function index()
    {
        return $this->view('Settings::departments', [
            'departments' => $this->service->all(),
        ]);
    }

    public function store(Request $request)
    {
        $body = $this->getJsonBody();

        $name = trim($body['name'] ?? $request->input('name', ''));

        if ($name === '') {
            return Response::json([
                'message' => 'Department name is required.'
            ], 422);
        }

        try {
            $department = $this->service->create($name);

            return Response::json([
                'success' => true,
                'data' => $department
            ]);

        } catch (RuntimeException $e) {
            return Response::json([
                'message' => $e->getMessage()
            ], 400);

        } catch (\Throwable $e) {
            return Response::json([
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function update(Request $request)
    {
        $body = $this->getJsonBody();

        $id = (int) ($body['id'] ?? $request->input('id', 0));
        $name = trim($body['name'] ?? $request->input('name', ''));

        if ($id <= 0) {
            return Response::json([
                'message' => 'Invalid department ID.'
            ], 422);
        }

        if ($name === '') {
            return Response::json([
                'message' => 'Department name is required.'
            ], 422);
        }

        try {
            $updated = $this->service->update($id, $name);

            return Response::json([
                'success' => true,
                'data' => $updated
            ]);

        } catch (RuntimeException $e) {
            return Response::json([
                'message' => $e->getMessage()
            ], 400);

        } catch (\Throwable $e) {
            return Response::json([
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    public function deactivate(Request $request)
    {
        $body = $this->getJsonBody();

        $id = (int) ($body['id'] ?? $request->input('id', 0));

        if ($id <= 0) {
            return Response::json([
                'message' => 'Invalid department ID.'
            ], 422);
        }

        try {
            $this->service->deactivate($id);

            return Response::json([
                'success' => true
            ]);

        } catch (RuntimeException $e) {
            return Response::json([
                'message' => $e->getMessage()
            ], 400);

        } catch (\Throwable $e) {
            return Response::json([
                'message' => $e->getMessage(),
                'file'    => $e->getFile(),
                'line'    => $e->getLine(),
            ], 500);
        }
    }

    private function getJsonBody(): array
    {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);

        return is_array($body) ? $body : [];
    }
}