<?php

namespace App\Modules\Platform\Controllers;

use App\Core\Request;
use App\Core\View;
use App\Modules\Platform\Services\PlatformAuditService;

class PlatformAuditController
{
    public function index(Request $request): mixed
    {
        View::render('Platform::audit', [
            'title' => 'Platform audit',
            'logs'  => (new PlatformAuditService())->recent(150),
        ], 'platform');

        return null;
    }
}
