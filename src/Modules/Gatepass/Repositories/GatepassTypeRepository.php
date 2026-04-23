<?php

namespace App\Modules\Gatepass\Repositories;

use App\Core\DB;
use PDO;

class GatepassTypeRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    public function findAllByTenant(int $tenantId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM gatepass_types
            WHERE tenant_id = :tenant_id
              AND is_active = 1
            ORDER BY name ASC
        ");

        $stmt->execute([':tenant_id' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}