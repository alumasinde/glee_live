<?php

namespace App\Modules\Platform\Services;

use App\Core\DB;
use App\Middleware\CSRFMiddleware;
use PDO;

/**
 * Time-boxed support access.
 *
 * The platform identity stays in its own session key; impersonation only adds
 * a tenant identity plus a session-bound grant row. The raw grant token lives
 * in the server-side session, the database stores only its SHA-256 hash, and
 * every request re-checks the row, expiry and account state. Starting or
 * ending a session regenerates the PHP session id and rotates the CSRF token.
 */
class PlatformImpersonationService
{
    public const SESSION_KEY = 'platform_impersonation';

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DB::connect();
    }

    /** @return array<string, mixed>|null */
    public static function active(): ?array
    {
        $session = $_SESSION[self::SESSION_KEY] ?? null;

        return is_array($session) && !empty($session['id']) ? $session : null;
    }

    /**
     * @return array{ok:bool, error?:string, tenant_id?:int, impersonation_id?:int}
     */
    public function start(
        int $platformUserId,
        int $tenantId,
        int $userId,
        ?string $reason = null
    ): array {
        $sessionPlatformId = (int) ($_SESSION['platform_user']['id'] ?? 0);

        if ($platformUserId <= 0 || $sessionPlatformId !== $platformUserId) {
            return ['ok' => false, 'error' => 'Your platform session is no longer valid.'];
        }

        if (self::active() !== null) {
            $existing = $this->validate();

            if (!empty($existing['ok'])) {
                return [
                    'ok'    => false,
                    'error' => 'A support session is already active. Return to the platform first.',
                ];
            }
        }

        $platformStmt = $this->db->prepare(
            'SELECT id, is_active, is_superadmin FROM platform_users WHERE id = ? LIMIT 1'
        );
        $platformStmt->execute([$platformUserId]);
        $platform = $platformStmt->fetch(PDO::FETCH_ASSOC);

        if (
            !$platform
            || (int) $platform['is_active'] !== 1
            || (int) $platform['is_superadmin'] !== 1
        ) {
            return ['ok' => false, 'error' => 'Only an active platform superadmin can start a support session.'];
        }

        $userStmt = $this->db->prepare(
            "SELECT u.id, u.tenant_id, u.email, u.first_name, u.last_name, u.is_admin,
                    t.code AS tenant_code, t.name AS tenant_name, t.logo AS tenant_logo,
                    (SELECT r.name
                       FROM user_roles ur
                       INNER JOIN roles r ON r.id = ur.role_id AND r.tenant_id = ur.tenant_id
                      WHERE ur.user_id = u.id AND ur.tenant_id = u.tenant_id
                      ORDER BY r.id ASC
                      LIMIT 1) AS role
               FROM users u
               INNER JOIN tenants t ON t.id = u.tenant_id
              WHERE u.id = ?
                AND u.tenant_id = ?
                AND u.is_active = 1
                AND t.is_active = 1
              LIMIT 1"
        );
        $userStmt->execute([$userId, $tenantId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            return ['ok' => false, 'error' => 'That tenant user is not available for support access.'];
        }

        $reason = trim((string) ($reason ?? ''));
        $reason = $reason === '' ? null : substr($reason, 0, 255);

        $rawToken = bin2hex(random_bytes(32));
        $startedAt = date('Y-m-d H:i:s');
        $expiresAt = date('Y-m-d H:i:s', time() + ($this->ttlMinutes() * 60));

        $insert = $this->db->prepare(
            'INSERT INTO platform_impersonations
                (platform_user_id, tenant_id, user_id, token_hash, reason, ip_address, started_at, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([
            $platformUserId,
            $tenantId,
            $userId,
            hash('sha256', $rawToken),
            $reason,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $startedAt,
            $expiresAt,
        ]);

        $impersonationId = (int) $this->db->lastInsertId();

        $this->establishSession($user, $impersonationId, $rawToken, $expiresAt);

        PlatformAuditService::log('tenant.impersonation_started', 'tenant', $tenantId, [
            'impersonation_id' => $impersonationId,
            'user_id'          => $userId,
            'user_email'       => (string) $user['email'],
            'reason'           => $reason,
            'expires_at'       => $expiresAt,
        ]);

        return ['ok' => true, 'tenant_id' => $tenantId, 'impersonation_id' => $impersonationId];
    }

    /**
     * Clear the support session, ending the grant row and auditing the reason.
     * Returns the tenant id so the caller can return the operator to its page.
     */
    public function stop(string $reason = 'operator_return'): ?int
    {
        $session = self::active();

        if ($session === null) {
            return null;
        }

        $tenantId = (int) ($session['tenant_id'] ?? 0);
        $impersonationId = (int) $session['id'];
        $userId = (int) ($session['user_id'] ?? 0);

        $this->finishRow($impersonationId, $reason);
        $this->clearSession();

        PlatformAuditService::log('tenant.impersonation_ended', 'tenant', $tenantId, [
            'impersonation_id' => $impersonationId,
            'user_id'          => $userId,
            'reason'           => $reason,
        ]);

        return $tenantId > 0 ? $tenantId : null;
    }

    /**
     * Re-check the active grant on every request.
     *
     * @return array{ok:bool, tenant_id:int|null, reason?:string}
     */
    public function validate(): array
    {
        $session = self::active();

        if ($session === null) {
            return ['ok' => false, 'tenant_id' => null];
        }

        $impersonationId = (int) $session['id'];
        $tenantId = (int) ($session['tenant_id'] ?? 0) ?: null;

        $stmt = $this->db->prepare(
            'SELECT i.tenant_id, i.token_hash, i.expires_at, i.ended_at,
                    p.is_active AS platform_active, p.is_superadmin,
                    u.is_active AS user_active,
                    t.is_active AS tenant_active
               FROM platform_impersonations i
               INNER JOIN platform_users p ON p.id = i.platform_user_id
               INNER JOIN users u ON u.id = i.user_id AND u.tenant_id = i.tenant_id
               INNER JOIN tenants t ON t.id = i.tenant_id
              WHERE i.id = ?
              LIMIT 1'
        );
        $stmt->execute([$impersonationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $reason = null;

        if (!$row) {
            $reason = 'missing';
        } elseif ($row['ended_at'] !== null) {
            $reason = 'ended';
        } elseif (strtotime((string) $row['expires_at']) <= time()) {
            $reason = 'expired';
        } elseif (!hash_equals(
            (string) $row['token_hash'],
            hash('sha256', (string) ($session['token'] ?? ''))
        )) {
            $reason = 'token_mismatch';
        } elseif ((int) $row['platform_active'] !== 1 || (int) $row['is_superadmin'] !== 1) {
            $reason = 'platform_inactive';
        } elseif ((int) $row['tenant_active'] !== 1) {
            $reason = 'tenant_inactive';
        } elseif ((int) $row['user_active'] !== 1) {
            $reason = 'user_inactive';
        }

        if ($reason !== null) {
            $endReason = $reason === 'expired' ? 'expired' : 'invalid';

            $this->finishRow($impersonationId, $endReason);
            $this->clearSession();

            PlatformAuditService::log(
                $reason === 'expired' ? 'tenant.impersonation_expired' : 'tenant.impersonation_invalid',
                'tenant',
                $tenantId,
                [
                    'impersonation_id' => $impersonationId,
                    'reason'           => $reason,
                ]
            );

            return ['ok' => false, 'tenant_id' => $tenantId, 'reason' => $reason];
        }

        return ['ok' => true, 'tenant_id' => (int) $row['tenant_id']];
    }

    public function ttlMinutes(): int
    {
        $configured = (int) (function_exists('config')
            ? config('platform.impersonation.ttl_minutes', 60)
            : 60);

        return max(5, min(240, $configured));
    }

    /**
     * @param array<string, mixed> $user
     */
    private function establishSession(
        array $user,
        int $impersonationId,
        string $rawToken,
        string $expiresAt
    ): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        CSRFMiddleware::rotate();

        $_SESSION['user'] = [
            'id'          => (int) $user['id'],
            'tenant_id'   => (int) $user['tenant_id'],
            'tenant_code' => (string) $user['tenant_code'],
            'email'       => (string) $user['email'],
            'first_name'  => (string) $user['first_name'],
            'last_name'   => (string) $user['last_name'],
            'role'        => (string) (($user['role'] ?? null) ?: ((int) $user['is_admin'] === 1 ? 'admin' : 'user')),
            'tenant_name' => (string) $user['tenant_name'],
            'tenant_logo' => $user['tenant_logo'] ?? null,
        ];

        $_SESSION['auth_at'] = date('Y-m-d H:i:s');

        $_SESSION[self::SESSION_KEY] = [
            'id'               => $impersonationId,
            'token'            => $rawToken,
            'tenant_id'        => (int) $user['tenant_id'],
            'user_id'          => (int) $user['id'],
            'platform_user_id' => (int) ($_SESSION['platform_user']['id'] ?? 0),
            'expires_at'       => $expiresAt,
        ];
    }

    private function clearSession(): void
    {
        unset($_SESSION['user'], $_SESSION['auth_at'], $_SESSION[self::SESSION_KEY]);

        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }

        CSRFMiddleware::rotate();
    }

    private function finishRow(int $impersonationId, string $reason): void
    {
        $actorId = (int) ($_SESSION['platform_user']['id'] ?? 0);
        $now = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare(
            'UPDATE platform_impersonations
                SET ended_at = COALESCE(ended_at, ?),
                    ended_by_platform_user_id = COALESCE(ended_by_platform_user_id, ?),
                    end_reason = COALESCE(end_reason, ?)
              WHERE id = ?
                AND ended_at IS NULL'
        );
        $stmt->execute([$now, $actorId > 0 ? $actorId : null, $reason, $impersonationId]);
    }
}
