<?php

namespace App\Core\Notification;

/**
 * Per-user, per-event channel switches.
 *
 * Absence of a row is not "off": email and in-app default to ON, SMS and push to
 * OFF, so users get approvals without having to configure anything, and the
 * channels that need a phone number or device token stay opt-in.
 */
final class NotificationPreferences
{
    public function __construct(private NotificationRepository $repo)
    {
    }

    public function isEnabled(int $tenantId, int $userId, string $eventKey, string $channel): bool
    {
        foreach ($this->repo->preferenceRows($tenantId, $userId) as $row) {
            if ($row['event_key'] === $eventKey && $row['channel'] === $channel) {
                return (bool) (int) $row['enabled'];
            }
        }

        return Channel::defaultEnabled($channel);
    }

    /** @return list<string> channels enabled for this user + event */
    public function enabledChannels(int $tenantId, int $userId, string $eventKey): array
    {
        return array_values(array_filter(
            Channel::ALL,
            fn (string $channel): bool => $this->isEnabled($tenantId, $userId, $eventKey, $channel)
        ));
    }

    /**
     * Full grid for the preferences screen: event => channel => bool.
     * Includes stored rows for events that are no longer registered, so a
     * hidden event never silently loses its setting.
     */
    public function grid(int $tenantId, int $userId): array
    {
        $grid = [];

        foreach (array_keys(Events::all()) as $eventKey) {
            $grid[$eventKey] = [];

            foreach (Channel::ALL as $channel) {
                $grid[$eventKey][$channel] = Channel::defaultEnabled($channel);
            }
        }

        foreach ($this->repo->preferenceRows($tenantId, $userId) as $row) {
            $grid[$row['event_key']][$row['channel']] = (bool) (int) $row['enabled'];
        }

        return $grid;
    }

    /**
     * Persist a submission shaped as $input[eventKey][channel] = 'on' | absent.
     * Unknown events/channels are ignored so a stale form cannot poison the table.
     *
     * @return int number of switches written
     */
    public function save(int $tenantId, int $userId, array $input): int
    {
        $known    = Events::all();
        $written  = 0;

        foreach ($input as $eventKey => $channels) {
            if (!isset($known[$eventKey]) || !is_array($channels)) {
                continue;
            }

            foreach (Channel::ALL as $channel) {
                $enabled = ($channels[$channel] ?? null) === 'on';

                $this->repo->savePreference($tenantId, $userId, (string) $eventKey, $channel, $enabled);
                $written++;
            }
        }

        return $written;
    }
}
