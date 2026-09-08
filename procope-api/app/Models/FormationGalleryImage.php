<?php

namespace App\Models;

use App\Core\Database;

/** Photos d'un album de formation. Fichiers publics dans public/uploads/galeries/. */
final class FormationGalleryImage
{
    public static function allForGallery(int $galleryId): array
    {
        return Database::run(
            'SELECT * FROM formation_gallery_images
              WHERE gallery_id = ?
              ORDER BY sort_order ASC, id ASC',
            [$galleryId]
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        return Database::run('SELECT * FROM formation_gallery_images WHERE id = ?', [$id])->fetch() ?: null;
    }

    public static function create(int $galleryId, string $path, string $mime, ?string $caption = null): int
    {
        $sort = (int) Database::run(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM formation_gallery_images WHERE gallery_id = ?',
            [$galleryId]
        )->fetchColumn();
        Database::run(
            'INSERT INTO formation_gallery_images (gallery_id, path, mime, caption, sort_order)
             VALUES (?, ?, ?, ?, ?)',
            [$galleryId, $path, $mime, $caption, $sort]
        );
        return (int) Database::pdo()->lastInsertId();
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM formation_gallery_images WHERE id = ?', [$id]);
    }

    /** Échange sort_order avec le voisin (direction -1 = plus haut, +1 = plus bas). */
    public static function move(int $galleryId, int $imageId, int $direction): bool
    {
        $rows = self::allForGallery($galleryId);
        $index = null;
        foreach ($rows as $i => $row) {
            if ((int) $row['id'] === $imageId) {
                $index = $i;
                break;
            }
        }
        if ($index === null) {
            return false;
        }
        $swap = $index + $direction;
        if ($swap < 0 || $swap >= count($rows)) {
            return false;
        }

        $a = $rows[$index];
        $b = $rows[$swap];
        Database::run(
            'UPDATE formation_gallery_images SET sort_order = ? WHERE id = ?',
            [(int) $b['sort_order'], (int) $a['id']]
        );
        Database::run(
            'UPDATE formation_gallery_images SET sort_order = ? WHERE id = ?',
            [(int) $a['sort_order'], (int) $b['id']]
        );
        return true;
    }
}
