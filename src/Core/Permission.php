<?php

namespace App\Core;

use PDO;

class Permission
{
    private static ?array $cached = null;

    public function __construct(private PDO $db)
    {
    }

    /**
     * Check permission against freshly-loaded, tenant-scoped grants.
     */
    public function can(string $permission): bool
    {
        return isset($this->load()[strtolower($permission)]);
    }

    /**
     * All effective permissions of the logged-in user (debug / UI use).
     */
    public function all(): array
    {
        return array_keys($this->load());
    }

    /**
     * Force a reload on next check (e.g. after grants change).
     */
    public static function flush(): void
    {
        self::$cached = null;
    }

    private function load(): array
    {
        if (self::$cached !== null) {
            return self::$cached;
        }

        $user = $_SESSION['user'] ?? null;

        if (!$user) {
            return self::$cached = [];
        }

        $stmt = $this->db->prepare("
            SELECT m.name AS module, a.name AS action
            FROM user_roles ur
            INNER JOIN role_permissions rp
                ON rp.role_id   = ur.role_id
               AND rp.tenant_id = ur.tenant_id
            INNER JOIN permissions p ON p.id = rp.permission_id
            INNER JOIN modules m     ON m.id = p.module_id
            INNER JOIN actions a     ON a.id = p.action_id
            WHERE ur.user_id   = :user_id
              AND ur.tenant_id = :tenant_id
        ");

        $stmt->execute([
            ':user_id'   => (int) $user['id'],
            ':tenant_id' => (int) $user['tenant_id'],
        ]);

        $perms = [];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $perms[strtolower($row['module'] . '.' . $row['action'])] = true;
        }

        return self::$cached = $perms;
    }
}
