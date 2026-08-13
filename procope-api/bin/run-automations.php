<?php

declare(strict_types=1);

/**
 * Tâche planifiée des automatisations (CLI uniquement).
 *
 * Traite les rappels J-5 des offres d'emploi publiées (auto_offre_rappel) :
 * respecte le master switch mail_enabled + le toggle, marque reminder_sent_at
 * avant envoi (anti-doublon) et journalise dans mail_logs.
 *
 * Usage local   : php bin/run-automations.php
 * Cron Hostinger (toutes les heures) :
 *   0 * * * * php /home/USER/domains/DOMAINE/procope-api/bin/run-automations.php >> /home/USER/procope-cron.log 2>&1
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Script CLI uniquement.\n");
}

use App\Core\Env;
use App\Services\JobNotifier;

$root = dirname(__DIR__);

if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
} else {
    spl_autoload_register(function (string $class) use ($root): void {
        if (str_starts_with($class, 'App\\')) {
            $file = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
}
require $root . '/app/helpers.php';

Env::load($root . '/.env');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', $root . '/storage/logs/php-error.log');
date_default_timezone_set('Africa/Lome');

echo '[' . date('Y-m-d H:i:s') . "] run-automations : démarrage\n";

try {
    $processed = JobNotifier::processDueReminders();
} catch (Throwable $e) {
    echo 'ERREUR : ' . $e->getMessage() . "\n";
    exit(1);
}

if (!$processed) {
    echo "Aucun rappel dû (aucune offre publiée à J-5, rappels déjà envoyés, toggle désactivé ou verrou actif).\n";
} else {
    foreach ($processed as $title => [$sent, $total]) {
        echo "Rappel envoyé — « $title » : $sent/$total destinataire(s)\n";
    }
}
echo '[' . date('Y-m-d H:i:s') . "] run-automations : terminé\n";
