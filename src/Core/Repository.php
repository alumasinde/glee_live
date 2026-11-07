<?php

namespace App\Core;

use PDO;

/**
 * Repository — single-file, multi-tenant data layer.
 *
 * Supersedes the ad-hoc query construction in BaseRepository for new code.
 * Search, filtering and sorting are delegated to QueryBuilder, which is now the
 * single implementation shared with BaseRepository and SearchBuilder.
 *
 * ┌─────────────────────────────────────────────┐
 * │  HOW TO USE IN A SUBCLASS                   │
 * ├─────────────────────────────────────────────┤
 * │  class GatepassRepository extends Repository│
 * │  {                                          │
 * │    protected string $table = 'gatepasses';  │
 * │    protected string $alias = 'g';           │
 * │                                             │
 * │    protected array $searchable = [          │
 * │        'g.reference_no', 'g.visitor_name',  │
 * │    ];                                       │
 * │                                             │
 * │    protected array $filterable = [          │
 * │        'status',           // indexed → g.status  │
 * │        'type' => 'g.type', // explicit SQL column │
 * │    ];                                       │
 * │                                             │
 * │    protected array $sortable = [            │
 * │        'created_at', 'visitor_name',        │
 * │    ];                                       │
 * │                                             │
 * │    // Optional overrides:                   │
 * │    protected function baseQuery(): string { │
 * │        return "FROM gatepasses g            │
 * │                JOIN departments d           │
 * │                  ON d.id = g.dept_id";      │
 * │    }                                        │
 * │                                             │
 * │    protected function selectColumns(): string { │
 * │        return "SELECT g.*, d.name AS dept"; │
 * │    }                                        │
 * │  }                                          │
 * └─────────────────────────────────────────────┘
 *
 * Then call:
 *   $repo->list($tenantId, $request->all());
 *
 * Expected $params keys:
 *   q / search   — full-text search string (both accepted)
 *   page         — page number (default 1)
 *   per_page     — rows per page (default 10, hard-capped at 100)
 *   sort_by      — column name (whitelisted against $sortable)
 *   sort_dir     — 'asc' or 'desc'
 *   filters      — associative array of filter values, flat or structured:
 *                    flat:       ['status' => 'active']
 *                    structured: ['status' => ['value'=>'active','operator'=>'=']]
 *                    range:      ['created_at' => ['value'=>['2024-01-01','2024-12-31'],'operator'=>'BETWEEN']]
 *                    list:       ['role_id' => ['value'=>[1,2,3],'operator'=>'IN']]
 */
abstract class Repository
{
    // ── Connection ───────────────────────────────────────────────────────────

    protected PDO $db;

    // ── Subclass configuration ───────────────────────────────────────────────

    /** Primary table name */
    protected string $table;

    /** SQL alias used in queries */
    protected string $alias = 't';

    /**
     * Fully-qualified columns for LIKE search.
     * @var string[]   e.g. ['t.name', 't.email']
     */
    protected array $searchable = [];

    /**
     * Whitelisted filter keys.
     *
     *   Indexed:      ['status', 'type']
     *                 → request key = SQL column with alias prefix (t.status, t.type)
     *
     *   Associative:  ['dept' => 'd.id']
     *                 → request key 'dept' maps to SQL column 'd.id'
     *
     * @var array<int|string, string>
     */
    protected array $filterable = [];

    /**
     * Whitelisted sort columns (bare name, no alias).
     * @var string[]
     */
    protected array $sortable = [];

    /** Fallback sort column when $sortBy is not whitelisted */
    protected string $defaultSort = 'created_at';

    /** Hard cap on per_page */
    private const MAX_PER_PAGE = 100;

    // ── Boot ─────────────────────────────────────────────────────────────────

    public function __construct()
    {
        $this->db = DB::connect();
    }

    // =========================================================================
    // PUBLIC API
    // =========================================================================

