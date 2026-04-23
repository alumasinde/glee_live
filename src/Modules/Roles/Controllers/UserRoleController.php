<?php

namespace App\Modules\Roles\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Roles\Services\UserRoleService;
use App\Modules\Roles\Services\RoleService;

class UserRoleController extends Controller
{
    private UserRoleService $service;
    private RoleService $roleService;

    public function __construct()
    {
        $this->service = new UserRoleService();
        $this->roleService = new RoleService();
    }

          private function user(): array
    {
        if (!isset($_SESSION['user'])) {
            Response::abort(403);
        }

        return $_SESSION['user'];
    }
    /* ============================================================
       ROLE ASSIGNMENT PAGE
    ============================================================ */

    public function index(Request $request, $userId)
    {
        $authUser = $this->user();
        $tenantId = (int) $authUser['tenant_id'];
        $userId   = (int) $userId;

        if ($userId <= 0) {
            Response::abort(400);
        }

        // Validate target user belongs to tenant
        $userExists = $this->validateUser($tenantId, $userId);
        if (!$userExists) {
            Response::abort(404);
        }

        $roles = $this->roleService->all($tenantId);

        $assignedRoleIds = $this->service->getUserRoleIds(
            $tenantId,
            $userId
        );

        return View::render('Roles::user_roles', [
            'roles' => $roles,
            'assignedRoleIds' => $assignedRoleIds,
            'userId' => $userId,
            'title' => 'Assign Roles to User'
        ], 'app');
    }

    /* ============================================================
       UPDATE USER ROLES
    ============================================================ */

    public function update(Request $request, $userId)
    {
        $authUser = $this->user();
        $tenantId = (int) $authUser['tenant_id'];
        $userId   = (int) $userId;

        if ($userId <= 0) {
            Response::abort(400);
        }

        // Validate target user
        $userExists = $this->validateUser($tenantId, $userId);
        if (!$userExists) {
            Response::abort(404);
        }

        $roleIds = $request->input('roles', []);

        if (!is_array($roleIds)) {
            Response::abort(400);
        }

        $this->service->assignRoles(
            $tenantId,
            $userId,
            $roleIds
        );

        header("Location: /users/{$userId}/roles");
        exit;
    }

    /* ============================================================
       INTERNAL VALIDATION
    ============================================================ */

    private function validateUser(int $tenantId, int $userId): bool
    {
        $db = \App\Core\DB::connect();

        $stmt = $db->prepare("
            SELECT id
            FROM users
            WHERE id = ?
              AND tenant_id = ?
            LIMIT 1
        ");

        $stmt->execute([$userId, $tenantId]);

        return (bool) $stmt->fetchColumn();
    }
}