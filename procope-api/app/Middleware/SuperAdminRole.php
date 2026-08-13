<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use App\Services\Auth;

/** Requiert le rôle super_admin (gestion des utilisateurs). */
final class SuperAdminRole
{
    public function handle(Request $request): void
    {
        if (!Auth::isAtLeast('super_admin')) {
            Response::abort(403, 'Droits insuffisants (rôle super admin requis).');
        }
    }
}
