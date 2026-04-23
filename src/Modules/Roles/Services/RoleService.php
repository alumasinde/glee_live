<?php

namespace App\Modules\Roles\Services;

use App\Core\DB;
use PDO;
use RuntimeException;

class RoleService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    /* ============================================================
       ROLES
    ============================================================ */

    public function all(int $tenantId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM roles
            WHERE tenant_id = ?
            ORDER BY name ASC
        ");
        $stmt->execute([$tenantId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find(int $tenantId, int $roleId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM roles
            WHERE tenant_id = ?
              AND id = ?
            LIMIT 1
        ");
        $stmt->execute([$tenantId, $roleId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(int $tenantId, string $name): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO roles (tenant_id, name)
            VALUES (?, ?)
        ");
        $stmt->execute([$tenantId, trim($name)]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $tenantId, int $roleId, string $name): bool
    {
        $stmt = $this->db->prepare("
            UPDATE roles
            SET name = ?
            WHERE id = ?
              AND tenant_id = ?
        ");

        return $stmt->execute([
            trim($name),
            $roleId,
            $tenantId
        ]);
    }

    public function delete(int $tenantId, int $roleId): bool
    {
        $this->db->beginTransaction();

        try {
            // Delete permissions first (FK safe)
            $stmt = $this->db->prepare("
                DELETE FROM role_permissions
                WHERE tenant_id = ?
                  AND role_id = ?
            ");
            $stmt->execute([$tenantId, $roleId]);

            $stmt = $this->db->prepare("
                DELETE FROM roles
                WHERE id = ?
                  AND tenant_id = ?
            ");
            $stmt->execute([$roleId, $tenantId]);

            $this->db->commit();
            return true;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /* ============================================================
       PERMISSIONS
    ============================================================ */

    public function allPermissions(): array
    {
        $stmt = $this->db->query("
            SELECT 
                p.id,
                a.name AS action,
                m.name AS module
            FROM permissions p
            INNER JOIN actions a ON p.action_id = a.id
            INNER JOIN modules m ON p.module_id = m.id
            ORDER BY m.name, a.name
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRolePermissions(int $tenantId, int $roleId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                p.id,
                a.name AS action,
                m.name AS module
            FROM role_permissions rp
            INNER JOIN permissions p ON rp.permission_id = p.id
            INNER JOIN actions a ON p.action_id = a.id
            INNER JOIN modules m ON p.module_id = m.id
            WHERE rp.tenant_id = ?
              AND rp.role_id = ?
            ORDER BY m.name, a.name
        ");

        $stmt->execute([$tenantId, $roleId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Used for checkbox binding
     */
    public function getRolePermissionIds(int $tenantId, int $roleId): array
    {
        $stmt = $this->db->prepare("
            SELECT permission_id
            FROM role_permissions
            WHERE tenant_id = ?
              AND role_id = ?
        ");

        $stmt->execute([$tenantId, $roleId]);

        return array_map(
            'intval',
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    public function assignPermissions(
        int $tenantId,
        int $roleId,
        array $permissionIds
    ): bool {

        $this->db->beginTransaction();

        try {

            // Ensure role exists under tenant
            $role = $this->find($tenantId, $roleId);
            if (!$role) {
                throw new RuntimeException("Invalid role.");
            }

            // 1️⃣ Clear existing permissions
            $delete = $this->db->prepare("
                DELETE FROM role_permissions
                WHERE tenant_id = ?
                  AND role_id = ?
            ");
            $delete->execute([$tenantId, $roleId]);

            if (empty($permissionIds)) {
                $this->db->commit();
                return true;
            }

            // 2️⃣ Sanitize + deduplicate
            $permissionIds = array_unique(
                array_filter(
                    array_map('intval', $permissionIds)
                )
            );

            // 3️⃣ Insert new permissions
            $insert = $this->db->prepare("
                INSERT INTO role_permissions
                (tenant_id, role_id, permission_id)
                VALUES (?, ?, ?)
            ");

            foreach ($permissionIds as $pid) {
                $insert->execute([
                    $tenantId,
                    $roleId,
                    $pid
                ]);
            }

            $this->db->commit();
            return true;

        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}