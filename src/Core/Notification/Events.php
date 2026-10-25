<?php

namespace App\Core\Notification;

/**
 * The notification events users can opt in or out of per channel.
 *
 * Keys are stored in notification_preferences.event_key, so renaming a key
 * silently resets that event's preferences — add new keys, do not reuse old ones.
 */
final class Events
{
    public const STEP_PENDING       = 'approval.step_pending';
    public const APPROVAL_ESCALATED = 'approval.escalated';
    public const DELEGATED_IN       = 'approval.delegated_in';
    public const GATEPASS_APPROVED  = 'gatepass.approved';
    public const GATEPASS_REJECTED  = 'gatepass.rejected';

    /** @return array<string, string> event key => human description */
    public static function all(): array
    {
        return [
            self::STEP_PENDING       => 'A gatepass is waiting for my approval',
            self::APPROVAL_ESCALATED => 'An approval I own is overdue (reminder)',
            self::DELEGATED_IN       => 'Approvals were delegated to me',
            self::GATEPASS_APPROVED  => 'A gatepass I requested was approved',
            self::GATEPASS_REJECTED  => 'A gatepass I requested was rejected',
        ];
    }

    public static function isKnown(string $eventKey): bool
    {
        return array_key_exists($eventKey, self::all());
    }

    public static function describe(string $eventKey): string
    {
        return self::all()[$eventKey] ?? $eventKey;
    }
}
