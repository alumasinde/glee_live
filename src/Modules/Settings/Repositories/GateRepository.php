<?php
declare(strict_types=1);

namespace App\Modules\Settings\Repositories;

use App\Core\DB;
use PDO;

final class GateRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    public function all(int $tenantId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, code, is_active, created_at, updated_at,
                   (key_hash IS NOT NULL) AS has_key
            FROM gates
            WHERE tenant_id = :tenant_id
            ORDER BY name
        ");

        $stmt->execute([':tenant_id' => $tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findActive(int $tenantId, int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, code
            FROM gates
            WHERE tenant_id = :tenant_id
              AND id        = :id
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function findByKeyHash(string $keyHash): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, tenant_id, name, code
            FROM gates
            WHERE key_hash  = :key_hash
              AND is_active = 1
            LIMIT 1
        ");

        $stmt->execute([':key_hash' => $keyHash]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(int $tenantId, string $name, string $code, string $keyHash): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO gates (tenant_id, name, code, key_hash)
            VALUES (:tenant_id, :name, :code, :key_hash)
        ");

        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':name'      => $name,
            ':code'      => $code,
            ':key_hash'  => $keyHash,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $tenantId, int $id, string $name, string $code, bool $isActive): bool
    {
        $stmt = $this->db->prepare("
            UPDATE gates
            SET name      = :name,
                code      = :code,
                is_active = :is_active
            WHERE tenant_id = :tenant_id
              AND id        = :id
        ");

        $stmt->execute([
            ':name'       => $name,
            ':code'       => $code,
            ':is_active'  => (int) $isActive,
            ':tenant_id'  => $tenantId,
            ':id'         => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function rotateKey(int $tenantId, int $id, string $keyHash): bool
    {
        $stmt = $this->db->prepare("
            UPDATE gates
            SET key_hash = :key_hash
            WHERE tenant_id = :tenant_id
              AND id        = :id
        ");

        $stmt->execute([
            ':key_hash'  => $keyHash,
            ':tenant_id' => $tenantId,
            ':id'        => $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function delete(int $tenantId, int $id): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM gates
            WHERE tenant_id = :tenant_id
              AND id        = :id
        ");

        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);

        return $stmt->rowCount() > 0;
    }

    public function hasScanLogs(int $tenantId, int $id): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM scan_logs
            WHERE tenant_id = :tenant_id
              AND gate_id   = :id
            LIMIT 1
        ");

        $stmt->execute([':tenant_id' => $tenantId, ':id' => $id]);

        return $stmt->fetchColumn() !== false;
    }
}
