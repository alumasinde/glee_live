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