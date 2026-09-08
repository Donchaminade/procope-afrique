<?php

namespace App\Models;

use App\Core\Database;

final class Formation
{
    public static function all(): array
    {
        return Database::run(
            'SELECT f.*, (SELECT COUNT(*) FROM inscriptions i WHERE i.formation_id = f.id) AS nb_inscriptions
               FROM formations f WHERE f.archived_at IS NULL ORDER BY f.created_at DESC'
        )->fetchAll();
    }

    /** Liste paginée des formations actives (rows/total/page/pages). */
    public static function paginate(int $page = 1, int $perPage = 25): array
    {
        $total = (int) Database::run('SELECT COUNT(*) FROM formations WHERE archived_at IS NULL')->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::run(
            "SELECT f.*, (SELECT COUNT(*) FROM inscriptions i WHERE i.formation_id = f.id) AS nb_inscriptions
               FROM formations f WHERE f.archived_at IS NULL ORDER BY f.created_at DESC
              LIMIT $perPage OFFSET $offset"
        )->fetchAll();
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / $perPage)];
    }

    /** Liste paginée des formations archivées (rows/total/page/pages). */
    public static function archivedPaginate(int $page = 1, int $perPage = 25): array
    {
        $total = (int) Database::run('SELECT COUNT(*) FROM formations WHERE archived_at IS NOT NULL')->fetchColumn();
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::run(
            "SELECT f.*, (SELECT COUNT(*) FROM inscriptions i WHERE i.formation_id = f.id) AS nb_inscriptions
               FROM formations f WHERE f.archived_at IS NOT NULL ORDER BY f.archived_at DESC
              LIMIT $perPage OFFSET $offset"
        )->fetchAll();
        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / $perPage)];
    }

    /** Nombre de formations archivées (carte « Formations bouclées » du dashboard). */
    public static function countArchived(): int
    {
        return (int) Database::run('SELECT COUNT(*) FROM formations WHERE archived_at IS NOT NULL')->fetchColumn();
    }

    /** Toutes les formations, archivées comprises (select des filtres inscriptions). */
    public static function allForFilter(): array
    {
        return Database::run(
            'SELECT id, titre, archived_at FROM formations ORDER BY archived_at IS NOT NULL, created_at DESC'
        )->fetchAll();
    }

    /**
     * Formations pour le select des galeries : titre + premier créneau
     * (année / mois à préremplir).
     */
    public static function allForGallerySelect(): array
    {
        return Database::run(
            'SELECT f.id, f.titre, f.archived_at,
                    (SELECT MIN(s.starts_at) FROM formation_slots s WHERE s.formation_id = f.id) AS first_starts_at
               FROM formations f
              ORDER BY f.archived_at IS NOT NULL, f.created_at DESC'
        )->fetchAll();
    }

    /** Formations archivées, avec leur nombre d'inscrits (page Archives). */
    public static function archived(): array
    {
        return Database::run(
            'SELECT f.*, (SELECT COUNT(*) FROM inscriptions i WHERE i.formation_id = f.id) AS nb_inscriptions
               FROM formations f WHERE f.archived_at IS NOT NULL ORDER BY f.archived_at DESC'
        )->fetchAll();
    }

    /** Archive la formation : horodatage + fermeture des inscriptions. */
    public static function archive(int $id): void
    {
        Database::run(
            'UPDATE formations SET archived_at = NOW(), inscriptions_ouvertes = 0 WHERE id = ?',
            [$id]
        );
    }

    /** Restaure la formation (sans rouvrir les inscriptions automatiquement). */
    public static function unarchive(int $id): void
    {
        Database::run('UPDATE formations SET archived_at = NULL WHERE id = ?', [$id]);
    }

    public static function find(int $id): ?array
    {
        return Database::run('SELECT * FROM formations WHERE id = ?', [$id])->fetch() ?: null;
    }

    public static function slots(int $formationId): array
    {
        return Database::run(
            'SELECT * FROM formation_slots WHERE formation_id = ? ORDER BY sort_order, starts_at',
            [$formationId]
        )->fetchAll();
    }

    /**
     * Toutes les formations ouvertes (non archivées, dans la fenêtre de dates).
     * Plus récente d'abord. Le formulaire public continue de n'en prendre qu'une.
     */
    public static function openAll(): array
    {
        return Database::run(
            'SELECT * FROM formations
              WHERE archived_at IS NULL
                AND inscriptions_ouvertes = 1
                AND (ouverte_du IS NULL OR ouverte_du <= NOW())
                AND (ouverte_au IS NULL OR ouverte_au >= NOW())
              ORDER BY created_at DESC'
        )->fetchAll();
    }

    /**
     * Formation active pour le formulaire public :
     * ouverte + dans la fenêtre de dates éventuelle. La plus récente d'abord.
     */
    public static function active(): ?array
    {
        $open = self::openAll();
        return $open[0] ?? null;
    }

    /** Dernière formation non archivée (même fermée) : pour le message de fermeture. */
    public static function latest(): ?array
    {
        return Database::run(
            'SELECT * FROM formations WHERE archived_at IS NULL ORDER BY created_at DESC LIMIT 1'
        )->fetch() ?: null;
    }

    public static function countInscriptions(int $formationId, ?array $statuts = null): int
    {
        if ($statuts) {
            $in = implode(',', array_fill(0, count($statuts), '?'));
            return (int) Database::run(
                "SELECT COUNT(*) FROM inscriptions WHERE formation_id = ? AND statut IN ($in)",
                array_merge([$formationId], $statuts)
            )->fetchColumn();
        }
        return (int) Database::run(
            'SELECT COUNT(*) FROM inscriptions WHERE formation_id = ?',
            [$formationId]
        )->fetchColumn();
    }

    public static function create(array $data): int
    {
        Database::run(
            'INSERT INTO formations
                (titre, slug, intro, programme, prix, devise, lieu, places_max,
                 inscriptions_ouvertes, ouverte_du, ouverte_au, message_fermeture, contact_phone)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['titre'], $data['slug'], $data['intro'], $data['programme'],
                $data['prix'], $data['devise'], $data['lieu'], $data['places_max'],
                $data['inscriptions_ouvertes'], $data['ouverte_du'], $data['ouverte_au'],
                $data['message_fermeture'], $data['contact_phone'],
            ]
        );
        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $data): void
    {
        Database::run(
            'UPDATE formations SET
                titre = ?, slug = ?, intro = ?, programme = ?, prix = ?, devise = ?, lieu = ?,
                places_max = ?, inscriptions_ouvertes = ?, ouverte_du = ?, ouverte_au = ?,
                message_fermeture = ?, contact_phone = ?
             WHERE id = ?',
            [
                $data['titre'], $data['slug'], $data['intro'], $data['programme'],
                $data['prix'], $data['devise'], $data['lieu'], $data['places_max'],
                $data['inscriptions_ouvertes'], $data['ouverte_du'], $data['ouverte_au'],
                $data['message_fermeture'], $data['contact_phone'], $id,
            ]
        );
    }

    /** Enregistre (ou efface) l'affiche publique de la formation. */
    public static function setAffiche(int $id, ?string $path, ?string $mime): void
    {
        Database::run('UPDATE formations SET affiche_path = ?, affiche_mime = ? WHERE id = ?', [$path, $mime, $id]);
    }

    public static function setOpen(int $id, bool $open): void
    {
        Database::run('UPDATE formations SET inscriptions_ouvertes = ? WHERE id = ?', [$open ? 1 : 0, $id]);
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM formations WHERE id = ?', [$id]);
    }

    /** Remplace tous les créneaux de la formation. */
    public static function replaceSlots(int $formationId, array $slots): void
    {
        Database::run('DELETE FROM formation_slots WHERE formation_id = ?', [$formationId]);
        $order = 1;
        foreach ($slots as $slot) {
            Database::run(
                'INSERT INTO formation_slots (formation_id, label, starts_at, ends_at, sort_order)
                 VALUES (?, ?, ?, ?, ?)',
                [$formationId, $slot['label'], $slot['starts_at'], $slot['ends_at'], $order++]
            );
        }
    }
}
