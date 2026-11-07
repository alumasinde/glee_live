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

            // A token older than the configured lifetime is refused even when it
            // still matches the session copy, and the next page gets a fresh one.
            $issuedAt = (int) ($_SESSION['_token_at'] ?? 0);

            if ($issuedAt > 0 && (time() - $issuedAt) > self::lifetime()) {
                self::rotate();

                Response::abort(403, 'CSRF token expired');
            }
        }
    }

    public static function token()
    {
        $issuedAt = $_SESSION['_token_at'] ?? null;

        if (
            !isset($_SESSION['_token']) ||
            ($issuedAt !== null && (time() - (int) $issuedAt) > self::lifetime())
        ) {
            return self::rotate();
        }

        return $_SESSION['_token'];
    }

    /**
     * Issue a fresh token: on first use, on expiry, and after a login so a token
     * from the pre-authentication session cannot be replayed with the new one.
     */
    public static function rotate(): string
    {
        $_SESSION['_token']    = bin2hex(random_bytes(32));
        $_SESSION['_token_at'] = time();

        return $_SESSION['_token'];
    }

    private static function lifetime(): int
    {
        $configured = function_exists('config')
            ? (int) config('security.csrf.token_lifetime', 7200)
            : 7200;

        return max(60, $configured);
    }
}
