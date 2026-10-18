<?php

namespace App\Modules\Auth\Controllers;

use App\Core\View;
use App\Core\Request;
use App\Core\Audit;
use App\Core\Response;
use App\Modules\Auth\Services\AuthService;
use App\Modules\Tenant\Repositories\TenantRepository;
use RuntimeException;

class PasswordController
{
    private AuthService $auth;
    private TenantRepository $tenantRepo;

    public function __construct(?AuthService $auth = null, ?TenantRepository $tenantRepo = null)
    {
        $this->auth = $auth ?? new AuthService();
        $this->tenantRepo = $tenantRepo ?? new TenantRepository();
    }

    /* =========================================================
     * FORGOT FORM
     * ========================================================= */

    public function forgot()
    {
        try {
            $tenantId = $this->resolveTenantId();
            $tenant = $this->auth->getTenantContext($tenantId);
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            http_response_code(400);
            exit('Invalid tenant');
        }

        return View::render(
            'Auth::forgot-password',
            [
                'title'        => 'Forgot Password',
                'company_logo' => $tenant['tenant_logo'],
                'tenant_name'  => $tenant['tenant_name'],
            ],
            'guest'
        );
    }

    /* =========================================================
     * SEND RESET LINK
     * ========================================================= */

    public function sendReset(Request $request)
    {
        $tenantId = $this->resolveTenantId();
        $email = trim((string) $request->input('email'));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->flashRedirect(
                '/forgot-password',
                'Please enter a valid email address.',
                'danger'
            );
        }

        try {
            $this->auth->createResetTokenByEmail($tenantId, $email);
        } catch (\Throwable $e) {
            // Log internally only
        }

        // Prevent enumeration
        return $this->flashRedirect(
            '/forgot-password',
            'If the email exists, a reset link was sent.',
            'success'
        );
    }

    /* =========================================================
     * RESET FORM
     * ========================================================= */

    public function resetForm(Request $request)
    {
        $token = trim((string) $request->input('token'));

        if (!$this->isValidTokenFormat($token)) {
            return Response::abort(400, 'Invalid reset token.');
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
            'Auth::reset-password',
            [
                'title'        => 'Reset Password',
                'token'        => $token,
                'company_logo' => $tenant['tenant_logo'],
                'tenant_name'  => $tenant['tenant_name'],
            ],
            'guest'
        );
    }

    /* =========================================================
     * HANDLE RESET
     * ========================================================= */

    public function reset(Request $request)
    {
        $tenantId = $this->resolveTenantId();
        $token = trim((string) $request->input('token'));
        $password = (string) $request->input('password');
        $confirm = (string) $request->input('password_confirmation');

        if (
            !$this->isValidTokenFormat($token) ||
            empty($password) ||
            $password !== $confirm
        ) {
            return $this->flashRedirect(
                '/reset-password?token=' . urlencode($token),
                'Invalid input.',
                'danger'
            );
        }

        try {
            $user = $this->auth->resetPassword($tenantId, $token, $password);

            Audit::system(
                $tenantId,
                'user.password_reset',
                'user',
                (int) $user['id'],
                [
                    'email' => $user['email']
                ]
            );

            return $this->flashRedirect(
                '/login',
                'Password reset successfully. You can now login.',
                'success'
            );

        } catch (\Throwable $e) {

            return $this->flashRedirect(
                '/reset-password?token=' . urlencode($token),
                'Invalid or expired reset token.',
                'danger'
            );
        }
    }

    /* =========================================================
     * HELPERS
     * ========================================================= */

    private function flashRedirect(string $url, string $message, string $type)
    {
        $_SESSION['flash'] = [
            'message' => $message,
            'type'    => $type
        ];

        header("Location: $url");
        exit;
    }

    private function isValidTokenFormat(string $token): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/', $token);
    }

    /*
    |--------------------------------------------------------------------------
    | TENANT CONTEXT
    |--------------------------------------------------------------------------
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

        $tenant = $this->tenantRepo->findActiveByCode($subdomain);

        if (!$tenant) {
            throw new RuntimeException('Invalid tenant.');
        }

        return (int) $tenant['id'];
    }

    private function getDefaultTenantId(): int
    {
        $tenant = $this->tenantRepo->findActiveByCode('default');

        if (!$tenant) {
            throw new RuntimeException('Default tenant not configured.');
        }

        return (int) $tenant['id'];
    }
}