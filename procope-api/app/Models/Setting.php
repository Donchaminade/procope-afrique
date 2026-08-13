<?php

namespace App\Models;

use App\Core\Database;
use App\Core\Env;

/** Réglages en base ; retombe sur .env si la clé n'est pas définie en base. */
final class Setting
{
    private static ?array $cache = null;

    /**
     * Clés éditables dans l'admin, avec leur variable .env de secours
     * (null = pas de secours .env, Setting::get retourne le défaut).
     */
    public const KEYS = [
        // La configuration SMTP (hôte, port, identifiants, expéditeur) vit
        // désormais UNIQUEMENT dans le .env — voir App\Services\Mailer.
        'mail_enabled'     => 'MAIL_ENABLED',
        'mail_notify'      => 'MAIL_NOTIFY',
        // Informations publiques du site
        'site_email'       => null,
        'site_phone'       => null,
        'site_whatsapp'    => null,
        'site_address'     => null,
        'social_facebook'  => null,
        'social_instagram' => null,
        'social_tiktok'    => null,
        'social_linkedin'  => null,
        'social_youtube'   => null,
    ];

    /** Clés éditables sur la page Réglages (mail_enabled est géré sur la page Automatisations). */
    public const SETTINGS_FORM_KEYS = [
        'mail_notify',
        'site_email', 'site_phone', 'site_whatsapp', 'site_address',
        'social_facebook', 'social_instagram', 'social_tiktok', 'social_linkedin', 'social_youtube',
    ];

    public static function all(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (Database::run('SELECT skey, svalue FROM settings')->fetchAll() as $row) {
                self::$cache[$row['skey']] = $row['svalue'];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = self::all();
        if (isset($all[$key]) && $all[$key] !== '') {
            return $all[$key];
        }
        $envKey = self::KEYS[$key] ?? null;
        return $envKey ? Env::get($envKey, $default) : $default;
    }

    public static function set(string $key, ?string $value): void
    {
        Database::run(
            'INSERT INTO settings (skey, svalue) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)',
            [$key, $value]
        );
        self::$cache = null;
    }
}
