<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

final class AuthMiddleware
{
    public function handle(Request $request): void
    {
        if (Auth::user() === null) {
            Response::redirect('/admin/login');
        }
    }
}
