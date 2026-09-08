<?php

namespace App\Models;

use App\Core\Database;

/** Réponses envoyées depuis l'admin à un message de contact (plusieurs possibles). */
final class ContactReply
{
    public static function allForMessage(int $messageId): array
    {
        return Database::run(
            'SELECT r.*, u.name AS user_name
               FROM contact_replies r
               LEFT JOIN users u ON u.id = r.user_id
              WHERE r.message_id = ?
              ORDER BY r.sent_at ASC, r.id ASC',
            [$messageId]
        )->fetchAll();
    }

    public static function create(array $d): int
    {
        Database::run(
            'INSERT INTO contact_replies (message_id, subject, body, user_id) VALUES (?, ?, ?, ?)',
            [$d['message_id'], $d['subject'], $d['body'], $d['user_id']]
        );
        return (int) Database::pdo()->lastInsertId();
    }
}
