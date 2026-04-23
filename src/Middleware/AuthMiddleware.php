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
            exit;
        }
    }
}