<?php

declare(strict_types=1);

namespace App\Modules\Badges\Repositories;

use App\Core\DB;
use App\Core\Paginator;
use App\Core\QueryBuilder;
use PDO;

final class BadgeRepository
{
    /** Status slugs understood by list(). */
    public const STATUS_ISSUED   = 'issued';
    public const STATUS_RETURNED = 'returned';

    /** Whitelisted sort keys => SQL expression (never interpolated raw). */
    private const SORTABLE = [
        'issued'   => 'b.printed_at',
        'returned' => 'b.returned_at',
        'visit'    => 'v.id',
        'holder'   => 'holder_name',
        'badge'    => 'b.badge_code',
    ];

    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    /*
    |--------------------------------------------------------------------------
    | ISSUE BADGE
    |--------------------------------------------------------------------------
    */
    public function issue(
        int $tenantId,
        int $visitId,
        string $badgeCode
    ): int {

        // Deactivate any previous active badge
        $this->db->prepare("
            UPDATE visit_badges
            SET is_active = 0,
                returned_at = NOW()
            WHERE tenant_id = :tenant_id
              AND visit_id = :visit_id
              AND is_active = 1
        ")->execute([
            ':tenant_id' => $tenantId,
            ':visit_id'  => $visitId
        ]);

        $stmt = $this->db->prepare("
            INSERT INTO visit_badges (
                tenant_id,
                visit_id,
                badge_code,
                printed_at,
                is_active
            )
            VALUES (
                :tenant_id,
                :visit_id,
                :badge_code,
                NOW(),
                1
            )
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':visit_id'  => $visitId,
            ':badge_code'=> $badgeCode
        ]);

        return (int) $this->db->lastInsertId();
    }

    /*
    |--------------------------------------------------------------------------
    | RETURN BADGE
    |--------------------------------------------------------------------------
    */
    public function returnActiveBadge(
        int $tenantId,
        int $visitId
    ): void {
        $stmt = $this->db->prepare("
            UPDATE visit_badges
            SET is_active = 0,
                returned_at = NOW()
            WHERE tenant_id = :tenant_id
              AND visit_id = :visit_id
              AND is_active = 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':visit_id'  => $visitId
        ]);
    }

    public function hasActiveBadge(int $tenantId, int $visitId): bool
    {
        $sql = "
            SELECT 1
            FROM visit_badges
            WHERE tenant_id = :tenant_id
              AND visit_id  = :visit_id
              AND is_active = 1
              AND returned_at IS NULL
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':visit_id'  => $visitId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /*
    |--------------------------------------------------------------------------
    | FIND ACTIVE BADGE
    |--------------------------------------------------------------------------
    */
    public function findActiveByVisit(
        int $tenantId,
        int $visitId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM visit_badges
            WHERE tenant_id = :tenant_id
              AND visit_id = :visit_id
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':visit_id'  => $visitId
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /*
    |--------------------------------------------------------------------------
    | BADGE CODE TAKEN (uk_vb_tenant_code is per tenant)
    |--------------------------------------------------------------------------
    */
    public function badgeCodeExists(int $tenantId, string $badgeCode): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM visit_badges
            WHERE tenant_id  = :tenant_id
              AND badge_code = :badge_code
            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id'  => $tenantId,
            ':badge_code' => $badgeCode,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /*
    |--------------------------------------------------------------------------
    | PAGINATED BADGE LIST (tenant scoped)
    |--------------------------------------------------------------------------
    |
    | One row per printed badge. Filters:
    |   visit_id, holder_id (visitor), gatepass_id, status (issued|returned),
    |   q|search, sort_by, sort_dir, page, per_page
    |
    | @return array{data: array, meta: array}
    */
    public function list(int $tenantId, array $params = []): array
    {
        $bindings = [':tenant_id' => $tenantId];

        $sql = $this->baseSelect() . "
            WHERE b.tenant_id = :tenant_id
        ";

        // -------------------------
        // RELATED ENTITY FILTERS
        // -------------------------
        $visitId = (int) ($params['visit_id'] ?? 0);

        if ($visitId > 0) {
            $sql .= " AND v.id = :visit_id";
            $bindings[':visit_id'] = $visitId;
        }

        $holderId = (int) ($params['holder_id'] ?? 0);

        if ($holderId > 0) {
            $sql .= " AND v.visitor_id = :holder_id";
            $bindings[':holder_id'] = $holderId;
        }

        $gatepassId = (int) ($params['gatepass_id'] ?? 0);

        if ($gatepassId > 0) {
            $sql .= "
                AND EXISTS (
                    SELECT 1
                    FROM gatepasses gpf
                    WHERE gpf.visit_id  = v.id
                      AND gpf.tenant_id = b.tenant_id
                      AND gpf.id        = :gatepass_id
                )
            ";
            $bindings[':gatepass_id'] = $gatepassId;
        }

        // -------------------------
        // STATUS (derived: no returned_at + still active)
        // -------------------------
        $status = strtolower(trim((string) ($params['status'] ?? '')));

        if ($status === self::STATUS_ISSUED) {
            $sql .= " AND b.returned_at IS NULL AND b.is_active = 1";
        } elseif ($status === self::STATUS_RETURNED) {
            $sql .= " AND b.returned_at IS NOT NULL";
        }

        // -------------------------
        // SEARCH (whitelisted columns, bound LIKE terms)
        // -------------------------
        $sql = QueryBuilder::applySearch(
            $sql,
            [
                'b.badge_code',
                'vis.first_name',
                'vis.last_name',
                'vis.id_number',
                'vc.name',
                'v.purpose',
            ],
            $bindings,
            $params['q'] ?? $params['search'] ?? null
        );

        // -------------------------
        // SORT (whitelist only)
        // -------------------------
        $sortKey  = strtolower(trim((string) ($params['sort_by'] ?? 'issued')));
        $sortCol  = self::SORTABLE[$sortKey] ?? self::SORTABLE['issued'];
        $sortDir  = strtolower(trim((string) ($params['sort_dir'] ?? 'desc'))) === 'asc' ? 'ASC' : 'DESC';

        $dataSql = $sql . " ORDER BY {$sortCol} {$sortDir}, b.id DESC";

        $paginator = new Paginator(
            $this->db,
            $sql,                                 // COUNT wrapper (no ORDER BY)
            $bindings,
            max(1, (int) ($params['page'] ?? 1)),
            (int) ($params['per_page'] ?? 20)
        );

        return $paginator->paginate($dataSql);
    }

    /*
    |--------------------------------------------------------------------------
    | DISTINCT HOLDERS SEEN ON BADGES (filter dropdown)
    |--------------------------------------------------------------------------
    */
    public function holderOptions(int $tenantId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                vis.id   AS id,
                CONCAT(vis.first_name, ' ', vis.last_name) AS name
            FROM visit_badges b

            INNER JOIN visits v
                ON v.id = b.visit_id
               AND v.tenant_id = b.tenant_id

            INNER JOIN visitors vis
                ON vis.id = v.visitor_id
               AND vis.tenant_id = v.tenant_id

            WHERE b.tenant_id = :tenant_id

            GROUP BY vis.id, vis.first_name, vis.last_name
            ORDER BY name ASC
        ");

        $stmt->execute([':tenant_id' => $tenantId]);

        $options = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $options[(int) $row['id']] = $row['name'];
        }

        return $options;
    }

    /*
    |--------------------------------------------------------------------------
    | CANDIDATE VISITS FOR ISSUANCE
    |--------------------------------------------------------------------------
    |
    | On site right now (checked in, not checked out) and holding no live badge.
    */
    public function candidateVisits(int $tenantId, int $limit = 100): array
    {
        $limit = max(1, min($limit, 250));

        $stmt = $this->db->prepare("
            SELECT
                v.id,
                CONCAT(vis.first_name, ' ', vis.last_name) AS visitor_name,
                vis.id_number,
                vc.name  AS company_name,
                d.name   AS department_name,
                v.purpose,
                v.checkin_time,
                gp.gatepass_number
            FROM visits v

            INNER JOIN visitors vis
                ON vis.id = v.visitor_id
               AND vis.tenant_id = v.tenant_id

            LEFT JOIN visitor_companies vc
                ON vc.id = vis.company_id
               AND vc.tenant_id = vis.tenant_id

            LEFT JOIN departments d
                ON d.id = v.department_id
               AND d.tenant_id = v.tenant_id

            LEFT JOIN gatepasses gp
                ON gp.id = (
                    SELECT id
                    FROM gatepasses
                    WHERE visit_id = v.id
                      AND tenant_id = v.tenant_id
                    ORDER BY id DESC
                    LIMIT 1
                )

            WHERE v.tenant_id = :tenant_id
              AND v.checkin_time IS NOT NULL
              AND v.checkout_time IS NULL
              AND NOT EXISTS (
                    SELECT 1
                    FROM visit_badges ab
                    WHERE ab.visit_id    = v.id
                      AND ab.tenant_id   = v.tenant_id
                      AND ab.is_active   = 1
                      AND ab.returned_at IS NULL
              )

            ORDER BY v.checkin_time ASC
            LIMIT {$limit}
        ");

        $stmt->execute([':tenant_id' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | VISIT + HOLDER SNAPSHOT FOR THE ISSUANCE FORM
    |--------------------------------------------------------------------------
    */
    public function visitForIssuance(int $tenantId, int $visitId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                v.id,
                v.visitor_id,
                v.purpose,
                v.checkin_time,
                v.checkout_time,
                CONCAT(vis.first_name, ' ', vis.last_name) AS visitor_name,
                vis.id_number,
                vis.risk_score,
                vis.is_blacklisted,
                vc.name  AS company_name,
                d.name   AS department_name,
                CONCAT(hu.first_name, ' ', hu.last_name) AS host_name,
                gp.id    AS gatepass_id,
                gp.gatepass_number,
                ab.badge_code AS active_badge_code
            FROM visits v

            INNER JOIN visitors vis
                ON vis.id = v.visitor_id
               AND vis.tenant_id = v.tenant_id

            LEFT JOIN visitor_companies vc
                ON vc.id = vis.company_id
               AND vc.tenant_id = vis.tenant_id

            LEFT JOIN departments d
                ON d.id = v.department_id
               AND d.tenant_id = v.tenant_id

            LEFT JOIN users hu
                ON hu.id = v.host_user_id
               AND hu.tenant_id = v.tenant_id

            LEFT JOIN gatepasses gp
                ON gp.id = (
                    SELECT id
                    FROM gatepasses
                    WHERE visit_id = v.id
                      AND tenant_id = v.tenant_id
                    ORDER BY id DESC
                    LIMIT 1
                )

            LEFT JOIN visit_badges ab
                ON ab.id = (
                    SELECT id
                    FROM visit_badges
                    WHERE visit_id    = v.id
                      AND tenant_id   = v.tenant_id
                      AND is_active   = 1
                      AND returned_at IS NULL
                    ORDER BY printed_at DESC
                    LIMIT 1
                )

            WHERE v.tenant_id = :tenant_id
              AND v.id        = :visit_id

            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':visit_id'  => $visitId,
        ]);

        $visit = $stmt->fetch(PDO::FETCH_ASSOC);

        return $visit ?: null;
    }

    /*
    |--------------------------------------------------------------------------
    | BASE SELECT (badge-centric, every alias unique so the COUNT wrapper
    | used by Paginator cannot collide on column names)
    |--------------------------------------------------------------------------
    */
    private function baseSelect(): string
    {
        return "
            SELECT
                b.id            AS badge_id,
                b.badge_code    AS badge_code,
                b.printed_at    AS issued_at,
                b.returned_at   AS returned_at,
                b.is_active     AS is_active,

                v.id            AS visit_id,
                v.purpose       AS purpose,
                v.checkin_time  AS checkin_time,
                v.checkout_time AS checkout_time,

                vis.id          AS holder_id,
                CONCAT(vis.first_name, ' ', vis.last_name) AS holder_name,
                vis.id_number   AS holder_id_number,

                vc.name         AS company_name,
                d.name          AS department_name,

                gp.id           AS gatepass_id,
                gp.gatepass_number AS gatepass_number

            FROM visit_badges b

            INNER JOIN visits v
                ON v.id = b.visit_id
               AND v.tenant_id = b.tenant_id

            INNER JOIN visitors vis
                ON vis.id = v.visitor_id
               AND vis.tenant_id = v.tenant_id

            LEFT JOIN visitor_companies vc
                ON vc.id = vis.company_id
               AND vc.tenant_id = vis.tenant_id

            LEFT JOIN departments d
                ON d.id = v.department_id
               AND d.tenant_id = v.tenant_id

            LEFT JOIN gatepasses gp
                ON gp.id = (
                    SELECT id
                    FROM gatepasses
                    WHERE visit_id = v.id
                      AND tenant_id = v.tenant_id
                    ORDER BY id DESC
                    LIMIT 1
                )
        ";
    }
}
