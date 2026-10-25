<?php

namespace App\Modules\Approval\Services;

use App\Core\Notification\Channel;
use App\Core\Notification\Events;
use App\Core\Notification\NotificationMessage;
use App\Core\Notification\NotificationPreferences;
use App\Core\Notification\NotificationRepository;
use App\Core\Notification\Notifier;
use Throwable;

/**
 * Approval-lifecycle notifications, honouring each recipient's channel
 * preferences.
 *
 * Queue-then-flush, because delivery must never happen inside the workflow
 * transaction:
 *   - in-app rows and SMS/push outbox rows are INSERTed inside the transaction,
 *     so a rollback takes them with it;
 *   - email is only sent from flush(), after the outermost commit. A rolled-back
 *     approval must not have mailed anyone.
 * Mailer never throws — failures land in email_outbox as 'failed' with a retry
 * window for bin/notify-outbox.php — so a dead SMTP server cannot break approvals.
 */
class ApprovalNotifier
{
    private NotificationRepository $repo;
    private Notifier $notifier;

    /** @var array<int, array{tenant: int, kind: string, to: string, message: NotificationMessage}> */
    private array $pending = [];

    public function __construct(?Notifier $notifier = null, ?NotificationRepository $repo = null)
    {
        $this->repo     = $repo ?? new NotificationRepository();
        $this->notifier = $notifier ?? new Notifier($this->repo, new NotificationPreferences($this->repo));
    }

    /**
     * @param array $approver id, email, first_name, last_name
     * @param array $gatepass gatepass_number, purpose, requested_by_name, gatepass_id
     */
    public function queueStepPending(int $tenantId, int $approvalId, array $approver, array $gatepass, string $stepName): void
    {
        $number = (string) ($gatepass['gatepass_number'] ?? '');
        $link   = $this->baseUrl() . '/approvals/' . $approvalId;
        $name   = $this->personName($approver);
        $email  = (string) ($approver['email'] ?? '');
        $userId = (int) ($approver['id'] ?? 0);

        $covers = $approver['covers'] ?? null;

        $coverText = $covers
            ? sprintf(' You are covering for %s until %s.', (string) $covers['name'], date('d M Y H:i', strtotime((string) $covers['ends_at'])))
            : '';

        $message = new NotificationMessage(
            Events::STEP_PENDING,
            sprintf('Approval needed — gatepass %s', $number),
            sprintf(
                '%s requested gatepass %s. Step: %s. Purpose: %s',
                (string) ($gatepass['requested_by_name'] ?? 'Someone'),
                $number,
                $stepName,
                (string) ($gatepass['purpose'] ?? '')
            ) . $coverText,
            $link,
            '<h2>A gatepass is waiting on you</h2>'
                . '<p>' . htmlspecialchars((string) ($gatepass['requested_by_name'] ?? 'Someone'))
                . ' requested gatepass <strong>' . htmlspecialchars($number) . '</strong>.</p>'
                . '<p>Step: <strong>' . htmlspecialchars($stepName) . '</strong></p>'
                . '<p>Purpose: ' . nl2br(htmlspecialchars((string) ($gatepass['purpose'] ?? ''))) . '</p>'
                . ($covers !== null
                    ? '<p><em>' . htmlspecialchars(trim($coverText)) . '</em></p>'
                    : '')
                . '<p>Hello ' . htmlspecialchars($name) . ', please review it here:</p>'
                . '<p><a href="' . htmlspecialchars($link) . '">' . htmlspecialchars($link) . '</a></p>',
            null,
            'approval'
        );

        $this->dispatch($tenantId, $userId, $email, $message, 'step_pending');
    }

