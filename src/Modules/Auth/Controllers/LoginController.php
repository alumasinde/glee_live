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
                'csrf_token'   => $this->generateCsrfToken(),
                'company_logo' => $tenant['tenant_logo'],
                'tenant_name'  => $tenant['tenant_name'],
            ],
            'guest'
        );
    }

public function store(Request $request)
{
    try {
        $this->assertValidCsrf($request->input('csrf_token'));
    } catch (\Throwable $e) {
        return $this->fail($request, 'Session expired. Please refresh and try again.', 419);
    }

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
    | AUTH FAILURE
    |--------------------------------------------------------------------------
    */
    if (!$this->auth->attempt($tenantId, $email, $password)) {

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
        return $this->json([
            'redirect' => '/dashboard'
        ], 200);
    }

    header('Location: /dashboard');
    exit;
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
                'csrf_token'   => $this->generateCsrfToken(),
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
     * CSRF
     */
    private function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    private function assertValidCsrf(?string $token): void
    {
        if (
            empty($token) ||
            empty($_SESSION['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $token)
        ) {
            throw new RuntimeException('Invalid CSRF token.');
        }
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