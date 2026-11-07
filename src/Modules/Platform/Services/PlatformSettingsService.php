<?php

namespace App\Modules\Platform\Services;

use App\Core\DB;
use App\Core\PlatformPolicy;
use PDO;

class PlatformSettingsService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DB::connect();
    }

    /**
     * Form semantics: submit the full feature set, a checked box means on.
     * Only keys present in the catalogue are stored.
     *
     * @param array<string, mixed> $submitted
     */
    public function saveFeatures(array $submitted, ?int $platformUserId = null): void
    {
        $map = [];

        foreach ((array) (function_exists('config') ? config('platform.features', []) : []) as $key => $meta) {
            $map[$key] = !empty($submitted[$key]);
        }

        $this->put('features', $map, $platformUserId);
        PlatformPolicy::flush();
    }

    /**
     * @param string[] $keys "module.action" values from the platform UI
     */
    public function saveRestrictedPermissions(array $keys, ?int $platformUserId = null): void
    {
        $valid = $this->permissionKeys();
        $clean = [];

        foreach ($keys as $key) {
            $key = strtolower(trim($key));

            if ($key !== '' && in_array($key, $valid, true)) {
                $clean[$key] = true;
            }
        }

        $this->put('restricted_permissions', array_keys($clean), $platformUserId);
        PlatformPolicy::flush();
    }

    /** @return string[] every "module.action" key in the permission matrix */
    public function permissionKeys(): array
    {
        $stmt = $this->db->query(
            "SELECT LOWER(CONCAT(m.name, '.', a.name)) AS permission_key
               FROM permissions p
               INNER JOIN modules m ON m.id = p.module_id
               INNER JOIN actions a ON a.id = p.action_id
              ORDER BY m.name ASC, a.name ASC"
        );

        return array_map('strval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    /** Permission matrix grouped by module for the platform UI. */
    public function permissionMatrix(): array
    {
        $stmt = $this->db->query(
            "SELECT m.name AS module, a.name AS action,
                    LOWER(CONCAT(m.name, '.', a.name)) AS permission_key
               FROM permissions p
               INNER JOIN modules m ON m.id = p.module_id
               INNER JOIN actions a ON a.id = p.action_id
              ORDER BY m.name ASC, a.name ASC"
        );

        $grouped = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $grouped[$row['module']][] = $row;
        }

        return $grouped;
    }

    private function put(string $key, array $value, ?int $platformUserId): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO platform_settings (setting_key, config_json, updated_by)
             VALUES (:setting_key, :config_json, :updated_by)
             ON DUPLICATE KEY UPDATE
                config_json = VALUES(config_json),
                updated_by  = VALUES(updated_by),
                updated_at  = CURRENT_TIMESTAMP'
        );

        $stmt->execute([
            ':setting_key' => $key,
            ':config_json' => json_encode($value, JSON_UNESCAPED_UNICODE),
            ':updated_by'  => $platformUserId,
        ]);
    }
}
