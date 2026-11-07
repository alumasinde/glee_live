<?php

namespace App\Modules\Auth\Repositories;

use App\Core\DB;
use PDO;
use RuntimeException;

class UserRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();

        // Enforce hardened PDO behavior (in case DB::connect doesn't)
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    /**
     * Normalize email for canonical comparison.
     */
    private function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    /**
     * Fetch active user by tenant + email.
     */
    public function findActiveByEmail(int $tenantId, string $email): ?array
    {
        $email = $this->normalizeEmail($email);

        $stmt = $this->db->prepare("
          SELECT 
    u.id,
    u.tenant_id,
    u.first_name,
    u.last_name,
    u.email,
    u.password_hash,
    u.is_active,
    t.code AS tenant_code,
    t.name AS tenant_name,
    t.logo AS tenant_logo,
    MIN(r.id) AS role_id,
    GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role
FROM users u
INNER JOIN tenants t 
    ON t.id = u.tenant_id
LEFT JOIN user_roles ur
    ON ur.user_id = u.id
   AND ur.tenant_id = u.tenant_id
LEFT JOIN roles r
    ON r.id = ur.role_id
   AND r.tenant_id = u.tenant_id
WHERE u.tenant_id = :tenant_id
  AND u.email = :email
  AND u.is_active = 1
  AND t.is_active = 1
GROUP BY u.id
LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':email'     => $email
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Login-path lookup: identity columns PLUS the two-factor / lockout state
     * from migration 0022. Kept separate from findActiveByEmail() on purpose —
     * the password-reset flow does not need (and must not be broken by) the
     * security columns. Deliberately does NOT select users.phone (PII).
     */
    public function findForLogin(int $tenantId, string $email): ?array
    {
        $email = $this->normalizeEmail($email);

        $stmt = $this->db->prepare("
            SELECT
                u.id,
                u.tenant_id,
                u.first_name,
                u.last_name,
                u.email,
                u.password_hash,
                u.is_active,
                u.mfa_enabled,
                u.mfa_secret,
                u.mfa_recovery_codes,
                u.failed_attempts,
                u.locked_until,
                u.tokens_invalid_before,
                t.code AS tenant_code,
                t.name AS tenant_name,
                t.logo AS tenant_logo,
                MIN(r.id) AS role_id,
                GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role
            FROM users u
            INNER JOIN tenants t
                ON t.id = u.tenant_id
            LEFT JOIN user_roles ur
                ON ur.user_id = u.id
               AND ur.tenant_id = u.tenant_id
            LEFT JOIN roles r
                ON r.id = ur.role_id
               AND r.tenant_id = u.tenant_id
            WHERE u.tenant_id = :tenant_id
              AND u.email     = :email
              AND u.is_active = 1
              AND t.is_active = 1
            GROUP BY u.id
            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':email'     => $email
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Re-hydrate a login that is parked in the two-factor challenge. Used after
     * the password step only, so a user disabled or locked out mid-challenge
     * cannot complete. Returns null when the account is no longer usable.
     */
    public function findForLoginById(int $tenantId, int $userId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                u.id,
                u.tenant_id,
                u.first_name,
                u.last_name,
                u.email,
                u.password_hash,
                u.is_active,
                u.mfa_enabled,
                u.mfa_secret,
                u.mfa_recovery_codes,
                u.failed_attempts,
                u.locked_until,
                u.tokens_invalid_before,
                t.code AS tenant_code,
                t.name AS tenant_name,
                t.logo AS tenant_logo,
                MIN(r.id) AS role_id,
                GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS role
            FROM users u
            INNER JOIN tenants t
                ON t.id = u.tenant_id
            LEFT JOIN user_roles ur
                ON ur.user_id = u.id
               AND ur.tenant_id = u.tenant_id
            LEFT JOIN roles r
                ON r.id = ur.role_id
               AND r.tenant_id = u.tenant_id
            WHERE u.tenant_id = :tenant_id
              AND u.id        = :user_id
              AND u.is_active = 1
              AND t.is_active = 1
            GROUP BY u.id
            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':user_id'   => $userId
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * DB-backed lockout, complementary to the IP/email rate limiter.
     *
     * Single atomic statement: MySQL applies SET assignments left-to-right, so
     * failed_attempts is already the incremented value when locked_until is
     * evaluated. Placeholders are uniquely named because native prepared
     * statements (EMULATE_PREPARES=false) cannot reuse one.
     */
    public function registerFailedAttempt(
        int $tenantId,
        int $userId,
        int $maxAttempts,
        int $lockoutMinutes
    ): void {
        $stmt = $this->db->prepare("
            UPDATE users
            SET failed_attempts = failed_attempts + 1,
                locked_until = CASE
                    WHEN failed_attempts >= :max_attempts
                        THEN DATE_ADD(NOW(), INTERVAL :lock_minutes MINUTE)
                    ELSE locked_until
                END
            WHERE tenant_id = :tenant_id
              AND id        = :user_id
        ");

        $stmt->execute([
            ':max_attempts' => $maxAttempts,
            ':lock_minutes' => $lockoutMinutes,
            ':tenant_id'    => $tenantId,
            ':user_id'      => $userId,
        ]);
    }

    /**
     * Successful authentication: clear the lockout counters and stamp
     * tokens_invalid_before so a later session/token validity check has a
     * database basis (compare it with the session's own auth timestamp).
     */
    public function recordLoginSuccess(int $tenantId, int $userId): string
    {
        // Stamped from PHP, not NOW(): App\Core\Auth compares it against a
        // session value this process wrote, so both sides must share one clock
        // and timezone.
        $stamp = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare("
            UPDATE users
            SET failed_attempts        = 0,
                locked_until           = NULL,
                tokens_invalid_before  = :stamp
            WHERE tenant_id = :tenant_id
              AND id        = :user_id
        ");

        $stmt->execute([
            ':stamp'     => $stamp,
            ':tenant_id' => $tenantId,
            ':user_id'   => $userId,
        ]);

        return $stamp;
    }

    /**
     * Revoke every session issued before now (password change or reset).
     * Returns the stamp so the caller can keep its own session usable.
     */
    public function invalidateSessions(int $tenantId, int $userId): string
    {
        $stamp = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare("
            UPDATE users
            SET tokens_invalid_before = :stamp
            WHERE tenant_id = :tenant_id
              AND id        = :user_id
        ");

        $stmt->execute([
            ':stamp'     => $stamp,
            ':tenant_id' => $tenantId,
            ':user_id'   => $userId,
        ]);

        return $stamp;
    }

    /**
     * Generate and store a reset token (one live token per user).
     * Returns raw token for email delivery; consumed on reset.
     */
    public function createPasswordResetToken(int $userId, \DateTimeImmutable $expiresAt): string
    {
        $rawToken = bin2hex(random_bytes(32));

        $stmt = $this->db->prepare("
            SELECT tenant_id FROM users
            WHERE id = :id AND is_active = 1
        ");
        $stmt->execute([':id' => $userId]);
        $tenantId = $stmt->fetchColumn();

        if ($tenantId === false) {
            throw new RuntimeException('Failed to store reset token.');
        }

        $this->db->prepare("
            DELETE FROM password_resets
            WHERE user_id = :id AND tenant_id = :tenant_id
        ")->execute([':id' => $userId, ':tenant_id' => $tenantId]);

        $stmt = $this->db->prepare("
            INSERT INTO password_resets (tenant_id, user_id, token, expires_at)
            VALUES (:tenant_id, :user_id, :token, :expires)
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':user_id'   => $userId,
            ':token'     => $rawToken,
            ':expires'   => $expiresAt->format('Y-m-d H:i:s'),
        ]);

        return $rawToken; // send this via email
    }

    /**
     * Lookup valid reset token (tenant-safe).
     */
    public function findByValidResetToken(
        int $tenantId,
        string $rawToken
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT 
                u.id,
                u.tenant_id,
                u.email,
                pr.expires_at
            FROM password_resets pr
            INNER JOIN users u 
                ON u.id = pr.user_id AND u.tenant_id = pr.tenant_id
            INNER JOIN tenants t ON t.id = u.tenant_id
            WHERE pr.tenant_id = :tenant_id
              AND pr.token = :token
              AND pr.expires_at > NOW()
              AND u.is_active = 1
              AND t.is_active = 1
            GROUP BY u.id
            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':token'     => $rawToken
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Atomically update password and consume the token.
     * The DELETE is the guard: only one caller can win a given token.
     */
    public function resetPasswordWithToken(
        int $tenantId,
        int $userId,
        string $rawToken,
        string $newPasswordHash
    ): void {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                DELETE FROM password_resets
                WHERE tenant_id = :tenant_id
                  AND user_id = :user_id
                  AND token = :token
                  AND expires_at > NOW()
            ");

            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':user_id'   => $userId,
                ':token'     => $rawToken
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Invalid or expired reset token.');
            }

            $stmt = $this->db->prepare("
                UPDATE users
                SET password_hash = :password
                WHERE id = :user_id
                  AND tenant_id = :tenant_id
                  AND is_active = 1
            ");

            $stmt->execute([
                ':password'  => $newPasswordHash,
                ':user_id'   => $userId,
                ':tenant_id' => $tenantId
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Invalid or expired reset token.');
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Explicit token invalidation (optional administrative action).
     */
    public function invalidateResetToken(int $tenantId, int $userId): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM password_resets
            WHERE user_id = :id
              AND tenant_id = :tenant_id
        ");

        $stmt->execute([
            ':id'        => $userId,
            ':tenant_id' => $tenantId
        ]);
    }

    /**
     * Direct password update (authenticated context).
     */
    public function updatePassword(
        int $tenantId,
        int $userId,
        string $passwordHash
    ): void {
        $this->invalidateResetToken($tenantId, $userId);

        $stmt = $this->db->prepare("
            UPDATE users
            SET password_hash = :password
            WHERE id = :id
              AND tenant_id = :tenant_id
              AND is_active = 1
        ");

        $stmt->execute([
            ':password'  => $passwordHash,
            ':id'        => $userId,
            ':tenant_id' => $tenantId
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Password update failed.');
        }
    }
}