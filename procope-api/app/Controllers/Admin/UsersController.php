<?php

namespace App\Controllers\Admin;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Models\User;
use App\Services\Audit;
use App\Services\Auth;

final class UsersController
{
    public function index(Request $request): void
    {
        $page = max(1, (int) ($request->input('page', '1') ?? 1));
        View::render('users/index', [
            'title'  => 'Utilisateurs',
            'result' => User::paginate($page),
        ]);
    }

    public function create(Request $request): void
    {
        View::render('users/form', ['title' => 'Nouvel utilisateur', 'user' => null]);
    }

    public function edit(Request $request): void
    {
        $user = User::find((int) $request->params['id']) ?: Response::abort(404, 'Utilisateur introuvable');
        View::render('users/form', ['title' => 'Modifier l\'utilisateur', 'user' => $user]);
    }

    public function store(Request $request): void
    {
        [$email, $name, $role, $password, $error] = $this->validated($request, true);
        if ($error) {
            flash('error', $error);
            Response::redirect('/admin/users/create');
        }
        if (User::findByEmail($email)) {
            flash('error', 'Un utilisateur avec cet email existe déjà.');
            Response::redirect('/admin/users/create');
        }
        $id = User::create($email, password_hash($password, PASSWORD_ARGON2ID), $name, $role);
        Audit::log('user.create', 'user', $id, ['email' => $email, 'role' => $role]);
        flash('success', 'Utilisateur créé.');
        Response::redirect('/admin/users');
    }

    public function update(Request $request): void
    {
        $id = (int) $request->params['id'];
        $user = User::find($id) ?: Response::abort(404, 'Utilisateur introuvable');
        [$email, $name, $role, $password, $error] = $this->validated($request, false);
        if ($error) {
            flash('error', $error);
            Response::redirect("/admin/users/$id/edit");
        }

        $isActive = $request->input('is_active') === '1';

        // Garde-fou : ne pas retirer le dernier super_admin actif
        if ($user['role'] === 'super_admin' && (int) $user['is_active'] === 1
            && ($role !== 'super_admin' || !$isActive)
            && User::countActiveSuperAdmins() <= 1) {
            flash('error', 'Impossible : il doit rester au moins un super admin actif.');
            Response::redirect("/admin/users/$id/edit");
        }

        $hash = $password !== '' ? password_hash($password, PASSWORD_ARGON2ID) : null;
        User::update($id, $email, $name, $role, $isActive, $hash);
        Audit::log('user.update', 'user', $id, ['email' => $email, 'role' => $role, 'active' => $isActive]);
        flash('success', 'Utilisateur mis à jour.');
        Response::redirect('/admin/users');
    }

    public function destroy(Request $request): void
    {
        $id = (int) $request->params['id'];
        $user = User::find($id) ?: Response::abort(404, 'Utilisateur introuvable');
        if ($id === (int) Auth::user()['id']) {
            flash('error', 'Vous ne pouvez pas désactiver votre propre compte.');
            Response::redirect('/admin/users');
        }
        if ($user['role'] === 'super_admin' && (int) $user['is_active'] === 1
            && User::countActiveSuperAdmins() <= 1) {
            flash('error', 'Impossible : il doit rester au moins un super admin actif.');
            Response::redirect('/admin/users');
        }
        User::deactivate($id);
        Audit::log('user.deactivate', 'user', $id, ['email' => $user['email']]);
        flash('success', 'Utilisateur désactivé.');
        Response::redirect('/admin/users');
    }

    /** @return array{0:string,1:string,2:string,3:string,4:?string} email, name, role, password, error */
    private function validated(Request $request, bool $passwordRequired): array
    {
        $email = mb_strtolower($request->input('email', '') ?? '');
        $name = $request->input('name', '') ?? '';
        $role = $request->input('role', 'operator') ?? 'operator';
        $password = (string) ($_POST['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return [$email, $name, $role, $password, 'Email invalide.'];
        }
        if ($name === '') {
            return [$email, $name, $role, $password, 'Le nom est obligatoire.'];
        }
        if (!in_array($role, User::ROLES, true)) {
            return [$email, $name, $role, $password, 'Rôle invalide.'];
        }
        if (($passwordRequired || $password !== '') && strlen($password) < 10) {
            return [$email, $name, $role, $password, 'Mot de passe trop court (min 10 caractères).'];
        }
        return [$email, mb_substr($name, 0, 150), $role, $password, null];
    }
}
