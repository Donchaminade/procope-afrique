<?php

namespace App\Models;

use App\Core\Database;

final class IncubatedProject
{
    public const STAGES = ['idee', 'prototype', 'lance', 'diplome'];

    public const STAGE_LABELS = [
        'idee'      => 'Idée',
        'prototype' => 'Prototype',
        'lance'     => 'Lancé',
        'diplome'   => 'Diplômé',
    ];

    /** Liste paginée (admin, projets non archivés) avec compteur de dépôts. */
    public static function paginate(int $page = 1, int $perPage = 25): array
    {
        $total = (int) Database::run(
            'SELECT COUNT(*) FROM incubated_projects WHERE archived_at IS NULL'
        )->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::run(
            "SELECT p.*,
                    (SELECT COUNT(*) FROM project_applications a WHERE a.project_id = p.id) AS nb_depots,
                    app.full_name AS source_name, app.statut AS source_statut
               FROM incubated_projects p
               LEFT JOIN project_applications app ON app.id = p.application_id
              WHERE p.archived_at IS NULL ORDER BY p.created_at DESC
              LIMIT $perPage OFFSET $offset"
        )->fetchAll();
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / $perPage)];
    }

    /** Projets publiés et non archivés (API publique + bouton Exécuter). */
    public static function published(): array
    {
        return Database::run(
            'SELECT * FROM incubated_projects
              WHERE is_published = 1 AND archived_at IS NULL
              ORDER BY created_at DESC'
        )->fetchAll();
    }

    /** Tous les projets (archivés compris) pour le select des filtres dépôts. */
    public static function allForFilter(): array
    {
        return Database::run(
            'SELECT id, title, archived_at FROM incubated_projects
              ORDER BY archived_at IS NOT NULL, created_at DESC'
        )->fetchAll();
    }

    public static function countPublished(): int
    {
        return (int) Database::run(
            'SELECT COUNT(*) FROM incubated_projects WHERE is_published = 1 AND archived_at IS NULL'
        )->fetchColumn();
    }

    public static function archived(): array
    {
        return Database::run(
            'SELECT p.*, (SELECT COUNT(*) FROM project_applications a WHERE a.project_id = p.id) AS nb_depots
               FROM incubated_projects p WHERE p.archived_at IS NOT NULL ORDER BY p.archived_at DESC'
        )->fetchAll();
    }

    public static function archive(int $id): void
    {
        Database::run(
            'UPDATE incubated_projects SET archived_at = NOW(), is_published = 0 WHERE id = ?',
            [$id]
        );
    }

    public static function unarchive(int $id): void
    {
        Database::run('UPDATE incubated_projects SET archived_at = NULL WHERE id = ?', [$id]);
    }

    public static function find(int $id): ?array
    {
        return Database::run('SELECT * FROM incubated_projects WHERE id = ?', [$id])->fetch() ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::run('SELECT * FROM incubated_projects WHERE slug = ?', [$slug])->fetch() ?: null;
    }

    public static function findByApplicationId(int $applicationId): ?array
    {
        return Database::run(
            'SELECT * FROM incubated_projects WHERE application_id = ?',
            [$applicationId]
        )->fetch() ?: null;
    }

    public static function create(array $d): int
    {
        Database::run(
            'INSERT INTO incubated_projects
                (application_id, title, slug, pitch, description, sector, stage, country, year, website, socials, is_published)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $d['application_id'] ?? null,
                $d['title'], $d['slug'], $d['pitch'], $d['description'], $d['sector'],
                $d['stage'], $d['country'], $d['year'], $d['website'], $d['socials'], $d['is_published'],
            ]
        );
        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $d): void
    {
        Database::run(
            'UPDATE incubated_projects SET title = ?, slug = ?, pitch = ?, description = ?,
                    sector = ?, stage = ?, country = ?, year = ?, website = ?, socials = ?, is_published = ?
              WHERE id = ?',
            [
                $d['title'], $d['slug'], $d['pitch'], $d['description'], $d['sector'],
                $d['stage'], $d['country'], $d['year'], $d['website'], $d['socials'], $d['is_published'], $id,
            ]
        );
    }

    public static function setPublished(int $id, bool $published): void
    {
        Database::run(
            'UPDATE incubated_projects SET is_published = ? WHERE id = ?',
            [$published ? 1 : 0, $id]
        );
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM incubated_projects WHERE id = ?', [$id]);
    }
}
