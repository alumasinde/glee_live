<?php

namespace App\Modules\Audit\Repositories;

use App\Core\Paginator;
use App\Core\QueryBuilder;
use PDO;

class AuditLogRepository
{
    /** Whitelisted sort keys => SQL expression (never interpolated raw). */
    private const SORTABLE = [
        'created_at' => 'al.created_at',
        'action'     => 'al.action',
        'entity'     => 'al.entity_type',
        'user'       => 'user_name',
    ];

    public function __construct(private PDO $db) {}

    /**
     * Persist one audit entry.
     *
     * @param int         $tenantId
     * @param int|null    $userId
     * @param string      $action      Machine-readable code, e.g. "user.login"
     * @param string|null $message     Human-readable sentence, e.g. "Albert Masinde logged in"
     * @param string|null $entityType  E.g. "user", "gatepass"
     * @param int|null    $entityId
     * @param array|null  $metadata    Arbitrary key/value context
     * @param string|null $ipAddress
     */
    public function log(
        int     $tenantId,
        ?int    $userId,
        string  $action,
        ?string $message    = null,
        ?string $entityType = null,
        ?int    $entityId   = null,
        ?array  $metadata   = null,
        ?string $ipAddress  = null
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO audit_logs
                (tenant_id, user_id, action, message, entity_type, entity_id, metadata, ip_address)
            VALUES
                (:tenant_id, :user_id, :action, :message, :entity_type, :entity_id, :metadata, :ip_address)
        ");

        $stmt->execute([
            ':tenant_id'   => $tenantId,
            ':user_id'     => $userId,
            ':action'      => $action,
            ':message'     => $message,
            ':entity_type' => $entityType,
            ':entity_id'   => $entityId,
            ':metadata'    => $this->encodeMetadata($metadata),
            ':ip_address'  => $ipAddress,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | PAGINATED LOG LIST (tenant scoped)
    |--------------------------------------------------------------------------
    |
    | Supported filters: action, entity_type, entity_id, user_id,
    | date_from / date_to (Y-m-d), search, sort_by, sort_dir, page, per_page.
    |
    | @return array{data: array, meta: array}
    */
    public function paginate(
        int   $tenantId,
        array $filters = [],
        int   $page    = 1,
        int   $perPage = 25
    ): array {
        $bindings = [':tenant_id' => $tenantId];

        $sql = $this->baseSelect() . "
            WHERE al.tenant_id = :tenant_id
        ";

        // -------------------------
        // EXACT-VALUE FILTERS
        // -------------------------
        $action = trim((string) ($filters['action'] ?? ''));

        if ($action !== '') {
            $sql .= " AND al.action = :action";
            $bindings[':action'] = $action;
        }

        $entityType = trim((string) ($filters['entity_type'] ?? ''));

        if ($entityType !== '') {
            $sql .= " AND al.entity_type = :entity_type";
            $bindings[':entity_type'] = $entityType;
        }

        $entityId = (int) ($filters['entity_id'] ?? 0);

        if ($entityId > 0) {
            $sql .= " AND al.entity_id = :entity_id";
            $bindings[':entity_id'] = $entityId;
        }

        $userId = (int) ($filters['user_id'] ?? 0);

        if ($userId > 0) {
            $sql .= " AND al.user_id = :user_id";
            $bindings[':user_id'] = $userId;
        }

        // -------------------------
        // DATE RANGE (inclusive days)
        // -------------------------
        $dateFrom = trim((string) ($filters['date_from'] ?? ''));

        if ($this->isDate($dateFrom)) {
            $sql .= " AND al.created_at >= :date_from";
            $bindings[':date_from'] = $dateFrom . ' 00:00:00';
        }

        $dateTo = trim((string) ($filters['date_to'] ?? ''));

        if ($this->isDate($dateTo)) {
            $sql .= " AND al.created_at <= :date_to";
            $bindings[':date_to'] = $dateTo . ' 23:59:59';
        }

        // -------------------------
        // SEARCH (whitelisted columns, bound LIKE terms)
        // -------------------------
        $sql = QueryBuilder::applySearch(
            $sql,
            [
                'al.action',
                'al.message',
                'al.entity_type',
                'u.first_name',
                'u.last_name',
                'u.email',
            ],
            $bindings,
            $filters['search'] ?? $filters['q'] ?? null
        );

        // -------------------------
        // SORT (whitelist only)
        // -------------------------
        $sortKey = strtolower(trim((string) ($filters['sort_by'] ?? 'created_at')));
        $sortCol = self::SORTABLE[$sortKey] ?? self::SORTABLE['created_at'];
        $sortDir = strtolower(trim((string) ($filters['sort_dir'] ?? 'desc'))) === 'asc' ? 'ASC' : 'DESC';

        $dataSql = $sql . " ORDER BY {$sortCol} {$sortDir}, al.id DESC";

        $paginator = new Paginator(
            $this->db,
            $sql,                                 // COUNT wrapper (no ORDER BY)
            $bindings,
            max(1, $page),
            $perPage
        );

        return $paginator->paginate($dataSql);
    }

    /*
    |--------------------------------------------------------------------------
    | SINGLE ENTRY (tenant scoped) — carries the raw JSON metadata column
    |--------------------------------------------------------------------------
    */
    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                al.*,
                CONCAT(u.first_name, ' ', u.last_name) AS user_name,
                u.email       AS user_email,
                u.username    AS user_username,
                t.name        AS tenant_name
            FROM audit_logs al

            LEFT JOIN users u
                ON u.id = al.user_id
               AND u.tenant_id = al.tenant_id

            LEFT JOIN tenants t
                ON t.id = al.tenant_id

            WHERE al.tenant_id = :tenant_id
              AND al.id        = :id

            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':id'        => $id,
        ]);

        $entry = $stmt->fetch(PDO::FETCH_ASSOC);

        return $entry ?: null;
    }

