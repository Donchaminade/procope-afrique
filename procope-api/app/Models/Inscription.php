<?php

namespace App\Models;

use App\Core\Database;

final class Inscription
{
    public const STATUTS = ['preinscrit', 'preuve_recue', 'valide', 'refuse', 'liste_attente', 'paiement_partiel'];

    public const STATUT_LABELS = [
        'preinscrit'       => 'Préinscrit',
        'preuve_recue'     => 'Preuve reçue',
        'valide'           => 'Validé',
        'refuse'           => 'Refusé',
        'liste_attente'    => "Liste d'attente",
        'paiement_partiel' => 'Paiement partiel',
    ];

    /** Liste filtrée + paginée. $filters : formation_id, statut, q, from, to. */
    public static function search(array $filters, int $page = 1, int $perPage = 25): array
    {
        [$where, $params] = self::buildWhere($filters);

        $total = (int) Database::run("SELECT COUNT(*) FROM inscriptions i $where", $params)->fetchColumn();

        $offset = max(0, ($page - 1) * $perPage);
        $rows = Database::run(
            "SELECT i.*, f.titre AS formation_titre
               FROM inscriptions i
               JOIN formations f ON f.id = i.formation_id
             $where
             ORDER BY i.created_at DESC
             LIMIT $perPage OFFSET $offset",
            $params
        )->fetchAll();

        return ['rows' => $rows, 'total' => $total, 'page' => $page, 'pages' => (int) ceil($total / $perPage)];
    }

    /** Toutes les lignes filtrées (export). */
    public static function allFiltered(array $filters): array
    {
        [$where, $params] = self::buildWhere($filters);
        return Database::run(
            "SELECT i.*, f.titre AS formation_titre, f.prix AS formation_prix, f.devise AS formation_devise
               FROM inscriptions i
               JOIN formations f ON f.id = i.formation_id
             $where
             ORDER BY i.created_at ASC",
            $params
        )->fetchAll();
    }

    private static function buildWhere(array $filters): array
    {
        $conditions = [];
        $params = [];
        if (!empty($filters['formation_id'])) {
            $conditions[] = 'i.formation_id = ?';
            $params[] = (int) $filters['formation_id'];
        }
        if (!empty($filters['statut']) && in_array($filters['statut'], self::STATUTS, true)) {
            $conditions[] = 'i.statut = ?';
            $params[] = $filters['statut'];
        }
        if (!empty($filters['q'])) {
            $conditions[] = '(i.full_name LIKE ? OR i.phone LIKE ? OR i.email LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            array_push($params, $like, $like, $like);
        }
        if (!empty($filters['from'])) {
            $conditions[] = 'i.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $conditions[] = 'i.created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }
        return [$conditions ? 'WHERE ' . implode(' AND ', $conditions) : '', $params];
    }

    public static function find(int $id): ?array
    {
        return Database::run(
            'SELECT i.*, f.titre AS formation_titre, f.prix AS formation_prix, f.devise AS formation_devise,
                    u.name AS validator_name
               FROM inscriptions i
               JOIN formations f ON f.id = i.formation_id
               LEFT JOIN users u ON u.id = i.validated_by
              WHERE i.id = ?',
            [$id]
        )->fetch() ?: null;
    }

    public static function existsForFormation(int $formationId, string $phone, ?string $email): bool
    {
        $sql = 'SELECT COUNT(*) FROM inscriptions WHERE formation_id = ? AND (phone = ?';
        $params = [$formationId, $phone];
        if ($email) {
            $sql .= ' OR (email IS NOT NULL AND email = ?)';
            $params[] = $email;
        }
        $sql .= ')';
        return (int) Database::run($sql, $params)->fetchColumn() > 0;
    }

    public static function create(array $d): int
    {
        Database::run(
            'INSERT INTO inscriptions
                (uuid, formation_id, full_name, gender, phone, email, city, professional_status,
                 is_entrepreneur, company_name, sector, motivation, modules, payment_method,
                 payment_type, amount_declared,
                 payment_proof_path, payment_proof_mime, payment_proof_name,
                 acquisition_source, acquisition_other, consent_image, statut, ip, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $d['uuid'], $d['formation_id'], $d['full_name'], $d['gender'], $d['phone'],
                $d['email'], $d['city'], $d['professional_status'], $d['is_entrepreneur'],
                $d['company_name'], $d['sector'], $d['motivation'], $d['modules'],
                $d['payment_method'], $d['payment_type'] ?? 'total', $d['amount_declared'] ?? null,
                $d['payment_proof_path'], $d['payment_proof_mime'],
                $d['payment_proof_name'], $d['acquisition_source'], $d['acquisition_other'],
                $d['consent_image'], $d['statut'], $d['ip'], $d['user_agent'],
            ]
        );
        return (int) Database::pdo()->lastInsertId();
    }

