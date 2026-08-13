<?php

namespace App\Services;

use App\Core\Database;

final class Audit
{
    public static function log(string $action, string $entity = '', ?int $entityId = null, array $meta = []): void
    {
        $user = Auth::user();
        Database::run(
            'INSERT INTO audit_logs (user_id, action, entity, entity_id, ip, meta, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())',
            [
                // Le compte racine (.env) a l'id 0 : journalisé en NULL (affiché « Système »)
                !empty($user['id']) ? (int) $user['id'] : null,
                $action,
                $entity,
                $entityId,
                $_SERVER['REMOTE_ADDR'] ?? '',
                $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null,
            ]
        );
        // Rétention 30 jours : ménage périodique (~1 appel sur 50)
        if (random_int(1, 50) === 1) {
            Database::run('DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
        }
    }
}
