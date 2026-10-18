<?php

namespace App\Modules\Reports\Repositories;

use App\Core\DB;
use PDO;

final class DepartmentReportRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    /**
     * One row per active department: linked users, gatepass volume, visit volume.
     */
    public function summary(int $tenantId): array
    {
        $monthStart = date('Y-m-01 00:00:00');

        $stmt = $this->db->prepare("
            SELECT
                d.id,
                d.name,
                d.code,
                (SELECT COUNT(*) FROM users us
                  WHERE us.department_id = d.id
                    AND us.tenant_id     = d.tenant_id
                    AND us.is_active     = 1) AS staff_count,
                (SELECT COUNT(*) FROM gatepasses gp
                  WHERE gp.department_id = d.id
                    AND gp.tenant_id     = d.tenant_id) AS gatepasses_total,
                (SELECT COUNT(*) FROM gatepasses gp
                  WHERE gp.department_id = d.id
                    AND gp.tenant_id     = d.tenant_id
                    AND gp.created_at   >= :month1) AS gatepasses_month,
                (SELECT COUNT(*) FROM gatepasses gp
                  WHERE gp.department_id = d.id
                    AND gp.tenant_id     = d.tenant_id
                    AND gp.actual_in    IS NOT NULL
                    AND gp.actual_out   IS NULL) AS gatepasses_on_site,
                (SELECT COUNT(*) FROM visits v
                  WHERE v.department_id = d.id
                    AND v.tenant_id     = d.tenant_id) AS visits_total,
                (SELECT COUNT(*) FROM visits v
                  WHERE v.department_id = d.id
                    AND v.tenant_id     = d.tenant_id
                    AND v.created_at   >= :month2) AS visits_month
            FROM departments d
            WHERE d.tenant_id = :tenant
              AND d.is_active = 1
            ORDER BY d.name
        ");

        $stmt->execute([
            ':tenant' => $tenantId,
            ':month1' => $monthStart,
            ':month2' => $monthStart,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
