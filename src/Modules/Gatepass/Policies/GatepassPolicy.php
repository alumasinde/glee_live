<?php

namespace App\Modules\Gatepass\Policies;

use App\Core\Permission;

class GatepassPolicy
{
    public function __construct(private Permission $permission) {}

    public function create(): bool
    {
        return $this->permission->can('gatepass.create');
    }

    public function approve(): bool
    {
        return $this->permission->can('approval.approve');
    }

    /**
     * Can this user open this gatepass's detail screen?
     *
     * Listings are department-scoped unless the user holds gatepass.view_all,
     * so the detail screen must apply the same scope or an ID guess leaks
     * another department's pass. Legacy rows without a department stay visible.
     */
    public function view(array $user, array $gatepass): bool
    {
        if ($this->permission->can('gatepass.view_all')) {
            return true;
        }

        $userId = (int) ($user['id'] ?? 0);

        if ($userId > 0 && (int) ($gatepass['created_by'] ?? 0) === $userId) {
            return true;
        }

        $passDepartment = $gatepass['department_id'] ?? null;

        if ($passDepartment === null || (int) $passDepartment === 0) {
            return true;
        }

        $userDepartment = $user['department_id'] ?? null;

        return $userDepartment !== null && (int) $userDepartment === (int) $passDepartment;
    }

    public function update(array $user, array $gatepass): bool
    {
        if (!$this->permission->can('gatepass.update')) {
            return false;
        }

        $userId = (int) ($user['id'] ?? 0);

        return $userId > 0
            && (int) ($gatepass['created_by'] ?? 0) === $userId
            && strtoupper((string) ($gatepass['status_code'] ?? '')) === 'PENDING';
    }

    public function delete(): bool
    {
        return $this->permission->can('gatepass.delete');
    }
}
