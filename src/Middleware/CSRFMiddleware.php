<?php

namespace App\Middleware;

use App\Core\Response;

class CSRFMiddleware
{
    public function handle()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {

            $token = $_POST['_token'] ?? '';

            if (!isset($_SESSION['_token']) || $token !== $_SESSION['_token']) {
                Response::abort(419, 'CSRF token mismatch');
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
