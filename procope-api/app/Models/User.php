<?php

namespace App\Models;

use App\Core\Database;

final class User
{
    public const ROLES = ['super_admin', 'admin', 'operator'];

    public const ROLE_LABELS = [
        'super_admin' => 'Super admin',
        'admin'       => 'Admin',
        'operator'    => 'Opérateur',
    ];

    public static function all(): array
    {
        return Database::run('SELECT id, email, name, role, is_active, last_login_at, created_at FROM users ORDER BY created_at')->fetchAll();
    }

    /** Liste paginée des comptes (rows/total/page/pages). */
    public static function paginate(int $page = 1, int $perPage = 25): array
    {
        $total = (int) Database::run('SELECT COUNT(*) FROM users')->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::run(
            "SELECT id, email, name, role, is_active, last_login_at, created_at
               FROM users ORDER BY created_at LIMIT $perPage OFFSET $offset"
        )->fetchAll();
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / $perPage)];
    }

    public static function find(int $id): ?array
    {
        return Database::run('SELECT * FROM users WHERE id = ?', [$id])->fetch() ?: null;
    }

    public static function findByEmail(string $email): ?array
    {
        return Database::run('SELECT * FROM users WHERE email = ?', [$email])->fetch() ?: null;
    }

    public static function create(string $email, string $passwordHash, string $name, string $role): int
    {
        Database::run(
            'INSERT INTO users (email, password_hash, name, role, is_active) VALUES (?, ?, ?, ?, 1)',
            [$email, $passwordHash, $name, $role]
        );
        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, string $email, string $name, string $role, bool $isActive, ?string $passwordHash = null): void
    {
        if ($passwordHash !== null) {
            Database::run(
                'UPDATE users SET email = ?, name = ?, role = ?, is_active = ?, password_hash = ? WHERE id = ?',
                [$email, $name, $role, $isActive ? 1 : 0, $passwordHash, $id]
            );
        } else {
            Database::run(
                'UPDATE users SET email = ?, name = ?, role = ?, is_active = ? WHERE id = ?',
                [$email, $name, $role, $isActive ? 1 : 0, $id]
            );
        }
    }

    public static function deactivate(int $id): void
    {
        Database::run('UPDATE users SET is_active = 0 WHERE id = ?', [$id]);
    }

    public static function touchLogin(int $id): void
    {
        Database::run('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$id]);
    }

    public static function countActiveSuperAdmins(): int
    {
        return (int) Database::run(
            "SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND is_active = 1"
        )->fetchColumn();
    }
}
