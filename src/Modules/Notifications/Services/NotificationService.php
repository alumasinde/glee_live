<?php

namespace App\Modules\Notifications\Services;

use App\Core\Notification\NotificationPreferences;
use App\Core\Notification\NotificationRepository;
use RuntimeException;

/**
 * The user's own notification surfaces: the in-app feed, their channel
 * preferences and their push devices. Everything here is scoped to one (tenant,
 * user) pair — a user can only ever read their own rows.
 */
class NotificationService
{
    private NotificationRepository $repo;
    private NotificationPreferences $prefs;

    public function __construct(?NotificationRepository $repo = null)
    {
        $this->repo  = $repo ?? new NotificationRepository();
        $this->prefs = new NotificationPreferences($this->repo);
    }

    /** @return array{data: list<array>, meta: array} */
    public function page(int $tenantId, int $userId, int $page = 1, int $perPage = 20, bool $unreadOnly = false): array
    {
        $perPage  = max(5, min($perPage, 100));
        $total    = $this->repo->countInbox($tenantId, $userId, $unreadOnly);
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page     = max(1, min($page, $lastPage));
        $offset   = ($page - 1) * $perPage;

        return [
            'data' => $this->repo->inbox($tenantId, $userId, $perPage, $offset, $unreadOnly),
            'meta' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => $lastPage,
                'from'         => $total > 0 ? $offset + 1 : 0,
                'to'           => min($offset + $perPage, $total),
            ],
        ];
    }

    public function unreadCount(int $tenantId, int $userId): int
    {
        return $this->repo->unreadCount($tenantId, $userId);
    }

    /** Mark one read and return where it points, so the caller can redirect. */
    public function markRead(int $tenantId, int $userId, int $id): string
    {
        $notification = $this->repo->find($tenantId, $userId, $id)
            ?? throw new RuntimeException('Notification not found.');

        $this->repo->markRead($tenantId, $userId, $id);

        return (string) ($notification['link'] ?? '');
    }

    public function markAllRead(int $tenantId, int $userId): int
    {
        return $this->repo->markAllRead($tenantId, $userId);
    }

    public function preferences(int $tenantId, int $userId): array
    {
        return $this->prefs->grid($tenantId, $userId);
    }

    public function savePreferences(int $tenantId, int $userId, array $input): int
    {
        return $this->prefs->save($tenantId, $userId, $input);
    }

    public function devices(int $tenantId, int $userId): array
    {
        return $this->repo->devices($tenantId, $userId);
    }

    /** Enrol a push token. Returns true when the token was newly created. */
    public function registerDevice(int $tenantId, int $userId, string $token, string $platform = 'web'): bool
    {
        $token = trim($token);

        if ($token === '' || strlen($token) > 255) {
            throw new RuntimeException('A push token is required.');
        }

        $known = $this->repo->devices($tenantId, $userId);

        $this->repo->saveDevice($tenantId, $userId, $platform, $token);

        foreach ($known as $device) {
            if ($device['token'] === $token) {
                return false;
            }
        }

        return true;
    }

    public function removeDevice(int $tenantId, int $userId, int $id): bool
    {
        return $this->repo->deleteDevice($tenantId, $userId, $id);
    }
}
