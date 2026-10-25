<?php

namespace App\Modules\Settings\DTOs;

class GatepassTypeDTO
{
    /**
     * allowedActions is kept exactly as the JSON column says. The one rule the
     * screen and the gate must agree on lives in allowsAction(): an absent key
     * means allowed, only an explicit false blocks.
     */
    public function __construct(
        public int $id,
        public int $tenantId,
        public string $name,
        public array $allowedActions = [],
        public ?string $code = null,
        public bool $isReturnable = false
    ) {}

    public function allowsAction(string $action): bool
    {
        return !array_key_exists($action, $this->allowedActions)
            || $this->allowedActions[$action] !== false;
    }

    public function hasActionConfig(): bool
    {
        return $this->allowedActions !== [];
    }
}
