<?php

declare(strict_types=1);

namespace App\Modules\Visits\Repositories;

use App\Core\DB;
use PDO;
use RuntimeException;

final class VisitRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    /*
    |--------------------------------------------------------------------------
    | BASE SELECT (Prevents N+1)
    |--------------------------------------------------------------------------
    */
    private function baseSelect(): string
    {
        return "
            SELECT 
    v.*,

    CONCAT(vis.first_name, ' ', vis.last_name) AS visitor_name,
    CONCAT(u.first_name, ' ', u.last_name)     AS host_name,

    d.name  AS department_name,
    vc.name AS visitor_company,
    s.name  AS status_name,

    vb.badge_code,
    vb.printed_at      AS badge_issued_at,
    vb.returned_at    AS badge_returned_at,
    vb.is_active      AS badge_active

FROM visits v

INNER JOIN visitors vis 
    ON vis.id = v.visitor_id
   AND vis.tenant_id = v.tenant_id

LEFT JOIN users u 
    ON u.id = v.host_user_id
   AND u.tenant_id = v.tenant_id

LEFT JOIN departments d 
    ON d.id = v.department_id
   AND d.tenant_id = v.tenant_id

LEFT JOIN visitor_companies vc 
    ON vc.id = vis.company_id
   AND vc.tenant_id = v.tenant_id

LEFT JOIN visit_statuses s 
    ON s.id = v.visit_status_id
   AND s.tenant_id = v.tenant_id

LEFT JOIN visit_badges vb 
    ON vb.id = (
        SELECT id
        FROM visit_badges
        WHERE visit_id = v.id
          AND tenant_id = v.tenant_id
        ORDER BY printed_at DESC
        LIMIT 1
    )
        ";
    }

    /*
    |--------------------------------------------------------------------------
    | FIND BY ID
    |--------------------------------------------------------------------------
    */
    public function find(int $tenantId, int $visitId): ?array
    {
        $sql = $this->baseSelect() . "
            WHERE v.tenant_id = :tenant_id
              AND v.id = :id
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':id', $visitId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /*
    |--------------------------------------------------------------------------
    | LIST ALL
    |--------------------------------------------------------------------------
    */
    public function all(int $tenantId): array
    {
        $sql = $this->baseSelect() . "
            WHERE v.tenant_id = :tenant_id
            ORDER BY v.created_at DESC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */
    public function create(array $data): int
{
    $sql = "
        INSERT INTO visits (
            tenant_id,
            department_id,
            visitor_id,
            host_user_id,
            visit_type_id,
            visit_status_id,
            purpose,
            expected_in,
            expected_out,
            created_by,
            created_at,
            updated_at
        ) VALUES (
            :tenant_id,
            :department_id,
            :visitor_id,
            :host_user_id,
            :visit_type_id,
            :visit_status_id,
            :purpose,
            :expected_in,
            :expected_out,
            :created_by,
            NOW(),
            NOW()
        )
    ";

    $stmt = $this->db->prepare($sql);

    $stmt->bindValue(':tenant_id', $data['tenant_id'], PDO::PARAM_INT);
    $stmt->bindValue(':department_id', $data['department_id'], PDO::PARAM_INT);
    $stmt->bindValue(':visitor_id', $data['visitor_id'], PDO::PARAM_INT);
    $stmt->bindValue(':host_user_id', $data['host_user_id'], PDO::PARAM_INT);
    $stmt->bindValue(':visit_type_id', $data['visit_type_id'], PDO::PARAM_INT);
    $stmt->bindValue(':visit_status_id', $data['visit_status_id'], PDO::PARAM_INT);
    $stmt->bindValue(':purpose', $data['purpose']);
    $stmt->bindValue(':expected_in', $data['expected_in']);
    $stmt->bindValue(':expected_out', $data['expected_out']);
    $stmt->bindValue(':created_by', $data['created_by'], PDO::PARAM_INT);

    $stmt->execute();

    return (int) $this->db->lastInsertId();

    }

    /*
    |--------------------------------------------------------------------------
    | CHECK IN
    |--------------------------------------------------------------------------
    */
    public function checkIn(
        int $tenantId,
        int $visitId,
        int $checkedInStatusId
    ): void {

        $sql = "
            UPDATE visits
            SET checkin_time = NOW(),
                visit_status_id = :status_id,
                updated_at = NOW()
            WHERE tenant_id = :tenant_id
              AND id = :visit_id
              AND checkin_time IS NULL
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':visit_id', $visitId, PDO::PARAM_INT);
        $stmt->bindValue(':status_id', $checkedInStatusId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Visit cannot be checked in.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK OUT
    |--------------------------------------------------------------------------
    */
    public function checkOut(
        int $tenantId,
        int $visitId,
        int $checkedOutStatusId
    ): void {

        $sql = "
            UPDATE visits
            SET checkout_time = NOW(),
                visit_status_id = :status_id,
                updated_at = NOW()
            WHERE tenant_id = :tenant_id
              AND id = :visit_id
              AND checkin_time IS NOT NULL
              AND checkout_time IS NULL
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':visit_id', $visitId, PDO::PARAM_INT);
        $stmt->bindValue(':status_id', $checkedOutStatusId, PDO::PARAM_INT);
        $stmt->execute();

        if ($stmt->rowCount() === 0) {
            throw new RuntimeException('Visit cannot be checked out.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ACTIVE VISITS
    |--------------------------------------------------------------------------
    */
    public function getActiveVisits(int $tenantId): array
{
    $sql = $this->baseSelect() . "
        WHERE v.tenant_id = :tenant_id
          AND v.checkout_time IS NULL
        ORDER BY v.created_at DESC
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


  public function getDepartments(int $tenantId): array
{
    $sql = "
        SELECT 
            d.id,
            d.name,
            d.code
        FROM departments d
        WHERE d.tenant_id = :tenant_id
          AND d.is_active = 1
        ORDER BY d.name ASC
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}


public function getHosts(int $tenantId): array
{
    $sql = "
        SELECT id, first_name, last_name, department_id
        FROM users
        WHERE tenant_id = :tenant_id
          AND is_active = 1
        ORDER BY first_name ASC
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute(['tenant_id' => $tenantId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
public function getVisitTypes(int $tenantId): array
{
    $sql = "
        SELECT id, name
        FROM visit_types
        WHERE tenant_id = :tenant_id
        ORDER BY name ASC
    ";

    $stmt = $this->db->prepare($sql);
    $stmt->execute(['tenant_id' => $tenantId]);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
    /*
    |--------------------------------------------------------------------------
    | FIND ACTIVE VISIT FOR VISITOR
    |--------------------------------------------------------------------------
    */
    public function findActiveByVisitor(
        int $tenantId,
        int $visitorId
    ): ?array {

        $sql = "
            SELECT *
            FROM visits
            WHERE tenant_id = :tenant_id
              AND visitor_id = :visitor_id
              AND checkin_time IS NOT NULL
              AND checkout_time IS NULL
            LIMIT 1
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':tenant_id', $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(':visitor_id', $visitorId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }
}