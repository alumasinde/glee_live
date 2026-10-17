<?php
declare(strict_types=1);

namespace App\Modules\Reports\Repositories;

use App\Core\DB;
use PDO;

final class OccupancyReportRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    public function summary(int $tenantId): array
    {
        return [
            'gates'           => $this->byGate($tenantId),
            'gatepasses'      => $this->gatepassesOnSite($tenantId),
            'visits'          => $this->visitsOnSite($tenantId),
            'recent_activity' => $this->recentScans($tenantId),
        ];
    }

    private function byGate(int $tenantId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                g.id,
                g.name,
                g.code,
                g.is_active,
                (
                    SELECT COUNT(*) FROM gatepasses gp
                    WHERE gp.tenant_id = g.tenant_id
                      AND gp.last_in_gate_id  = g.id
                      AND gp.actual_in  IS NOT NULL
                      AND gp.actual_out IS NULL
                ) + (
                    SELECT COUNT(*) FROM visits v
                    WHERE v.tenant_id = g.tenant_id
                      AND v.last_in_gate_id  = g.id
                      AND v.checkin_time  IS NOT NULL
                      AND v.checkout_time IS NULL
                ) AS on_site,
                (
                    SELECT COUNT(*) FROM scan_logs sl
                    WHERE sl.tenant_id = g.tenant_id
                      AND sl.gate_id   = g.id
                      AND sl.action <> 'rejected'
                      AND DATE(sl.created_at) = CURDATE()
                ) AS scans_today
            FROM gates g
            WHERE g.tenant_id = :tenant_id
            ORDER BY g.name
        ");

        $stmt->execute([':tenant_id' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function gatepassesOnSite(int $tenantId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                gp.gatepass_number,
                gp.purpose,
                gp.actual_in,
                gp.is_returnable,
                gi.name  AS gate_name,
                go.name  AS out_gate_name,
                st.name  AS status_name,
                d.name   AS department_name,
                CONCAT(u.first_name, ' ', u.last_name) AS holder_name
            FROM gatepasses gp
            LEFT JOIN users  u  ON u.id  = gp.created_by AND u.tenant_id  = gp.tenant_id
            LEFT JOIN departments d ON d.id = gp.department_id AND d.tenant_id = gp.tenant_id
            LEFT JOIN gatepass_statuses st ON st.id = gp.status_id AND st.tenant_id = gp.tenant_id
            LEFT JOIN gates gi ON gi.id = gp.last_in_gate_id  AND gi.tenant_id = gp.tenant_id
            LEFT JOIN gates go ON go.id = gp.last_out_gate_id AND go.tenant_id = gp.tenant_id
            WHERE gp.tenant_id  = :tenant_id
              AND gp.actual_in  IS NOT NULL
              AND gp.actual_out IS NULL
            ORDER BY gp.actual_in DESC
            LIMIT 200
        ");

        $stmt->execute([':tenant_id' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function visitsOnSite(int $tenantId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                CONCAT(vis.first_name, ' ', vis.last_name) AS visitor_name,
                v.purpose,
                v.checkin_time,
                gi.name AS gate_name,
                d.name  AS department_name,
                CONCAT(h.first_name, ' ', h.last_name) AS host_name
            FROM visits v
            INNER JOIN visitors vis ON vis.id = v.visitor_id AND vis.tenant_id = v.tenant_id
            LEFT JOIN departments d ON d.id = v.department_id AND d.tenant_id = v.tenant_id
            LEFT JOIN users h       ON h.id = v.host_user_id  AND h.tenant_id = v.tenant_id
            LEFT JOIN gates gi      ON gi.id = v.last_in_gate_id AND gi.tenant_id = v.tenant_id
            WHERE v.tenant_id     = :tenant_id
              AND v.checkin_time  IS NOT NULL
              AND v.checkout_time IS NULL
            ORDER BY v.checkin_time DESC
            LIMIT 200
        ");

        $stmt->execute([':tenant_id' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function recentScans(int $tenantId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                sl.created_at,
                sl.subject_type,
                sl.action,
                sl.source,
                g.name AS gate_name,
                COALESCE(gp.gatepass_number, '') AS gatepass_number,
                COALESCE(CONCAT(vis.first_name, ' ', vis.last_name), '') AS visitor_name
            FROM scan_logs sl
            LEFT JOIN gates g      ON g.id = sl.gate_id AND g.tenant_id = sl.tenant_id
            LEFT JOIN gatepasses gp ON gp.id = sl.subject_id
                AND sl.subject_type = 'gatepass' AND gp.tenant_id = sl.tenant_id
            LEFT JOIN visits v      ON v.id = sl.subject_id
                AND sl.subject_type = 'visit' AND v.tenant_id = sl.tenant_id
            LEFT JOIN visitors vis  ON vis.id = v.visitor_id AND vis.tenant_id = sl.tenant_id
            WHERE sl.tenant_id = :tenant_id
            ORDER BY sl.created_at DESC
            LIMIT 30
        ");

        $stmt->execute([':tenant_id' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
