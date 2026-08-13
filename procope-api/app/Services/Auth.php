<?php

namespace App\Services;

use App\Core\Database;
use App\Core\Env;

final class Auth
{
    private static ?array $user = null;
    private static bool $loaded = false;

    public static function user(): ?array
    {
        if (self::$loaded) {
            return self::$user;
        }
        self::$loaded = true;
        // Compte super admin « racine » défini dans le .env (absent de la table users)
        if (!empty($_SESSION['is_root_admin'])) {
            self::$user = [
                'id'        => 0,
                'email'     => Env::get('ROOT_ADMIN_USERNAME', 'root') ?? 'root',
                'name'      => 'Super admin (racine)',
                'role'      => 'super_admin',
                'is_active' => 1,
            ];
            return self::$user;
        }
        $id = $_SESSION['user_id'] ?? null;
        if (!$id) {
            return null;
        }
        $user = Database::run(
            'SELECT id, email, name, role, is_active FROM users WHERE id = ? LIMIT 1',
            [$id]
        )->fetch() ?: null;
        if (!$user || !(int) $user['is_active']) {
            self::logout();
            return null;
        }
        self::$user = $user;
        return $user;
    }

    public static function login(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        self::$user = null;
        self::$loaded = false;
    }

    /** Ouvre une session pour le compte racine du .env (aucune ligne en base). */
    public static function loginRoot(): void
    {
        session_regenerate_id(true);
        unset($_SESSION['user_id']);
        $_SESSION['is_root_admin'] = true;
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        self::$user = null;
        self::$loaded = false;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
            session_destroy();
        }
        self::$user = null;
        self::$loaded = true;
    }

    public static function isAtLeast(string $role): bool
    {
        $levels = ['operator' => 1, 'admin' => 2, 'super_admin' => 3];
        $user = self::user();
        return $user !== null
            && ($levels[$user['role']] ?? 0) >= ($levels[$role] ?? PHP_INT_MAX);
    }
}
