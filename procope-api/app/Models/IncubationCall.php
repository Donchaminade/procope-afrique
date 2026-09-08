<?php

namespace App\Models;

use App\Core\Database;

final class IncubationCall
{
    public static function paginate(int $page = 1, int $perPage = 25): array
    {
        $total = (int) Database::run(
            'SELECT COUNT(*) FROM incubation_calls WHERE archived_at IS NULL'
        )->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::run(
            "SELECT c.*, (SELECT COUNT(*) FROM project_applications a WHERE a.call_id = c.id) AS nb_depots
               FROM incubation_calls c WHERE c.archived_at IS NULL ORDER BY c.created_at DESC
              LIMIT $perPage OFFSET $offset"
        )->fetchAll();
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / $perPage)];
    }

    /** Appels publiés, ouverts maintenant (API publique). */
    public static function openPublished(): array
    {
        return Database::run(
            'SELECT * FROM incubation_calls
              WHERE is_published = 1 AND archived_at IS NULL
                AND opens_at <= NOW() AND closes_at > NOW()
              ORDER BY closes_at ASC'
        )->fetchAll();
    }

    public static function allForFilter(): array
    {
        return Database::run(
            'SELECT id, title, archived_at FROM incubation_calls
              ORDER BY archived_at IS NOT NULL, created_at DESC'
        )->fetchAll();
    }

    public static function archived(): array
    {
        return Database::run(
            'SELECT c.*, (SELECT COUNT(*) FROM project_applications a WHERE a.call_id = c.id) AS nb_depots
               FROM incubation_calls c WHERE c.archived_at IS NOT NULL ORDER BY c.archived_at DESC'
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        return Database::run('SELECT * FROM incubation_calls WHERE id = ?', [$id])->fetch() ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::run('SELECT * FROM incubation_calls WHERE slug = ?', [$slug])->fetch() ?: null;
    }

    public static function isOpen(array $call): bool
    {
        if (!(int) $call['is_published'] || !empty($call['archived_at'])) {
            return false;
        }
        $now = time();
        $opens = strtotime((string) $call['opens_at']);
        $closes = strtotime((string) $call['closes_at']);
        return $opens !== false && $closes !== false && $opens <= $now && $closes > $now;
    }

    public static function create(array $d): int
    {
        Database::run(
            'INSERT INTO incubation_calls
                (title, slug, sector, description, opens_at, closes_at, is_published)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $d['title'], $d['slug'], $d['sector'], $d['description'],
                $d['opens_at'], $d['closes_at'], $d['is_published'],
            ]
        );
        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $d): void
    {
        Database::run(
            'UPDATE incubation_calls SET title = ?, slug = ?, sector = ?, description = ?,
                    opens_at = ?, closes_at = ?, is_published = ? WHERE id = ?',
            [
                $d['title'], $d['slug'], $d['sector'], $d['description'],
                $d['opens_at'], $d['closes_at'], $d['is_published'], $id,
            ]
        );
    }

    public static function setPublished(int $id, bool $published): void
    {
        Database::run(
            'UPDATE incubation_calls SET is_published = ? WHERE id = ?',
            [$published ? 1 : 0, $id]
        );
    }

    public static function archive(int $id): void
    {
        Database::run(
            'UPDATE incubation_calls SET archived_at = NOW(), is_published = 0 WHERE id = ?',
            [$id]
        );
    }

    public static function unarchive(int $id): void
    {
        Database::run('UPDATE incubation_calls SET archived_at = NULL WHERE id = ?', [$id]);
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM incubation_calls WHERE id = ?', [$id]);
    }

    public static function countOpenPublished(): int
    {
        return (int) Database::run(
            'SELECT COUNT(*) FROM incubation_calls
              WHERE is_published = 1 AND archived_at IS NULL
                AND opens_at <= NOW() AND closes_at > NOW()'
        )->fetchColumn();
    }
}
