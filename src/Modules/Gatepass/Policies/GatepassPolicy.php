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

    public function update(array $user, array $gatepass): bool
    {
        if (!$this->permission->can('gatepass.update')) {
            return false;
        }

        return (int) $gatepass['created_by'] === (int) $user['id']
            && strtoupper($gatepass['status_code']) === 'PENDING';
    }

    public function delete(): bool
    {
        return $this->permission->can('gatepass.delete');
    }
}
