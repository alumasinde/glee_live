<?php

namespace App\Modules\Settings\Validation;


class GatepassTypeValidator
{
    public static function validateActions(bool $checkin, bool $checkout): void
    {
        // Both false is valid — a type may have no actions permitted
    }
}