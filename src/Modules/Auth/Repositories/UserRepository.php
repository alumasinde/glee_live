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