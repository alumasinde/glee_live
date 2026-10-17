<?php

namespace App\Modules\Auth\Controllers;

use App\Modules\Auth\Services\AuthService;
use App\Core\Request;
use App\Core\Response;
use App\Core\Audit;

class LogoutController
{
    private AuthService $auth;

    public function __construct(?AuthService $auth = null)
    {
        $this->auth = $auth ?? new AuthService();
    }

    public function __invoke(Request $request)
    {
        // Enforce POST-only logout
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return Response::abort(405, 'Method Not Allowed');
        }

        $user = $_SESSION['user'];

          Audit::log(
        'user.logout',
        'user',
        (int) $user['id'],
        [
            'email' => $user['email']
        ]
    );

        $this->auth->logout();

        // Regenerate new clean session after destroy
        session_start();
        session_regenerate_id(true);

        header('Location: /login');
        exit;
    }
}