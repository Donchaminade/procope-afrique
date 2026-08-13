<?php

namespace App\Models;

use App\Core\Database;

final class MailLog
{
    /** Durée de conservation du journal (jours). */
    public const RETENTION_DAYS = 14;

    public static function record(?int $inscriptionId, string $type, string $to, bool $sent, ?string $error = null): void
    {
        Database::run(
            'INSERT INTO mail_logs (inscription_id, type, to_email, status, error) VALUES (?, ?, ?, ?, ?)',
            [$inscriptionId, $type, $to, $sent ? 'sent' : 'failed', $error]
        );
        // Rétention 14 jours : ménage périodique (~1 appel sur 50), même pattern qu'Audit::log
        if (random_int(1, 50) === 1) {
            self::purgeOld();
        }
    }

    /** Supprime les entrées plus vieilles que RETENTION_DAYS. Retourne le nombre supprimé. */
    public static function purgeOld(): int
    {
        return Database::run(
            'DELETE FROM mail_logs WHERE sent_at < DATE_SUB(NOW(), INTERVAL ' . self::RETENTION_DAYS . ' DAY)'
        )->rowCount();
    }

    /** Supprime les envois en échec (optionnellement d'un seul type). Retourne le nombre supprimé. */
    public static function deleteFailed(?string $type = null): int
    {
        if ($type !== null && $type !== '') {
            return Database::run("DELETE FROM mail_logs WHERE status = 'failed' AND type = ?", [$type])->rowCount();
        }
        return Database::run("DELETE FROM mail_logs WHERE status = 'failed'")->rowCount();
    }

    public static function forInscription(int $inscriptionId): array
    {
        return Database::run(
            'SELECT * FROM mail_logs WHERE inscription_id = ? ORDER BY sent_at DESC',
            [$inscriptionId]
        )->fetchAll();
    }

    /** Nombre total de mails journalisés (compteur de l'onglet Journal). */
    public static function countAll(): int
    {
        return (int) Database::run('SELECT COUNT(*) FROM mail_logs')->fetchColumn();
    }

    /** Types distincts présents dans le journal (options du filtre). */
    public static function types(): array
    {
        return Database::run('SELECT DISTINCT type FROM mail_logs ORDER BY type')
            ->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * Journal paginé avec filtres (status: sent|failed, type).
     * Retourne rows/total/page/pages.
     */
    public static function paginate(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $where = [];
        $params = [];
        if (in_array($filters['status'] ?? '', ['sent', 'failed'], true)) {
            $where[] = 'status = ?';
            $params[] = $filters['status'];
        }
        if (($filters['type'] ?? '') !== '') {
            $where[] = 'type = ?';
            $params[] = $filters['type'];
        }
        $sql = $where ? ' WHERE ' . implode(' AND ', $where) : '';

        $total = (int) Database::run('SELECT COUNT(*) FROM mail_logs' . $sql, $params)->fetchColumn();
        $perPage = max(1, $perPage);
        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::run(
            "SELECT * FROM mail_logs$sql ORDER BY sent_at DESC, id DESC LIMIT $perPage OFFSET $offset",
            $params
        )->fetchAll();

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / $perPage)];
    }
}
