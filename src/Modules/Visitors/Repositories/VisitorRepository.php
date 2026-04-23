<?php

declare(strict_types=1);

namespace App\Modules\Visitors\Repositories;

use App\Core\DB;
use App\Core\SearchBuilder;
use PDO;

final class VisitorRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    /*
    |--------------------------------------------------------------------------
    | FIND BY ID
    |--------------------------------------------------------------------------
    */
    public function find(int $tenantId, int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM visitors
            WHERE tenant_id = :tenant_id
              AND id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':id' => $id
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /*
    |--------------------------------------------------------------------------
    | FIND BY ID NUMBER (Normalized)
    |--------------------------------------------------------------------------
    */
    public function findByIdNumber(int $tenantId, string $idNumber): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM visitors
            WHERE tenant_id = :tenant_id
              AND UPPER(TRIM(id_number)) = UPPER(TRIM(:id_number))
            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':id_number' => $idNumber
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO visitors (
                tenant_id,
                first_name,
                last_name,
                id_type_id,
                id_number,
                phone,
                email,
                company_id,
                risk_score,
                is_blacklisted
            )
            VALUES (
                :tenant_id,
                :first_name,
                :last_name,
                :id_type_id,
                :id_number,
                :phone,
                :email,
                :company_id,
                :risk_score,
                :is_blacklisted
            )
        ");

        $stmt->execute([
            ':tenant_id'      => $data['tenant_id'],
            ':first_name'     => $data['first_name'],
            ':last_name'      => $data['last_name'],
            ':id_type_id'     => $data['id_type_id'] ?? null,
            ':id_number'      => $data['id_number'] ?? null,
            ':phone'          => $data['phone'] ?? null,
            ':email'          => $data['email'] ?? null,
            ':company_id'     => $data['company_id'] ?? null,
            ':risk_score'     => $data['risk_score'] ?? 0,
            ':is_blacklisted' => $data['is_blacklisted'] ?? 0,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE
    |--------------------------------------------------------------------------
    */
    public function update(int $tenantId, int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE visitors
            SET first_name = :first_name,
                last_name = :last_name,
                id_type_id = :id_type_id,
                id_number = :id_number,
                phone = :phone,
                email = :email,
                company_id = :company_id
            WHERE tenant_id = :tenant_id
              AND id = :id
        ");

        $stmt->execute([
            ':tenant_id'  => $tenantId,
            ':id'         => $id,
            ':first_name' => $data['first_name'],
            ':last_name'  => $data['last_name'],
            ':id_type_id' => $data['id_type_id'] ?? null,
            ':id_number'  => $data['id_number'] ?? null,
            ':phone'      => $data['phone'] ?? null,
            ':email'      => $data['email'] ?? null,
            ':company_id' => $data['company_id'] ?? null,
        ]);
    }

  

public function allWithRelations(int $tenantId): array
{
    $sql = "
        SELECT 
            v.id,
            v.first_name,
            v.last_name,
            v.id_number,
            v.risk_score,
            v.is_blacklisted,
            v.created_at,

            it.name AS id_type_name,
            vc.name AS company_name,

            COALESCE(stats.total_visits, 0)      AS total_visits,
            COALESCE(stats.active_visits, 0)     AS active_visits,
            stats.last_visit_at

        FROM visitors v

        LEFT JOIN identification_types it
            ON it.id = v.id_type_id
           AND it.tenant_id = v.tenant_id

        LEFT JOIN visitor_companies vc
            ON vc.id = v.company_id
           AND vc.tenant_id = v.tenant_id

        LEFT JOIN (
            SELECT 
                visitor_id,
                tenant_id,
                COUNT(*) AS total_visits,
                SUM(CASE 
                        WHEN checkin_time IS NOT NULL 
                         AND checkout_time IS NULL 
                        THEN 1 ELSE 0 
                    END) AS active_visits,
                MAX(created_at) AS last_visit_at
            FROM visits
            GROUP BY visitor_id, tenant_id
        ) stats
            ON stats.visitor_id = v.id
           AND stats.tenant_id = v.tenant_id

        WHERE v.tenant_id = :tenant_id
    ";

    $bindings = [
        ':tenant_id' => $tenantId
    ];

    // Global Search
    $sql = SearchBuilder::apply(
        $sql,
        [
            'v.first_name',
            'v.last_name',
            'v.id_number',
            'vc.name',
            'it.name'
        ],
        $bindings
    );

    $sql .= " ORDER BY v.created_at DESC";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($bindings);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

public function findWithVisits(int $tenantId, int $visitorId): array
{
    // 1️⃣ Fetch Visitor Details
    $stmt = $this->db->prepare("
        SELECT 
            v.*,
            it.name AS id_type_name,
            vc.name AS company_name
        FROM visitors v

        LEFT JOIN identification_types it
            ON it.id = v.id_type_id
           AND it.tenant_id = v.tenant_id

        LEFT JOIN visitor_companies vc
            ON vc.id = v.company_id
           AND vc.tenant_id = v.tenant_id

        WHERE v.tenant_id = :tenant_id
          AND v.id = :visitor_id
        LIMIT 1
    ");

    $stmt->execute([
        ':tenant_id' => $tenantId,
        ':visitor_id' => $visitorId
    ]);

    $visitor = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$visitor) {
        return [];
    }

    // 2️⃣ Fetch Visit History (Enriched)
    $stmt = $this->db->prepare("
        SELECT 
            vis.id,
            vis.created_at,
            vis.checkin_time,
            vis.checkout_time,

            d.name  AS department_name,
            s.name  AS status_name,

            vb.badge_code,
            vb.returned_at AS badge_returned_at,
            vb.is_active   AS badge_active

        FROM visits vis

        LEFT JOIN departments d
            ON d.id = vis.department_id
           AND d.tenant_id = vis.tenant_id

        LEFT JOIN visit_statuses s
            ON s.id = vis.visit_status_id
           AND s.tenant_id = vis.tenant_id

        LEFT JOIN visit_badges vb
            ON vb.id = (
                SELECT id
                FROM visit_badges
                WHERE visit_id = vis.id
                  AND tenant_id = vis.tenant_id
                ORDER BY printed_at DESC
                LIMIT 1
            )

        WHERE vis.tenant_id = :tenant_id
          AND vis.visitor_id = :visitor_id

        ORDER BY vis.created_at DESC
    ");

    $stmt->execute([
        ':tenant_id' => $tenantId,
        ':visitor_id' => $visitorId
    ]);

    $visitor['visits'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $visitor;
}

public function countVisits(int $tenantId, int $visitorId): int
{
    $sql = "
        SELECT COUNT(*) 
        FROM visits 
        WHERE tenant_id = :tenant_id 
          AND visitor_id = :visitor_id
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute([
        ':tenant_id'  => $tenantId,
        ':visitor_id' => $visitorId,
    ]);

    return (int) $stmt->fetchColumn();
}

    public function isOnWatchlist(int $tenantId, int $visitorId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM visitor_watchlist
            WHERE tenant_id = :tenant_id
              AND visitor_id = :visitor_id
            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id'  => $tenantId,
            ':visitor_id' => $visitorId
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /*
    |--------------------------------------------------------------------------
    | BLACKLIST
    |--------------------------------------------------------------------------
    */
    public function blacklist(int $tenantId, int $visitorId, int $riskScore = 100): void
    {
        $stmt = $this->db->prepare("
            UPDATE visitors
            SET is_blacklisted = 1,
                risk_score = :risk_score
            WHERE tenant_id = :tenant_id
              AND id = :visitor_id
        ");

        $stmt->execute([
            ':tenant_id'  => $tenantId,
            ':visitor_id' => $visitorId,
            ':risk_score' => $riskScore
        ]);
    }

    public function unblacklist(int $tenantId, int $visitorId): void
    {
        $stmt = $this->db->prepare("
            UPDATE visitors
            SET is_blacklisted = 0,
                risk_score = 0
            WHERE tenant_id = :tenant_id
              AND id = :visitor_id
        ");

        $stmt->execute([
            ':tenant_id'  => $tenantId,
            ':visitor_id' => $visitorId
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | IDENTIFICATION TYPES
    |--------------------------------------------------------------------------
    */
    public function getIdentificationTypes(int $tenantId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name
            FROM identification_types
            WHERE tenant_id = :tenant_id
            ORDER BY name ASC
        ");

        $stmt->execute([':tenant_id' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | COMPANIES
    |--------------------------------------------------------------------------
    */
    public function getCompanies(int $tenantId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name
            FROM visitor_companies
            WHERE tenant_id = :tenant_id
            ORDER BY name ASC
        ");

        $stmt->execute([':tenant_id' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE COMPANY (Safe Reuse)
    |--------------------------------------------------------------------------
    */
    public function createCompany(int $tenantId, string $name): int
    {
        $normalized = trim($name);

        $stmt = $this->db->prepare("
            SELECT id
            FROM visitor_companies
            WHERE tenant_id = :tenant_id
              AND UPPER(TRIM(name)) = UPPER(TRIM(:name))
            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':name'      => $normalized
        ]);

        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            return (int) $existing['id'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO visitor_companies (tenant_id, name)
            VALUES (:tenant_id, :name)
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':name'      => $normalized
        ]);

        return (int) $this->db->lastInsertId();
    }
}