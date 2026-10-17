<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

class CSRFMiddleware
{
    public function handle(?Request $request = null)
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $token = $_POST['_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

            if (
                empty($token) ||
                !isset($_SESSION['_token']) ||
                !hash_equals($_SESSION['_token'], $token)
            ) {
                Response::abort(403, 'CSRF token mismatch');
            }
        }
    }

    public static function token()
    {
        if (!isset($_SESSION['_token'])) {
            $_SESSION['_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_token'];
    }
}
