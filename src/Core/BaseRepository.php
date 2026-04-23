<?php

namespace App\Core;

use PDO;

abstract class BaseRepository
{
    protected PDO $db;
    protected string $table;
    protected string $alias = 't';

    /** Fully-qualified columns for full-text search, e.g. ['t.name', 't.email'] */
    protected array $searchable = [];

    /**
     * Whitelisted filter keys.
     *
     * Two formats are supported:
     *   Indexed:   ['status', 'type']
     *              Key = column used in SQL = request key (alias prepended automatically)
     *   Associative: ['status' => 't.status', 'dept' => 'd.id']
     *              Left = request key, Right = actual SQL column
     */
    protected array $filterable = [];

    /** Whitelisted sort columns (no table alias, just the bare column name) */
    protected array $sortable = [];

    /** Default sort column */
    protected string $defaultSort = 'created_at';

    public function __construct()
    {
        $this->db = DB::connect();
    }
    /**
     * Paginated, searchable, filterable list for any module.
     *
     * @param int   $tenantId Tenant scope
     * @param array $params   {q, search, page, per_page, sort_by, sort_dir, filters}
     */
    public function list(int $tenantId, array $params = []): array
{
    $search  = $params['search'] ?? null;
    $filters = $params['filters'] ?? [];
    $sortBy  = $params['sort_by'] ?? 'created_at';
    $sortDir = strtolower($params['sort_dir'] ?? 'desc');
    $page    = (int)($params['page'] ?? 1);
    $perPage = (int)($params['per_page'] ?? 10);

    $bindings = [
        ':tenant_id' => $tenantId
    ];

    // FIX: start from FULL query, not baseQuery alone
    $sql = "
        {$this->selectColumns()}
        {$this->baseQuery()}
        WHERE {$this->alias}.tenant_id = :tenant_id
    ";

    // SEARCH
    if (!empty($this->searchable)) {
        $sql = QueryBuilder::applySearch(
            $sql,
            $this->searchable,
            $bindings,
            $search
        );
    }

    // FILTERS
    $allowedFilters = [];
    foreach ($this->filterable as $column) {
        if (array_key_exists($column, $filters)) {
            $allowedFilters[$column] = $filters[$column];
        }
    }

    $sql = QueryBuilder::applyFilters($sql, $allowedFilters, $bindings);

    // SORT SAFETY
    if (!in_array($sortBy, $this->sortable, true)) {
        $sortBy = 'created_at';
    }

    $sortDir = $sortDir === 'asc' ? 'ASC' : 'DESC';

    $orderBy = " ORDER BY {$this->alias}.{$sortBy} {$sortDir}";

    $dataSql = $sql . $orderBy;

    $paginator = new Paginator(
        $this->db,
        $sql,
        $bindings,
        $page,
        $perPage
    );

    return $paginator->paginate($dataSql);
}

    /**
     * Simple total count for a tenant (no filters).
     */
    public function count(int $tenantId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE tenant_id = :tenant_id"
        );
        $stmt->execute([':tenant_id' => $tenantId]);

        return (int) $stmt->fetchColumn();
    }

    // =========================================================================
    // HOOKS — override in subclasses to customise queries
    // =========================================================================

    /**
     * The FROM … WHERE tenant_id = :tenant_id fragment.
     * Override to add JOINs, extra base conditions, etc.
     */
    protected function baseQuery(): string
    {
        return "FROM {$this->table} {$this->alias}
                WHERE {$this->alias}.tenant_id = :tenant_id";
    }

    /**
     * The SELECT projection.
     * Override to add specific columns, computed fields, joined columns, etc.
     */
    protected function selectColumns(): string
    {
        return "SELECT {$this->alias}.*";
    }

    // =========================================================================
    // TRANSACTIONS
    // =========================================================================

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
    // PRIVATE HELPERS
    // =========================================================================

    /**
     * Resolve and whitelist filters from the request.
     *
     * Handles both $filterable formats:
     *   Indexed     ['status', 'type']           → key = column = alias.key
     *   Associative ['status' => 't.status']     → maps request key → SQL column
     *
     * Also handles both filter value shapes:
     *   Flat        'status' => 'active'
     *   Structured  'status' => ['value' => 'active', 'operator' => '=']
     *
     * @param  array $rawFilters Filters straight from the request
     * @return array             Normalised [sqlColumn => structuredEntry]
     */
    private function normaliseFilters(array $rawFilters): array
    {
        if (empty($rawFilters) || empty($this->filterable)) {
            return [];
        }

        // Build a lookup: requestKey → sqlColumn
        $columnMap = [];
        foreach ($this->filterable as $key => $value) {
            if (is_int($key)) {
                // Indexed format: use alias prefix for the SQL column
                $columnMap[$value] = "{$this->alias}.{$value}";
            } else {
                // Associative format: caller provides explicit SQL column
                $columnMap[$key] = $value;
            }
        }

        $result = [];
        foreach ($rawFilters as $requestKey => $entry) {
            if (!array_key_exists($requestKey, $columnMap)) {
                continue; // not whitelisted — skip silently
            }

            $sqlColumn = $columnMap[$requestKey];

            // Normalise value shape to structured form
            if (!is_array($entry) || !array_key_exists('value', $entry)) {
                // Flat scalar — wrap into structured shape
                $result[$sqlColumn] = ['value' => $entry, 'operator' => '='];
            } else {
                // Already structured
                $result[$sqlColumn] = $entry;
            }
        }

        return $result;
    }
}