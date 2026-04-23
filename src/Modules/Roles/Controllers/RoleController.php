<?php

namespace App\Modules\Roles\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Roles\Services\RoleService;

class RoleController extends Controller
{
    private RoleService $service;

    public function __construct()
    {
        $this->service = new RoleService();
    }

        private function user(): array
    {
        if (!isset($_SESSION['user'])) {
            Response::abort(403);
        }

        return $_SESSION['user'];
    }
    /* ============================================================
       INDEX
    ============================================================ */

    public function index(Request $request)
    {
        $user = $this->user();
        $tenantId = (int) $user['tenant_id'];

        $roles = $this->service->all($tenantId);

        return View::render('Roles::index', [
            'roles' => $roles,
            'title' => 'Roles Management'
        ], 'app');
    }

    /* ============================================================
       CREATE
    ============================================================ */

    public function create(Request $request)
    {
        return View::render('Roles::create', [
            'title' => 'Create Role'
        ], 'app');
    }

    public function store(Request $request)
    {
        $user = $this->user();
        $tenantId = (int) $user['tenant_id'];

        $name = trim((string) $request->input('name'));

        if ($name === '') {
            Response::abort(400, 'Role name is required.');
        }

        $this->service->create($tenantId, $name);

        header('Location: /roles');
        exit;
    }

    /* ============================================================
       EDIT
    ============================================================ */

    public function edit(Request $request, $id)
    {
        $user = $this->user();
        $tenantId = (int) $user['tenant_id'];
        $roleId = (int) $id;

        if ($roleId <= 0) {
            Response::abort(400);
        }

        $role = $this->service->find($tenantId, $roleId);

        if (!$role) {
            Response::abort(404);
        }

        return View::render('Roles::edit', [
            'role' => $role,
            'title' => 'Edit Role'
        ], 'app');
    }

    public function update(Request $request, $id)
    {
        $user = $this->user();
        $tenantId = (int) $user['tenant_id'];
        $roleId = (int) $id;

        if ($roleId <= 0) {
            Response::abort(400);
        }

        $name = trim((string) $request->input('name'));

        if ($name === '') {
            Response::abort(400, 'Role name is required.');
        }

        $role = $this->service->find($tenantId, $roleId);

        if (!$role) {
            Response::abort(404);
        }

        $this->service->update($tenantId, $roleId, $name);

        header('Location: /roles');
        exit;
    }

    /* ============================================================
       DELETE
    ============================================================ */

    public function delete(Request $request, $id)
    {
        $user = $this->user();
        $tenantId = (int) $user['tenant_id'];
        $roleId = (int) $id;

        if ($roleId <= 0) {
            Response::abort(400);
        }

        $role = $this->service->find($tenantId, $roleId);

        if (!$role) {
            Response::abort(404);
        }

        $this->service->delete($tenantId, $roleId);

        header('Location: /roles');
        exit;
    }

    /* ============================================================
       PERMISSIONS PAGE
    ============================================================ */

   public function permissions(Request $request, $id)
{
    $tenantId = (int) $this->user()['tenant_id'];
    $roleId   = (int) $id;

    $role = $this->service->find($tenantId, $roleId);

    if (!$role) {
        Response::abort(404);
    }

    // Get full permission rows
    $rolePermissions = $this->service->getRolePermissions($tenantId, $roleId);

    // Convert to ID array (IMPORTANT FIX)
    $rolePermissionIds = array_column($rolePermissions, 'id');

    $allPermissions = $this->service->allPermissions();

    return View::render('Roles::permissions', [
        'role' => $role,
        'rolePermissions' => $rolePermissionIds, // now guaranteed array
        'allPermissions' => $allPermissions,
        'title' => 'Assign Permissions'
    ], 'app');
}

    /* ============================================================
       UPDATE PERMISSIONS
    ============================================================ */

    public function updatePermissions(Request $request, $id)
    {
        $user = $this->user();
        $tenantId = (int) $user['tenant_id'];
        $roleId = (int) $id;

        if ($roleId <= 0) {
            Response::abort(400);
        }

        $role = $this->service->find($tenantId, $roleId);

        if (!$role) {
            Response::abort(404);
        }

        $permissionIds = $request->input('permissions', []);

        if (!is_array($permissionIds)) {
            Response::abort(400);
        }

        $this->service->assignPermissions(
            $tenantId,
            $roleId,
            $permissionIds
        );

        header("Location: /roles/{$roleId}/permissions");
        exit;
    }
}