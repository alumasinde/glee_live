<?php

namespace App\Modules\Settings\Repositories;

use App\Core\DB;
use App\Core\Tenant;
use App\Modules\Settings\DTOs\DepartmentDTO;

class DepartmentRepository
{
    public function all(): array
    {
        $tenantId = Tenant::require();

        $rows = DB::query("
            SELECT id, tenant_id, name, code, is_active, created_at
            FROM departments
            WHERE tenant_id = ?
            ORDER BY name ASC
        ", [$tenantId])->fetchAll();

        return array_map([$this, 'map'], $rows);
    }

    public function find(int $id, int $tenantId): ?DepartmentDTO
    {
        $row = DB::query("
            SELECT id, tenant_id, name, code, is_active, created_at
            FROM departments
            WHERE id = ?
              AND tenant_id = ?
            LIMIT 1
        ", [$id, $tenantId])->fetch();

        return $row ? $this->map($row) : null;
    }

    public function create(string $name, string $code): DepartmentDTO
    {
        $tenantId = Tenant::require();

        DB::query("
            INSERT INTO departments (tenant_id, name, code, is_active)
            VALUES (?, ?, ?, 1)
        ", [$tenantId, $name, $code]);

        $id = DB::lastInsertId();

        return $this->find((int)$id, $tenantId);
    }

    public function update(int $id, string $name, string $code): ?DepartmentDTO
    {
        $tenantId = Tenant::require();

        // Never gate on rowCount(): MariaDB reports 0 affected rows for a save
        // that changed nothing, which is a successful edit, not a failure.
        DB::query("
            UPDATE departments
            SET name = ?,
                code = ?
            WHERE id = ?
              AND tenant_id = ?
        ", [$name, $code, $id, $tenantId]);

        return $this->find($id, $tenantId);
    }

    public function updateStatus(int $id, bool $isActive): ?DepartmentDTO
    {
        $tenantId = Tenant::require();

        DB::query("
            UPDATE departments
            SET is_active = ?
            WHERE id = ?
              AND tenant_id = ?
        ", [$isActive ? 1 : 0, $id, $tenantId]);

        return $this->find($id, $tenantId);
    }

    public function delete(int $id): bool
    {
        $tenantId = Tenant::require();

        $stmt = DB::query("
            DELETE FROM departments
            WHERE id = ?
              AND tenant_id = ?
        ", [$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * uk_dept_tenant_code is a real unique key, so a colliding code aborts the
     * statement with an SQLSTATE 23000 error. Checked up front to answer with a
     * readable message instead of the driver's text.
     */
    public function codeExists(string $code, int $ignoreId = 0): bool
    {
        $tenantId = Tenant::require();

        $row = DB::query("
            SELECT 1
            FROM departments
            WHERE tenant_id = ?
              AND code = ?
              AND id <> ?
            LIMIT 1
        ", [$tenantId, $code, $ignoreId])->fetch();

        return $row !== false;
    }

    private function map(array $row): DepartmentDTO
    {
        return new DepartmentDTO(
            id:        (int) $row['id'],
            tenantId:  (int) $row['tenant_id'],
            name:      $row['name'],
            code:      $row['code'],
            isActive:  (bool) $row['is_active'],
            createdAt: $row['created_at'] ?? null
        );
    }
}