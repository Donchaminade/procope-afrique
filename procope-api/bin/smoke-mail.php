<?php
// Smoke test local : rend les 3 templates mail sans envoyer.
$root = dirname(__DIR__);
spl_autoload_register(function (string $class) use ($root): void {
    if (str_starts_with($class, 'App\\')) {
        $file = $root . '/app/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
require $root . '/app/helpers.php';

use App\Services\Mailer;

$formation = [
    'id' => 1,
    'titre' => 'Formation PROCOPE 2027 — 1ère vague',
    'lieu' => 'Salle Melli FERA, Adidoadin',
    'prix' => 25000,
    'contact_phone' => '+228 96 45 76 95',
];
$slots = [
    ['label' => 'Samedi 30 janvier 2027', 'starts_at' => '2027-01-30 08:30:00', 'ends_at' => '2027-01-30 17:00:00'],
];

foreach ([
    'confirmation' => ['full_name' => 'Test User', 'formation' => $formation, 'slots' => $slots, 'has_proof' => true],
    'alert' => ['inscription_id' => 1, 'full_name' => 'Test User', 'phone' => '+228 90 00 00 00', 'email' => 't@t.tg', 'formation' => $formation, 'has_proof' => false],
    'status' => ['inscription' => ['full_name' => 'Test User'], 'formation' => $formation, 'statut' => 'valide', 'slots' => $slots],
] as $name => $data) {
    $html = Mailer::template($name, $data);
    echo str_pad($name, 14) . (strlen($html) > 500 && str_contains($html, 'PROCOPE') ? 'OK (' . strlen($html) . " octets)\n" : "SUSPECT\n");
}
