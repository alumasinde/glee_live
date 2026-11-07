<?php

namespace App\Modules\Platform\Services;

use App\Core\DB;
use PDO;
use RuntimeException;

/**
 * Suspending a tenant is an access action, not a data action:
 *
 *   - tenants.is_active is cleared, so login lookups (which join the tenant)
 *     stop authenticating and subdomain resolution finds nothing;
 *   - every tenant user gets tokens_invalid_before stamped, so sessions issued
 *     before the suspension fail App\Core\Auth::sessionIsValid() on their next
 *     request (the same mechanism a password change uses);
 *   - every open support grant is ended with cause tenant_suspended, and the
 *     per-request impersonation guard clears the tenant identity;
 *   - reactivation restores sign-in without resurrecting those old sessions.
 *
 * Nothing is deleted, and module entitlements are untouched.
 */
class TenantStatusService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DB::connect();
    }

    /**
     * @return array{changed:bool, revoked_users:int, ended_support_sessions:int}
     */
    public function suspend(int $tenantId, ?int $platformUserId = null, ?string $reason = null): array
    {
        return $this->apply($tenantId, false, $platformUserId, $reason);
    }

    /**
     * @return array{changed:bool, revoked_users:int, ended_support_sessions:int}
     */
    public function reactivate(int $tenantId, ?int $platformUserId = null, ?string $reason = null): array
    {
        return $this->apply($tenantId, true, $platformUserId, $reason);
    }

    /**
     * @return array{changed:bool, revoked_users:int, ended_support_sessions:int}
     */
    private function apply(
        int $tenantId,
        bool $activate,
        ?int $platformUserId,
        ?string $reason
    ): array {
        if ($tenantId <= 0) {
            throw new RuntimeException('Tenant not found.');
        }

        $ownTransaction = !$this->db->inTransaction();

        if ($ownTransaction) {
            $this->db->beginTransaction();
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT id, name, code, is_active FROM tenants WHERE id = ? FOR UPDATE'
            );
            $stmt->execute([$tenantId]);
            $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tenant) {
                throw new RuntimeException('Tenant not found.');
            }

            $alreadyActive = (int) $tenant['is_active'] === 1;

            if ($alreadyActive === $activate) {
                if ($ownTransaction) {
                    $this->db->commit();
                }

                return ['changed' => false, 'revoked_users' => 0, 'ended_support_sessions' => 0];
            }

            $update = $this->db->prepare('UPDATE tenants SET is_active = ? WHERE id = ?');
            $update->execute([$activate ? 1 : 0, $tenantId]);

            $revokedUsers = 0;
            $endedSupportSessions = 0;
            $stamp = date('Y-m-d H:i:s');

            if (!$activate) {
                $revoke = $this->db->prepare(
                    'UPDATE users SET tokens_invalid_before = ? WHERE tenant_id = ?'
                );
                $revoke->execute([$stamp, $tenantId]);
                $revokedUsers = $revoke->rowCount();

                $endSupport = $this->db->prepare(
                    "UPDATE platform_impersonations
                        SET ended_at = ?,
                            ended_by_platform_user_id = ?,
                            end_reason = 'tenant_suspended'
                      WHERE tenant_id = ?
                        AND ended_at IS NULL"
                );
                $endSupport->execute([$stamp, $platformUserId, $tenantId]);
                $endedSupportSessions = $endSupport->rowCount();
            }

            $reason = trim((string) ($reason ?? ''));
            $reason = $reason === '' ? null : substr($reason, 0, 255);

            PlatformAuditService::log(
                $activate ? 'tenant.reactivated' : 'tenant.suspended',
                'tenant',
                $tenantId,
                [
                    'code'                  => (string) $tenant['code'],
                    'reason'                => $reason,
                    'revoked_users'         => $revokedUsers,
                    'ended_support_sessions' => $endedSupportSessions,
                    'actor'                 => $platformUserId,
                ]
            );

            if ($ownTransaction) {
                $this->db->commit();
            }

            return [
                'changed'                => true,
                'revoked_users'          => $revokedUsers,
                'ended_support_sessions' => $endedSupportSessions,
            ];
        } catch (\Throwable $e) {
            if ($ownTransaction) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }
}
