<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

/** Requiert le rôle admin ou super_admin. */
final class AdminRole
{
    public function handle(Request $request): void
    {
        if (!Auth::isAtLeast('admin')) {
            Response::abort(403, 'Droits insuffisants (rôle admin requis).');
        }
    }
}
