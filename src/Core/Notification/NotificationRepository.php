<?php

namespace App\Core\Notification;

use App\Core\DB;
use App\Core\Mailer;
use PDO;

/**
 * Persistence for every notification surface: the in-app feed, the SMS/push
 * outbox, device tokens, per-user preferences and the email outbox retry queue.
 *
 * Rows written here inside a business transaction commit or roll back with it —
 * only the actual delivery (SMTP / HTTP) happens outside, after the commit.
 */
final class NotificationRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DB::connect();
    }

    /* ---------------------------------------------------------------- contact */

    public function userContact(int $tenantId, int $userId): ?array
    {
        return $this->fetchOne("
            SELECT id, tenant_id, email, first_name, last_name,
                   CONCAT(first_name, ' ', last_name) AS name
            FROM users
            WHERE id = ? AND tenant_id = ? AND is_active = 1
        ", [$userId, $tenantId]);
    }

    /**
     * Phone number for SMS delivery. Only present once migration 0022 has run;
     * older schemas simply get null.
     */
    public function userPhone(int $tenantId, int $userId): ?string
    {
        try {
            return $this->fetchOne("
                SELECT phone FROM users WHERE id = ? AND tenant_id = ?
            ", [$userId, $tenantId])['phone'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    /* ----------------------------------------------------------------- in-app */

    public function insertInApp(int $tenantId, int $userId, NotificationMessage $message): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO notifications
                (tenant_id, user_id, type, title, body, link)
            VALUES
                (?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $tenantId,
            $userId,
            $message->type,
            $message->title,
            $message->body,
            $message->link,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @return list<array> newest first */
    public function inbox(int $tenantId, int $userId, int $limit = 30, int $offset = 0, bool $unreadOnly = false): array
    {
        return $this->fetchAll("
            SELECT id, type, title, body, link, read_at, created_at
            FROM notifications
            WHERE tenant_id = ? AND user_id = ?
              " . ($unreadOnly ? "AND read_at IS NULL" : '') . "
            ORDER BY created_at DESC, id DESC
            LIMIT " . max(1, min($limit, 100)) . " OFFSET " . max(0, $offset) . "
        ", [$tenantId, $userId]);
    }

    public function countInbox(int $tenantId, int $userId, bool $unreadOnly = false): int
    {
        return (int) ($this->fetchOne("
            SELECT COUNT(*) AS c
            FROM notifications
            WHERE tenant_id = ? AND user_id = ?
              " . ($unreadOnly ? "AND read_at IS NULL" : '') . "
        ", [$tenantId, $userId])['c'] ?? 0);
    }

    public function find(int $tenantId, int $userId, int $id): ?array
    {
        return $this->fetchOne("
            SELECT id, type, title, body, link, read_at, created_at
            FROM notifications
            WHERE id = ? AND tenant_id = ? AND user_id = ?
        ", [$id, $tenantId, $userId]);
    }

    public function unreadCount(int $tenantId, int $userId): int
    {
        return (int) ($this->fetchOne("
            SELECT COUNT(*) AS c
            FROM notifications
            WHERE tenant_id = ? AND user_id = ? AND read_at IS NULL
        ", [$tenantId, $userId])['c'] ?? 0);
    }

    public function markRead(int $tenantId, int $userId, int $id): void
    {
        $this->db->prepare("
            UPDATE notifications
            SET read_at = NOW()
            WHERE id = ? AND tenant_id = ? AND user_id = ? AND read_at IS NULL
        ")->execute([$id, $tenantId, $userId]);
    }

    public function markAllRead(int $tenantId, int $userId): int
    {
        $stmt = $this->db->prepare("
            UPDATE notifications
            SET read_at = NOW()
            WHERE tenant_id = ? AND user_id = ? AND read_at IS NULL
        ");

        $stmt->execute([$tenantId, $userId]);

        return $stmt->rowCount();
    }

    /* ---------------------------------------------------- sms / push outbox */

    public function queueOutbox(
        int $tenantId,
        ?int $userId,
        string $channel,
        ?string $recipient,
        string $subject,
        string $body
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO notification_outbox
                (tenant_id, user_id, channel, recipient, subject, body, status)
            VALUES
                (?, ?, ?, ?, ?, ?, 'queued')
        ");

        $stmt->execute([$tenantId, $userId, $channel, $recipient, $subject, $body]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Rows ready to dispatch: queued, or failed and inside its retry window.
     * @return list<array>
     */
    public function dueOutboxRows(int $limit, ?int $tenantId = null, int $maxAttempts = Mailer::MAX_ATTEMPTS): array
    {
        $sql = "
            SELECT id, tenant_id, user_id, channel, recipient, subject, body, status, attempts
            FROM notification_outbox
            WHERE (
                    status = 'queued'
                 OR (status = 'failed' AND attempts < :max_attempts
                     AND (next_retry_at IS NULL OR next_retry_at <= NOW()))
                  )
        ";
        $params = [':max_attempts' => $maxAttempts];

        if ($tenantId !== null) {
            $sql .= " AND tenant_id = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        }

        $sql .= " ORDER BY created_at, id LIMIT :limit";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateOutboxRow(
        int $id,
        string $status,
        ?string $error,
        int $attempts,
        ?string $nextRetryAt,
        ?string $recipient = null
    ): void {
        $stmt = $this->db->prepare("
            UPDATE notification_outbox
            SET status        = :status,
                error         = :error,
                attempts      = :attempts,
                next_retry_at = :next_retry_at,
                recipient     = COALESCE(:recipient, recipient)
            WHERE id = :id
        ");

        $stmt->execute([
            ':status'        => $status,
            ':error'         => $error,
            ':attempts'      => $attempts,
            ':next_retry_at' => $nextRetryAt,
            ':recipient'     => $recipient,
            ':id'            => $id,
        ]);
    }

    /** Rows currently eligible for dispatch (queued, or failed inside its retry window). */
    public function countDueOutbox(?int $tenantId = null, int $maxAttempts = Mailer::MAX_ATTEMPTS): int
    {
        $sql = "
            SELECT COUNT(*)
            FROM notification_outbox
            WHERE status = 'queued'
               OR (status = 'failed' AND attempts < :max_attempts
                   AND (next_retry_at IS NULL OR next_retry_at <= NOW()))
        ";
        $params = [':max_attempts' => $maxAttempts];

        if ($tenantId !== null) {
            $sql .= " AND tenant_id = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /** Failed email rows whose retry window has elapsed. */
    public function dueEmailIds(int $limit, ?int $tenantId = null, int $maxAttempts = Mailer::MAX_ATTEMPTS): array
    {
        $sql = "
            SELECT id
            FROM email_outbox
            WHERE status = 'failed'
              AND attempts < :max_attempts
              AND (next_retry_at IS NULL OR next_retry_at <= NOW())
        ";
        $params = [':max_attempts' => $maxAttempts];

        if ($tenantId !== null) {
            $sql .= " AND tenant_id = :tenant_id";
            $params[':tenant_id'] = $tenantId;
        }

        $sql .= " ORDER BY created_at, id LIMIT :limit";

        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /* -------------------------------------------------------------- devices */

    /** @return list<array{id:int, token:string, platform:string}> */
    public function devices(int $tenantId, int $userId): array
    {
        return $this->fetchAll("
            SELECT id, token, platform, last_seen_at
            FROM notification_devices
            WHERE tenant_id = ? AND user_id = ?
            ORDER BY last_seen_at DESC
        ", [$tenantId, $userId]);
    }

    /** Register (or re-register) a push token for this user. */
    public function saveDevice(int $tenantId, int $userId, string $platform, string $token): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO notification_devices
                (tenant_id, user_id, platform, token, last_seen_at)
            VALUES
                (?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                user_id      = VALUES(user_id),
                platform     = VALUES(platform),
                last_seen_at = NOW()
        ");

        $stmt->execute([$tenantId, $userId, in_array($platform, ['ios', 'android', 'web'], true) ? $platform : 'web', $token]);
    }

    public function deleteDevice(int $tenantId, int $userId, int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM notification_devices
            WHERE id = ? AND tenant_id = ? AND user_id = ?
        ");

        $stmt->execute([$id, $tenantId, $userId]);

        return $stmt->rowCount() > 0;
    }

    /* ----------------------------------------------------------- preferences */

    /** @return list<array{event_key:string, channel:string, enabled:int}> */
    public function preferenceRows(int $tenantId, int $userId): array
    {
        return $this->fetchAll("
            SELECT event_key, channel, enabled
            FROM notification_preferences
            WHERE tenant_id = ? AND user_id = ?
        ", [$tenantId, $userId]);
    }

    public function savePreference(int $tenantId, int $userId, string $eventKey, string $channel, bool $enabled): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO notification_preferences
                (tenant_id, user_id, event_key, channel, enabled)
            VALUES
                (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                enabled    = VALUES(enabled),
                updated_at = CURRENT_TIMESTAMP
        ");

        $stmt->execute([$tenantId, $userId, $eventKey, $channel, $enabled ? 1 : 0]);
    }

    /* ------------------------------------------------------------------- util */

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
