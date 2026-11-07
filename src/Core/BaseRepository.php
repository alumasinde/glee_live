<?php

namespace App\Core;

use PDO;

abstract class BaseRepository
{
    protected PDO $db;
    protected string $table;
    protected string $alias = 't';

    protected array $searchable = [];
    protected array $filterable = [];
    protected array $sortable = [];

    /** Default sort column (can be alias-qualified or full column) */
    protected string $defaultSort = 'created_at';

    public function __construct()
    {
        $this->db = DB::connect();
    }

    public function list(int $tenantId, array $params = []): array
    {
        $search  = $params['search'] ?? null;
        $filters = $params['filters'] ?? [];
        $sortBy  = $params['sort_by'] ?? $this->defaultSort;
        $sortDir = strtolower($params['sort_dir'] ?? 'desc');
        $page    = (int)($params['page'] ?? 1);
        $perPage = (int)($params['per_page'] ?? 10);

        $bindings = [
            ':tenant_id' => $tenantId
        ];

        // ✅ ALWAYS include full query (SELECT + JOINs)
        $sql = "
            {$this->selectColumns()}
            {$this->baseQuery()}
            WHERE {$this->alias}.tenant_id = :tenant_id
        ";

        // =========================
        // SEARCH
        // =========================
        if (!empty($this->searchable) && !empty($search)) {
            $sql = QueryBuilder::applySearch(
                $sql,
                $this->searchable,
                $bindings,
                $search
            );
        }

        // =========================
        // FILTERS (FIXED)
        // =========================
        $normalizedFilters = $this->normaliseFilters($filters);

        if (!empty($normalizedFilters)) {
            $sql = QueryBuilder::applyFilters($sql, $normalizedFilters, $bindings);
        }

        // =========================
        // SORT (FIXED)
        // =========================
        if (!in_array($sortBy, $this->sortable, true)) {
            $sortBy = $this->defaultSort;
        }

        $sortDir = $sortDir === 'asc' ? 'ASC' : 'DESC';

        // Same ORDER BY construction as App\Core\Repository, so the two list
        // implementations cannot drift apart again.
        $dataSql = QueryBuilder::applySort($sql, $sortBy, $sortDir, $this->sortable, $this->defaultSort);

        $paginator = new Paginator(
            $this->db,
            $sql,       // base SQL (used for COUNT)
            $bindings,
            $page,
            $perPage
        );

        return $paginator->paginate($dataSql);
    }

    public function count(int $tenantId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE tenant_id = :tenant_id"
        );
        $stmt->execute([':tenant_id' => $tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Default SELECT (override in child)
     */
    protected function selectColumns(): string
    {
        return "SELECT {$this->alias}.*";
    }

    /**
     * Default baseQuery (override in child)
     */
    protected function baseQuery(): string
    {
        return "FROM {$this->table} {$this->alias}";
    }

    public function begin(): void
    {
        $this->db->beginTransaction();
    }

    public function commit(): void
    {
        $this->db->commit();
    }

    public function rollback(): void
    {
        if ($this->db->inTransaction()) {
            $this->db->rollBack();
        }
    }

    // =========================================================================
    // FILTER NORMALISATION (NOW ACTUALLY USED)
    // =========================================================================

    private function normaliseFilters(array $rawFilters): array
    {
        if (empty($rawFilters) || empty($this->filterable)) {
            return [];
        }

        $columnMap = [];

        foreach ($this->filterable as $key => $value) {
            if (is_int($key)) {
                $columnMap[$value] = $value; // already fully qualified
            } else {
                $columnMap[$key] = $value;
            }
        }

        $result = [];

        foreach ($rawFilters as $requestKey => $entry) {
            if (!array_key_exists($requestKey, $columnMap)) {
                continue;
            }

            $sqlColumn = $columnMap[$requestKey];

            if (!is_array($entry) || !array_key_exists('value', $entry)) {
                $result[$sqlColumn] = [
                    'value' => $entry,
                    'operator' => '='
                ];
            } else {
                $result[$sqlColumn] = $entry;
            }
        }

        return $result;
    }
}