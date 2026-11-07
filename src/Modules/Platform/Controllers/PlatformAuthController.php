<?php

namespace App\Modules\Platform\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Middleware\RateLimitMiddleware;
use App\Modules\Platform\Services\PlatformAuditService;
use App\Modules\Platform\Services\PlatformAuthService;
use App\Modules\Platform\Services\PlatformImpersonationService;

class PlatformAuthController extends Controller
{
    public function loginForm(Request $request): mixed
    {
        if (PlatformAuthService::check()) {
            Response::redirect('/platform');
        }

        View::render('Platform::login', ['title' => 'Platform sign in'], 'platform');

        return null;
    }

    public function login(Request $request): mixed
    {
        $email    = trim((string) $request->input('email'));
        $password = (string) $request->input('password');

        $auth = new PlatformAuthService();

        if ($email === '' || $password === '' || !$auth->attempt($email, $password)) {
            http_response_code(401);

            View::render('Platform::login', [
                'title' => 'Platform sign in',
                'error' => 'Invalid credentials.',
                'email' => $email,
            ], 'platform');

            return null;
        }

        RateLimitMiddleware::reset('login', $request);
        Response::redirect('/platform');
    }

    public function logout(Request $request): never
    {
        // A platform sign-out also ends any support session it owns; otherwise
        // the tenant identity would outlive the platform account that created it.
        if (PlatformImpersonationService::active() !== null) {
            (new PlatformImpersonationService())->stop('platform_logout');
        }

        PlatformAuditService::log('platform.logout');
        PlatformAuthService::logout();
        Response::redirect('/platform/login');
    }
}
