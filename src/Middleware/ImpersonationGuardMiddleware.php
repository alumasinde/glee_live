<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Modules\Platform\Services\PlatformImpersonationService;

/**
 * Revalidates support access on every request, so expiry or a disabled tenant
 * user ends the tenant identity even if the operator never presses "return".
 */
class ImpersonationGuardMiddleware
{
    public function handle(?Request $request = null): void
    {
        if (PlatformImpersonationService::active() === null) {
            return;
        }

        $result = (new PlatformImpersonationService())->validate();

        if (!empty($result['ok'])) {
            return;
        }

        $tenantId = $result['tenant_id'] ?? null;

        $_SESSION['flash'] = [
            'message' => $result['reason'] === 'expired'
                ? 'The support session expired. You have been returned to the platform.'
                : 'The support session ended. You have been returned to the platform.',
            'type' => 'info',
        ];

        Response::redirect($tenantId ? '/platform/tenants/' . (int) $tenantId : '/platform/login');
    }
}
