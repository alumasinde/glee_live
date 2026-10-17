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

    public function updateActions(int $id, bool $checkin, bool $checkout): void
    {
        GatepassTypeValidator::validateActions($checkin, $checkout);

        $tenantId = Tenant::require();

        $type = $this->repo->find($id, $tenantId);

        if (! $type) {
            throw new RuntimeException('Gatepass type not found.');
        }

        $before = $type->allowedActions ?? [];

        $after = [
            'checkin'  => (bool) $checkin,
            'checkout' => (bool) $checkout,
        ];

        try {
            DB::transaction(function () use ($id, $after, $before) {

                $updated = $this->repo->updateActions($id, $after);

                // IMPORTANT FIX: rowCount() is unreliable for updates
                if ($updated === false) {
                    throw new RuntimeException('Update failed at database level.');
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

        } catch (\Throwable $e) {
            // IMPORTANT: rethrow so controller can show real error
            throw new RuntimeException(
                'Gatepass update failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }
}