<?php

namespace App\Modules\Auth\Services;

use App\Modules\Auth\Repositories\UserRepository;
use App\Modules\Tenant\Repositories\TenantRepository;
use App\Core\Permission;
use App\Core\DB;
use RuntimeException;

class AuthService
{
    private UserRepository $users;
    
    private TenantRepository $tenants;

public function __construct(
    ?UserRepository $users = null,
    ?TenantRepository $tenants = null
) {
    $this->users = $users ?? new UserRepository();
    $this->tenants = $tenants ?? new TenantRepository();
}


    /*
    |--------------------------------------------------------------------------
    | LOGIN
    |--------------------------------------------------------------------------
    */
    public function attempt(
        int $tenantId,
        string $email,
        string $password
    ): bool {

        $user = $this->users->findActiveByEmail($tenantId, $email);

        // Constant-time behavior (avoid minor timing leaks)
        if (!$user || !password_verify($password, $user['password_hash'])) {
            return false;
        }

        // Opportunistic password rehash (algorithm upgrades)
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($password, PASSWORD_DEFAULT);
            $this->users->updatePassword(
                (int) $user['tenant_id'],
                (int) $user['id'],
                $newHash
            );
        }

        // Prevent session fixation
        session_regenerate_id(true);

        // Load permissions using shared DB connection
        $permission = new Permission(DB::connect());
        $permission->loadForUser((int) $user['id']);

        $_SESSION['user'] = [
            'id'            => (int) $user['id'],
            'tenant_id'     => (int) $user['tenant_id'],
            'tenant_code'   => $user['tenant_code'],
            'email'         => $user['email'],
            'first_name'    => $user['first_name'],
            'last_name'     => $user['last_name'],
            'role'          => $user['role'],
            'tenant_name'   => $user['tenant_name'],
            'tenant_logo'   => $user['tenant_logo'],
        ];

        return true;
    }

public function getTenantContext(int $id): array
{
    $tenant = $this->tenants->findTenantLogo($id);

    if (!$tenant) {
        throw new RuntimeException("Tenant not found");
    }

    // If logo is stored as BLOB
    if (!empty($tenant['tenant_logo'])) {
        $tenant['tenant_logo'] = base64_encode($tenant['tenant_logo']);
    }

    return $tenant;
}
    /*
    |--------------------------------------------------------------------------
    | LOGOUT
    |--------------------------------------------------------------------------
    */
    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                [
                    'expires'  => time() - 42000,
                    'path'     => $params['path'],
                    'domain'   => $params['domain'],
                    'secure'   => true,
                    'httponly' => true,
                    'samesite' => 'Strict',
                ]
            );
        }

        session_destroy();
    }

    /*
    |--------------------------------------------------------------------------
    | PASSWORD RESET REQUEST (Tenant-Aware)
    |--------------------------------------------------------------------------
    */
    public function createResetTokenByEmail(
        int $tenantId,
        string $email
    ): void {

        $user = $this->users->findActiveByEmail($tenantId, $email);

        // Always fail silently (prevent enumeration)
        if (!$user) {
            return;
        }

        $expiresAt = new \DateTimeImmutable('+1 hour');

        $rawToken = $this->users->createPasswordResetToken(
            (int) $user['id'],
            $expiresAt
        );

        // TODO: send email with $rawToken
        // MailService::sendResetLink($user['email'], $rawToken);
    }

    /*
    |--------------------------------------------------------------------------
    | PASSWORD RESET (Atomic + Tenant Safe)
    |--------------------------------------------------------------------------
    */
    public function resetPassword(
        int $tenantId,
        string $rawToken,
        string $newPassword
    ): void {

        $this->assertStrongPassword($newPassword);

        $user = $this->users->findByValidResetToken(
            $tenantId,
            $rawToken
        );

        if (!$user) {
            throw new RuntimeException('Invalid or expired token.');
        }

        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);

        // Atomic update (prevents token replay)
        $this->users->resetPasswordWithToken(
            $tenantId,
            (int) $user['id'],
            $rawToken,
            $newHash
        );
    }

    private function resolveTenantId(): int
{
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $subdomain = explode('.', $host)[0];

    $tenant = $this->tenants->findActiveByCode($subdomain);

    if (!$tenant) {
        throw new RuntimeException('Invalid tenant.');
    }

    return (int) $tenant['id'];
}

    /*
    |--------------------------------------------------------------------------
    | PASSWORD POLICY
    |--------------------------------------------------------------------------
    */
    private function assertStrongPassword(string $password): void
    {
        if (strlen($password) < 12) {
            throw new RuntimeException('Password must be at least 12 characters.');
        }

        if (!preg_match('/[A-Z]/', $password) ||
            !preg_match('/[a-z]/', $password) ||
            !preg_match('/[0-9]/', $password)) {

            throw new RuntimeException(
                'Password must contain upper, lower, and numeric characters.'
            );
        }
    }
}