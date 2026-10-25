<?php

namespace App\Modules\Approval\Services;

use App\Core\Audit;
use App\Core\DB;
use App\Core\Notification\Events;
use App\Core\Notification\NotificationMessage;
use App\Core\Notification\Notifier;
use PDO;
use RuntimeException;

/**
 * Approval delegation (out-of-office coverage).
 *
 * A delegation moves NEW approvals only: rows already assigned to the delegator
 * stay theirs until an admin reassigns them (ApprovalService::reassign).
 * Scoping is by approval role — role_id NULL covers every role the delegator
 * approves for, otherwise only that role's steps are redirected.
 */
class DelegationService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DB::connect();
    }

    /* ------------------------------------------------------------ resolution */

    /**
     * Live delegations for a set of users, evaluated for one approval role.
     * The most specific match wins (role-scoped beats global), then the one
     * covering the longest.
     *
     * @param  list<int> $userIds
     * @return array<int, array{user_id:int, delegate_user_id:int, delegate_name:string, email:?string, role_id:?int, ends_at:string, note:?string}>
     */
    public function activeDelegatesFor(int $tenantId, array $userIds, ?int $roleId): array
    {
        $userIds = array_values(array_unique(array_map('intval', $userIds)));

        if (!$userIds) {
            return [];
        }

        $in = implode(',', array_fill(0, count($userIds), '?'));

        $sql = "
            SELECT ad.user_id,
                   ad.delegate_user_id,
                   CONCAT(d.first_name, ' ', d.last_name) AS delegate_name,
                   d.email,
                   ad.role_id,
                   ad.ends_at,
                   ad.note
            FROM approval_delegates ad
            INNER JOIN users d
                ON d.id = ad.delegate_user_id
               AND d.tenant_id = ad.tenant_id
               AND d.is_active = 1
            WHERE ad.tenant_id = ?
              AND ad.user_id IN ($in)
              AND ad.starts_at <= NOW()
              AND ad.ends_at   >  NOW()
        ";

        $params = [$tenantId, ...$userIds];

        if ($roleId !== null) {
            $sql .= " AND (ad.role_id IS NULL OR ad.role_id = ?)";
            $params[] = $roleId;
        } else {
            $sql .= " AND ad.role_id IS NULL";
        }

        // role-scoped first, then longest coverage — the first row per user wins.
        $sql .= " ORDER BY ad.user_id, ad.role_id IS NULL, ad.ends_at DESC, ad.id";

        $wins = [];

        foreach ($this->fetchAll($sql, $params) as $row) {
            $userId = (int) $row['user_id'];

            if (!isset($wins[$userId])) {
                $wins[$userId] = $row;
            }
        }

        return $wins;
    }

    /* ----------------------------------------------------------------- lists */

    /** Delegations this user has set up. */
    public function outgoing(int $tenantId, int $userId): array
    {
        return $this->fetchAll("
            SELECT ad.id, ad.delegate_user_id, ad.role_id, ad.starts_at, ad.ends_at, ad.note,
                   CONCAT(d.first_name, ' ', d.last_name) AS delegate_name,
                   d.email AS delegate_email,
                   r.name AS role_name,
                   ad.ends_at < NOW() AS has_expired
            FROM approval_delegates ad
            INNER JOIN users d ON d.id = ad.delegate_user_id AND d.tenant_id = ad.tenant_id
            LEFT JOIN roles r  ON r.id = ad.role_id AND r.tenant_id = ad.tenant_id
            WHERE ad.tenant_id = ? AND ad.user_id = ?
            ORDER BY ad.ends_at DESC, ad.id DESC
        ", [$tenantId, $userId]);
    }

    /** People who are currently covered by this user. */
    public function incoming(int $tenantId, int $userId): array
    {
        return $this->fetchAll("
            SELECT ad.id, ad.user_id, ad.role_id, ad.starts_at, ad.ends_at, ad.note,
                   CONCAT(u.first_name, ' ', u.last_name) AS delegator_name,
                   r.name AS role_name
            FROM approval_delegates ad
            INNER JOIN users u ON u.id = ad.user_id AND u.tenant_id = ad.tenant_id
            LEFT JOIN roles r  ON r.id = ad.role_id AND r.tenant_id = ad.tenant_id
            WHERE ad.tenant_id = ? AND ad.delegate_user_id = ?
              AND ad.starts_at <= NOW() AND ad.ends_at > NOW()
            ORDER BY ad.ends_at, ad.id
        ", [$tenantId, $userId]);
    }

    /** Active users that could be given coverage, with their approval roles. */
    public function candidates(int $tenantId, int $userId): array
    {
        return $this->fetchAll("
            SELECT u.id,
                   CONCAT(u.first_name, ' ', u.last_name) AS name,
                   u.email,
                   GROUP_CONCAT(DISTINCT r.name ORDER BY r.name SEPARATOR ', ') AS roles
            FROM users u
            LEFT JOIN user_roles ur ON ur.user_id = u.id AND ur.tenant_id = u.tenant_id
            LEFT JOIN roles r       ON r.id = ur.role_id AND r.tenant_id = ur.tenant_id
            WHERE u.tenant_id = ?
              AND u.is_active = 1
              AND u.id <> ?
            GROUP BY u.id, name, u.email
            ORDER BY name
        ", [$tenantId, $userId]);
    }

    /** Roles this user actually approves for, to scope a delegation. */
    public function approvableRoles(int $tenantId, int $userId): array
    {
        return $this->fetchAll("
            SELECT r.id, r.name
            FROM user_roles ur
            INNER JOIN roles r ON r.id = ur.role_id AND r.tenant_id = ur.tenant_id
            INNER JOIN workflow_steps ws
                ON ws.role_id = r.id
               AND ws.tenant_id = r.tenant_id
            WHERE ur.tenant_id = ? AND ur.user_id = ?
            GROUP BY r.id, r.name
            ORDER BY r.name
        ", [$tenantId, $userId]);
    }

    /* --------------------------------------------------------------- mutations */

    public function create(
        int $tenantId,
        int $userId,
        int $delegateUserId,
        ?int $roleId,
        string $startsAt,
        string $endsAt,
        ?string $note = null
    ): int {
        if ($delegateUserId === $userId) {
            throw new RuntimeException('You cannot delegate approvals to yourself.');
        }

        $delegate = $this->fetchOne("
            SELECT id, first_name, last_name
            FROM users
            WHERE id = ? AND tenant_id = ? AND is_active = 1
        ", [$delegateUserId, $tenantId])
            ?? throw new RuntimeException('The delegate must be an active user in this tenant.');

        if ($roleId !== null) {
            $this->fetchOne("
                SELECT id FROM roles WHERE id = ? AND tenant_id = ?
            ", [$roleId, $tenantId])
                ?? throw new RuntimeException('Selected approval role does not exist.');
        }

        if ($endsAt <= $startsAt) {
            throw new RuntimeException('The delegation must end after it starts.');
        }

        if ($endsAt <= date('Y-m-d H:i:s')) {
            throw new RuntimeException('The delegation end time must be in the future.');
        }

        $overlap = $this->fetchOne("
            SELECT id FROM approval_delegates
            WHERE tenant_id = ? AND user_id = ?
              AND (role_id = ? OR (? IS NULL AND role_id IS NULL) OR role_id IS NULL)
              AND starts_at < ? AND ends_at > ?
        ", [$tenantId, $userId, $roleId, $roleId, $endsAt, $startsAt]);

        if ($overlap) {
            throw new RuntimeException('This already overlaps a delegation you have for the same period.');
        }

        $stmt = $this->db->prepare("
            INSERT INTO approval_delegates
                (tenant_id, user_id, delegate_user_id, role_id, starts_at, ends_at, note)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([$tenantId, $userId, $delegateUserId, $roleId, $startsAt, $endsAt, $note]);

        $id = (int) $this->db->lastInsertId();

        Audit::log('approval.delegate_created', 'approval_delegate', $id, [
            'delegate_user_id' => $delegateUserId,
            'role_id'          => $roleId,
            'starts_at'        => $startsAt,
            'ends_at'          => $endsAt,
            'target_label'     => sprintf('%s → %s', $userId, $delegateUserId),
        ]);

        $this->notifyDelegate($tenantId, $userId, $delegateUserId, $delegate, $endsAt, $note);

        return $id;
    }

    public function delete(int $tenantId, int $userId, int $id): bool
    {
        $delegation = $this->fetchOne("
            SELECT id, delegate_user_id, ends_at
            FROM approval_delegates
            WHERE id = ? AND tenant_id = ? AND user_id = ?
        ", [$id, $tenantId, $userId])
            ?? throw new RuntimeException('Delegation not found.');

        $stmt = $this->db->prepare("
            DELETE FROM approval_delegates WHERE id = ? AND tenant_id = ? AND user_id = ?
        ");

        $stmt->execute([$id, $tenantId, $userId]);

        Audit::log('approval.delegate_removed', 'approval_delegate', $id, [
            'delegate_user_id' => (int) $delegation['delegate_user_id'],
            'target_label'     => (string) $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /* ---------------------------------------------------------------- helpers */

    private function notifyDelegate(
        int $tenantId,
        int $userId,
        int $delegateUserId,
        array $delegate,
        string $endsAt,
        ?string $note
    ): void {
        try {
            $delegator = $this->fetchOne("
                SELECT CONCAT(first_name, ' ', last_name) AS name FROM users
                WHERE id = ? AND tenant_id = ?
            ", [$userId, $tenantId]);

            $message = new NotificationMessage(
                Events::DELEGATED_IN,
                sprintf('You are now covering approvals for %s', (string) ($delegator['name'] ?? 'a colleague')),
                sprintf(
                    'Approvals that would go to %s now come to you until %s.%s',
                    (string) ($delegator['name'] ?? 'a colleague'),
                    date('d M Y H:i', strtotime($endsAt)),
                    $note ? ' Note: ' . $note : ''
                )
            );

            Notifier::make()->notify($tenantId, $delegateUserId, $message);
        } catch (\Throwable $e) {
            // A coverage arrangement is still valid if the courtesy notice fails.
            error_log('[Delegation] delegate notice failed: ' . $e->getMessage());
        }
    }

    private function fetchOne(string $sql, array $params): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    private function fetchAll(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
