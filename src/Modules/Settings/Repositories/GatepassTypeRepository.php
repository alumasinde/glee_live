<?php

namespace App\Modules\Settings\Repositories;

use App\Core\DB;
use App\Core\Tenant;
use App\Modules\Settings\DTOs\GatepassTypeDTO;

class GatepassTypeRepository
{
    /**
     * One column list for every read, so map() can never be handed a row that
     * lacks a key it dereferences (the old all() omitted is_returnable and
     * find() returned type_code, while map() asked for code).
     */
    private const COLUMNS = 'id, tenant_id, name, type_code AS code, allowed_actions, is_returnable, is_active, created_at';

    public function all(): array
    {
        $tenantId = Tenant::require();

        $rows = DB::query("
            SELECT " . self::COLUMNS . "
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
            SELECT " . self::COLUMNS . "
            FROM gatepass_types
            WHERE id        = ?
              AND tenant_id = ?
            LIMIT 1
        ", [$id, $tenantId])->fetch();

        return $row ? $this->map($row) : null;
    }

    /**
     * @param array<string, bool> $actions
     * @param bool|null $isReturnable null leaves the stored flag untouched
     */
    public function updateConfig(int $id, array $actions, ?bool $isReturnable): void
    {
        $tenantId = Tenant::require();

        $sets = ['allowed_actions = ?'];
        $args = [json_encode($actions, JSON_UNESCAPED_SLASHES)];

        if ($isReturnable !== null) {
            $sets[] = 'is_returnable = ?';
            $args[] = $isReturnable ? 1 : 0;
        }

        // Row existence is validated by find() before this runs; MySQL reports
        // rowCount 0 for a save that changed nothing, so never gate on it.
        $args[] = $id;
        $args[] = $tenantId;

        DB::query("
            UPDATE gatepass_types
            SET " . implode(', ', $sets) . "
            WHERE id        = ?
              AND tenant_id = ?
        ", $args);
    }

    private function map(array $row): GatepassTypeDTO
    {
        $actions = json_decode((string) ($row['allowed_actions'] ?? '[]'), true);

        return new GatepassTypeDTO(
            id:             (int) $row['id'],
            tenantId:       (int) $row['tenant_id'],
            name:           (string) $row['name'],
            allowedActions: is_array($actions) ? $actions : [],
            code:           isset($row['code']) ? (string) $row['code'] : null,
            isReturnable:   !empty($row['is_returnable'])
        );
    }
}
