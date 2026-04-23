<?php

namespace App\Modules\Roles\Services;

use App\Core\DB;
use PDO;
use RuntimeException;

class UserRoleService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DB::connect();
    }

    /* ============================================================
       FETCH USER ROLES
    ============================================================ */

    public function getUserRoles(int $tenantId, int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT r.id, r.name
            FROM user_roles ur
            INNER JOIN roles r 
                ON ur.role_id = r.id
               AND r.tenant_id = ur.tenant_id
            WHERE ur.tenant_id = ?
              AND ur.user_id = ?
            ORDER BY r.name ASC
        ");

        $stmt->execute([$tenantId, $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Returns role IDs only (for checkbox binding)
     */
    public function getUserRoleIds(int $tenantId, int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT role_id
            FROM user_roles
            WHERE tenant_id = ?
              AND user_id = ?
        ");

        $stmt->execute([$tenantId, $userId]);

        return array_map(
            'intval',
            $stmt->fetchAll(PDO::FETCH_COLUMN)
        );
    }

    /* ============================================================
       ASSIGN ROLES
    ============================================================ */

    public function assignRoles(
        int $tenantId,
        int $userId,
        array $roleIds
    ): bool {

        $this->db->beginTransaction();

        try {

            // 1️⃣ Validate user belongs to tenant
            $userCheck = $this->db->prepare("
                SELECT id
                FROM users
                WHERE id = ?
                  AND tenant_id = ?
                LIMIT 1
            ");
            $userCheck->execute([$userId, $tenantId]);

            if (!$userCheck->fetchColumn()) {
                throw new RuntimeException("Invalid user.");
            }

            // 2️⃣ Clear existing roles (tenant scoped)
            $delete = $this->db->prepare("
                DELETE FROM user_roles
                WHERE tenant_id = ?
                  AND user_id = ?
            ");
            $delete->execute([$tenantId, $userId]);

            if (empty($roleIds)) {
                $this->db->commit();
                return true;
            }

            // 3️⃣ Sanitize & deduplicate
            $roleIds = array_unique(
                array_filter(
                    array_map('intval', $roleIds)
                )
            );

            // 4️⃣ Validate roles belong to tenant
            $placeholders = implode(',', array_fill(0, count($roleIds), '?'));

            $roleCheck = $this->db->prepare("
                SELECT id
                FROM roles
                WHERE tenant_id = ?
                  AND id IN ($placeholders)
            ");

            $roleCheck->execute(array_merge([$tenantId], $roleIds));

            $validRoleIds = array_map(
                'intval',
                $roleCheck->fetchAll(PDO::FETCH_COLUMN)
            );

            if (empty($validRoleIds)) {
                $this->db->commit();
                return true;
            }

            // 5️⃣ Insert roles
            $insert = $this->db->prepare("
                INSERT INTO user_roles
                (tenant_id, user_id, role_id)
                VALUES (?, ?, ?)
            ");

            foreach ($validRoleIds as $rid) {
                $insert->execute([
                    $tenantId,
                    $userId,
                    $rid
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