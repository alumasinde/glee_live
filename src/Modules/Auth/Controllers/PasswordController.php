<?php

namespace App\Modules\Auth\Controllers;

use App\Core\View;
use App\Core\Request;
use App\Core\Audit;
use App\Core\Response;
use App\Modules\Auth\Services\AuthService;
use RuntimeException;

class PasswordController
{
    private AuthService $auth;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
    }

    /* =========================================================
     * FORGOT FORM
     * ========================================================= */

    public function forgot()
    {
        return View::render('Auth::forgot-password', [
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    /* =========================================================
     * SEND RESET LINK
     * ========================================================= */

    public function sendReset(Request $request)
    {
        $this->assertValidCsrf($request->input('csrf_token'));

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

        return View::render('Auth::reset-password', [
            'token'      => $token,
            'csrf_token' => $this->generateCsrfToken()
        ]);
    }

    /* =========================================================
     * HANDLE RESET
     * ========================================================= */

    public function reset(Request $request)
    {
        $this->assertValidCsrf($request->input('csrf_token'));

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
            $this->auth->resetPassword($tenantId, $token, $password);

              $user = $_SESSION['user'];

          Audit::log(
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
    | CSRF
    |--------------------------------------------------------------------------
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

    /*
    |--------------------------------------------------------------------------
    | TENANT CONTEXT
    |--------------------------------------------------------------------------
    */

    private function resolveTenantId(): int
    {
        if (empty($_SESSION['tenant_id'])) {
            throw new RuntimeException('Tenant context missing.');
        }

        return (int) $_SESSION['tenant_id'];
    }
}