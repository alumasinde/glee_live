<?php

namespace App\Modules\Reports\Repositories;

use App\Core\BaseRepository;
use PDO;

class ScanLogReportRepository extends BaseRepository
{
    protected string $table = 'scan_logs';
    protected string $alias = 'sl';

    protected array $searchable = [
        'g.name',
        'g.code',
        'gp.gatepass_number',
        'vis.first_name',
        'vis.last_name',
        'sl.detail',
        'op.first_name',
        'op.last_name',
    ];

    protected array $filterable = [
        'sl.gate_id',
        'sl.action',
        'sl.subject_type',
        'sl.source',
    ];

    protected array $sortable = [
        'sl.created_at',
        'g.name',
        'sl.action',
        'sl.source',
    ];

    protected string $defaultSort = 'sl.created_at';

    protected function baseQuery(): string
    {
        return "
            FROM scan_logs sl

            LEFT JOIN gates g
                ON g.id = sl.gate_id
               AND g.tenant_id = sl.tenant_id

            LEFT JOIN gatepasses gp
                ON gp.id = sl.subject_id
               AND sl.subject_type = 'gatepass'
               AND gp.tenant_id = sl.tenant_id

            LEFT JOIN visits v
                ON v.id = sl.subject_id
               AND sl.subject_type = 'visit'
               AND v.tenant_id = sl.tenant_id

            LEFT JOIN visitors vis
                ON vis.id = v.visitor_id
               AND vis.tenant_id = sl.tenant_id

            LEFT JOIN users op
                ON op.id = sl.user_id
               AND op.tenant_id = sl.tenant_id
        ";
    }

    protected function selectColumns(): string
    {
        return "
            SELECT
                sl.id,
                sl.created_at,
                sl.subject_type,
                sl.subject_id,
                sl.action,
                sl.source,
                sl.detail,
                g.name  AS gate_name,
                g.code  AS gate_code,
                gp.gatepass_number,
                CONCAT(vis.first_name, ' ', vis.last_name) AS visitor_name,
                CONCAT(op.first_name, ' ', op.last_name)   AS operator_name
        ";
    }

    /**
     * gate_id => label options for the filter bar.
     */
    public function gateOptions(int $tenantId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name
            FROM gates
            WHERE tenant_id = :tenant
            ORDER BY name
        ");

        $stmt->execute([':tenant' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Distinct recorded values for a scan column, as filter options.
     */
    public function distinctOptions(int $tenantId, string $column): array
    {
        $allowed = ['action', 'source', 'subject_type'];

        if (!in_array($column, $allowed, true)) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT DISTINCT {$column}
            FROM scan_logs
            WHERE tenant_id = :tenant
            ORDER BY {$column}
        ");

        $stmt->execute([':tenant' => $tenantId]);

        $values = $stmt->fetchAll(PDO::FETCH_COLUMN);

        return array_combine($values, array_map(
            static fn (string $v): string => ucfirst(str_replace('_', ' ', $v)),
            $values
        ));
    }
}
