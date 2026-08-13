<?php

namespace App\Models;

use App\Core\Database;

/** Messages envoyés depuis le formulaire de contact du site public. */
final class ContactMessage
{
    public const STATUTS = ['nouveau', 'lu', 'traite'];

    public const STATUT_LABELS = [
        'nouveau' => 'Nouveau',
        'lu'      => 'Lu',
        'traite'  => 'Traité',
    ];

    /** Liste filtrée + paginée. $filters : statut, q. */
    public static function search(array $filters, int $page = 1, int $perPage = 25): array
    {
        $conditions = [];
        $params = [];
        if (!empty($filters['statut']) && in_array($filters['statut'], self::STATUTS, true)) {
            $conditions[] = 'statut = ?';
            $params[] = $filters['statut'];
        }
        if (!empty($filters['q'])) {
            $conditions[] = '(name LIKE ? OR email LIKE ? OR phone LIKE ? OR subject LIKE ? OR message LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $total = (int) Database::run("SELECT COUNT(*) FROM contact_messages $where", $params)->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::run(
            "SELECT * FROM contact_messages $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
            $params
        )->fetchAll();

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / $perPage)];
    }

    public static function find(int $id): ?array
    {
        return Database::run('SELECT * FROM contact_messages WHERE id = ?', [$id])->fetch() ?: null;
    }

    public static function create(array $d): int
    {
        Database::run(
            'INSERT INTO contact_messages (name, email, phone, subject, message, ip, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$d['name'], $d['email'], $d['phone'], $d['subject'], $d['message'], $d['ip'], $d['user_agent']]
        );
        return (int) Database::pdo()->lastInsertId();
    }

    public static function updateStatus(int $id, string $statut): void
    {
        Database::run('UPDATE contact_messages SET statut = ? WHERE id = ?', [$statut, $id]);
    }

    /** Nombre de messages non encore lus (pastille sidebar). */
    public static function countNew(): int
    {
        return (int) Database::run("SELECT COUNT(*) FROM contact_messages WHERE statut = 'nouveau'")->fetchColumn();
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM contact_messages WHERE id = ?', [$id]);
    }
}
