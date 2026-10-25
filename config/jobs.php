<?php

/*
|--------------------------------------------------------------------------
| Scheduled job configuration (bin/*.php cron workers)
|--------------------------------------------------------------------------
|
| Read these with config('jobs.<key>') so a job never hard-codes a threshold.
| Every value is env-overridable, because production tuning should not need a
| code change or a redeploy.
|
| Consumed by:
|   bin/notify-outbox.php       -> outbox.*
|   bin/approval-escalation.php -> reminder_hours, admin_hours, max_escalations, escalation_limit
|   bin/audit-retention.php     -> retention.*
|
*/

$int = static function (mixed $value, int $default): int {
    if ($value === null || $value === '') {
        return $default;
    }

    return max(0, (int) $value);
};

return [

    /*
    |--------------------------------------------------------------------------
    | Approval escalation sweep — bin/approval-escalation.php
    |--------------------------------------------------------------------------
    | reminder_hours  age (in hours) after which the approver of a still-pending
    |                 step gets an Events::APPROVAL_ESCALATED nudge. Also the
    |                 quiet period: a row nudged less than reminder_hours ago is
    |                 skipped, which is what gatepass_approvals.escalated_at is for.
    |                 Read by ApprovalService::reminderHours() too — keep it flat.
    | admin_hours     age after which tenant users holding approval.reassign are
    |                 told the gatepass is stuck, plus an approval.escalated_admin
    |                 audit entry per workflow instance.
    | max_escalations hard cap on nudges per approval row (escalation_count), so a
    |                 row that nobody acts on cannot mail its approver forever.
    */

    'reminder_hours'   => $int(env('JOBS_REMINDER_HOURS', 24), 24),
    'admin_hours'      => $int(env('JOBS_ADMIN_HOURS', 48), 48),
    'max_escalations'  => max(1, $int(env('JOBS_MAX_ESCALATIONS', 3), 3)),
    'escalation_limit' => max(1, $int(env('JOBS_ESCALATION_LIMIT', 200), 200)),

    /*
    |--------------------------------------------------------------------------
    | Outbox worker — bin/notify-outbox.php
    |--------------------------------------------------------------------------
    | Per-run row budgets. Keep them modest: one cron tick must finish long before
    | the next one starts, and a provider outage must not build an unbounded backlog
    | into a single run.
    |
    | The attempt ceiling is NOT configured here: App\Core\Mailer::MAX_ATTEMPTS (5)
    | owns it, because Mailer stamps the retry window when it records the failure.
    */

    'outbox' => [
        'email_limit'    => max(1, $int(env('JOBS_EMAIL_RETRY_LIMIT', 50), 50)),
        'dispatch_limit' => max(1, $int(env('JOBS_DISPATCH_LIMIT', 100), 100)),
    ],

    /*
    |--------------------------------------------------------------------------
    | PII retention — bin/audit-retention.php
    |--------------------------------------------------------------------------
    | audit_days   age after which audit_logs rows lose their personal columns.
    | notify_days  age after which already-read notifications rows are deleted.
    | chunk_size   rows per UPDATE/DELETE statement, so a retention run never
    |              holds a long lock on a busy table.
    |
    | Anonymising keeps action, entity_type, entity_id and created_at — the
    | statistical history survives — and overwrites the personal columns with the
    | markers below. The human message is regenerated from AuditService templates
    | with the actor's name in it, so it counts as personal data too.
    */

    'retention' => [
        'audit_days'  => max(1, $int(env('JOBS_AUDIT_RETENTION_DAYS', 365), 365)),
        'notify_days' => max(1, $int(env('JOBS_NOTIFY_RETENTION_DAYS', 90), 90)),
        'chunk_size'  => min(10000, max(1, $int(env('JOBS_RETENTION_CHUNK', 1000), 1000))),

        'message_marker'  => (string) env('JOBS_RETENTION_MESSAGE_MARKER', 'anonymised (retention policy)'),
        'metadata_marker' => ['anonymised' => true, 'reason' => 'retention_policy'],
    ],

];
