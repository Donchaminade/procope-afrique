<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Env;

/** Témoignages écrits : dépôt public (modération) ou création admin. */
final class Testimonial
{
    public const STATUTS = ['en_attente', 'publie', 'refuse'];

    public const STATUT_LABELS = [
        'en_attente' => 'En attente',
        'publie'     => 'Publié',
        'refuse'     => 'Refusé',
    ];

    public const SOURCES = ['public', 'admin'];

    public const SOURCE_LABELS = [
        'public' => 'Site public',
        'admin'  => 'Admin',
    ];

    /** Liste filtrée + paginée. $filters : statut, source, q. */
    public static function search(array $filters, int $page = 1, int $perPage = 25): array
    {
        $conditions = [];
        $params = [];
        if (!empty($filters['statut']) && in_array($filters['statut'], self::STATUTS, true)) {
            $conditions[] = 'statut = ?';
            $params[] = $filters['statut'];
        }
        if (!empty($filters['source']) && in_array($filters['source'], self::SOURCES, true)) {
            $conditions[] = 'source = ?';
            $params[] = $filters['source'];
        }
        if (!empty($filters['q'])) {
            $conditions[] = '(author_name LIKE ? OR role_title LIKE ? OR quote LIKE ? OR author_email LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like, $like);
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $total = (int) Database::run("SELECT COUNT(*) FROM testimonials $where", $params)->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::run(
            "SELECT * FROM testimonials $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset",
            $params
        )->fetchAll();

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / $perPage)];
    }

    /** Publiés, plus récents d'abord (site public). */
    public static function published(int $limit = 24): array
    {
        $limit = max(1, min(50, $limit));
        return Database::run(
            "SELECT * FROM testimonials
              WHERE statut = 'publie'
              ORDER BY COALESCE(published_at, created_at) DESC, id DESC
              LIMIT $limit"
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        return Database::run('SELECT * FROM testimonials WHERE id = ?', [$id])->fetch() ?: null;
    }

    public static function create(array $d): int
    {
        Database::run(
            'INSERT INTO testimonials
                (author_name, author_email, role_title, quote, photo_path, photo_mime, source, statut, published_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $d['author_name'],
                $d['author_email'] ?? null,
                $d['role_title'] ?? null,
                $d['quote'],
                $d['photo_path'] ?? null,
                $d['photo_mime'] ?? null,
                $d['source'] ?? 'public',
                $d['statut'] ?? 'en_attente',
                $d['published_at'] ?? null,
            ]
        );
        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $d): void
    {
        Database::run(
            'UPDATE testimonials SET
                author_name = ?, author_email = ?, role_title = ?, quote = ?,
                photo_path = ?, photo_mime = ?, statut = ?, published_at = ?
              WHERE id = ?',
            [
                $d['author_name'],
                $d['author_email'] ?? null,
                $d['role_title'] ?? null,
                $d['quote'],
                $d['photo_path'] ?? null,
                $d['photo_mime'] ?? null,
                $d['statut'],
                $d['published_at'] ?? null,
                $id,
            ]
        );
    }

    public static function setStatut(int $id, string $statut, ?string $publishedAt): void
    {
        Database::run(
            'UPDATE testimonials SET statut = ?, published_at = ? WHERE id = ?',
            [$statut, $publishedAt, $id]
        );
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM testimonials WHERE id = ?', [$id]);
    }

    public static function countPending(): int
    {
        return (int) Database::run("SELECT COUNT(*) FROM testimonials WHERE statut = 'en_attente'")->fetchColumn();
    }

    public static function photoUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        $base = rtrim((string) Env::get('APP_URL', ''), '/');
        return $base . '/uploads/temoignages/' . rawurlencode(basename($path));
    }

    /** Payload public (GET /api/temoignages). */
    public static function toPublic(array $row): array
    {
        return [
            'name'  => $row['author_name'],
            'role'  => $row['role_title'] ?: null,
            'quote' => $row['quote'],
            'photo' => self::photoUrl($row['photo_path'] ?? null),
        ];
    }
}
