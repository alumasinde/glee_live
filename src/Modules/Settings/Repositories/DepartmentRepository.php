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
            SELECT id, tenant_id, name, is_active, created_at
            FROM departments
            WHERE tenant_id = ?
              AND is_active = 1
            ORDER BY name ASC
        ", [$tenantId])->fetchAll();

        return array_map([$this, 'map'], $rows);
    }

    public function find(int $id, int $tenantId): ?DepartmentDTO
    {
        $row = DB::query("
            SELECT id, tenant_id, name, is_active, created_at
            FROM departments
            WHERE id = ?
              AND tenant_id = ?
            LIMIT 1
        ", [$id, $tenantId])->fetch();

        return $row ? $this->map($row) : null;
    }

    public function create(string $name): DepartmentDTO
    {
        $tenantId = Tenant::require();

        DB::query("
            INSERT INTO departments (tenant_id, name, is_active)
            VALUES (?, ?, 1)
        ", [$tenantId, $name]);

        $id = DB::lastInsertId();

        // Fetch fresh row (best practice for consistency)
        return $this->find((int)$id, $tenantId);
    }

    public function update(int $id, string $name): ?DepartmentDTO
    {
        $tenantId = Tenant::require();

        $stmt = DB::query("
            UPDATE departments
            SET name = ?
            WHERE id = ?
              AND tenant_id = ?
        ", [$name, $id, $tenantId]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return $this->find($id, $tenantId);
    }

    public function updateStatus(int $id, bool $isActive): ?DepartmentDTO
    {
        $tenantId = Tenant::require();

        $stmt = DB::query("
            UPDATE departments
            SET is_active = ?
            WHERE id = ?
              AND tenant_id = ?
        ", [$isActive ? 1 : 0, $id, $tenantId]);

        if ($stmt->rowCount() === 0) {
            return null;
        }

        return $this->find($id, $tenantId);
    }

    private function map(array $row): DepartmentDTO
    {
        return new DepartmentDTO(
            id:        (int) $row['id'],
            tenantId:  (int) $row['tenant_id'],
            name:      $row['name'],
            isActive:  (bool) $row['is_active'],
            createdAt: $row['created_at'] ?? null
        );
    }
}