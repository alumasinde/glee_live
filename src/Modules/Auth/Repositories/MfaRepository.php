<?php

namespace App\Modules\Auth\Repositories;

use App\Core\DB;
use PDO;
use RuntimeException;

/**
 * MfaRepository
 *
 * Every statement is tenant-scoped (tenant_id in WHERE *and* in the FK pair)
 * and prepared. Never SELECTs users.phone or users.password_hash into a view,
 * and never writes secret material into a column other than mfa_secret.
 */
class MfaRepository
{
    private PDO $db;

    public function __construct()
    {
        // No constructor injection here on purpose: App\Core\Container
        // auto-resolves every non-builtin type hint before looking at default
        // values, and PDO itself is not bindable.
        $this->db = DB::connect();

        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    /**
     * MFA state + verification material for one user. Used by the enrolment
     * controller and by the OTP challenge (which needs email for key
     * derivation and the current password hash for the disable check).
     */
    public function findForUser(int $tenantId, int $userId, bool $withTenantState = true): ?array
    {
        $tenantCondition = $withTenantState ? ' AND t.is_active = 1' : '';

        $stmt = $this->db->prepare("
            SELECT
                u.id,
                u.tenant_id,
                u.email,
                u.first_name,
                u.last_name,
                u.password_hash,
                u.is_active,
                u.mfa_enabled,
                u.mfa_secret,
                u.mfa_enabled_at,
                u.mfa_recovery_codes
            FROM users u
            INNER JOIN tenants t ON t.id = u.tenant_id
            WHERE u.tenant_id = :tenant_id
              AND u.id        = :user_id
              {$tenantCondition}
            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':user_id'   => $userId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Persist enrolment atomically: secret (already encrypted by the caller),
     * the enabled flag/timestamp and the recovery-code digests land together,
     * so there is no window where MFA is on with no secret or no codes.
     *
     * @param string[] $recoveryCodeDigests password_hash digests, never plaintext
     */
    public function enable(
        int    $tenantId,
        int    $userId,
        string $encryptedSecret,
        array  $recoveryCodeDigests
    ): void {
        $json = json_encode(
            $recoveryCodeDigests,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES
        );

        DB::transaction(function () use ($tenantId, $userId, $encryptedSecret, $json): void {
            $stmt = $this->db->prepare("
                UPDATE users
                SET mfa_secret         = :secret,
                    mfa_enabled        = 1,
                    mfa_enabled_at     = NOW(),
                    mfa_recovery_codes = :codes
                WHERE tenant_id = :tenant_id
                  AND id        = :user_id
                  AND is_active = 1
            ");

            $stmt->execute([
                ':secret'    => $encryptedSecret,
                ':codes'     => $json,
                ':tenant_id' => $tenantId,
                ':user_id'   => $userId,
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Unable to enable two-factor authentication.');
            }
        });
    }

    /**
     * Full teardown: flag, secret and recovery codes all go to NULL/0.
     */
    public function disable(int $tenantId, int $userId): void
    {
        DB::transaction(function () use ($tenantId, $userId): void {
            $stmt = $this->db->prepare("
                UPDATE users
                SET mfa_secret         = NULL,
                    mfa_enabled        = 0,
                    mfa_enabled_at     = NULL,
                    mfa_recovery_codes = NULL
                WHERE tenant_id = :tenant_id
                  AND id        = :user_id
            ");

            $stmt->execute([
                ':tenant_id' => $tenantId,
                ':user_id'   => $userId,
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Unable to disable two-factor authentication.');
            }
        });
    }

    /**
     * Is MFA already on for this user? (cheap check for route guards)
     */
    public function isEnabled(int $tenantId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT mfa_enabled
            FROM users
            WHERE tenant_id = :tenant_id
              AND id        = :user_id
            LIMIT 1
        ");

        $stmt->execute([':tenant_id' => $tenantId, ':user_id' => $userId]);

        return (int) $stmt->fetchColumn() === 1;
    }

    /**
     * Overwrite recovery-code digests (used if an install later adds a
     * "regenerate codes" action). Digests only.
     */
    public function storeRecoveryDigests(int $tenantId, int $userId, array $digests): void
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET mfa_recovery_codes = :codes
            WHERE tenant_id = :tenant_id
              AND id        = :user_id
              AND is_active = 1
        ");

        $stmt->execute([
            ':codes'     => json_encode($digests, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES),
            ':tenant_id' => $tenantId,
            ':user_id'   => $userId,
        ]);
    }
}