    /**
     * Final decision for the requester.
     *
     * @param array $decisionRow user_id, email, first_name, last_name, gatepass_id, gatepass_number
     */
    public function queueDecision(int $tenantId, array $decisionRow, bool $approved, ?string $reason = null): void
    {
        $number = (string) ($decisionRow['gatepass_number'] ?? '');
        $link   = $this->baseUrl() . '/gatepasses/' . (int) ($decisionRow['gatepass_id'] ?? 0);
        $name   = $this->personName($decisionRow);
        $email  = (string) ($decisionRow['email'] ?? '');
        $userId = (int) ($decisionRow['user_id'] ?? 0);

        $eventKey = $approved ? Events::GATEPASS_APPROVED : Events::GATEPASS_REJECTED;

        if ($approved) {
            $subject = sprintf('Your gatepass %s has been approved', $number);
            $text    = sprintf('Your gatepass %s has completed all approval steps.', $number);
            $html    = '<h2>Gatepass approved</h2>'
                . '<p>Hello ' . htmlspecialchars($name) . ', your gatepass <strong>'
                . htmlspecialchars($number) . '</strong> has completed all approval steps.</p>'
                . '<p><a href="' . htmlspecialchars($link) . '">' . htmlspecialchars($link) . '</a></p>';
        } else {
            $subject = sprintf('Your gatepass %s was rejected', $number);
            $text    = sprintf('Your gatepass %s was rejected. Reason: %s', $number, (string) $reason);
            $html    = '<h2>Gatepass rejected</h2>'
                . '<p>Hello ' . htmlspecialchars($name) . ', your gatepass <strong>'
                . htmlspecialchars($number) . '</strong> was rejected.</p>'
                . '<p>Reason: ' . nl2br(htmlspecialchars((string) $reason)) . '</p>'
                . '<p><a href="' . htmlspecialchars($link) . '">' . htmlspecialchars($link) . '</a></p>';
        }

        $message = new NotificationMessage($eventKey, $subject, $text, $link, $html, $subject, $approved ? 'success' : 'warning');

        $this->dispatch($tenantId, $userId, $email, $message, $eventKey);
    }

    /**
     * Route one message through the recipient's preferred channels: email is
     * deferred to flush(), everything else is written to its table now (inside
     * the caller's transaction, so a rollback removes it).
     */
    private function dispatch(int $tenantId, int $userId, string $email, NotificationMessage $message, string $kind): void
    {
        if ($userId <= 0) {
            // No resolvable user: email is the only channel left.
            if ($email !== '') {
                $this->pending[] = ['tenant' => $tenantId, 'kind' => $kind, 'to' => $email, 'message' => $message];
            }

            return;
        }

        foreach ($this->notifier->channels($tenantId, $userId, $message->eventKey) as $channel) {
            if ($channel === Channel::EMAIL) {
                if ($email !== '') {
                    $this->pending[] = ['tenant' => $tenantId, 'kind' => $kind, 'to' => $email, 'message' => $message];
                }

                continue;
            }

            $this->notifier->record($tenantId, $userId, $message, $channel);
        }
    }

    /** Send every deferred email. Safe to call multiple times; never throws. */
    public function flush(): void
    {
        if (!$this->pending) {
            return;
        }

        $queue         = $this->pending;
        $this->pending = [];

        foreach ($queue as $mail) {
            try {
                $this->notifier->sendEmail($mail['tenant'], $mail['to'], $mail['message']);
            } catch (Throwable $e) {
                error_log(sprintf('ApprovalNotifier flush failed (%s -> %s): %s', $mail['kind'], $mail['to'], $e->getMessage()));
            }
        }
    }

    public function discard(): void
    {
        $this->pending = [];
    }

    /** Emails still waiting to be sent. */
    public function pendingCount(): int
    {
        return count($this->pending);
    }

    private function personName(array $user): string
    {
        $name = trim((string) ($user['name'] ?? ''));

        if ($name === '') {
            $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
        }

        return $name !== '' ? $name : 'there';
    }

    private function baseUrl(): string
    {
        $https = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return ($https ? 'https' : 'http') . '://' . $host;
    }
}
