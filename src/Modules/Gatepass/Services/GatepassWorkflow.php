<?php

namespace App\Modules\Gatepass\Services;

use RuntimeException;

class GatepassWorkflow
{
    /**
     * Returns which state-transition actions are eligible for the given gatepass row.
     * Does not enforce type-level allowed_actions — that is handled by
     * GatepassService::assertTypeActionAllowed() at the gate operation entry points.
     *
     * @param  array $g  Must contain: status_code. Optionally: actual_in, actual_out, is_returnable.
     * @return array{checkin_eligible: bool, checkout_eligible: bool}
     */
    public static function eligibility(array $g): array
    {
        if (empty($g['status_code'])) {
            throw new RuntimeException('Invalid gatepass state: missing status_code.');
        }

        $status     = strtoupper($g['status_code']);
        $returnable = (int) ($g['is_returnable'] ?? 0) === 1;

        return [
            'checkin_eligible'  => self::checkinEligible($status, $returnable, $g),
            'checkout_eligible' => self::checkoutEligible($status, $g),
        ];
    }

    private static function checkinEligible(string $status, bool $returnable, array $g): bool
    {
        if (!empty($g['actual_in'])) {
            return false;
        }

        if ($status === 'CHECKED_OUT' && $returnable) {
            return true;
        }

        return $status === 'APPROVED';
    }

    private static function checkoutEligible(string $status, array $g): bool
    {
        if (!empty($g['actual_out'])) {
            return false;
        }

        // A returnable item must be at the gate before it can leave again;
        // without this an approved pass could be checked out (and reported as
        // gone) without ever having come in.
        if ((int) ($g['is_returnable'] ?? 0) === 1 && empty($g['actual_in'])) {
            return false;
        }

        return in_array($status, ['APPROVED', 'CHECKED_IN'], true);
    }
}
