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
    r.id AS role_id,
    r.name AS role
FROM users u
INNER JOIN tenants t 
    ON t.id = u.tenant_id
INNER JOIN user_roles ur 
    ON ur.user_id = u.id
INNER JOIN roles r 
    ON r.id = ur.role_id
WHERE u.tenant_id = :tenant_id
  AND u.email = :email
  AND u.is_active = 1
  AND t.is_active = 1
LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':email'     => $email
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Generate and store hashed reset token.
     * Returns raw token for email delivery.
     */
    public function createPasswordResetToken(int $userId, \DateTimeImmutable $expiresAt): string
    {
        $rawToken = bin2hex(random_bytes(32));
        $hashedToken = hash('sha256', $rawToken);

        $stmt = $this->db->prepare("
            UPDATE users
            SET reset_token = :token,
                reset_expires = :expires
            WHERE id = :id
              AND is_active = 1
        ");

        $stmt->execute([
            ':token'   => $hashedToken,
            ':expires' => $expiresAt->format('Y-m-d H:i:s'),
            ':id'      => $userId
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Failed to store reset token.');
        }

        return $rawToken; // send this via email
    }

    /**
     * Lookup valid reset token (tenant-safe).
     */
    public function findByValidResetToken(
        int $tenantId,
        string $rawToken
    ): ?array {
        $hashedToken = hash('sha256', $rawToken);

        $stmt = $this->db->prepare("
            SELECT 
                u.id,
                u.tenant_id,
                u.email,
                u.reset_expires
            FROM users u
            INNER JOIN tenants t ON t.id = u.tenant_id
            WHERE u.tenant_id = :tenant_id
              AND u.reset_token = :token
              AND u.reset_expires > NOW()
              AND u.is_active = 1
              AND t.is_active = 1
            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':token'     => $hashedToken
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Atomically update password and invalidate token.
     * Prevents race condition token reuse.
     */
    public function resetPasswordWithToken(
        int $tenantId,
        int $userId,
        string $rawToken,
        string $newPasswordHash
    ): void {
        $hashedToken = hash('sha256', $rawToken);

        $stmt = $this->db->prepare("
            UPDATE users u
            INNER JOIN tenants t ON t.id = u.tenant_id
            SET u.password_hash = :password,
                u.reset_token = NULL,
                u.reset_expires = NULL
            WHERE u.id = :user_id
              AND u.tenant_id = :tenant_id
              AND u.reset_token = :token
              AND u.reset_expires > NOW()
              AND u.is_active = 1
              AND t.is_active = 1
        ");

        $stmt->execute([
            ':password'  => $newPasswordHash,
            ':user_id'   => $userId,
            ':tenant_id' => $tenantId,
            ':token'     => $hashedToken
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException('Invalid or expired reset token.');
        }
    }

    /**
     * Explicit token invalidation (optional administrative action).
     */
    public function invalidateResetToken(int $tenantId, int $userId): void
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET reset_token = NULL,
                reset_expires = NULL
            WHERE id = :id
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
        $stmt = $this->db->prepare("
            UPDATE users
            SET password_hash = :password,
                reset_token = NULL,
                reset_expires = NULL
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