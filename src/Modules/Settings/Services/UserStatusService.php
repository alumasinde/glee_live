<?php

namespace App\Modules\Settings\Services;

use App\Core\Audit;
use App\Modules\Auth\Repositories\UserRepository;
use PDO;
use RuntimeException;

/**
 * Activating and deactivating accounts.
 *
 * Deactivation is the reversible alternative to deleting a user: the row and
 * its history stay, but login, password reset and every live session stop
 * working, because UserRepository::findForLogin() and Auth::sessionIsValid()
 * both require users.is_active = 1.
 *
 * Two ways to lock a tenant out of its own user management are refused here:
 * deactivating yourself, and deactivating the last active administrator.
 */
final class UserStatusService
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @throws RuntimeException when the user does not belong to the tenant,
     *                          the actor targets themselves, or the tenant
     *                          would be left without an active administrator.
     */
    public function setActive(int $tenantId, int $userId, int $actorId, bool $active): void
    {
        $target = $this->find($tenantId, $userId);

        if (! $active) {
            if ($userId === $actorId) {
                throw new RuntimeException('You cannot deactivate your own account.');
            }

            if ((int) $target['is_active'] === 1 && ! $this->anotherActiveManagerExists($tenantId, $userId)) {
                throw new RuntimeException(
                    'This is the last active administrator. Give another user user-management access first.'
                );
            }
        }

        if ((int) $target['is_active'] === ($active ? 1 : 0)) {
            return; // already in the requested state
        }

        if ($active) {
            $this->db->prepare('UPDATE users SET is_active = 1 WHERE id = ? AND tenant_id = ?')
                ->execute([$userId, $tenantId]);
        } else {
            $this->db->prepare('UPDATE users SET is_active = 0 WHERE id = ? AND tenant_id = ?')
                ->execute([$userId, $tenantId]);

            // Ends sessions issued before now, including legacy ones whose
            // auth_at is missing and that only the is_active check would stop.
            (new UserRepository())->invalidateSessions($tenantId, $userId);
        }

        Audit::log(
            $active ? 'user.reactivated' : 'user.deactivated',
            'user',
            $userId,
            ['target_label' => $target['email']]
        );
    }

    private function find(int $tenantId, int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, email, is_active FROM users WHERE id = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$userId, $tenantId]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $user) {
            throw new RuntimeException('User not found.');
        }

        return $user;
    }

    /**
     * Would someone able to manage users remain after this account is disabled?
     * An administrator (users.is_admin) or a holder of users.update counts.
     */
    private function anotherActiveManagerExists(int $tenantId, int $userId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
              FROM users u
             WHERE u.tenant_id = :tenant_id
               AND u.is_active = 1
               AND u.id <> :user_id
               AND (
                    u.is_admin = 1
                    OR EXISTS (
                        SELECT 1
                          FROM user_roles ur
                          JOIN role_permissions rp
                            ON rp.role_id   = ur.role_id
                           AND rp.tenant_id = ur.tenant_id
                          JOIN permissions p ON p.id = rp.permission_id
                          JOIN modules m ON m.id = p.module_id AND m.name = 'users'
                          JOIN actions a ON a.id = p.action_id AND a.name = 'update'
                         WHERE ur.user_id   = u.id
                           AND ur.tenant_id = u.tenant_id
                    )
               )
             LIMIT 1
        ");

        $stmt->execute([':tenant_id' => $tenantId, ':user_id' => $userId]);

        return $stmt->fetchColumn() !== false;
    }
}
