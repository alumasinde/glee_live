<?php

namespace App\Modules\Settings\Repositories;

use App\Core\DB;
use App\Core\Tenant;
use App\Modules\Settings\DTOs\GatepassTypeDTO;

class GatepassTypeRepository
{
    public function all(): array
    {
        $tenantId = Tenant::require();

        $rows = DB::query("
            SELECT *
            FROM gatepass_types
            WHERE tenant_id = ?
              AND is_active  = 1
            ORDER BY name ASC
        ", [$tenantId])->fetchAll();

        return array_map([$this, 'map'], $rows);
    }

    public function find(int $id, int $tenantId): ?GatepassTypeDTO
    {
        $row = DB::query("
            SELECT *
            FROM gatepass_types
            WHERE id        = ?
              AND tenant_id = ?
            LIMIT 1
        ", [$id, $tenantId])->fetch();

        return $row ? $this->map($row) : null;
    }

    public function updateActions(int $id, array $actions): bool
    {
        $tenantId = Tenant::require();

        $stmt = DB::query("
            UPDATE gatepass_types
            SET allowed_actions = ?
            WHERE id        = ?
              AND tenant_id  = ?
        ", [json_encode($actions), $id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    private function map(array $row): GatepassTypeDTO
{
    return new GatepassTypeDTO(
        id:             (int) $row['id'],
        tenantId:       (int) $row['tenant_id'],
        name:           $row['name'],
        allowedActions: json_decode($row['allowed_actions'] ?? '{}', true) ?? [],
        code:           $row['code'] ?? null,
        isReturnable:   !empty($row['is_returnable'])
    );
}
}