<?php

namespace App\Modules\Tenant\Repositories;

use App\Core\DB;
use PDO;

class TenantRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    public function findById(int $tenantId): ?array
{
    $stmt = $this->db->prepare("
        SELECT name, logo 
        FROM tenants
        WHERE id = :tenant_id
          AND is_active = 1
        LIMIT 1
    ");

    $stmt->execute([
        ':tenant_id' => $tenantId
    ]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

public function updateLogo(int $tenantId, string $logo): bool
{
    $stmt = $this->db->prepare("
        UPDATE tenants 
        SET logo = :logo 
        WHERE id = :id
    ");

    return $stmt->execute([
        ':logo' => $logo,
        ':id'   => $tenantId
    ]);
}
    public function findActiveByCode(string $code): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, code
            FROM tenants
            WHERE code = :code
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([
            ':code' => $code
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    
/* Fetch Logo to display in login page */
public function findTenantLogo(int $id): ?array
{
    $stmt = $this->db->prepare(
        "SELECT logo AS tenant_logo, name AS tenant_name, email FROM tenants WHERE id = :id"
    );

    $stmt->bindParam(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    return $result ?: null;
}
}
