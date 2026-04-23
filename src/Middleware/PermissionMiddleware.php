<?php

namespace App\Middleware;

use App\Core\Permission;

class PermissionMiddleware
{
    public function handle(string $permission)
    {
        $perm = new Permission(\App\Core\DB::connect());

        if (!$perm->can($permission)) {

            http_response_code(403);

            die("403 Forbidden — Missing Permission: {$permission}");
        }
    }
}