    public static function updateStatus(int $id, string $statut, int $userId): void
    {
        Database::run(
            'UPDATE inscriptions SET statut = ?, validated_by = ?, validated_at = NOW() WHERE id = ?',
            // id 0 = compte racine (.env), absent de users : NULL pour respecter la FK
            [$statut, $userId > 0 ? $userId : null, $id]
        );
    }

    /** Validation du paiement par l'admin : montant reçu + statut résultant. */
    public static function recordPayment(int $id, float $amountReceived, string $statut, int $userId): void
    {
        Database::run(
            'UPDATE inscriptions
                SET amount_received = ?, statut = ?, validated_by = ?, validated_at = NOW()
              WHERE id = ?',
            [$amountReceived, $statut, $userId > 0 ? $userId : null, $id]
        );
    }

    /** Inscriptions par jour sur les N derniers jours (dashboard, lecture seule). */
    public static function countPerDay(int $days = 14, ?int $formationId = null): array
    {
        $days = max(1, min(60, $days));
        $sql = 'SELECT DATE(created_at) AS d, COUNT(*) AS n FROM inscriptions WHERE created_at >= ?';
        $params = [date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'))];
        if ($formationId) {
            $sql .= ' AND formation_id = ?';
            $params[] = $formationId;
        }
        $sql .= ' GROUP BY DATE(created_at)';

        $counts = [];
        foreach (Database::run($sql, $params)->fetchAll() as $row) {
            $counts[$row['d']] = (int) $row['n'];
        }

        $out = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $out[] = ['date' => $date, 'n' => $counts[$date] ?? 0];
        }
        return $out;
    }

    /** Répartition hommes / femmes (dashboard, lecture seule). */
    public static function countByGender(?int $formationId = null): array
    {
        $sql = 'SELECT gender, COUNT(*) AS n FROM inscriptions';
        $params = [];
        if ($formationId) {
            $sql .= ' WHERE formation_id = ?';
            $params[] = $formationId;
        }
        $sql .= ' GROUP BY gender';

        $result = ['M' => 0, 'F' => 0];
        foreach (Database::run($sql, $params)->fetchAll() as $row) {
            if (isset($result[$row['gender']])) {
                $result[$row['gender']] = (int) $row['n'];
            }
        }
        return $result;
    }

    /** Chiffre d'affaires encaissé (somme des montants vérifiés), toutes formations. */
    public static function totalRevenue(): float
    {
        return (float) Database::run(
            'SELECT COALESCE(SUM(amount_received), 0) FROM inscriptions'
        )->fetchColumn();
    }

    /** Chiffre d'affaires encaissé pour une formation donnée. */
    public static function revenueForFormation(int $formationId): float
    {
        return (float) Database::run(
            'SELECT COALESCE(SUM(amount_received), 0) FROM inscriptions WHERE formation_id = ?',
            [$formationId]
        )->fetchColumn();
    }

    /**
     * E-mails distincts des anciens participants (inscriptions validées ou en
     * paiement partiel) des autres formations — destinataires de l'annonce
     * d'une nouvelle formation.
     */
    public static function pastParticipantEmails(int $excludeFormationId): array
    {
        return Database::run(
            "SELECT DISTINCT email FROM inscriptions
              WHERE formation_id != ?
                AND email IS NOT NULL
                AND statut IN ('valide', 'paiement_partiel')
              ORDER BY email",
            [$excludeFormationId]
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    /**
     * E-mails distincts de toutes les inscriptions (toutes formations, tous
     * statuts SAUF « refuse ») : base de diffusion des e-mails « offres
     * d'emploi ». Les refusés sont exclus — ils n'ont pas rejoint la
     * communauté PROCOPE et ne s'attendent pas à recevoir nos annonces.
     */
    public static function allEmails(): array
    {
        return Database::run(
            "SELECT DISTINCT email FROM inscriptions
              WHERE email IS NOT NULL AND email != '' AND statut != 'refuse'
              ORDER BY email"
        )->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function countByStatut(?int $formationId = null): array
    {
        $sql = 'SELECT statut, COUNT(*) AS n FROM inscriptions';
        $params = [];
        if ($formationId) {
            $sql .= ' WHERE formation_id = ?';
            $params[] = $formationId;
        }
        $sql .= ' GROUP BY statut';
        $result = array_fill_keys(self::STATUTS, 0);
        foreach (Database::run($sql, $params)->fetchAll() as $row) {
            $result[$row['statut']] = (int) $row['n'];
        }
        return $result;
    }
}
