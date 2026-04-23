<?php

namespace App\Modules\Settings\DTOs;

class GatepassTypeDTO
{
    public function __construct(
        public int $id,
        public int $tenantId,
        public string $name,
        public array $allowedActions = [],  // must include 'checkin' and 'checkout'
        public ?string $code = null,        // optional code
        public bool $isReturnable = false   // optional boolean
    ) {
        // Ensure allowedActions always has the expected keys
        $this->allowedActions = [
            'checkin' => $allowedActions['checkin'] ?? false,
            'checkout' => $allowedActions['checkout'] ?? false,
        ];
    }
}