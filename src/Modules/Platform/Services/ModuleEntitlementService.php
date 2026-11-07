<?php

namespace App\Modules\Platform\Services;

use App\Core\DB;
use PDO;

/**
 * Per-tenant module entitlements.
 *
 * The `modules` catalogue doubles as the feature catalogue: it already names
 * every area that has permissions, and now carries is_core / depends_on.
 * A missing tenant_modules row means "default on", so existing tenants keep
 * every module until a platform admin changes something.
 */
class ModuleEntitlementService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? DB::connect();
    }

    /** @return array<int, array{name:string,is_core:int,depends_on:?string,sort_order:int}> */
    public function catalogue(): array
    {
        $stmt = $this->db->query(
            'SELECT name, is_core, depends_on, sort_order
               FROM modules
              ORDER BY sort_order ASC, name ASC'
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /** @return array<string, bool> module name => enabled */
    public function states(int $tenantId): array
    {
        $states     = [];
        $moduleCore = [];

        foreach ($this->catalogue() as $module) {
            $moduleCore[$module['name']] = !empty($module['is_core']);
            // Default is ON: a missing tenant_modules row must never black out
            // an existing tenant. Rows below are the only thing that turns a
            // module off, and core stays on whatever a stale row says.
            $states[$module['name']] = true;
        }

        $stmt = $this->db->prepare(
            'SELECT module_name, is_enabled FROM tenant_modules WHERE tenant_id = ?'
        );
        $stmt->execute([$tenantId]);

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $name = $row['module_name'];

            if (!array_key_exists($name, $states)) {
                continue;
            }

            if (!empty($moduleCore[$name])) {
                $states[$name] = true;
                continue;
            }

            $states[$name] = (bool) $row['is_enabled'];
        }

        return $states;
    }

    public function isEnabled(int $tenantId, string $module): bool
    {
        $states = $this->states($tenantId);

        // Unknown modules are not gated: a new screen must not disappear
        // merely because the catalogue predates it.
        return $states[$module] ?? true;
    }

    /**
     * Persist entitlements from a submitted set of enabled module names.
     *
     * @param string[] $enabledNames
     * @return string[] validation errors; empty when saved
     */
    public function save(int $tenantId, array $enabledNames, ?int $platformUserId = null): array
    {
        $enabledNames = array_map('strval', $enabledNames);
        $catalogue    = $this->catalogue();
        $states       = [];

        foreach ($catalogue as $module) {
            $name = (string) $module['name'];

            $states[$name] = !empty($module['is_core'])
                ? true
                : in_array($name, $enabledNames, true);
        }

        $errors = [];

        foreach ($catalogue as $module) {
            $name = (string) $module['name'];

            if (empty($states[$name])) {
                continue;
            }

            $dependsOn = trim((string) ($module['depends_on'] ?? ''));

            if ($dependsOn !== '' && empty($states[$dependsOn])) {
                $errors[] = $name . ' requires ' . $dependsOn . ' to be enabled.';
            }
        }

        if ($errors !== []) {
            return $errors;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO tenant_modules (tenant_id, module_name, is_enabled, updated_by)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                is_enabled = VALUES(is_enabled),
                updated_by = VALUES(updated_by),
                updated_at = CURRENT_TIMESTAMP'
        );

        // Support callers already inside a transaction (tests, future bulk edits).
        $ownTransaction = !$this->db->inTransaction();

        if ($ownTransaction) {
            $this->db->beginTransaction();
        }

        try {
            foreach ($catalogue as $module) {
                if (!empty($module['is_core'])) {
                    continue;
                }

                $stmt->execute([
                    $tenantId,
                    $module['name'],
                    $states[$module['name']] ? 1 : 0,
                    $platformUserId,
                ]);
            }

            if ($ownTransaction) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTransaction) {
                $this->db->rollBack();
            }

            throw $e;
        }

        return [];
    }
}
