<?php

namespace App\Modules\Platform\Services;

use App\Modules\Platform\Repositories\PlatformUserRepository;

/**
 * Platform identity is deliberately stored under its own session key. A tenant
 * session can never satisfy the platform guard, and signing into the platform
 * never grants a tenant session.
 */
class PlatformAuthService
{
    public const SESSION_KEY = 'platform_user';

    private PlatformUserRepository $users;

    public function __construct(?PlatformUserRepository $users = null)
    {
        $this->users = $users ?? new PlatformUserRepository();
    }

    public static function check(): bool
    {
        return isset($_SESSION[self::SESSION_KEY]);
    }

    public static function user(): ?array
    {
        $user = $_SESSION[self::SESSION_KEY] ?? null;

        return is_array($user) ? $user : null;
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findActiveByEmail($email);

        if (!$user || !password_verify($password, (string) $user['password_hash'])) {
            PlatformAuditService::log('platform.login_failed', null, null, ['email' => $email]);
            return false;
        }

        if (password_needs_rehash((string) $user['password_hash'], PASSWORD_BCRYPT, ['cost' => 12])) {
            $this->users->updatePasswordHash((int) $user['id'], password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]));
        }

        if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION[self::SESSION_KEY] = [
            'id'    => (int) $user['id'],
            'name'  => (string) $user['name'],
            'email' => (string) $user['email'],
        ];

        $this->users->touchLogin((int) $user['id']);
        PlatformAuditService::log('platform.login', 'platform_user', (int) $user['id']);

        return true;
    }

    public static function logout(): void
    {
        unset($_SESSION[self::SESSION_KEY]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
