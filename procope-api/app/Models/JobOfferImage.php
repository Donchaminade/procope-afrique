<?php

namespace App\Models;

use App\Core\Database;

/**
 * Affiches (images) d'une offre d'emploi : plusieurs par offre, une seule
 * principale (is_main = 1). Fichiers publics dans public/uploads/offres/.
 */
final class JobOfferImage
{
    /** Images d'une offre, principale d'abord puis ordre d'ajout. */
    public static function allForOffer(int $offerId): array
    {
        return Database::run(
            'SELECT * FROM job_offer_images WHERE offer_id = ?
              ORDER BY is_main DESC, sort_order ASC, id ASC',
            [$offerId]
        )->fetchAll();
    }

    /** Image principale d'une offre (null si aucune image). */
    public static function mainForOffer(int $offerId): ?array
    {
        return Database::run(
            'SELECT * FROM job_offer_images WHERE offer_id = ?
              ORDER BY is_main DESC, sort_order ASC, id ASC LIMIT 1',
            [$offerId]
        )->fetch() ?: null;
    }

    public static function find(int $id): ?array
    {
        return Database::run('SELECT * FROM job_offer_images WHERE id = ?', [$id])->fetch() ?: null;
    }

    public static function create(int $offerId, string $path, string $mime, bool $isMain = false): int
    {
        $sort = (int) Database::run(
            'SELECT COALESCE(MAX(sort_order), 0) + 1 FROM job_offer_images WHERE offer_id = ?',
            [$offerId]
        )->fetchColumn();
        Database::run(
            'INSERT INTO job_offer_images (offer_id, path, mime, is_main, sort_order) VALUES (?, ?, ?, ?, ?)',
            [$offerId, $path, $mime, $isMain ? 1 : 0, $sort]
        );
        return (int) Database::pdo()->lastInsertId();
    }

    /** Désigne l'image principale (exclusif : les autres repassent à 0). */
    public static function setMain(int $offerId, int $imageId): void
    {
        Database::run('UPDATE job_offer_images SET is_main = 0 WHERE offer_id = ?', [$offerId]);
        Database::run('UPDATE job_offer_images SET is_main = 1 WHERE id = ? AND offer_id = ?', [$imageId, $offerId]);
    }

    /** Garantit qu'une principale existe : sinon la première le devient. */
    public static function ensureMain(int $offerId): void
    {
        $hasMain = (int) Database::run(
            'SELECT COUNT(*) FROM job_offer_images WHERE offer_id = ? AND is_main = 1',
            [$offerId]
        )->fetchColumn();
        if ($hasMain > 0) {
            return;
        }
        $first = Database::run(
            'SELECT id FROM job_offer_images WHERE offer_id = ? ORDER BY sort_order ASC, id ASC LIMIT 1',
            [$offerId]
        )->fetchColumn();
        if ($first) {
            Database::run('UPDATE job_offer_images SET is_main = 1 WHERE id = ?', [(int) $first]);
        }
    }

    public static function delete(int $id): void
    {
        Database::run('DELETE FROM job_offer_images WHERE id = ?', [$id]);
    }
}
