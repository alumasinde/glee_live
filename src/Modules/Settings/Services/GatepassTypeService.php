<?php

namespace App\Modules\Settings\Services;

use App\Core\Audit;
use App\Core\DB;
use App\Core\Tenant;
use App\Modules\Gatepass\DTOs\GatepassDTO;
use App\Modules\Gatepass\Services\GatepassWorkflow;
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

    public function updateActions(int $id, bool $checkin, bool $checkout): void
    {
        GatepassTypeValidator::validateActions($checkin, $checkout);

        $tenantId = Tenant::require();

        $type = $this->repo->find($id, $tenantId);

        if (! $type) {
            throw new RuntimeException('Gatepass type not found.');
        }

        $before = $type->allowedActions;

        $after = [
            'checkin'  => $checkin,
            'checkout' => $checkout,
        ];

        // FIX: App\Core\Transaction does not exist — replaced with DB::transaction()
        DB::transaction(function () use ($id, $after, $before) {

            $updated = $this->repo->updateActions(
                id:      $id,
                actions: $after
            );

            if (! $updated) {
                throw new RuntimeException('Update failed.');
            }

            Audit::log(
                action:     'gatepass_type.updated_actions',
                entityType: 'gatepass_type',
                entityId:   $id,
                metadata:   [
                    'before' => $before,
                    'after'  => $after,
                ]
            );
        });
    }

    public function resolveActions(GatepassDTO $gatepass, $type): array
    {
        $allowed = $type->allowedActions;

        $eligibility = GatepassWorkflow::eligibility([
            'status_code'   => $gatepass->statusCode,
            'actual_in'     => $gatepass->actualIn,
            'actual_out'    => $gatepass->actualOut,
            'is_returnable' => $gatepass->isReturnable,
        ]);

        return [
            'can_checkin'  => ($allowed['checkin']  ?? false) && $eligibility['checkin_eligible'],
            'can_checkout' => ($allowed['checkout'] ?? false) && $eligibility['checkout_eligible'],
        ];
    }
}