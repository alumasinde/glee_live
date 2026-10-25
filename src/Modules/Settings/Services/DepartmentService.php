<?php

namespace App\Modules\Settings\Services;

use App\Core\Tenant;
use App\Core\Audit;
use App\Modules\Settings\Repositories\DepartmentRepository;
use RuntimeException;

class DepartmentService
{
    public function __construct(
        private DepartmentRepository $repo
    ) {}

    public function all(): array
    {
        return $this->repo->all();
    }

    public function find(int $id)
    {
        $tenantId = Tenant::require();

        $department = $this->repo->find($id, $tenantId);

        if (! $department) {
            throw new RuntimeException('Department not found.');
        }

        return $department;
    }

    public function create(string $name, string $code)
    {
        $this->validateName($name);
        $this->validateCode($code);

        if ($this->repo->codeExists($code)) {
            throw new RuntimeException('Department code is already in use.');
        }

        $department = $this->repo->create($name, $code);

        Audit::log(
            action: 'department.created',
            entityType: 'department',
            entityId: $department->id,
            metadata: [
                'name' => $department->name,
                'code' => $code,
            ]
        );

        return $department;
    }

    public function update(int $id, string $name, string $code)
    {
        $this->validateName($name);
        $this->validateCode($code);

        $tenantId = Tenant::require();

        $existing = $this->repo->find($id, $tenantId);

        if (! $existing) {
            throw new RuntimeException('Department not found.');
        }

        if ($this->repo->codeExists($code, $id)) {
            throw new RuntimeException('Department code is already in use.');
        }

        $updated = $this->repo->update($id, $name, $code);

        if (! $updated) {
            throw new RuntimeException('Department not found.');
        }

        Audit::log(
            action: 'department.updated',
            entityType: 'department',
            entityId: $id,
            metadata: [
                'before' => [
                    'name' => $existing->name,
                    'code' => $existing->code,
                ],
                'after' => [
                    'name' => $updated->name,
                    'code' => $updated->code,
                ],
            ]
        );

        return $updated;
    }

    public function delete(int $id): bool
    {
        $tenantId = Tenant::require();

        $existing = $this->repo->find($id, $tenantId);

        if (! $existing) {
            throw new RuntimeException('Department not found.');
        }

        if (! $this->repo->delete($id)) {
            throw new RuntimeException('Department not found.');
        }

        Audit::log(
            action: 'department.deleted',
            entityType: 'department',
            entityId: $id,
            metadata: [
                'name' => $existing->name,
            ]
        );

        return true;
    }

    public function toggle(int $id)
    {
        $tenantId = Tenant::require();

        $department = $this->repo->find($id, $tenantId);

        if (! $department) {
            throw new RuntimeException('Department not found.');
        }

        $newStatus = ! (bool) $department->isActive;

        $updated = $this->repo->updateStatus($id, $newStatus);

        if (! $updated) {
            throw new RuntimeException('Department not found.');
        }

        Audit::log(
            action: 'department.toggled',
            entityType: 'department',
            entityId: $id,
            metadata: [
                'previous' => (bool) $department->isActive,
                'new' => $newStatus,
            ]
        );

        return $updated;
    }

    private function validateName(string $name): void
    {
        $name = trim($name);

        if ($name === '') {
            throw new RuntimeException('Department name is required.');
        }

        if (strlen($name) < 2) {
            throw new RuntimeException('Department name must be at least 2 characters.');
        }

        if (strlen($name) > 150) {
            throw new RuntimeException('Department name is too long.');
        }
    }

    private function validateCode(string $code): void
    {
        $code = trim($code);

        if ($code === '') {
            throw new RuntimeException('Department code is required.');
        }

        if (strlen($code) < 2) {
            throw new RuntimeException('Department code must be at least 2 characters.');
        }

        if (strlen($code) > 20) {
            throw new RuntimeException('Department code is too long.');
        }
    }
}