    /*
    |--------------------------------------------------------------------------
    | RELATED ENTRIES (same entity, newest first) — context for the detail view
    |--------------------------------------------------------------------------
    */
    public function relatedTo(int $tenantId, ?string $entityType, ?int $entityId, int $excludeId, int $limit = 10): array
    {
        if ($entityType === null || $entityType === '' || !$entityId || $entityId <= 0) {
            return [];
        }

        $limit = max(1, min($limit, 50));

        $stmt = $this->db->prepare("
            SELECT
                al.id,
                al.action,
                al.message,
                al.created_at,
                CONCAT(u.first_name, ' ', u.last_name) AS user_name
            FROM audit_logs al

            LEFT JOIN users u
                ON u.id = al.user_id
               AND u.tenant_id = al.tenant_id

            WHERE al.tenant_id   = :tenant_id
              AND al.entity_type = :entity_type
              AND al.entity_id   = :entity_id
              AND al.id         <> :exclude_id

            ORDER BY al.created_at DESC
            LIMIT {$limit}
        ");

        $stmt->execute([
            ':tenant_id'   => $tenantId,
            ':entity_type' => $entityType,
            ':entity_id'   => $entityId,
            ':exclude_id'  => $excludeId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | FILTER BAR OPTIONS
    |--------------------------------------------------------------------------
    */
    public function actionOptions(int $tenantId): array
    {
        return $this->pluck(
            $tenantId,
            "
                SELECT al.action AS value, COUNT(*) AS hits
                FROM audit_logs al
                WHERE al.tenant_id = :tenant_id
                GROUP BY al.action
                ORDER BY al.action ASC
            ",
            static fn (array $row): string => $row['value'] . ' (' . (int) $row['hits'] . ')'
        );
    }

    public function entityTypeOptions(int $tenantId): array
    {
        $values = $this->pluck(
            $tenantId,
            "
                SELECT DISTINCT al.entity_type AS value
                FROM audit_logs al
                WHERE al.tenant_id = :tenant_id
                  AND al.entity_type IS NOT NULL
                  AND al.entity_type <> ''
                ORDER BY value ASC
            "
        );

        return $values;
    }

    /**
     * Actors seen in the log, resolved against the tenant's users so a
     * deleted account cannot leak another tenant's name.
     */
    public function userOptions(int $tenantId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                u.id,
                CONCAT(u.first_name, ' ', u.last_name) AS name,
                u.email
            FROM audit_logs al

            INNER JOIN users u
                ON u.id = al.user_id
               AND u.tenant_id = al.tenant_id

            WHERE al.tenant_id = :tenant_id

            GROUP BY u.id, u.first_name, u.last_name, u.email
            ORDER BY name ASC
        ");

        $stmt->execute([':tenant_id' => $tenantId]);

        $options = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $options[(int) $row['id']] = trim($row['name'] . ' — ' . $row['email']);
        }

        return $options;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Every alias in the SELECT list must be unique: Paginator wraps this SQL
     * in a COUNT(*) subquery, and duplicate column names break derived tables.
     */
    private function baseSelect(): string
    {
        return "
            SELECT
                al.id            AS id,
                al.action        AS action,
                al.message       AS message,
                al.entity_type   AS entity_type,
                al.entity_id     AS entity_id,
                al.metadata      AS metadata,
                al.ip_address    AS ip_address,
                al.created_at    AS created_at,
                al.user_id       AS user_id,
                CONCAT(u.first_name, ' ', u.last_name) AS user_name,
                u.email          AS user_email
            FROM audit_logs al

            LEFT JOIN users u
                ON u.id = al.user_id
               AND u.tenant_id = al.tenant_id
        ";
    }

    /**
     * Run a tenant-scoped option query and return value => label.
     */
    private function pluck(int $tenantId, string $sql, ?callable $label = null): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':tenant_id' => $tenantId]);

        $options = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $value = (string) ($row['value'] ?? '');

            if ($value === '') {
                continue;
            }

            $options[$value] = $label !== null ? $label($row) : $value;
        }

        return $options;
    }

    private function isDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1
            && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4));
    }

    private function encodeMetadata(?array $metadata): ?string
    {
        if (empty($metadata)) {
            return null;
        }

        return json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
