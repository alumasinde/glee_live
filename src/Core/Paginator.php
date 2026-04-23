<?php

namespace App\Core;

use PDO;

/**
 * Paginator
 *
 * Accepts the BASE SQL (FROM … WHERE … without SELECT or ORDER BY),
 * runs a COUNT(*) query for the total, then appends LIMIT/OFFSET to
 * the full data query and returns both the rows and pagination metadata.
 *
 * The same $bindings array is reused for both queries, so search/filter
 * params are always in sync between the count and data fetches.
 */
class Paginator
{
    private PDO    $db;
    private string $baseSql;
    private array  $bindings;
    private int    $page;
    private int    $perPage;

    /** Hard cap on per-page to prevent runaway queries */
    private const MAX_PER_PAGE = 100;

    public function __construct(
        PDO    $db,
        string $baseSql,    // FROM … WHERE … (no SELECT, no ORDER BY)
        array  $bindings,   // All named params used in baseSql
        int    $page    = 1,
        int    $perPage = 20
    ) {
        $this->db       = $db;
        $this->baseSql  = $baseSql;
        $this->bindings = $bindings;
        $this->page     = max(1, $page);
        $this->perPage  = max(1, min($perPage, self::MAX_PER_PAGE));
    }

    /**
     * Run the paginated query.
     *
     * @param string $dataSql Full SELECT … FROM … WHERE … ORDER BY query (no LIMIT/OFFSET)
     * @return array{data: array, meta: array}
     */
    public function paginate(string $dataSql): array
    {
        $total  = $this->getTotal();
        $offset = ($this->page - 1) * $this->perPage;

        $stmt = $this->db->prepare($dataSql . ' LIMIT :limit OFFSET :offset');

        foreach ($this->bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->bindValue(':limit',  $this->perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset,        PDO::PARAM_INT);
        $stmt->execute();

        $lastPage = $total > 0
            ? (int) ceil($total / $this->perPage)
            : 1;

        return [
            'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'meta' => [
                'total'        => $total,
                'per_page'     => $this->perPage,
                'current_page' => $this->page,
                'last_page'    => $lastPage,
                'from'         => $total > 0 ? $offset + 1 : 0,
                'to'           => min($offset + $this->perPage, $total),
            ],
        ];
    }

    // -------------------------------------------------------------------------

    private function getTotal(): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) {$this->baseSql}");

        foreach ($this->bindings as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();

        return (int) $stmt->fetchColumn();
    }
}