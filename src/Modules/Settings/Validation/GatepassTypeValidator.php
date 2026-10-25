<?php

namespace App\Modules\Settings\Validation;


class GatepassTypeValidator
{
    public static function validateActions(bool $checkin, bool $checkout, bool $return): void
    {
        // All three off is valid — a type may permit no gate action at all.
    }
}
