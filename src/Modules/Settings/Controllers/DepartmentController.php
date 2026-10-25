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

    /**
     * Unified input handler (JSON + form-data safe)
     */
    private function input(Request $request): array
    {
        $raw = file_get_contents('php://input');
        $json = json_decode($raw, true);

        if (is_array($json)) {
            return $json;
        }

        // fallback for form POST
        return $request->all() ?: [];
    }

 public function store(Request $request)
{
    $body = $this->input($request);

    $name = trim($body['name'] ?? '');
    $code = trim($body['code'] ?? '');
    if ($name === '') {
        return Response::redirect('/settings/departments?error=Department name is required');
    }

    if ($code === '') {
        return Response::redirect('/settings/departments?error=Department code is required');
    }

    try {
        $this->service->create($name, $code);

        return Response::redirect('/settings/departments?success=Department created successfully');

    } catch (\PDOException $e) {
        error_log(sprintf('DepartmentController::store hit a DB constraint: %s', $e->getMessage()));

        return Response::redirect('/settings/departments?error=' . urlencode('Department could not be saved.'));

    } catch (RuntimeException $e) {
        return Response::redirect('/settings/departments?error=' . urlencode($e->getMessage()));

    } catch (\Throwable $e) {
        return Response::redirect('/settings/departments?error=Server error');
    }
}

    public function update(Request $request)
    {
        $body = $this->input($request);

        $id = (int) ($body['id'] ?? 0);
        $name = trim((string) ($body['name'] ?? ''));
        $code = trim((string) ($body['code'] ?? ''));

        if ($id <= 0) {
            return Response::json(['message' => 'Invalid department ID.'], 422);
        }

        try {
            $updated = $this->service->update($id, $name, $code);

            return Response::json([
                'success' => true,
                'data' => $updated
            ]);

        } catch (\PDOException $e) {
            error_log(sprintf('DepartmentController::update hit a DB constraint for department %d: %s', $id, $e->getMessage()));

            return Response::json(['message' => 'Department could not be saved.'], 400);

        } catch (RuntimeException $e) {
            return Response::json(['message' => $e->getMessage()], 400);

        } catch (\Throwable $e) {
            // Never leak internal error text to the client
            error_log(sprintf('DepartmentController::update failed for department %d: %s', $id, $e->getMessage()));

            return Response::json(['message' => 'Server error while updating the department.'], 500);
        }
    }

    public function delete(Request $request)
    {
        $body = $this->input($request);
        $id = (int) ($body['id'] ?? 0);

        if ($id <= 0) {
            return Response::json(['message' => 'Invalid department ID.'], 422);
        }

        try {
            $this->service->delete($id);

            return Response::json(['success' => true]);

        } catch (\PDOException $e) {
            error_log(sprintf('DepartmentController::delete hit a DB constraint for department %d: %s', $id, $e->getMessage()));

            return Response::json(['message' => 'Department is still linked to other records.'], 400);

        } catch (RuntimeException $e) {
            return Response::json(['message' => $e->getMessage()], 400);

        } catch (\Throwable $e) {
            error_log(sprintf('DepartmentController::delete failed for department %d: %s', $id, $e->getMessage()));

            return Response::json(['message' => 'Server error while deleting the department.'], 500);
        }
    }
    public function toggle(Request $request)
    {
        $body = $this->input($request);
        $id = (int) ($body['id'] ?? 0);

        if ($id <= 0) {
            return Response::json([
                'message' => 'Invalid department ID.'
            ], 422);
        }

        try {
            $this->service->toggle($id);

            return Response::json([
                'success' => true
            ]);

        } catch (\PDOException $e) {
            error_log(sprintf('DepartmentController::toggle hit a DB constraint for department %d: %s', $id, $e->getMessage()));

            return Response::json([
                'message' => 'Department could not be updated.'
            ], 400);

        } catch (RuntimeException $e) {
            return Response::json([
                'message' => $e->getMessage()
            ], 400);

        } catch (\Throwable $e) {
            error_log(sprintf('DepartmentController::toggle failed for department %d: %s', $id, $e->getMessage()));

            return Response::json([
                'message' => 'Server error while toggling the department.'
            ], 500);
        }
    }
}