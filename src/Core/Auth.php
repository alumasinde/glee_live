<?php
namespace App\Core;

class Auth
{
    /** Memoised result of the session-validity check for this request. */
    private static ?bool $sessionValid = null;

    public static function user(): ?array
    {
        return $_SESSION['user'] ?? null;
    }

    public static function id(): ?int
    {
        return $_SESSION['user']['id'] ?? null;
    }

    public static function tenantId(): ?int
    {
        return $_SESSION['user']['tenant_id'] ?? null;
    }

    public static function check(): bool
    {
        return isset($_SESSION['user']);
    }

    /**
     * Is the stored session still backed by a usable account row?
     *
     * Every login stamps users.tokens_invalid_before (and so does a password
     * change or reset). A session whose auth_at predates that stamp was issued
     * for a password that no longer exists, or for an account that has since
     * been disabled, so it must not keep working.
     *
     * Both values are PHP-formatted datetimes, which keeps the comparison
     * independent of MySQL's clock and timezone.
     */
    public static function sessionIsValid(): bool
    {
        $user = $_SESSION['user'] ?? null;

        if (!$user) {
            return false;
        }

        $authAt = $_SESSION['auth_at'] ?? null;

        // Sessions issued before auth_at existed (or built by callers that only
        // populate $_SESSION['user']) have no password stamp to compare, but
        // they must still lose access when the account or tenant is disabled
        // (for example by a platform suspension).
        if (!is_string($authAt) || $authAt === '') {
            $row = DB::selectOne(
                'SELECT 1 AS ok
                   FROM users u
                   INNER JOIN tenants t
                     ON t.id = u.tenant_id
                    AND t.is_active = 1
                  WHERE u.id = :id
                    AND u.tenant_id = :tenant_id
                    AND u.is_active = 1
                  LIMIT 1',
                [
                    ':id'        => (int) ($user['id'] ?? 0),
                    ':tenant_id' => (int) ($user['tenant_id'] ?? 0),
                ]
            );

            return self::$sessionValid = $row !== null;
        }

        if (self::$sessionValid !== null) {
            return self::$sessionValid;
        }

        $row = DB::selectOne(
            'SELECT 1 AS ok
               FROM users u
               INNER JOIN tenants t
                 ON t.id = u.tenant_id
                AND t.is_active = 1
              WHERE u.id = :id
                AND u.tenant_id = :tenant_id
                AND u.is_active = 1
                AND (u.tokens_invalid_before IS NULL OR u.tokens_invalid_before <= :auth_at)
              LIMIT 1',
            [
                ':id'        => (int) ($user['id'] ?? 0),
                ':tenant_id' => (int) ($user['tenant_id'] ?? 0),
                ':auth_at'   => $authAt,
            ]
        );

        return self::$sessionValid = $row !== null;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user'] = $user;

        self::$sessionValid = null;
    }

    /** Drop the local identity without destroying the session (used when stale). */
    public static function forget(): void
    {
        unset($_SESSION['user'], $_SESSION['auth_at']);

        self::$sessionValid = null;
    }

    public static function logout(): void
    {
        self::$sessionValid = null;
        session_destroy();
    }
}