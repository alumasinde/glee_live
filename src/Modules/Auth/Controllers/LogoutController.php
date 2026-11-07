<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\Services\AuthService;
use App\Modules\Platform\Services\PlatformImpersonationService;
use App\Core\Request;
use App\Core\Response;
use App\Core\Audit;

class LogoutController
{
    private AuthService $auth;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
    }

    public function __invoke(Request $request)
    {
        // Enforce POST-only logout
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return Response::abort(405, 'Method Not Allowed');
        }

        // Ending a support session must not destroy the platform sign-in that
        // owns it; return the operator to the tenant console instead.
        if (PlatformImpersonationService::active() !== null) {
            $tenantId = (new PlatformImpersonationService())->stop('tenant_logout');

            $_SESSION['flash'] = [
                'message' => 'Support session ended. You are back in the platform console.',
                'type'    => 'success',
            ];

            Response::redirect($tenantId ? '/platform/tenants/' . $tenantId : '/platform');
        }

        $user = $_SESSION['user'];

          Audit::log(
        'user.logout',
        'user',
        (int) $user['id'],
        [
            'email' => $user['email']
        ]
    );

        $this->auth->logout();

        // Regenerate new clean session after destroy
        session_start();
        session_regenerate_id(true);

        header('Location: /login');
        exit;
    }
}