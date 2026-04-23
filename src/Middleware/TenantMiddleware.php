<?php

namespace App\Middleware;

use App\Core\Tenant;
use App\Core\Response;

class TenantMiddleware
{
    public function handle()
    {
        if (!Tenant::id()) {
            Response::abort(403, 'Tenant not resolved');
        }
    }
}
