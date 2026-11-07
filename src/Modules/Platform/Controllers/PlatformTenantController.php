<?php

namespace App\Modules\Platform\Controllers;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Modules\Platform\Services\ModuleEntitlementService;
use App\Modules\Platform\Services\PlatformAuditService;
use App\Modules\Platform\Services\PlatformAuthService;
use App\Modules\Platform\Services\PlatformImpersonationService;
use App\Modules\Platform\Services\TenantProvisioningService;
use App\Modules\Platform\Services\TenantStatusService;
use PDO;
use RuntimeException;

class PlatformTenantController
{
    public function createForm(Request $request): mixed
    {
        View::render('Platform::tenant_create', [
            'title' => 'New tenant',
            'old'   => [],
            'error' => null,
        ], 'platform');

        return null;
    }

    public function store(Request $request): mixed
    {
        $input = [
            'name'            => (string) $request->input('name', ''),
            'code'            => (string) $request->input('code', ''),
            'email'           => (string) $request->input('email', ''),
            'phone'           => (string) $request->input('phone', ''),
            'country'         => (string) $request->input('country', ''),
            'admin_first_name' => (string) $request->input('admin_first_name', ''),
            'admin_last_name'  => (string) $request->input('admin_last_name', ''),
            'admin_email'      => (string) $request->input('admin_email', ''),
            'admin_password'   => (string) $request->input('admin_password', ''),
        ];

        $platformUser = $_SESSION[PlatformAuthService::SESSION_KEY] ?? [];
        $actorId = !empty($platformUser['id']) ? (int) $platformUser['id'] : null;

        try {
            $tenantId = (new TenantProvisioningService())->create($input, $actorId);
        } catch (RuntimeException $e) {
            View::render('Platform::tenant_create', [
                'title' => 'New tenant',
                'old'   => $input,
                'error' => $e->getMessage(),
            ], 'platform');

            return null;
        } catch (\Throwable $e) {
            error_log('[platform-tenant] create failed: ' . $e->getMessage());

            View::render('Platform::tenant_create', [
                'title' => 'New tenant',
                'old'   => $input,
                'error' => 'The tenant could not be created. Please try again.',
            ], 'platform');

            return null;
        }

        $_SESSION['flash'] = [
            'message' => 'Tenant created. Its first administrator can now sign in.',
            'type'    => 'success',
        ];

        Response::redirect('/platform/tenants/' . $tenantId);
    }

    public function show(Request $request, mixed $id): mixed
    {
        $tenant = $this->findTenant((int) $id);

        if ($tenant === null) {
            Response::abort(404, 'Tenant not found.');
        }

        $entitlements = new ModuleEntitlementService();

        View::render('Platform::tenant', [
            'title'         => 'Modules — ' . $tenant['name'],
            'tenant'        => $tenant,
            'modules'       => $entitlements->catalogue(),
            'states'        => $entitlements->states((int) $tenant['id']),
            'users'         => $this->users((int) $tenant['id']),
            'support'       => PlatformImpersonationService::active(),
            'supportTtl'    => (new PlatformImpersonationService())->ttlMinutes(),
        ], 'platform');

        return null;
    }

    public function updateModules(Request $request, mixed $id): never
    {
        $tenant = $this->findTenant((int) $id);

        if ($tenant === null) {
            Response::abort(404, 'Tenant not found.');
        }

        $enabled = $request->input('modules', []);
        $enabled = is_array($enabled) ? array_map('strval', $enabled) : [];

        $platformUser = $_SESSION['platform_user'] ?? [];
        $errors = (new ModuleEntitlementService())->save(
            (int) $tenant['id'],
            $enabled,
            !empty($platformUser['id']) ? (int) $platformUser['id'] : null
        );

        if ($errors !== []) {
            $_SESSION['flash'] = ['message' => implode(' ', $errors), 'type' => 'danger'];
        } else {
            PlatformAuditService::log('tenant.modules_updated', 'tenant', (int) $tenant['id'], [
                'enabled' => $enabled,
            ]);

            $_SESSION['flash'] = ['message' => 'Module entitlements saved.', 'type' => 'success'];
        }

        Response::redirect('/platform/tenants/' . (int) $tenant['id']);
    }