    /**
     * Paginated, searchable, filterable list scoped to a tenant.
     *
     * @param  int   $tenantId
     * @param  array $params   {q, search, page, per_page, sort_by, sort_dir, filters}
     * @return array{data: array, meta: array}
     */
    public function list(int $tenantId, array $params = []): array
    {
        // ── Parameters ───────────────────────────────────────────────────────
        $search  = trim((string)($params['q'] ?? $params['search'] ?? ''));
        $filters = $params['filters'] ?? [];
        $sortBy  = $params['sort_by'] ?? $this->defaultSort;
        $sortDir = strtolower($params['sort_dir'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
        $page    = max(1, (int)($params['page'] ?? 1));
        $perPage = max(1, min((int)($params['per_page'] ?? 10), self::MAX_PER_PAGE));

        // ── Base SQL ─────────────────────────────────────────────────────────
        $bindings = [':tenant_id' => $tenantId];

        $sql = "{$this->selectColumns()} {$this->baseQuery()} WHERE {$this->alias}.tenant_id = :tenant_id";

        // ── Search ───────────────────────────────────────────────────────────
        if ($search !== '' && !empty($this->searchable)) {
            $sql = QueryBuilder::applySearch($sql, $this->searchable, $bindings, $search);
        }

        // ── Filters ──────────────────────────────────────────────────────────
        if (!empty($filters)) {
            $normalised = $this->normaliseFilters($filters);

            if ($normalised !== []) {
                $sql = QueryBuilder::applyFilters($sql, $normalised, $bindings);
            }
        }

        // ── Sort ─────────────────────────────────────────────────────────────
        if (!in_array($sortBy, $this->sortable, true)) {
            $sortBy = $this->defaultSort;
        }
        $orderBy = " ORDER BY {$this->alias}.{$sortBy} {$sortDir}";

        // ── Count (reuse same FROM+WHERE, strip SELECT) ───────────────────────
        $fromWhere = $this->stripSelect($sql);
        $total     = $this->queryCount($fromWhere, $bindings);

        // ── Data ─────────────────────────────────────────────────────────────
        $offset   = ($page - 1) * $perPage;
        $dataSql  = $sql . $orderBy . ' LIMIT :limit OFFSET :offset';

        $stmt = $this->db->prepare($dataSql);
        foreach ($bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit',  $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,  PDO::PARAM_INT);
        $stmt->execute();

        $lastPage = $total > 0 ? (int)ceil($total / $perPage) : 1;

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'meta' => [
                'total'        => $total,
                'per_page'     => $perPage,
                'current_page' => $page,
                'last_page'    => $lastPage,
                'from'         => $total > 0 ? $offset + 1 : 0,
                'to'           => min($offset + $perPage, $total),
            ],
        ];
    }

    /**
     * Simple total count for a tenant (no filters applied).
     */
    public function count(int $tenantId): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE tenant_id = :tenant_id"
        );
        $stmt->execute([':tenant_id' => $tenantId]);
        return (int)$stmt->fetchColumn();
    }

    // =========================================================================
    // HOOKS — override in subclasses
    // =========================================================================

    /**
     * FROM … fragment. Override to add JOINs or extra base conditions.
     * Do NOT include WHERE here; the tenant scope is appended automatically.
     */
    protected function baseQuery(): string
    {
        return "FROM {$this->table} {$this->alias}";
    }

    /**
     * SELECT projection. Override to add computed fields, joined columns, etc.
     */
    protected function selectColumns(): string
    {
        return "SELECT {$this->alias}.*";
    }

    // =========================================================================
    // TRANSACTIONS
    // =========================================================================

    public function begin(): void    { $this->db->beginTransaction(); }
    public function commit(): void   { $this->db->commit(); }
    public function rollback(): void { if ($this->db->inTransaction()) $this->db->rollBack(); }

    // =========================================================================
    // INTERNAL — search, filter, count
    // =========================================================================

    /**
     * Translate request filter keys to whitelisted SQL columns.
     *
     * The clause construction itself lives in QueryBuilder, so a filter means
     * the same thing here and in BaseRepository.
     *
     * @return array<string, mixed> SQL column => raw value/operator entry
     */
    private function normaliseFilters(array $rawFilters): array
    {
        $colMap = [];

        foreach ($this->filterable as $key => $column) {
            if (is_int($key)) {
                $colMap[$column] = "{$this->alias}.{$column}";   // indexed
            } else {
                $colMap[$key] = $column;                        // associative
            }
        }

        $normalised = [];

        foreach ($rawFilters as $key => $raw) {
            if (!array_key_exists($key, $colMap)) {
                continue;
            }

            $normalised[$colMap[$key]] = $raw;
        }

        return $normalised;
    }

    /**
     * Strip the SELECT clause so we can wrap the rest in COUNT(*).
     * Works for both "SELECT …" and "SELECT …\nFROM …" styles.
     */
    private function stripSelect(string $sql): string
    {
        // Remove everything up to (and including) the first FROM
        return preg_replace('/^\s*SELECT\b.+?\bFROM\b/is', 'FROM', $sql);
    }

    /** Run a COUNT(*) against the FROM+WHERE fragment. */
    private function queryCount(string $fromWhere, array $bindings): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) {$fromWhere}");
        foreach ($bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        return (int)$stmt->fetchColumn();
    }
}