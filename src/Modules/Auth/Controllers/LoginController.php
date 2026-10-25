<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\Services\AuthService;
use App\Modules\Tenant\Repositories\TenantRepository;
use App\Core\Controller;
use App\Core\Audit;
use App\Core\View;
use App\Core\Request;
use RuntimeException;

class LoginController extends Controller
{
    private AuthService $auth;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
    }

    public function index()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['user'])) {
            header('Location: /dashboard');
            exit;
        }

        // Backing out to the sign-in form abandons any parked challenge.
        if (!empty($_SESSION[AuthService::MFA_SESSION_KEY])) {
            $this->auth->clearPendingChallenge();
        }

        try {
            $tenantId = $this->resolveTenantId();
            $tenant = $this->auth->getTenantContext($tenantId);
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            http_response_code(400);
            exit('Invalid tenant');
        }

        return View::render(
            'Auth::login',
            [
                'title'        => 'Login',
                'company_logo' => $tenant['tenant_logo'],
                'tenant_name'  => $tenant['tenant_name'],
            ],
            'guest'
        );
    }

public function store(Request $request)
{
    $tenantId = $this->resolveTenantId();

    $email    = trim((string) $request->input('email'));
    $password = (string) $request->input('password');

    /*
    |--------------------------------------------------------------------------
    | INPUT VALIDATION FAILURE
    |--------------------------------------------------------------------------
    */
    if (!$this->isValidEmail($email) || $password === '') {

        Audit::system(
            $tenantId,
            'user.login_failed',
            'auth',
            null,
            [
                'email'  => $email ?: null,
                'reason' => 'invalid_input'
            ]
        );

        return $this->fail($request, 'Invalid credentials.', 422);
    }

    /*
    |--------------------------------------------------------------------------
    | AUTH
    |--------------------------------------------------------------------------
    |
    | login() returns a status rather than a boolean so the two-factor hand-off
    | never looks like a failure: an MFA account must not see "Invalid
    | credentials." after typing its password correctly.
    */
    $result = $this->auth->login($tenantId, $email, $password);

    switch ($result['status']) {
        case AuthService::STATUS_MFA_REQUIRED:
            if ($this->isAjax($request)) {
                $this->json(['redirect' => '/login/two-factor'], 200);
                return;
            }

            header('Location: /login/two-factor');
            exit;

        case AuthService::STATUS_LOCKED:
            Audit::system(
                $tenantId,
                'user.login_failed',
                'auth',
                null,
                [
                    'email'  => $email,
                    'reason' => 'locked'
                ]
            );

            return $this->fail($request, 'Too many attempts. Try again later.', 423);

        case AuthService::STATUS_FAILED:
            Audit::system(
                $tenantId,
                'user.login_failed',
                'auth',
                null,
                [
                    'email'  => $email,
                    'reason' => 'invalid_credentials'
                ]
            );

            return $this->fail($request, 'Invalid credentials.', 401);
    }

    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */
    $user = $_SESSION['user'];

    Audit::log(
        'user.login',
        'user',
        (int) $user['id'],
        [
            'email' => $user['email']
        ]
    );

    /*
    |--------------------------------------------------------------------------
    | RESPONSE
    |--------------------------------------------------------------------------
    */
    if ($this->isAjax($request)) {
        $this->json([
            'redirect' => '/dashboard'
        ], 200);
        return;
    }

    header('Location: /dashboard');
    exit;
}

    /*
    |--------------------------------------------------------------------------
    | TWO-FACTOR CHALLENGE (pre-authentication)
    |--------------------------------------------------------------------------
    |
    | Reachable only with the parked challenge that a correct password leaves in
    | the session; anyone else is bounced back to the login form.
    */
    public function twoFactorForm()
    {
        $this->startSession();

        if (!$this->auth->pendingChallenge()) {
            header('Location: /login');
            exit;
        }

        return $this->renderTwoFactor();
    }

    public function twoFactor(Request $request)
    {
        $this->startSession();

        if (!$this->auth->pendingChallenge()) {
            return $this->errorResponse('Your verification window expired. Please sign in again.');
        }

        $result = $this->auth->verifyChallenge(trim((string) $request->input('code')));

        if ($result['status'] === AuthService::STATUS_LOGIN) {
            $user = $_SESSION['user'];

            Audit::log('user.login', 'user', (int) $user['id'], [
                'email'  => $user['email'],
                'method' => $result['method'] ?? 'totp',
            ]);

            if ($this->isAjax($request)) {
                $this->json(['redirect' => '/dashboard'], 200);
                return;
            }

            header('Location: /dashboard');
            exit;
        }

        $message = $result['status'] === AuthService::STATUS_LOCKED
            ? 'Too many attempts. Try again later.'
            : 'That code was not accepted.';

        if ($result['status'] === AuthService::STATUS_LOCKED) {
            $this->auth->clearPendingChallenge();
        }

        return $this->renderTwoFactor($message);
    }

    private function renderTwoFactor(?string $error = null)
    {
        try {
            $tenant = $this->auth->getTenantContext($this->resolveTenantId());
        } catch (\Throwable $e) {
            $tenant = ['tenant_name' => 'Company', 'tenant_logo' => null];
        }

        return View::render(
            'Auth::two-factor',
            [
                'title'        => 'Two-Factor Verification',
                'error'        => $error,
                'company_logo' => $tenant['tenant_logo'],
                'tenant_name'  => $tenant['tenant_name'],
            ],
            'guest'
        );
    }

    private function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Unified failure handler
     */
    private function fail(Request $request, string $message, int $status = 400)
    {
        if ($this->isAjax($request)) {
            $this->json(['message' => $message], $status);
            return;
        }

        return $this->errorResponse($message);
    }

    /**
     * Detect AJAX properly
     */
    private function isAjax(Request $request): bool
    {
        return (
            ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest' ||
            str_contains(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), 'application/json')
        );
    }

    /**
     * HTML fallback
     */
    private function errorResponse(string $message)
    {
        try {
            $tenantId = $this->resolveTenantId();
            $tenant = $this->auth->getTenantContext($tenantId);
        } catch (\Throwable $e) {
            $tenant = [
                'tenant_name'  => 'Company',
                'tenant_logo'  => null,
            ];
        }

        return View::render(
            'Auth::login',
            [
                'title'        => 'Login',
                'error'        => $message,
                'company_logo' => $tenant['tenant_logo'],
                'tenant_name'  => $tenant['tenant_name'],
            ],
            'guest'
        );
    }

    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Tenant resolution
     */
    private function resolveTenantId(): int
    {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $parts = explode('.', $host);

        if ($host === 'localhost' || count($parts) < 2) {
            return $this->getDefaultTenantId();
        }

        $subdomain = $parts[0];

        if ($subdomain === 'www') {
            throw new RuntimeException('Tenant subdomain missing.');
        }

        $tenantRepo = new TenantRepository();
        $tenant = $tenantRepo->findActiveByCode($subdomain);

        if (!$tenant) {
            throw new RuntimeException('Invalid tenant.');
        }

        return (int) $tenant['id'];
    }

    private function getDefaultTenantId(): int
    {
        $tenantRepo = new TenantRepository();
        $tenant = $tenantRepo->findActiveByCode('default');

        if (!$tenant) {
            throw new RuntimeException('Default tenant not configured.');
        }

        return (int) $tenant['id'];
    }
}