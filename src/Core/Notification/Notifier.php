<?php

namespace App\Core\Notification;

use App\Core\Mailer;
use Throwable;

/**
 * Resolves a message into the channels a user actually wants and delivers it.
 *
 * Defaults (no preference row): email and in-app ON, SMS and push OFF.
 *
 * Two entry shapes, because ordering matters:
 *   record()    writes in-app / SMS / push rows — plain SQL, safe inside a
 *               business transaction, and discarded when that transaction rolls back.
 *   sendEmail() talks to SMTP — only ever after the commit, so a rolled-back
 *               approval can never still mail the approver.
 * notify() does both immediately and is for callers already outside a transaction
 * (CLI jobs, self-service screens).
 */
final class Notifier
{
    public function __construct(
        private NotificationRepository $repo,
        private NotificationPreferences $prefs,
    ) {
    }

    public static function make(): self
    {
        $repo = new NotificationRepository();

        return new self($repo, new NotificationPreferences($repo));
    }

    /** @return list<string> channels this user wants for this event */
    public function channels(int $tenantId, int $userId, string $eventKey): array
    {
        return $this->prefs->enabledChannels($tenantId, $userId, $eventKey);
    }

    /**
     * Deliver to one recipient right now, on every channel they want.
     *
     * @return array<string, string> channel => outcome status
     */
    public function notify(int $tenantId, int $userId, NotificationMessage $message): array
    {
        $contacts = $this->repo->userContact($tenantId, $userId);

        if (!$contacts) {
            return [];
        }

        $outcomes = [];

        foreach ($this->channels($tenantId, $userId, $message->eventKey) as $channel) {
            $outcomes[$channel] = $channel === Channel::EMAIL
                ? $this->sendEmail($tenantId, (string) ($contacts['email'] ?? ''), $message)
                : $this->record($tenantId, $userId, $message, $channel);
        }

        return $outcomes;
    }

    /**
     * Fan one message out to many recipients (escalation sweeps, admin notices).
     *
     * @return array<string, int> "channel:status" => count
     */
    public function deliverMany(int $tenantId, array $userIds, NotificationMessage $message): array
    {
        $counts = [];

        foreach (array_unique(array_map('intval', $userIds)) as $userId) {
            if ($userId <= 0) {
                continue;
            }

            foreach ($this->notify($tenantId, $userId, $message) as $channel => $status) {
                $key = $channel . ':' . $status;
                $counts[$key] = ($counts[$key] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * Put the message on a queue-shaped channel: an in-app row, or a pending
     * SMS/push row for bin/notify-outbox.php to dispatch. The email channel is
     * deliberately not handled here — see sendEmail().
     */
    public function record(int $tenantId, int $userId, NotificationMessage $message, string $channel): string
    {
        return match ($channel) {
            Channel::IN_APP => $this->inApp($tenantId, $userId, $message),
            Channel::SMS    => $this->sms($tenantId, $userId, $message),
            Channel::PUSH   => $this->push($tenantId, $userId, $message),
            default         => Mailer::STATUS_SKIPPED,
        };
    }

    /** SMTP send. Never throws: Mailer records the attempt in email_outbox. */
    public function sendEmail(int $tenantId, string $to, NotificationMessage $message): string
    {
        if (trim($to) === '') {
            return Mailer::STATUS_SKIPPED;
        }

        try {
            return Mailer::send($tenantId, $to, $message->emailSubject(), $message->emailHtml());
        } catch (Throwable $e) {
            error_log('[Notifier] email failed: ' . $e->getMessage());

            return Mailer::STATUS_FAILED;
        }
    }

    private function inApp(int $tenantId, int $userId, NotificationMessage $message): string
    {
        $this->repo->insertInApp($tenantId, $userId, $message);

        return 'recorded';
    }

    private function sms(int $tenantId, int $userId, NotificationMessage $message): string
    {
        $phone = trim((string) ($this->repo->userPhone($tenantId, $userId) ?? ''));

        // Queued even without a number, so the outbox records the attempt and why
        // it could not go out — the same rule email_outbox follows.
        $this->repo->queueOutbox(
            $tenantId,
            $userId,
            Channel::SMS,
            $phone !== '' ? $phone : null,
            $message->title,
            $message->shortBody()
        );

        return 'queued';
    }

    /**
     * One queued row per registered token — a dead token must not block the
     * others, and every attempt needs its own retry bookkeeping.
     */
    private function push(int $tenantId, int $userId, NotificationMessage $message): string
    {
        $devices = $this->repo->devices($tenantId, $userId);

        if (!$devices) {
            return Mailer::STATUS_SKIPPED;
        }

        foreach ($devices as $device) {
            $this->repo->queueOutbox(
                $tenantId,
                $userId,
                Channel::PUSH,
                (string) $device['token'],
                $message->title,
                $message->shortBody()
            );
        }

        return 'queued';
    }
}
