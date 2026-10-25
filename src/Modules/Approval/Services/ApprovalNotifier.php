<?php

namespace App\Modules\Approval\Services;

use App\Core\Mailer;
use Throwable;

/**
 * Queues approval-lifecycle emails inside the workflow transaction and sends
 * them only after the outermost commit (flush). A rolled-back transaction must
 * never notify, so entries are built (and ids resolved) inside the transaction
 * but dispatched outside it. Mailer never throws — failures land in
 * email_outbox as 'failed' — so a dead SMTP server cannot break approvals.
 */
class ApprovalNotifier
{
    /** @var array<int, array{tenant: int, to: string, subject: string, html: string, kind: string}> */
    private array $pending = [];

    public function queueStepPending(int $tenantId, int $approvalId, array $approver, array $gatepass, string $stepName): void
    {
        if (empty($approver['email'])) {
            return;
        }

        $link  = $this->baseUrl() . '/approvals/' . $approvalId;
        $label = $this->personName($approver);

        $this->pending[] = [
            'tenant'  => $tenantId,
            'kind'    => 'step_pending',
            'to'      => (string) $approver['email'],
            'subject' => sprintf('Approval needed — gatepass %s', $gatepass['gatepass_number']),
            'html'    => '<h2>A gatepass is waiting on you</h2>'
                . '<p>' . htmlspecialchars((string) ($gatepass['requested_by_name'] ?? 'Someone'))
                . ' requested gatepass <strong>' . htmlspecialchars((string) $gatepass['gatepass_number']) . '</strong>.</p>'
                . '<p>Step: <strong>' . htmlspecialchars($stepName) . '</strong></p>'
                . '<p>Purpose: ' . nl2br(htmlspecialchars((string) ($gatepass['purpose'] ?? ''))) . '</p>'
                . '<p>Hello ' . htmlspecialchars($label) . ', please review it here:</p>'
                . '<p><a href="' . htmlspecialchars($link) . '">' . htmlspecialchars($link) . '</a></p>',
        ];
    }

    /**
     * @param array $decisionRow row with email, first_name, last_name, gatepass_number, gatepass_id
     *                           (ApprovalService::requesterContext shape)
     */
    public function queueDecision(int $tenantId, array $decisionRow, bool $approved, ?string $reason = null): void
    {
        if (empty($decisionRow['email'])) {
            return;
        }

        $number = (string) $decisionRow['gatepass_number'];
        $link   = $this->baseUrl() . '/gatepasses/' . (int) $decisionRow['gatepass_id'];
        $name   = $this->personName($decisionRow);

        if ($approved) {
            $subject = sprintf('Your gatepass %s has been approved', $number);
            $html    = '<h2>Gatepass approved</h2>'
                . '<p>Hello ' . htmlspecialchars($name) . ', your gatepass <strong>'
                . htmlspecialchars($number) . '</strong> has completed all approval steps.</p>'
                . '<p><a href="' . htmlspecialchars($link) . '">' . htmlspecialchars($link) . '</a></p>';
        } else {
            $subject = sprintf('Your gatepass %s was rejected', $number);
            $html    = '<h2>Gatepass rejected</h2>'
                . '<p>Hello ' . htmlspecialchars($name) . ', your gatepass <strong>'
                . htmlspecialchars($number) . '</strong> was rejected.</p>'
                . '<p>Reason: ' . nl2br(htmlspecialchars((string) $reason)) . '</p>'
                . '<p><a href="' . htmlspecialchars($link) . '">' . htmlspecialchars($link) . '</a></p>';
        }

        $this->pending[] = [
            'tenant'  => $tenantId,
            'kind'    => $approved ? 'approved' : 'rejected',
            'to'      => (string) $decisionRow['email'],
            'subject' => $subject,
            'html'    => $html,
        ];
    }

    /** Send everything queued so far. Safe to call multiple times; never throws. */
    public function flush(): void
    {
        if (!$this->pending) {
            return;
        }

        $queue         = $this->pending;
        $this->pending = [];

        foreach ($queue as $mail) {
            try {
                Mailer::send($mail['tenant'], $mail['to'], $mail['subject'], $mail['html']);
            } catch (Throwable $e) {
                error_log(sprintf('ApprovalNotifier flush failed (%s -> %s): %s', $mail['kind'], $mail['to'], $e->getMessage()));
            }
        }
    }

    public function discard(): void
    {
        $this->pending = [];
    }

    public function pendingCount(): int
    {
        return count($this->pending);
    }

    private function personName(array $user): string
    {
        $name = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));

        return $name !== '' ? $name : 'there';
    }

    private function baseUrl(): string
    {
        $https = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return ($https ? 'https' : 'http') . '://' . $host;
    }
}
