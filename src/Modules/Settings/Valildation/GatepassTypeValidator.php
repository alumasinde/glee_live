<?php

namespace App\Modules\Settings\Validation;

use RuntimeException;

class GatepassTypeValidator
{
    public static function validateActions(bool $checkin, bool $checkout): void
    {
        if (!$checkin && !$checkout) {
            throw new RuntimeException("At least one action must be enabled.");
        }
    }
}