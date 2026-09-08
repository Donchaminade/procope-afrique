<?php

namespace App\Models;

use App\Core\Database;

/**
 * Affiches d'un projet incubé : plusieurs par projet, une seule principale
 * (is_main = 1). Fichiers publics dans public/uploads/projets/.
 */
final class ProjectImage
{
    public static function allForProject(int $projectId): array
    {
        return Database::run(
            'SELECT * FROM project_images WHERE project_id = ?
              ORDER BY is_main DESC, sort_order ASC, id ASC',
            [$projectId]
        )->fetchAll();
    }

    public static function mainForProject(int $projectId): ?array
    {
        return Database::run(
            'SELECT * FROM project_images WHERE project_id = ?
              ORDER BY is_main DESC, sort_order ASC, id ASC LIMIT 1',
            [$projectId]
        )->fetch() ?: null;
    }

    public static function find(int $id): ?array
    {
        return Database::run('SELECT * FROM project_images WHERE id = ?', [$id])->fetch() ?: null;
    }

    public static function create(int $projectId, string $path, string $mime, bool $isMain = false): int
    {
        $sort = (int) Database::run(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM project_images WHERE project_id = ?',
            [$projectId]
        )->fetchColumn();
        Database::run(
            'INSERT INTO project_images (project_id, path, mime, is_main, sort_order) VALUES (?, ?, ?, ?, ?)',
            [$projectId, $path, $mime, $isMain ? 1 : 0, $sort]
        );
        return (int) Database::pdo()->lastInsertId();
    }

    public static function setMain(int $projectId, int $imageId): void
    {
        Database::run('UPDATE project_images SET is_main = 0 WHERE project_id = ?', [$projectId]);
        Database::run(
            'UPDATE project_images SET is_main = 1 WHERE id = ? AND project_id = ?',
            [$imageId, $projectId]
        );
    }

    public static function ensureMain(int $projectId): void
    {
        $hasMain = (int) Database::run(
            'SELECT COUNT(*) FROM project_images WHERE project_id = ? AND is_main = 1',
            [$projectId]
        )->fetchColumn();
        if ($hasMain > 0) {
            return;
        }
        $first = Database::run(
            'SELECT id FROM project_images WHERE project_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1',
            [$projectId]
        )->fetchColumn();
        if ($first) {
            Database::run('UPDATE project_images SET is_main = 1 WHERE id = ?', [(int) $first]);
        }
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM project_images WHERE id = ?', [$id]);
    }
}
