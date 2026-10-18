<?php

namespace App\Modules\Dashboard\Services;

use App\Core\DB;
use App\Core\Helpers\DateHelper;
use App\Core\Helpers\ChartHelper;
use PDO;

class DashboardService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    public function getStats(array $user): array
    {
        $tenantId = (int) $user['tenant_id'];
        $userId   = (int) $user['id'];

        $stats = [];

        if (can('gatepass.view')) {
            $stats['total_gatepasses'] = $this->countGatepasses($tenantId);
        }

        if (can('approval.view')) {
            $stats['my_pending_approvals'] = $this->countMyPendingApprovals($tenantId, $userId);
        }

        if (can('gatepass.checkin')) {
            $stats['checked_in_today'] = $this->countCheckinsToday($tenantId);
        }

        if (can('gatepass.checkout')) {
            $stats['checked_out_today'] = $this->countCheckoutsToday($tenantId);
        }

        if (can('visits.view')) {
            $stats['active_visitors'] = $this->countActiveVisitors($tenantId);
            $stats['total_visitors']  = $this->totalVisitors($tenantId);
        }

        if (can('reports.view_occupancy')) {
            $stats['people_on_site'] = $this->visitsOnSite($tenantId);
            $stats['items_on_site']  = $this->gatepassesOnSite($tenantId);
        }

        if (can('reports.view_scans')) {
            $stats['scans_today']  = $this->countScansToday($tenantId);
            $stats['recent_scans'] = $this->recentScans($tenantId, 8);
        }

        if (can('visitors.blacklist')) {
            $stats['blacklisted_visitors'] = $this->countBlacklistedVisitors($tenantId);
        }

        if (can('reports.view_departments')) {
            $stats['top_departments'] = $this->topDepartmentsThisMonth($tenantId);
        }

        return $stats;
    }

    /** Total gatepasses */
    private function countGatepasses(int $tenantId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM gatepasses
            WHERE tenant_id = :tenant
        ");

        $stmt->execute([':tenant' => $tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /** Pending approvals for logged-in user */
    private function countMyPendingApprovals(int $tenantId, int $userId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM gatepass_approvals ga
            JOIN gatepass_workflow_instances gwi 
                ON ga.workflow_instance_id = gwi.id
            WHERE ga.tenant_id = :tenant
              AND ga.approver_user_id = :user
              AND ga.status = 'pending'
              AND gwi.status = 'in_progress'
        ");

        $stmt->execute([
            ':tenant' => $tenantId,
            ':user'   => $userId
        ]);

        return (int) $stmt->fetchColumn();
    }

    /** Gatepasses checked in today */
    private function countCheckinsToday(int $tenantId): int
    {
        $today = DateHelper::today();

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM gatepasses
            WHERE tenant_id = :tenant
              AND DATE(actual_in) = :today
        ");

        $stmt->execute([
            ':tenant' => $tenantId,
            ':today'  => $today
        ]);

        return (int) $stmt->fetchColumn();
    }


      private function countCheckoutsToday(int $tenantId): int
    {
        $today = DateHelper::today();

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM gatepasses
            WHERE tenant_id = :tenant
              AND DATE(actual_out) = :today
        ");

        $stmt->execute([
            ':tenant' => $tenantId,
            ':today'  => $today
        ]);

        return (int) $stmt->fetchColumn();
    }
    
    /** Active visitors */
    private function countActiveVisitors(int $tenantId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM visits
            WHERE tenant_id = :tenant
              AND checkin_time IS NOT NULL
              AND checkout_time IS NULL
        ");

        $stmt->execute([':tenant' => $tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /** Total visitors */
    private function totalVisitors(int $tenantId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM visitors
            WHERE tenant_id = :tenant
              AND is_blacklisted = 0
        ");

        $stmt->execute([':tenant' => $tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /** Visitors currently on site (checked in, not out) */
    private function visitsOnSite(int $tenantId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM visits
            WHERE tenant_id     = :tenant
              AND checkin_time  IS NOT NULL
              AND checkout_time IS NULL
        ");

        $stmt->execute([':tenant' => $tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /** Gatepasses currently on site (checked in, not out) */
    private function gatepassesOnSite(int $tenantId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM gatepasses
            WHERE tenant_id   = :tenant
              AND actual_in   IS NOT NULL
              AND actual_out  IS NULL
        ");

        $stmt->execute([':tenant' => $tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /** Successful gate scans today */
    private function countScansToday(int $tenantId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM scan_logs
            WHERE tenant_id = :tenant
              AND action <> 'rejected'
              AND DATE(created_at) = CURDATE()
        ");

        $stmt->execute([':tenant' => $tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /** Latest gate scans for the dashboard activity feed */
    private function recentScans(int $tenantId, int $limit): array
    {
        $stmt = $this->db->prepare("
            SELECT
                sl.created_at,
                sl.subject_type,
                sl.subject_id,
                sl.action,
                sl.source,
                g.name AS gate_name,
                COALESCE(gp.gatepass_number, '') AS gatepass_number,
                COALESCE(CONCAT(vis.first_name, ' ', vis.last_name), '') AS visitor_name
            FROM scan_logs sl
            LEFT JOIN gates g       ON g.id = sl.gate_id   AND g.tenant_id = sl.tenant_id
            LEFT JOIN gatepasses gp ON gp.id = sl.subject_id
                AND sl.subject_type = 'gatepass' AND gp.tenant_id = sl.tenant_id
            LEFT JOIN visits v      ON v.id = sl.subject_id
                AND sl.subject_type = 'visit' AND v.tenant_id = sl.tenant_id
            LEFT JOIN visitors vis  ON vis.id = v.visitor_id AND vis.tenant_id = sl.tenant_id
            WHERE sl.tenant_id = :tenant
            ORDER BY sl.created_at DESC
            LIMIT " . max(1, min($limit, 50)) . "
        ");

        $stmt->execute([':tenant' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Blacklisted visitor count */
    private function countBlacklistedVisitors(int $tenantId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM visitors
            WHERE tenant_id = :tenant
              AND is_blacklisted = 1
        ");

        $stmt->execute([':tenant' => $tenantId]);

        return (int) $stmt->fetchColumn();
    }

    /** Busiest departments this month by gatepass + visit volume */
    private function topDepartmentsThisMonth(int $tenantId): array
    {
        $monthStart = date('Y-m-01 00:00:00');

        $stmt = $this->db->prepare("
            SELECT
                d.id,
                d.name,
                (SELECT COUNT(*) FROM gatepasses gp
                  WHERE gp.department_id = d.id
                    AND gp.tenant_id = d.tenant_id
                    AND gp.created_at >= :month1) AS gatepasses,
                (SELECT COUNT(*) FROM visits v
                  WHERE v.department_id = d.id
                    AND v.tenant_id = d.tenant_id
                    AND v.created_at >= :month2) AS visits
            FROM departments d
            WHERE d.tenant_id = :tenant
              AND d.is_active = 1
            ORDER BY (
                (SELECT COUNT(*) FROM gatepasses gp2
                  WHERE gp2.department_id = d.id AND gp2.tenant_id = d.tenant_id AND gp2.created_at >= :month3)
                +
                (SELECT COUNT(*) FROM visits v2
                  WHERE v2.department_id = d.id AND v2.tenant_id = d.tenant_id AND v2.created_at >= :month4)
            ) DESC, d.name ASC
            LIMIT 6
        ");

        $stmt->execute([
            ':tenant' => $tenantId,
            ':month1' => $monthStart,
            ':month2' => $monthStart,
            ':month3' => $monthStart,
            ':month4' => $monthStart,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Dashboard charts — each dataset built only when permitted */
    public function getChartData(int $tenantId, int $days = 30): array
    {
        $range = DateHelper::rangeDays($days);
        $data  = [];

        if (can('approval.view')) {
            $stmt = $this->db->prepare("
                SELECT status, COUNT(*) as total
                FROM gatepass_workflow_instances
                WHERE tenant_id = :tenant
                GROUP BY status
            ");

            $stmt->execute([':tenant' => $tenantId]);
            $data['workflow_status'] = ChartHelper::dataset($stmt->fetchAll(PDO::FETCH_ASSOC), 'status', 'total');
        }

        if (can('gatepass.view')) {
            $stmt = $this->db->prepare("
                SELECT DATE(created_at) as date,
                       COUNT(*) as total
                FROM gatepasses
                WHERE tenant_id = :tenant
                  AND created_at BETWEEN :start AND :end
                GROUP BY DATE(created_at)
                ORDER BY date ASC
            ");

            $stmt->execute([
                ':tenant' => $tenantId,
                ':start'  => $range['start'],
                ':end'    => $range['end']
            ]);

            $data['daily_gatepasses'] = ChartHelper::dataset($stmt->fetchAll(PDO::FETCH_ASSOC), 'date', 'total');
        }

        if (can('visits.view')) {
            $stmt = $this->db->prepare("
                SELECT DATE(checkin_time) as date,
                       COUNT(*) as total
                FROM visits
                WHERE tenant_id = :tenant
                  AND checkin_time BETWEEN :start AND :end
                GROUP BY DATE(checkin_time)
                ORDER BY date ASC
            ");

            $stmt->execute([
                ':tenant' => $tenantId,
                ':start'  => $range['start'],
                ':end'    => $range['end']
            ]);

            $data['weekly_visits'] = ChartHelper::dataset($stmt->fetchAll(PDO::FETCH_ASSOC), 'date', 'total');
        }

        return $data;
    }
}