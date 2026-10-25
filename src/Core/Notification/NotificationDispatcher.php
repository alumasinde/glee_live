<?php

namespace App\Core\Notification;

use App\Core\Mailer;

/**
 * Drains the outbound queues. Called by bin/notify-outbox.php (cron) — never
 * from a web request, so a slow provider cannot stall a login or an approval.
 *
 * SMS/push: dispatches queued + retry-window rows from notification_outbox.
 * Email: re-sends failed email_outbox rows in place; Mailer owns that table's
 * attempt/backoff bookkeeping.
 */
final class NotificationDispatcher
{
    public function __construct(private NotificationRepository $repo)
    {
    }

    /**
     * @return array<string, int> status => count
     */
    public function dispatchOutbox(int $limit = 100, ?int $tenantId = null): array
    {
        $counts = ['sent' => 0, 'failed' => 0, 'skipped' => 0];

        $gateways = [];

        foreach ($this->repo->dueOutboxRows($limit, $tenantId) as $row) {
            $rowTenant = (int) $row['tenant_id'];
            $channel   = (string) $row['channel'];
            $attempts  = (int) $row['attempts'] + 1;

            if (empty($row['recipient'])) {
                $this->repo->updateOutboxRow((int) $row['id'], Mailer::STATUS_SKIPPED,
                    'No reachable recipient for this channel', $attempts, null);
                $counts['skipped']++;

                continue;
            }

            $gateways[$rowTenant] ??= new NotificationGateway($rowTenant);
            $config = $gateways[$rowTenant]->config($channel);

            if (!$config['enabled'] || $config['driver'] === NotificationGateway::DRIVER_NULL) {
                $this->repo->updateOutboxRow((int) $row['id'], Mailer::STATUS_SKIPPED,
                    ucfirst($channel) . ' provider not configured', $attempts, null);
                $counts['skipped']++;

                continue;
            }

            try {
                $gateways[$rowTenant]->send(
                    $channel,
                    (string) $row['recipient'],
                    (string) $row['subject'],
                    (string) $row['body']
                );

                $this->repo->updateOutboxRow((int) $row['id'], Mailer::STATUS_SENT, null, $attempts, null);
                $counts['sent']++;
            } catch (\Throwable $e) {
                $exhausted = $attempts >= Mailer::MAX_ATTEMPTS;

                $this->repo->updateOutboxRow(
                    (int) $row['id'],
                    Mailer::STATUS_FAILED,
                    substr($e->getMessage(), 0, 500),
                    $attempts,
                    $exhausted ? null : date('Y-m-d H:i:s', time() + Mailer::backoffMinutes($attempts) * 60)
                );
                $counts['failed']++;
            }
        }

        return $counts;
    }

    /**
     * @return array<string, int> status => count
     */
    public function retryEmails(int $limit = 50, ?int $tenantId = null): array
    {
        $counts = ['sent' => 0, 'failed' => 0];

        foreach ($this->repo->dueEmailIds($limit, $tenantId) as $outboxId) {
            $counts[Mailer::retry($outboxId) === Mailer::STATUS_SENT ? 'sent' : 'failed']++;
        }

        return $counts;
    }

    /** How many rows are waiting, for the worker's summary line. */
    public function pendingCount(?int $tenantId = null): int
    {
        return $this->repo->countDueOutbox($tenantId);
    }
}
