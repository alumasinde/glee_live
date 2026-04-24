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

    public function create(string $name)
    {
        $this->validateName($name);

        try {
            $department = $this->repo->create($name);

            if (! $department) {
                throw new RuntimeException('Department creation failed.');
            }
          Audit::log(
                action:     'department.created',
                entityType: 'department',
                entityId:   $department->id,
                metadata:   [
                    'name' => $department->name,
                ]  
                );

            return $department;

            

        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Department creation failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function update(int $id, string $name)
    {
        $this->validateName($name);

        $tenantId = Tenant::require();

        $existing = $this->repo->find($id, $tenantId);

        if (! $existing) {
            throw new RuntimeException('Department not found.');
        }

        try {
            $updated = $this->repo->update($id, $name);

            if (! $updated) {
                throw new RuntimeException('Department update failed.');
            }
            Audit::log(
                action:     'department.updated',
                entityType: 'department',
                entityId:   $id,
                metadata:   [
                    'before' => [
                        'name' => $existing->name,
                    ],
                    'after'  => [
                        'name' => $name,
                    ],
                ]
            );

            return $updated;

        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Department update failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    public function deactivate(int $id): void
    {
        $tenantId = Tenant::require();

        $department = $this->repo->find($id, $tenantId);

        if (! $department) {
            throw new RuntimeException('Department not found.');
        }

        try {
            $updated = $this->repo->updateStatus($id, false);

            if (! $updated) {
                throw new RuntimeException('Department deactivation failed.');
            }

        } catch (\Throwable $e) {
            throw new RuntimeException(
                'Department deactivation failed: ' . $e->getMessage(),
                0,
                $e
            );
        }
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
}