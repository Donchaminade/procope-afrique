<?php

namespace App\Services;

use App\Core\Database;

/** Anti brute-force (login) et rate-limit générique (API publique). */
final class Security
{
    public const LOGIN_MAX_ATTEMPTS = 5;
    public const LOGIN_WINDOW_MIN   = 15;

    /** Minutes de blocage restantes pour ce couple ip/email, 0 si autorisé. */
    public static function loginLockedFor(string $ip, string $email): int
    {
        $row = Database::run(
            'SELECT COUNT(*) AS n, MAX(created_at) AS last
               FROM login_attempts
              WHERE success = 0
                AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
                AND (ip = ? OR email = ?)',
            [self::LOGIN_WINDOW_MIN, $ip, $email]
        )->fetch();

        if ((int) $row['n'] < self::LOGIN_MAX_ATTEMPTS) {
            return 0;
        }
        $elapsed = time() - strtotime($row['last']);
        return max(1, (int) ceil((self::LOGIN_WINDOW_MIN * 60 - $elapsed) / 60));
    }

    public static function recordLoginAttempt(string $ip, string $email, bool $success): void
    {
        Database::run(
            'INSERT INTO login_attempts (ip, email, success, created_at) VALUES (?, ?, ?, NOW())',
            [$ip, $email, $success ? 1 : 0]
        );
        if ($success) {
            // Purge les échecs du compte après une connexion réussie
            Database::run('DELETE FROM login_attempts WHERE email = ? AND success = 0', [$email]);
        }
        // Ménage périodique (~1 requête sur 50)
        if (random_int(1, 50) === 1) {
            Database::run("DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)");
        }
    }

    /** true si la limite est atteinte pour cette action/IP. */
    public static function rateLimited(string $ip, string $action, int $max, int $windowMinutes): bool
    {
        $count = (int) Database::run(
            'SELECT COUNT(*) FROM rate_limits
              WHERE ip = ? AND action = ? AND created_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)',
            [$ip, $action, $windowMinutes]
        )->fetchColumn();
        return $count >= $max;
    }

    public static function recordHit(string $ip, string $action): void
    {
        Database::run(
            'INSERT INTO rate_limits (ip, action, created_at) VALUES (?, ?, NOW())',
            [$ip, $action]
        );
        if (random_int(1, 50) === 1) {
            Database::run("DELETE FROM rate_limits WHERE created_at < DATE_SUB(NOW(), INTERVAL 2 DAY)");
        }
    }
}
