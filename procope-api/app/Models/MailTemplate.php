<?php

namespace App\Models;

use App\Core\Database;

/**
 * Overrides des modèles d'e-mail (table mail_templates).
 * Absent de la table = le rendu vient du fichier templates/mail/<name>.php.
 */
final class MailTemplate
{
    /** @var array<string, array|null> cache par requête (find appelé pour sujet + body) */
    private static array $cache = [];

    public static function find(string $name): ?array
    {
        if (!array_key_exists($name, self::$cache)) {
            self::$cache[$name] = Database::run(
                'SELECT * FROM mail_templates WHERE name = ?',
                [$name]
            )->fetch() ?: null;
        }
        return self::$cache[$name];
    }

    /** Tous les overrides, indexés par nom (cartes de l'onglet Modèles). */
    public static function allByName(): array
    {
        $rows = Database::run('SELECT * FROM mail_templates')->fetchAll();
        $byName = [];
        foreach ($rows as $row) {
            $byName[$row['name']] = $row;
        }
        return $byName;
    }

    /** Crée ou met à jour l'override d'un template. */
    public static function save(string $name, string $subject, string $body): void
    {
        Database::run(
            'INSERT INTO mail_templates (name, subject, body) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE subject = VALUES(subject), body = VALUES(body)',
            [$name, $subject, $body]
        );
        unset(self::$cache[$name]);
    }

    /** Supprime l'override (retour au rendu fichier). Retourne true si une ligne existait. */
    public static function reset(string $name): bool
    {
        $deleted = Database::run('DELETE FROM mail_templates WHERE name = ?', [$name])->rowCount() > 0;
        unset(self::$cache[$name]);
        return $deleted;
    }
}
