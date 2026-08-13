<?php

/**
 * Crée (ou met à jour) un utilisateur admin en ligne de commande.
 *
 * Usage :
 *   php bin/create-admin.php email@exemple.com "Nom Prénom" super_admin
 *   (le mot de passe est demandé de façon interactive, ou passé en 4e argument)
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Ce script s'exécute uniquement en CLI.\n");
}

$root = dirname(__DIR__);
spl_autoload_register(function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) {
        $file = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

use App\Core\Database;
use App\Core\Env;

Env::load($root . '/.env');

$email = $argv[1] ?? null;
$name  = $argv[2] ?? null;
$role  = $argv[3] ?? 'super_admin';

if (!$email || !$name || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Usage : php bin/create-admin.php email \"Nom\" [super_admin|admin|operator] [mot_de_passe]\n");
}
if (!in_array($role, ['super_admin', 'admin', 'operator'], true)) {
    exit("Rôle invalide : $role\n");
}

$password = $argv[4] ?? null;
if (!$password) {
    echo "Mot de passe (min 10 caractères) : ";
    $password = trim((string) fgets(STDIN));
}
if (strlen($password) < 10) {
    exit("Mot de passe trop court (min 10 caractères).\n");
}

$hash = password_hash($password, PASSWORD_ARGON2ID);

Database::run(
    'INSERT INTO users (email, password_hash, name, role, is_active)
     VALUES (?, ?, ?, ?, 1)
     ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash), name = VALUES(name), role = VALUES(role), is_active = 1',
    [$email, $hash, $name, $role]
);

echo "OK : utilisateur « $email » ($role) créé/mis à jour.\n";
