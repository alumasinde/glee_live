<?php

namespace App\Middleware;

use App\Core\DB;
use App\Core\Request;
use App\Core\Response;

/**
 * Guard for the platform console. Requires the platform session namespace,
 * which tenant login never writes.
 */
class PlatformAuthMiddleware
{
    public function handle(?Request $request = null): void
    {
        $session = $_SESSION['platform_user'] ?? null;

        if (!is_array($session) || empty($session['id'])) {
            Response::redirect('/platform/login');
        }

        $ok = DB::selectOne(
            'SELECT 1 AS ok FROM platform_users WHERE id = :id AND is_active = 1',
            [':id' => (int) $session['id']]
        );

        if (!$ok) {
            unset($_SESSION['platform_user']);
            Response::redirect('/platform/login');
        }
    }
}
