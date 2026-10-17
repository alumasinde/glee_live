<?php

namespace App\Middleware;

use App\Core\DB;
use App\Core\Permission;
use App\Core\Request;
use App\Core\Response;

class PermissionMiddleware
{
    public function handle(Request $request, string $permission): void
    {
        $perm = new Permission(DB::connect());

        if (!$perm->can($permission)) {
            Response::abort(403, 'You do not have permission to access this resource.');
        }
    }
}
