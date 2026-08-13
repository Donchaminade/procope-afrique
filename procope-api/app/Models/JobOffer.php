<?php

namespace App\Models;

use App\Core\Database;

final class JobOffer
{
    public const CONTRACT_TYPES = ['CDI', 'CDD', 'Stage', 'Freelance', 'Autre'];

    /** Jours avant clôture auxquels le rappel automatique est envoyé. */
    public const REMINDER_DAYS = 5;

    /** Liste paginée (admin, offres non archivées) avec compteur de candidatures. */
    public static function paginate(int $page = 1, int $perPage = 25): array
    {
        $total = (int) Database::run('SELECT COUNT(*) FROM job_offers WHERE archived_at IS NULL')->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::run(
            "SELECT o.*, (SELECT COUNT(*) FROM job_applications a WHERE a.offer_id = o.id) AS nb_candidatures
               FROM job_offers o WHERE o.archived_at IS NULL ORDER BY o.created_at DESC
              LIMIT $perPage OFFSET $offset"
        )->fetchAll();
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / $perPage)];
    }

    /** Offres publiées, non clôturées et non archivées (bouton « Exécuter » + API publique). */
    public static function published(): array
    {
        return Database::run(
            'SELECT * FROM job_offers
              WHERE is_published = 1 AND archived_at IS NULL AND closes_at > NOW()
              ORDER BY closes_at ASC'
        )->fetchAll();
    }

    /** Toutes les offres (archivées comprises) pour le select des filtres candidatures. */
    public static function allForFilter(): array
    {
        return Database::run(
            'SELECT id, title, archived_at FROM job_offers ORDER BY archived_at IS NOT NULL, created_at DESC'
        )->fetchAll();
    }

    /** Nombre d'offres publiées en cours (non clôturées, non archivées) — carte du dashboard. */
    public static function countPublishedOpen(): int
    {
        return (int) Database::run(
            'SELECT COUNT(*) FROM job_offers WHERE is_published = 1 AND archived_at IS NULL AND closes_at > NOW()'
        )->fetchColumn();
    }

    /**
     * « Offre en cours » : offre publiée non archivée qui clôture le plus
     * prochainement, avec son nombre de candidatures (carte du dashboard).
     */
    public static function soonestClosing(): ?array
    {
        return Database::run(
            'SELECT o.*, (SELECT COUNT(*) FROM job_applications a WHERE a.offer_id = o.id) AS nb_candidatures
               FROM job_offers o
              WHERE o.is_published = 1 AND o.archived_at IS NULL AND o.closes_at > NOW()
              ORDER BY o.closes_at ASC LIMIT 1'
        )->fetch() ?: null;
    }

    /** Offres archivées, avec leur nombre de candidatures (page Archives). */
    public static function archived(): array
    {
        return Database::run(
            'SELECT o.*, (SELECT COUNT(*) FROM job_applications a WHERE a.offer_id = o.id) AS nb_candidatures
               FROM job_offers o WHERE o.archived_at IS NOT NULL ORDER BY o.archived_at DESC'
        )->fetchAll();
    }

    /** Archive l'offre : horodatage + dépublication. */
    public static function archive(int $id): void
    {
        Database::run(
            'UPDATE job_offers SET archived_at = NOW(), is_published = 0 WHERE id = ?',
            [$id]
        );
    }

    /** Restaure l'offre (sans la republier automatiquement). */
    public static function unarchive(int $id): void
    {
        Database::run('UPDATE job_offers SET archived_at = NULL WHERE id = ?', [$id]);
    }

    public static function find(int $id): ?array
    {
        return Database::run('SELECT * FROM job_offers WHERE id = ?', [$id])->fetch() ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        return Database::run('SELECT * FROM job_offers WHERE slug = ?', [$slug])->fetch() ?: null;
    }

    public static function create(array $d): int
    {
        Database::run(
            'INSERT INTO job_offers (title, slug, description, location, contract_type, salary, closes_at, is_published)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $d['title'], $d['slug'], $d['description'], $d['location'],
                $d['contract_type'], $d['salary'], $d['closes_at'], $d['is_published'],
            ]
        );
        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $d): void
    {
        Database::run(
            'UPDATE job_offers SET title = ?, slug = ?, description = ?, location = ?,
                    contract_type = ?, salary = ?, closes_at = ?, is_published = ?
              WHERE id = ?',
            [
                $d['title'], $d['slug'], $d['description'], $d['location'],
                $d['contract_type'], $d['salary'], $d['closes_at'], $d['is_published'], $id,
            ]
        );
    }

    public static function setPublished(int $id, bool $published): void
    {
        Database::run('UPDATE job_offers SET is_published = ? WHERE id = ?', [$published ? 1 : 0, $id]);
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM job_offers WHERE id = ?', [$id]);
    }

    public static function countApplications(int $offerId): int
    {
        return (int) Database::run(
            'SELECT COUNT(*) FROM job_applications WHERE offer_id = ?',
            [$offerId]
        )->fetchColumn();
    }

    /**
     * Offres dont le rappel J-5 est dû : publiées, non clôturées, jamais
     * rappelées, et dont la clôture arrive dans REMINDER_DAYS jours ou moins.
     */
    public static function dueForReminder(): array
    {
        return Database::run(
            'SELECT * FROM job_offers
              WHERE is_published = 1
                AND archived_at IS NULL
                AND reminder_sent_at IS NULL
                AND closes_at > NOW()
                AND closes_at <= DATE_ADD(NOW(), INTERVAL ' . self::REMINDER_DAYS . ' DAY)
              ORDER BY closes_at ASC'
        )->fetchAll();
    }

    /** Marque le rappel comme envoyé (anti-doublon). */
    public static function markReminderSent(int $id): void
    {
        Database::run('UPDATE job_offers SET reminder_sent_at = NOW() WHERE id = ?', [$id]);
    }

    /** Réarme le rappel (offre prolongée au-delà de la fenêtre J-5). */
    public static function resetReminder(int $id): void
    {
        Database::run('UPDATE job_offers SET reminder_sent_at = NULL WHERE id = ?', [$id]);
    }
}
