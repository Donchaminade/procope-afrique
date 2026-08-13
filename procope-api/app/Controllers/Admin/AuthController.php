<?php

namespace App\Controllers\Admin;

use App\Core\Env;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\User;
use App\Services\Audit;
use App\Services\Auth;
use App\Services\Security;

final class AuthController
{
    public function root(Request $request): void
    {
        Response::redirect(Auth::user() ? '/admin' : '/admin/login');
    }

    public function showLogin(Request $request): void
    {
        if (Auth::user()) {
            Response::redirect('/admin');
        }
        View::renderBare('login', ['error' => null]);
    }

    public function login(Request $request): void
    {
        $email = mb_strtolower($request->input('email', '') ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $ip = $request->ip();

        // CSRF (le middleware global n'est pas branché sur cette route pré-session)
        $sent = $_POST['_csrf'] ?? '';
        if (empty($_SESSION['csrf_token']) || !is_string($sent) || !hash_equals($_SESSION['csrf_token'], $sent)) {
            View::renderBare('login', ['error' => 'Session expirée, veuillez réessayer.']);
        }

        if ($minutes = Security::loginLockedFor($ip, $email)) {
            View::renderBare('login', [
                'error' => "Trop de tentatives. Compte temporairement bloqué, réessayez dans $minutes min.",
            ]);
        }

        // Compte super admin « racine » défini dans le .env (jamais en base) :
        // même anti brute-force et même message générique que les comptes normaux.
        $rootUsername = mb_strtolower(Env::get('ROOT_ADMIN_USERNAME', '') ?? '');
        $rootHash = Env::get('ROOT_ADMIN_PASSWORD_HASH', '') ?? '';
        if ($rootUsername !== '' && $rootHash !== '' && $email === $rootUsername) {
            $valid = password_verify($password, $rootHash);
            Security::recordLoginAttempt($ip, $email, $valid);
            if (!$valid) {
                sleep(1);
                View::renderBare('login', ['error' => 'Identifiants invalides.']);
            }
            Auth::loginRoot();
            Audit::log('login', 'user');
            Response::redirect('/admin');
        }

        $user = $email ? User::findByEmail($email) : null;
        $valid = $user
            && (int) $user['is_active'] === 1
            && password_verify($password, $user['password_hash']);

        Security::recordLoginAttempt($ip, $email, (bool) $valid);

        if (!$valid) {
            // Délai progressif contre les attaques automatisées
            sleep(1);
            View::renderBare('login', ['error' => 'Identifiants invalides.']);
        }

        Auth::login($user);
        User::touchLogin((int) $user['id']);
        Audit::log('login', 'user', (int) $user['id']);
        Response::redirect('/admin');
    }

    public function logout(Request $request): void
    {
        $userId = (int) (Auth::user()['id'] ?? 0);
        Audit::log('logout', 'user', $userId > 0 ? $userId : null);
        Auth::logout();
        Response::redirect('/admin/login');
    }
}
