<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

final class CsrfMiddleware
{
    public function handle(Request $request): void
    {
        $sent = $_POST['_csrf'] ?? '';
        $expected = $_SESSION['csrf_token'] ?? '';
        if ($expected === '' || !is_string($sent) || !hash_equals($expected, $sent)) {
            Response::abort(419, 'Session expirée ou jeton CSRF invalide. Rechargez la page.');
        }
    }
}
