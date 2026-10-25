<?php
declare(strict_types=1);

namespace App\Modules\Gatepass\Repositories;

use App\Core\DB;
use App\Core\SearchBuilder;
use InvalidArgumentException;
use PDO;

final class GatepassRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    /*
    |--------------------------------------------------------------------------
    | CREATE
    |--------------------------------------------------------------------------
    */
    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO gatepasses (
                tenant_id, visit_id, gatepass_number, scan_token, gatepass_type_id,
                status_id, created_by, purpose, is_returnable,
                expected_return_date, needs_approval, department_id
            ) VALUES (
                :tenant_id, :visit_id, :gatepass_number, :scan_token, :gatepass_type_id,
                :status_id, :created_by, :purpose, :is_returnable,
                :expected_return_date, :needs_approval, :department_id
            )
        ");

        $stmt->execute([
            ':tenant_id'            => (int) $data['tenant_id'],
            ':visit_id'             => isset($data['visit_id']) ? (int) $data['visit_id'] : null,
            ':gatepass_number'      => trim($data['gatepass_number']),
            ':scan_token'           => bin2hex(random_bytes(32)),
            ':gatepass_type_id'     => isset($data['gatepass_type_id']) ? (int) $data['gatepass_type_id'] : null,
            ':status_id'            => (int) $data['status_id'],
            ':created_by'           => (int) $data['created_by'],
            ':purpose'              => trim($data['purpose']),
            ':is_returnable'        => (int) $data['is_returnable'],
            ':expected_return_date' => $data['expected_return_date'] ?? null,
            ':needs_approval'       => (int) $data['needs_approval'],
            ':department_id'        => (int) $data['department_id'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE (partial — only whitelisted fields)
    |--------------------------------------------------------------------------
    */
    public function update(int $tenantId, int $id, array $data): bool
    {
        $allowed = [
            'visit_id', 'gatepass_type_id', 'purpose',
            'is_returnable', 'expected_return_date', 'needs_approval',
        ];

        $setClauses = [];
        $bindings   = [':tenant_id' => $tenantId, ':id' => $id];

        foreach ($allowed as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            $setClauses[] = "{$field} = :{$field}";

            $bindings[":{$field}"] = match ($field) {
                'visit_id', 'gatepass_type_id'
                    => $data[$field] ? (int) $data[$field] : null,
                'is_returnable', 'needs_approval'
                    => (int) (bool) $data[$field],
                'purpose'
                    => trim((string) $data[$field]),
                default => $data[$field],
            };
        }

        if (empty($setClauses)) {
            throw new InvalidArgumentException('No updatable fields provided.');
        }

        if (isset($bindings[':purpose']) && $bindings[':purpose'] === '') {
            throw new InvalidArgumentException('Purpose cannot be empty.');
        }

        $stmt = $this->db->prepare("
            UPDATE gatepasses
            SET " . implode(', ', $setClauses) . "
            WHERE tenant_id = :tenant_id
              AND id        = :id
        ");

        $stmt->execute($bindings);

        return $stmt->rowCount() > 0;
    }

    /*
    |--------------------------------------------------------------------------
    | UPDATE STATUS
    |--------------------------------------------------------------------------
    */
    public function updateStatus(int $tenantId, int $id, int $statusId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE gatepasses
            SET status_id = :status_id
            WHERE tenant_id = :tenant_id
              AND id        = :id
        ");

        $stmt->execute([
            ':status_id' => $statusId,
            ':tenant_id' => $tenantId,
            ':id'        => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK IN
    | Pass the resolved status IDs in — do not inject a status repo here.
    |--------------------------------------------------------------------------
    */
    public function checkIn(
        int    $tenantId,
        int    $gatepassId,
        ?int   $userId,
        string $timestamp,
        int    $checkedInStatusId,
        int    $checkedOutStatusId,
        int    $approvedStatusId,
        ?int   $gateId = null
    ): bool {
        $stmt = $this->db->prepare("
            UPDATE gatepasses
            SET actual_in     = :timestamp,
                checked_in_by = :user_id,
                status_id     = :checked_in_status
                " . ($gateId ? ", last_in_gate_id = :gate_id" : '') . "
            WHERE tenant_id = :tenant_id
              AND id        = :id
              AND actual_in IS NULL
              AND status_id IN (:checked_out_status, :approved_status)
        ");

        $params = [
            ':timestamp'          => $timestamp,
            ':user_id'            => $userId,
            ':checked_in_status'  => $checkedInStatusId,
            ':tenant_id'          => $tenantId,
            ':id'                 => $gatepassId,
            ':checked_out_status' => $checkedOutStatusId,
            ':approved_status'    => $approvedStatusId,
        ];

        if ($gateId) {
            $params[':gate_id'] = $gateId;
        }

        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /*
    |--------------------------------------------------------------------------
    | CHECK OUT
    | Pass the resolved status ID in — do not inject a status repo here.
    |--------------------------------------------------------------------------
    */
    public function checkOut(
        int    $tenantId,
        int    $gatepassId,
        ?int   $userId,
        string $timestamp,
        int    $checkedOutStatusId,
        ?int   $gateId = null
    ): bool {
        $set = "
                actual_out     = :timestamp,
                checked_out_by = :user_id,
                status_id      = :status_id";

        $params = [
            ':timestamp' => $timestamp,
            ':user_id'   => $userId,
            ':status_id' => $checkedOutStatusId,
            ':tenant_id' => $tenantId,
            ':id'        => $gatepassId,
        ];

        if ($gateId) {
            $set .= ",\n                last_out_gate_id = :gate_id";
            $params[':gate_id'] = $gateId;
        }

        $stmt = $this->db->prepare("
            UPDATE gatepasses
            SET $set
            WHERE tenant_id    = :tenant_id
              AND id           = :id
              AND actual_out IS NULL
        ");

        $stmt->execute($params);

        return $stmt->rowCount() > 0;
    }

    /*
    |--------------------------------------------------------------------------
    | FIND BY ID
    |--------------------------------------------------------------------------
    */
    public function findById(int $tenantId, int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                g.id, g.tenant_id, g.gatepass_number, g.scan_token, g.visit_id,
                g.gatepass_type_id, g.status_id, g.department_id,
                g.checked_in_by, g.checked_out_by,
                g.actual_in, g.actual_out,
                g.created_by, g.created_at, g.purpose,
                g.is_returnable, g.expected_return_date,
                g.actual_return_date, g.is_fully_returned, g.needs_approval,
                s.name  AS status_name,
                s.code  AS status_code,
                gt.name AS gatepass_type_name,
                gt.type_code,
                gt.allowed_actions,
                u.first_name AS created_by_first_name,
                u.last_name  AS created_by_last_name
            FROM gatepasses g
            INNER JOIN gatepass_statuses s
                ON s.id = g.status_id AND s.tenant_id = g.tenant_id
            INNER JOIN gatepass_types gt
                ON gt.id = g.gatepass_type_id AND gt.tenant_id = g.tenant_id
            LEFT JOIN users u
                ON u.id = g.created_by AND u.tenant_id = g.tenant_id
            WHERE g.tenant_id = :tenant_id
              AND g.id        = :id
            LIMIT 1
        ");

        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['status_code'] = strtoupper($row['status_code']);
        $row['type_code']   = strtoupper($row['type_code']);

        return $row;
    }

    /*
    |--------------------------------------------------------------------------
    | FIND BY GATEPASS NUMBER
    |--------------------------------------------------------------------------
    */
    public function findByNumber(int $tenantId, string $number): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                g.*,
                s.name     AS status_name,
                s.code     AS status_code,
                gt.name    AS gatepass_type_name,
                gt.type_code,
                u.first_name,
                u.last_name
            FROM gatepasses g
            INNER JOIN gatepass_statuses s
                ON s.id = g.status_id AND s.tenant_id = g.tenant_id
            INNER JOIN gatepass_types gt
                ON gt.id = g.gatepass_type_id AND gt.tenant_id = g.tenant_id
            LEFT JOIN users u
                ON u.id = g.created_by AND u.tenant_id = g.tenant_id
            WHERE g.tenant_id       = :tenant_id
              AND g.gatepass_number = :number
            LIMIT 1
        ");

        $stmt->execute([':tenant_id' => $tenantId, ':number' => $number]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $row['status_code'] = strtoupper($row['status_code']);
        $row['type_code']   = strtoupper($row['type_code']);

        return $row;
    }

    /*
    |--------------------------------------------------------------------------
    | FIND BY SCAN TOKEN
    |--------------------------------------------------------------------------
    */
    public function findByToken(int $tenantId, string $token): ?array
    {
        $stmt = $this->db->prepare("
            SELECT g.* FROM gatepasses g
            WHERE g.tenant_id = :tenant_id
              AND g.scan_token = :token
            LIMIT 1
        ");

        $stmt->execute([':tenant_id' => $tenantId, ':token' => $token]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /*
    |--------------------------------------------------------------------------
    | FIND ALL (Searchable) — identical pattern to VisitorRepository
    |--------------------------------------------------------------------------
    */
    public function findAllByTenant(int $tenantId, ?string $search = null): array
    {
        $sql = "
            SELECT
                g.id, g.gatepass_number, g.actual_in, g.actual_out,
                g.is_returnable, g.needs_approval, g.purpose, g.created_at,

                gs.name AS status_name,
                gs.code AS status_code,

                gt.name AS gatepass_type_name,
                gt.type_code,

                d.name AS department_name,

                CONCAT(v.first_name, ' ', v.last_name) AS visitor_name,
                vc.name AS company,

                CONCAT(u.first_name, ' ', u.last_name) AS requested_by

            FROM gatepasses g

            LEFT JOIN visits vi
                ON vi.id = g.visit_id AND vi.tenant_id = g.tenant_id

            LEFT JOIN visitors v
                ON v.id = vi.visitor_id AND v.tenant_id = g.tenant_id

            LEFT JOIN visitor_companies vc
                ON vc.id = v.company_id AND vc.tenant_id = g.tenant_id

            LEFT JOIN gatepass_statuses gs
                ON gs.id = g.status_id AND gs.tenant_id = g.tenant_id

            LEFT JOIN gatepass_types gt
                ON gt.id = g.gatepass_type_id AND gt.tenant_id = g.tenant_id

            LEFT JOIN departments d
                ON d.id = g.department_id AND d.tenant_id = g.tenant_id

            LEFT JOIN users u
                ON u.id = g.created_by AND u.tenant_id = g.tenant_id

            WHERE g.tenant_id = :tenant_id
        ";

        $bindings = [
            ':tenant_id' => $tenantId,
        ];

        $sql = SearchBuilder::apply(
            $sql,
            [
                'g.gatepass_number',
                'g.purpose',
                'd.name',
                'v.first_name',
                'v.last_name',
                'vc.name',
                'u.first_name',
                'u.last_name',
            ],
            $bindings,
            $search
        );

        $sql .= " ORDER BY g.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | FIND ALL BY DEPARTMENT
    |--------------------------------------------------------------------------
    */
    public function findAllByDepartment(int $tenantId, int $departmentId, ?string $search = null): array
    {
        $sql = "
            SELECT
                g.id, g.gatepass_number, g.actual_in, g.actual_out,
                g.is_returnable, g.needs_approval, g.purpose, g.created_at,
                gs.name AS status_name,
                gs.code AS status_code,
                gt.name AS gatepass_type_name,
                gt.type_code,
                d.name  AS department_name,
                CONCAT(v.first_name, ' ', v.last_name) AS visitor_name,
                vc.name AS company,
                CONCAT(u.first_name, ' ', u.last_name) AS requested_by
            FROM gatepasses g
            LEFT JOIN visits vi
                ON vi.id = g.visit_id AND vi.tenant_id = g.tenant_id
            LEFT JOIN visitors v
                ON v.id = vi.visitor_id AND v.tenant_id = g.tenant_id
            LEFT JOIN visitor_companies vc
                ON vc.id = v.company_id AND vc.tenant_id = g.tenant_id
            LEFT JOIN gatepass_statuses gs
                ON gs.id = g.status_id AND gs.tenant_id = g.tenant_id
            LEFT JOIN gatepass_types gt
                ON gt.id = g.gatepass_type_id AND gt.tenant_id = g.tenant_id
            LEFT JOIN departments d
                ON d.id = g.department_id AND d.tenant_id = g.tenant_id
            LEFT JOIN users u
                ON u.id = g.created_by AND u.tenant_id = g.tenant_id
            WHERE g.tenant_id     = :tenant_id
              AND g.department_id = :department_id
        ";

        $bindings = [
            ':tenant_id'     => $tenantId,
            ':department_id' => $departmentId,
        ];

        $sql = SearchBuilder::apply(
            $sql,
            [
                'g.gatepass_number',
                'g.purpose',
                'd.name',
                'v.first_name',
                'v.last_name',
                'vc.name',
                'u.first_name',
                'u.last_name',
            ],
            $bindings,
            $search
        );

        $sql .= " ORDER BY g.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /*
    |--------------------------------------------------------------------------
    | WORKFLOW
    |--------------------------------------------------------------------------
    */
    public function getWorkflowIdFromType(int $tenantId, int $gatepassTypeId): ?int
    {
        $stmt = $this->db->prepare("
            SELECT workflow_id
            FROM workflow_gatepass_type
            WHERE tenant_id        = :tenant_id
              AND gatepass_type_id = :gatepass_type_id
            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id'        => $tenantId,
            ':gatepass_type_id' => $gatepassTypeId,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int) $row['workflow_id'] : null;
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE
    |--------------------------------------------------------------------------
    */
    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM gatepasses
            WHERE tenant_id = :tenant_id
              AND id        = :id
        ");

        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);

        return $stmt->rowCount() > 0;
    }
}