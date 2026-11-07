<?php

namespace App\Modules\Platform\Controllers;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Middleware\RateLimitMiddleware;
use App\Modules\Platform\Services\ModuleEntitlementService;
use App\Modules\Platform\Services\PlatformAuditService;

class PlatformDashboardController
{
    public function index(Request $request): mixed
    {
        $tenants = DB::connect()->query(
            "SELECT t.id, t.name, t.code, t.email, t.is_active, t.created_at,
                    (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id AND u.is_active = 1) AS user_count,
                    (SELECT COUNT(*) FROM gatepasses g WHERE g.tenant_id = t.id) AS gatepass_count,
                    (SELECT COUNT(*) FROM tenant_modules tm WHERE tm.tenant_id = t.id AND tm.is_enabled = 0) AS disabled_count
               FROM tenants t
              ORDER BY t.name ASC"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $modules = (new ModuleEntitlementService())->catalogue();

        View::render('Platform::dashboard', [
            'title'       => 'Platform console',
            'tenants'     => $tenants,
            'moduleTotal' => count($modules),
        ], 'platform');

        return null;
    }

    public function showAudit(): mixed
    {
        View::render('Platform::audit', [
            'title' => 'Platform audit',
            'logs'  => (new PlatformAuditService())->recent(150),
        ], 'platform');

        return null;
    }
}