    public function impersonate(Request $request, mixed $id): never
    {
        $tenant = $this->findTenant((int) $id);

        if ($tenant === null) {
            Response::abort(404, 'Tenant not found.');
        }

        $platformUser = $_SESSION[PlatformAuthService::SESSION_KEY] ?? [];
        $reason = trim((string) $request->input('reason', ''));

        $result = (new PlatformImpersonationService())->start(
            (int) ($platformUser['id'] ?? 0),
            (int) $tenant['id'],
            (int) $request->input('user_id', 0),
            $reason !== '' ? $reason : null
        );

        if (empty($result['ok'])) {
            $_SESSION['flash'] = [
                'message' => (string) ($result['error'] ?? 'Support access could not be started.'),
                'type'    => 'danger',
            ];

            Response::redirect('/platform/tenants/' . (int) $tenant['id']);
        }

        $_SESSION['flash'] = [
            'message' => 'Support session started. Actions are audited and the session expires automatically.',
            'type'    => 'info',
        ];

        Response::redirect('/dashboard');
    }

    public function stopImpersonation(Request $request): never
    {
        $tenantId = (new PlatformImpersonationService())->stop('operator_return');

        $_SESSION['flash'] = [
            'message' => 'Support session ended. You are back in the platform console.',
            'type'    => 'success',
        ];

        Response::redirect($tenantId ? '/platform/tenants/' . $tenantId : '/platform');
    }

    public function suspend(Request $request, mixed $id): never
    {
        $this->updateStatus($request, (int) $id, false);
    }

    public function reactivate(Request $request, mixed $id): never
    {
        $this->updateStatus($request, (int) $id, true);
    }

    private function updateStatus(Request $request, int $tenantId, bool $activate): never
    {
        $tenant = $this->findTenant($tenantId);

        if ($tenant === null) {
            Response::abort(404, 'Tenant not found.');
        }

        $platformUser = $_SESSION[PlatformAuthService::SESSION_KEY] ?? [];
        $actorId = !empty($platformUser['id']) ? (int) $platformUser['id'] : null;
        $reason = trim((string) $request->input('reason', ''));

        try {
            $service = new TenantStatusService();
            $result = $activate
                ? $service->reactivate($tenantId, $actorId, $reason)
                : $service->suspend($tenantId, $actorId, $reason);

            if (empty($result['changed'])) {
                $_SESSION['flash'] = [
                    'message' => $activate
                        ? 'This tenant is already active.'
                        : 'This tenant is already suspended.',
                    'type'    => 'info',
                ];
            } elseif ($activate) {
                $_SESSION['flash'] = [
                    'message' => 'Tenant reactivated. Users can sign in again with a fresh session.',
                    'type'    => 'success',
                ];
            } else {
                $users = (int) $result['revoked_users'];
                $support = (int) $result['ended_support_sessions'];

                $_SESSION['flash'] = [
                    'message' => sprintf(
                        'Tenant suspended. %d user session%s revoked and %d support session%s ended.',
                        $users,
                        $users === 1 ? '' : 's',
                        $support,
                        $support === 1 ? '' : 's'
                    ),
                    'type'    => 'success',
                ];
            }
        } catch (RuntimeException $e) {
            $_SESSION['flash'] = ['message' => $e->getMessage(), 'type' => 'danger'];
        } catch (\Throwable $e) {
            error_log('[platform-tenant] status change failed: ' . $e->getMessage());
            $_SESSION['flash'] = [
                'message' => 'The tenant status could not be changed. Please try again.',
                'type'    => 'danger',
            ];
        }

        Response::redirect('/platform/tenants/' . $tenantId);
    }

    private function findTenant(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = DB::connect()->prepare(
            'SELECT id, name, code, email, phone, country, is_active, created_at
               FROM tenants
              WHERE id = ?
              LIMIT 1'
        );
        $stmt->execute([$id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Active and inactive users of the tenant, with their role names.
     *
     * @return array<int, array<string, mixed>>
     */
    private function users(int $tenantId): array
    {
        $stmt = DB::connect()->prepare(
            "SELECT u.id, u.first_name, u.last_name, u.email, u.is_active,
                    GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS roles
               FROM users u
               LEFT JOIN user_roles ur
                 ON ur.user_id = u.id
                AND ur.tenant_id = u.tenant_id
               LEFT JOIN roles r
                 ON r.id = ur.role_id
                AND r.tenant_id = u.tenant_id
              WHERE u.tenant_id = ?
              GROUP BY u.id, u.first_name, u.last_name, u.email, u.is_active
              ORDER BY u.is_active DESC, u.first_name ASC"
        );
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
