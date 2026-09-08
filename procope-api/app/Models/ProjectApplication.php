<?php

namespace App\Models;

use App\Core\Database;

final class ProjectApplication
{
    public const STATUTS = ['nouvelle', 'en_examen', 'retenue', 'refusee'];

    /** Dossiers encore en cours : un doublon spontané n'est bloqué que dans ces cas. */
    public const ACTIVE_STATUTS = ['nouvelle', 'en_examen', 'retenue'];

    public const STATUT_LABELS = [
        'nouvelle'  => 'Nouvelle',
        'en_examen' => 'En examen',
        'retenue'   => 'Retenue',
        'refusee'   => 'Refusée',
    ];

    /**
     * Liste paginée. $filters : call_id, statut.
     * call_id = -1 : dépôts spontanés (call_id IS NULL).
     */
    public static function search(array $filters, int $page = 1, int $perPage = 25): array
    {
        [$where, $params] = self::buildWhere($filters);
        $total = (int) Database::run(
            "SELECT COUNT(*) FROM project_applications a $where",
            $params
        )->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::run(
            "SELECT a.*, c.title AS call_title, p.title AS project_title,
                    pub.id AS published_project_id, pub.is_published AS published_is_published
               FROM project_applications a
               LEFT JOIN incubation_calls c ON c.id = a.call_id
               LEFT JOIN incubated_projects p ON p.id = a.project_id
               LEFT JOIN incubated_projects pub ON pub.application_id = a.id
             $where
             ORDER BY a.created_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        )->fetchAll();
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / $perPage)];
    }

    public static function allFiltered(array $filters): array
    {
        [$where, $params] = self::buildWhere($filters);
        return Database::run(
            "SELECT a.*, c.title AS call_title, p.title AS project_title
               FROM project_applications a
               LEFT JOIN incubation_calls c ON c.id = a.call_id
               LEFT JOIN incubated_projects p ON p.id = a.project_id
             $where
             ORDER BY a.created_at ASC",
            $params
        )->fetchAll();
    }

    private static function buildWhere(array $filters): array
    {
        $conditions = [];
        $params = [];
        if (isset($filters['call_id']) && $filters['call_id'] !== null && $filters['call_id'] !== '') {
            $cid = (int) $filters['call_id'];
            if ($cid === -1) {
                $conditions[] = 'a.call_id IS NULL';
            } elseif ($cid > 0) {
                $conditions[] = 'a.call_id = ?';
                $params[] = $cid;
            }
        }
        if (isset($filters['project_id']) && $filters['project_id'] !== null && $filters['project_id'] !== '') {
            $pid = (int) $filters['project_id'];
            if ($pid === -1) {
                $conditions[] = 'a.project_id IS NULL';
            } elseif ($pid > 0) {
                $conditions[] = 'a.project_id = ?';
                $params[] = $pid;
            }
        }
        if (!empty($filters['statut']) && in_array($filters['statut'], self::STATUTS, true)) {
            $conditions[] = 'a.statut = ?';
            $params[] = $filters['statut'];
        }
        return [$conditions ? 'WHERE ' . implode(' AND ', $conditions) : '', $params];
    }

    public static function allForProject(int $projectId): array
    {
        return self::allFiltered(['project_id' => $projectId]);
    }

    public static function countAll(): int
    {
        return (int) Database::run('SELECT COUNT(*) FROM project_applications')->fetchColumn();
    }

    public static function countWithStatut(string $statut): int
    {
        return (int) Database::run(
            'SELECT COUNT(*) FROM project_applications WHERE statut = ?',
            [$statut]
        )->fetchColumn();
    }

    public static function find(int $id): ?array
    {
        return Database::run(
            'SELECT a.*, c.title AS call_title, c.slug AS call_slug,
                    p.title AS project_title, p.slug AS project_slug,
                    pub.id AS published_project_id, pub.slug AS published_project_slug,
                    pub.is_published AS published_is_published, pub.title AS published_project_title
               FROM project_applications a
               LEFT JOIN incubation_calls c ON c.id = a.call_id
               LEFT JOIN incubated_projects p ON p.id = a.project_id
               LEFT JOIN incubated_projects pub ON pub.application_id = a.id
              WHERE a.id = ?',
            [$id]
        )->fetch() ?: null;
    }

    /** Nom de projet comparable : trim + minuscules (insensible à la casse). */
    public static function normalizeProjectName(?string $name): string
    {
        return mb_strtolower(trim((string) $name));
    }

    /**
     * Un e-mail = une candidature par appel (tous statuts, y compris refusée).
     * call_id null : pas de blocage — le spontané se juge sur le nom du projet.
     */
    public static function existsForCall(?int $callId, string $email): bool
    {
        if ($callId === null || $callId <= 0) {
            return false;
        }
        return (int) Database::run(
            'SELECT COUNT(*) FROM project_applications WHERE call_id = ? AND email = ?',
            [$callId, $email]
        )->fetchColumn() > 0;
    }

    /**
     * Doublon spontané évident : même e-mail + même nom (normalisé),
     * dossier encore actif (nouvelle / en_examen / retenue).
     * Nom vide : pas un doublon évident (on n'bloque pas).
     */
    public static function existsActiveSpontaneous(string $email, string $projectName): bool
    {
        $normalized = self::normalizeProjectName($projectName);
        if ($normalized === '') {
            return false;
        }
        $placeholders = implode(',', array_fill(0, count(self::ACTIVE_STATUTS), '?'));
        $rows = Database::run(
            "SELECT project_name FROM project_applications
              WHERE call_id IS NULL AND email = ? AND statut IN ($placeholders)",
            array_merge([$email], self::ACTIVE_STATUTS)
        )->fetchAll();
        foreach ($rows as $row) {
            if (self::normalizeProjectName($row['project_name'] ?? '') === $normalized) {
                return true;
            }
        }
        return false;
    }

    public static function create(array $d): int
    {
        Database::run(
            'INSERT INTO project_applications
                (project_id, call_id, full_name, email, phone, project_name, sector, pitch, message,
                 file_path, file_mime, file_name, statut, ip, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $d['project_id'] ?? null, $d['call_id'] ?? null,
                $d['full_name'], $d['email'], $d['phone'],
                $d['project_name'], $d['sector'], $d['pitch'], $d['message'],
                $d['file_path'], $d['file_mime'], $d['file_name'],
                $d['statut'] ?? 'nouvelle', $d['ip'], $d['user_agent'],
            ]
        );
        return (int) Database::pdo()->lastInsertId();
    }

    public static function linkProject(int $id, int $projectId): void
    {
        Database::run('UPDATE project_applications SET project_id = ? WHERE id = ?', [$projectId, $id]);
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM project_applications WHERE id = ?', [$id]);
    }

    public static function updateStatus(int $id, string $statut): void
    {
        Database::run('UPDATE project_applications SET statut = ? WHERE id = ?', [$statut, $id]);
    }

    /** E-mails distincts des dépôts de projets (diffusion communauté). */
    public static function allEmails(): array
    {
        return Database::run(
            "SELECT DISTINCT email FROM project_applications WHERE email IS NOT NULL AND email != '' ORDER BY email"
        )->fetchAll(\PDO::FETCH_COLUMN);
    }
}
