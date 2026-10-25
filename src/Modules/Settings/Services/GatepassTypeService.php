<?php

namespace App\Modules\Settings\Services;

use App\Core\Audit;
use App\Core\DB;
use App\Core\Tenant;
use App\Modules\Settings\Repositories\GatepassTypeRepository;
use App\Modules\Settings\Validation\GatepassTypeValidator;
use RuntimeException;

class GatepassTypeService
{
    public function __construct(
        private GatepassTypeRepository $repo
    ) {}

    public function all(): array
    {
        return $this->repo->all();
    }

    public function find(int $id)
    {
        $type = $this->repo->find($id, Tenant::require());

        if (! $type) {
            throw new RuntimeException('Gatepass type not found.');
        }

        return $type;
    }

    /**
     * @param bool|null $isReturnable null = caller did not send the flag, so the
     *                                stored value survives untouched.
     */
    public function updateConfig(int $id, bool $checkin, bool $checkout, bool $return, ?bool $isReturnable): void
    {
        GatepassTypeValidator::validateActions($checkin, $checkout, $return);

        $tenantId = Tenant::require();

        $type = $this->repo->find($id, $tenantId);

        if (! $type) {
            throw new RuntimeException('Gatepass type not found.');
        }

        $actions = [
            'checkin'  => $checkin,
            'checkout' => $checkout,
            'return'   => $return,
        ];

        $returnableAfter = $isReturnable ?? $type->isReturnable;

        DB::transaction(function () use ($id, $type, $actions, $isReturnable, $returnableAfter) {

            $this->repo->updateConfig($id, $actions, $isReturnable);

            Audit::log(
                action:     'gatepass_type.updated_config',
                entityType: 'gatepass_type',
                entityId:   $id,
                metadata:   [
                    'before' => [
                        'allowed_actions' => $type->allowedActions,
                        'is_returnable'   => $type->isReturnable,
                    ],
                    'after'  => [
                        'allowed_actions' => $actions,
                        'is_returnable'   => $returnableAfter,
                    ],
                ]
            );
        });
    }
}
