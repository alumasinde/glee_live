<?php

namespace App\Modules\Approval\Policies;

use App\Core\Permission;

class ApprovalPolicy
{
     public function __construct(
        private Permission $permission
    ) {}

    /* =========================================================
     * VIEW ANY (See approvals dashboard)
     * ========================================================= */

    public function viewAny(): bool
    {
        //permission key: approval.view
return $this->permission->can('approval.view');        
}
    

    /* =========================================================
     * APPROVE / REJECT
     * ========================================================= */

    public function approve(): bool
     {
      return $this->permission->can('approval.approve');
        }

    public function reject(): bool
    {
        return $this->permission->can('approval.reject');
    }

    /* =========================================================
     * VIEW SINGLE APPROVAL
     * ========================================================= */

public function view(array $user, array $approval): bool
{
    if (!$this->permission->can('approval.view')) {
        return false;
    }

    if (isset($user['role']) && $user['role'] === 'admin') {
        return true;
    }

    return true;
}

}