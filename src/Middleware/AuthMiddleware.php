<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

class AuthMiddleware
{
    public function handle(Request $request): void
    {
        if (!Auth::check()) {
            Response::redirect('/login');
        }

        if (!Auth::sessionIsValid()) {
            // Disabled account or a session issued before the last password
            // change: drop the identity so /login does not bounce back here.
            Auth::forget();

            Response::redirect('/login');
        }
    }
}