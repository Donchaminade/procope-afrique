<?php

namespace App\Models;

use App\Core\Database;

final class JobApplication
{
    public const STATUTS = ['nouvelle', 'en_examen', 'retenue', 'refusee'];

    public const STATUT_LABELS = [
        'nouvelle'  => 'Nouvelle',
        'en_examen' => 'En examen',
        'retenue'   => 'Retenue',
        'refusee'   => 'Refusée',
    ];

    /**
     * Liste paginée avec filtres (admin). $filters : offer_id, statut.
     * Sert la vue globale (toutes offres) et la vue par offre.
     */
    public static function search(array $filters, int $page = 1, int $perPage = 25): array
    {
        [$where, $params] = self::buildWhere($filters);
        $total = (int) Database::run(
            "SELECT COUNT(*) FROM job_applications a $where",
            $params
        )->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::run(
            "SELECT a.*, o.title AS offer_title
               FROM job_applications a
               JOIN job_offers o ON o.id = a.offer_id
             $where
             ORDER BY a.created_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        )->fetchAll();
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / $perPage)];
    }

    /** Candidatures d'une offre, paginées (admin, filtre statut optionnel). */
    public static function paginateForOffer(int $offerId, int $page = 1, ?string $statut = null): array
    {
        return self::search(['offer_id' => $offerId, 'statut' => $statut], $page);
    }

    /** Toutes les lignes filtrées (exports Excel / PDF). $filters : offer_id, statut. */
    public static function allFiltered(array $filters): array
    {
        [$where, $params] = self::buildWhere($filters);
        return Database::run(
            "SELECT a.*, o.title AS offer_title
               FROM job_applications a
               JOIN job_offers o ON o.id = a.offer_id
             $where
             ORDER BY a.created_at ASC",
            $params
        )->fetchAll();
    }

    private static function buildWhere(array $filters): array
    {
        $conditions = [];
        $params = [];
        if (!empty($filters['offer_id'])) {
            $conditions[] = 'a.offer_id = ?';
            $params[] = (int) $filters['offer_id'];
        }
        if (!empty($filters['statut']) && in_array($filters['statut'], self::STATUTS, true)) {
            $conditions[] = 'a.statut = ?';
            $params[] = $filters['statut'];
        }
        return [$conditions ? 'WHERE ' . implode(' AND ', $conditions) : '', $params];
    }

    /** Toutes les candidatures d'une offre (export, suppression des CV). */
    public static function allForOffer(int $offerId): array
    {
        return self::allFiltered(['offer_id' => $offerId]);
    }

    /** Nombre total de dossiers déposés, toutes offres confondues (dashboard). */
    public static function countAll(): int
    {
        return (int) Database::run('SELECT COUNT(*) FROM job_applications')->fetchColumn();
    }

    /** Nombre de candidatures d'un statut donné, toutes offres (dashboard). */
    public static function countWithStatut(string $statut): int
    {
        return (int) Database::run(
            'SELECT COUNT(*) FROM job_applications WHERE statut = ?',
            [$statut]
        )->fetchColumn();
    }

    public static function find(int $id): ?array
    {
        return Database::run(
            'SELECT a.*, o.title AS offer_title, o.slug AS offer_slug
               FROM job_applications a
               JOIN job_offers o ON o.id = a.offer_id
              WHERE a.id = ?',
            [$id]
        )->fetch() ?: null;
    }

    public static function existsForOffer(int $offerId, string $email): bool
    {
        return (int) Database::run(
            'SELECT COUNT(*) FROM job_applications WHERE offer_id = ? AND email = ?',
            [$offerId, $email]
        )->fetchColumn() > 0;
    }

    public static function create(array $d): int
    {
        Database::run(
            'INSERT INTO job_applications
                (offer_id, full_name, email, phone, message, cv_path, cv_mime, cv_name, statut, ip, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $d['offer_id'], $d['full_name'], $d['email'], $d['phone'], $d['message'],
                $d['cv_path'], $d['cv_mime'], $d['cv_name'], $d['statut'] ?? 'nouvelle',
                $d['ip'], $d['user_agent'],
            ]
        );
        return (int) Database::pdo()->lastInsertId();
    }

    public static function updateStatus(int $id, string $statut): void
    {
        Database::run('UPDATE job_applications SET statut = ? WHERE id = ?', [$statut, $id]);
    }

    /** E-mails distincts de tous les candidats aux offres (diffusion emploi). */
    public static function allEmails(): array
    {
        return Database::run(
            "SELECT DISTINCT email FROM job_applications WHERE email IS NOT NULL AND email != '' ORDER BY email"
        )->fetchAll(\PDO::FETCH_COLUMN);
    }
}
