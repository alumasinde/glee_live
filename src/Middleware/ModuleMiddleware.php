<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Modules\Platform\Services\ModuleEntitlementService;

/**
 * Hides whole modules a tenant is not entitled to.
 *
 * Path-based rather than route-based so the existing routes/web.php stays
 * declarative: the longest matching prefix wins, and unknown paths fall
 * through untouched. Disabled modules answer 404 so the console does not
 * advertise screens the tenant does not own.
 */
class ModuleMiddleware
{
    private const MAP = [
        '/gatepasses'            => 'gatepass',
        '/gate-scan'             => 'gatepass',
        '/visitors'              => 'visitors',
        '/visits'                => 'visits',
        '/badges'                => 'badges',
        '/approvals'             => 'approval',
        '/notifications'         => 'notifications',
        '/reports'               => 'reports',
        '/audit'                 => 'audit',
        '/roles'                 => 'roles',
        '/users'                 => 'roles',
        '/settings/gates'        => 'gates',
        '/settings/email'        => 'email',
        '/settings/gatepass-types' => 'gatepass_types',
        '/settings/workflows'    => 'workflows',
        '/settings'              => 'settings',
        '/dashboard'             => 'dashboard',
    ];

    public function handle(?Request $request = null): void
    {
        $user = $_SESSION['user'] ?? null;

        if (!is_array($user) || empty($user['tenant_id'])) {
            return;
        }

        $path = $this->currentPath();

        if (str_starts_with($path, '/platform')) {
            return;
        }

        $module = $this->moduleForPath($path);

        if ($module === null) {
            return;
        }

        if (!(new ModuleEntitlementService())->isEnabled((int) $user['tenant_id'], $module)) {
            Response::abort(404, 'This module is not enabled for your organisation.');
        }
    }

    private function currentPath(): string
    {
        $path = (string) (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $path = '/' . trim($path, '/');

        return $path === '//' ? '/' : $path;
    }

    private function moduleForPath(string $path): ?string
    {
        $match = null;
        $length = 0;

        foreach (self::MAP as $prefix => $module) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                if (strlen($prefix) > $length) {
                    $match  = $module;
                    $length = strlen($prefix);
                }
            }
        }

        return $match;
    }
}
