<?php

namespace App\Modules\Gatepass\Repositories;

use App\Core\DB;
use PDO;
use RuntimeException;

class GatepassStatusRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    public function getIdByCode(int $tenantId, string $code): ?int
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM gatepass_statuses
            WHERE tenant_id = :tenant_id
              AND code      = :code
            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':code'      => strtolower($code),
        ]);

        $id = $stmt->fetchColumn();

        return $id !== false ? (int) $id : null;
    }

    public function requireIdByCode(int $tenantId, string $code): int
    {
        $id = $this->getIdByCode($tenantId, $code);

        if ($id === null) {
            throw new RuntimeException(
                "Gatepass status '{$code}' not found for tenant {$tenantId}."
            );
        }

        return $id;
    }

    public function isApproved(int $tenantId, int $statusId): bool
    {
        return $this->statusMatches($tenantId, $statusId, 'approved');
    }

    public function isRejected(int $tenantId, int $statusId): bool
    {
        return $this->statusMatches($tenantId, $statusId, 'rejected');
    }

    private function statusMatches(int $tenantId, int $statusId, string $code): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM gatepass_statuses
            WHERE id        = :id
              AND tenant_id = :tenant_id
              AND code      = :code
            LIMIT 1
        ");

        $stmt->execute([
            ':id'        => $statusId,
            ':tenant_id' => $tenantId,
            ':code'      => $code,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
}
