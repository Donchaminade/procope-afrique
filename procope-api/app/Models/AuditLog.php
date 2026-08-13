<?php

namespace App\Models;

use App\Core\Database;

/** Journal d'audit (page Surveillance) — lecture seule, alimenté par App\Services\Audit. */
final class AuditLog
{
    /** Libellés français des codes d'action journalisés. */
    public const ACTION_LABELS = [
        'login'                        => 'Connexion',
        'logout'                       => 'Déconnexion',
        'formation.create'             => 'Formation créée',
        'formation.update'             => 'Formation modifiée',
        'formation.open'               => 'Inscriptions ouvertes',
        'formation.close'              => 'Inscriptions fermées',
        'formation.delete'             => 'Formation supprimée',
        'formation.archive'            => 'Formation archivée',
        'formation.unarchive'          => 'Formation restaurée',
        'formation.announce'           => 'Annonce de formation envoyée',
        'inscription.status'           => "Statut d'inscription modifié",
        'inscription.validate_payment' => 'Paiement validé',
        'inscription.send_mail'        => 'E-mail manuel envoyé',
        'inscriptions.export'          => 'Export des inscriptions',
        'automations.update'           => 'Automatisations modifiées',
        'automations.test_mail'        => 'E-mail de test envoyé',
        'settings.update'              => 'Réglages modifiés',
        'user.create'                  => 'Utilisateur créé',
        'user.update'                  => 'Utilisateur modifié',
        'user.deactivate'              => 'Utilisateur désactivé',
        'contact_message.status'       => 'Statut de message modifié',
        'contact_message.delete'       => 'Message supprimé',
        'job_offer.archive'            => 'Offre archivée',
        'job_offer.unarchive'          => 'Offre restaurée',
        'job_application.status'       => 'Statut de candidature modifié',
        'job_applications.export'      => 'Export des candidatures',
        'job_applications.export_pdf'  => 'Export PDF des candidatures',
    ];

    /** Liste filtrée + paginée. $filters : user_id, action, from, to. */
    public static function search(array $filters, int $page = 1, int $perPage = 25): array
    {
        $conditions = [];
        $params = [];
        if (!empty($filters['user_id'])) {
            $conditions[] = 'a.user_id = ?';
            $params[] = (int) $filters['user_id'];
        }
        if (!empty($filters['action'])) {
            $conditions[] = 'a.action = ?';
            $params[] = $filters['action'];
        }
        if (!empty($filters['from'])) {
            $conditions[] = 'a.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $conditions[] = 'a.created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $total = (int) Database::run("SELECT COUNT(*) FROM audit_logs a $where", $params)->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::run(
            "SELECT a.*, u.name AS user_name, u.email AS user_email
               FROM audit_logs a
               LEFT JOIN users u ON u.id = a.user_id
             $where
             ORDER BY a.created_at DESC, a.id DESC
             LIMIT $perPage OFFSET $offset",
            $params
        )->fetchAll();

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / $perPage)];
    }

    /**
     * Historique d'une entité (ex. changements de statut d'une candidature),
     * du plus ancien au plus récent — lecture seule.
     */
    public static function forEntity(string $entity, int $entityId, array $actions = []): array
    {
        $sql = 'SELECT a.*, u.name AS user_name
                  FROM audit_logs a
                  LEFT JOIN users u ON u.id = a.user_id
                 WHERE a.entity = ? AND a.entity_id = ?';
        $params = [$entity, $entityId];
        if ($actions) {
            $sql .= ' AND a.action IN (' . implode(',', array_fill(0, count($actions), '?')) . ')';
            array_push($params, ...$actions);
        }
        return Database::run($sql . ' ORDER BY a.created_at ASC, a.id ASC', $params)->fetchAll();
    }

    /** Actions distinctes présentes dans le journal (select du filtre). */
    public static function actions(): array
    {
        return Database::run('SELECT DISTINCT action FROM audit_logs ORDER BY action')
            ->fetchAll(\PDO::FETCH_COLUMN);
    }
}
