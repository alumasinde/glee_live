<?php

namespace App\Core;

use PDO;

/**
 * Provider-owned policy read by the tenant plane.
 *
 * Kept in Core (rather than the Platform module) so Permission and the
 * middleware can consult it without importing feature-module code.
 */
class PlatformPolicy
{
    private static ?array $settings = null;

    public static function flush(): void
    {
        self::$settings = null;
    }

    /** Feature catalogue merged with stored overrides, keyed by feature name. */
    public static function features(): array
    {
        $catalogue = function_exists('config') ? (array) config('platform.features', []) : [];
        $stored    = self::settings()['features'] ?? [];
        $stored    = is_array($stored) ? $stored : [];

        $features = [];

        foreach ($catalogue as $key => $meta) {
            $meta = is_array($meta) ? $meta : [];

            $features[$key] = [
                'label'       => (string) ($meta['label'] ?? $key),
                'description' => (string) ($meta['description'] ?? ''),
                'enabled'     => array_key_exists($key, $stored)
                    ? (bool) $stored[$key]
                    : (bool) ($meta['default'] ?? true),
            ];
        }

        return $features;
    }

    public static function feature(string $key): bool
    {
        return (bool) (self::features()[$key]['enabled'] ?? true);
    }

    /** @return string[] lowercase "module.action" keys */
    public static function restrictedPermissions(): array
    {
        $stored = self::settings()['restricted_permissions'] ?? null;

        if (!is_array($stored)) {
            $stored = function_exists('config')
                ? (array) config('platform.restricted_permissions', [])
                : [];
        }

        $keys = [];

        foreach ($stored as $key) {
            if (!is_string($key)) {
                continue;
            }

            $key = strtolower(trim($key));

            if ($key !== '') {
                $keys[$key] = true;
            }
        }

        return array_keys($keys);
    }

    private static function settings(): array
    {
        if (self::$settings !== null) {
            return self::$settings;
        }

        $decoded = [];

        try {
            $stmt = DB::connect()->query('SELECT setting_key, config_json FROM platform_settings');

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $decoded[$row['setting_key']] = json_decode((string) $row['config_json'], true);
            }
        } catch (\Throwable $e) {
            // Table not migrated yet: fall back to config defaults.
            $decoded = [];
        }

        return self::$settings = $decoded;
    }
}
