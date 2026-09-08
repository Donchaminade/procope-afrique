<?php

namespace App\Models;

use App\Core\Database;

/** Album photo d'une formation, ou affiche d'événement passé. */
final class FormationGallery
{
    public const KIND_PHOTOS = 'photos';
    public const KIND_AFFICHE = 'affiche';

    public const KINDS = [
        self::KIND_PHOTOS  => 'Photos de formation',
        self::KIND_AFFICHE => 'Affiche événement',
    ];

    public const MONTH_LABELS = [
        1  => 'janvier',
        2  => 'février',
        3  => 'mars',
        4  => 'avril',
        5  => 'mai',
        6  => 'juin',
        7  => 'juillet',
        8  => 'août',
        9  => 'septembre',
        10 => 'octobre',
        11 => 'novembre',
        12 => 'décembre',
    ];

    /** Liste paginée admin (toutes, avec compteur de photos). */
    public static function paginate(int $page = 1, int $perPage = 25): array
    {
        $total = (int) Database::run('SELECT COUNT(*) FROM formation_galleries')->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::run(
            "SELECT g.*,
                    f.titre AS formation_titre,
                    (SELECT COUNT(*) FROM formation_gallery_images i WHERE i.gallery_id = g.id) AS nb_images
               FROM formation_galleries g
               LEFT JOIN formations f ON f.id = g.formation_id
              ORDER BY g.year DESC, g.month IS NULL, g.month DESC, g.title ASC
              LIMIT $perPage OFFSET $offset"
        )->fetchAll();
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / $perPage)];
    }

    /** Albums publiés (avec au moins une image), plus récente année d'abord. */
    public static function published(?string $kind = self::KIND_PHOTOS): array
    {
        $sql = 'SELECT * FROM formation_galleries g
                 WHERE g.is_published = 1
                   AND EXISTS (
                       SELECT 1 FROM formation_gallery_images i WHERE i.gallery_id = g.id
                   )';
        $params = [];
        if ($kind !== null && $kind !== '') {
            $sql .= ' AND g.kind = ?';
            $params[] = $kind;
        }
        $sql .= ' ORDER BY g.year DESC, g.month IS NULL, g.month DESC, g.title ASC, g.id ASC';
        return Database::run($sql, $params)->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $row = Database::run(
            'SELECT g.*, f.titre AS formation_titre
               FROM formation_galleries g
               LEFT JOIN formations f ON f.id = g.formation_id
              WHERE g.id = ?',
            [$id]
        )->fetch();
        return $row ?: null;
    }

    public static function create(array $data): int
    {
        Database::run(
            'INSERT INTO formation_galleries
                (formation_id, title, year, month, description, kind, is_published)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['formation_id'],
                $data['title'],
                $data['year'],
                $data['month'],
                $data['description'],
                $data['kind'],
                $data['is_published'],
            ]
        );
        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Database::run(
            'UPDATE formation_galleries SET
                formation_id = ?, title = ?, year = ?, month = ?,
                description = ?, kind = ?, is_published = ?
             WHERE id = ?',
            [
                $data['formation_id'],
                $data['title'],
                $data['year'],
                $data['month'],
                $data['description'],
                $data['kind'],
                $data['is_published'],
                $id,
            ]
        );
    }

    public static function setPublished(int $id, bool $published): void
    {
        Database::run(
            'UPDATE formation_galleries SET is_published = ? WHERE id = ?',
            [$published ? 1 : 0, $id]
        );
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM formation_galleries WHERE id = ?', [$id]);
    }

    public static function monthLabel(?int $month): ?string
    {
        if ($month === null || $month < 1 || $month > 12) {
            return null;
        }
        return self::MONTH_LABELS[$month];
    }

    public static function normalizeKind(?string $kind): string
    {
        return $kind === self::KIND_AFFICHE ? self::KIND_AFFICHE : self::KIND_PHOTOS;
    }

    public static function kindLabel(?string $kind): string
    {
        $key = self::normalizeKind($kind);
        return self::KINDS[$key];
    }
}
