<?php

namespace App\Modules\Settings\Services;

use App\Core\Auth;
use App\Core\DB;
use PDO;

class TenantSettingService
{
    private PDO $db;
    private int $tenantId;

    public function __construct(?int $tenantId = null)
    {
        $this->db = DB::connect();

        if ($tenantId !== null) {
            $this->tenantId = $tenantId;
            return;
        }

        // FIX: Use Auth::tenantId() instead of reading $_SESSION directly
        $resolved = Auth::tenantId();

        if ($resolved === null) {
            throw new \RuntimeException('Tenant not found in session.');
        }

        $this->tenantId = $resolved;
    }

    /**
     * Get a setting value (auto JSON-decoded).
     * If the key doesn't exist and a non-empty default is provided, it is persisted.
     */
    public function get(string $key, array $default = []): array
    {
        $stmt = $this->db->prepare("
            SELECT config_json
            FROM tenant_settings
            WHERE tenant_id  = :tenant_id
              AND setting_key = :key
            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $this->tenantId,
            ':key'       => $key,
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (! $row) {
            if (! empty($default)) {
                $this->set($key, $default);
            }
            return $default;
        }

        return json_decode($row['config_json'], true) ?? $default;
    }

    /**
     * Save a setting (auto JSON-encoded).
     * Uses INSERT … ON DUPLICATE KEY UPDATE to match the schema's unique key
     * uk_ts_tenant_key (tenant_id, setting_key).
     */
    public function set(string $key, array $value): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO tenant_settings (tenant_id, setting_key, config_json)
            VALUES (:tenant_id, :key, :value)
            ON DUPLICATE KEY UPDATE
                config_json = VALUES(config_json),
                updated_at  = CURRENT_TIMESTAMP
        ");

        $stmt->execute([
            ':tenant_id' => $this->tenantId,
            ':key'       => $key,
            ':value'     => json_encode($value, JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * Delete a setting.
     */
    public function delete(string $key): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM tenant_settings
            WHERE tenant_id  = :tenant_id
              AND setting_key = :key
        ");

        $stmt->execute([
            ':tenant_id' => $this->tenantId,
            ':key'       => $key,
        ]);
    }

    /**
     * Check if a setting exists.
     */
    public function exists(string $key): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1
            FROM tenant_settings
            WHERE tenant_id  = :tenant_id
              AND setting_key = :key
            LIMIT 1
        ");

        $stmt->execute([
            ':tenant_id' => $this->tenantId,
            ':key'       => $key,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Increment a numeric field inside a JSON setting and persist it.
     */
    public function increment(string $key, string $field, int $step = 1): int
    {
        $data = $this->get($key);

        $current       = (int) ($data[$field] ?? 0);
        $current      += $step;
        $data[$field]  = $current;

        $this->set($key, $data);

        return $current;
    }
}