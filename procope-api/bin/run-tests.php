<?php

/**
 * Suite de tests HTTP + unitaires (sans PHPUnit, zéro dépendance nouvelle).
 *
 * Usage : php bin/run-tests.php
 *
 * Prérequis :
 *   - serveur API lancé : php -S 127.0.0.1:8088 -t public bin/router.php
 *   - base procope_test à jour (bin/smoke-db.php + upgrades)
 *   - données de démo : php bin/seed-demo.php
 *   - compte super admin : admin@procope.test / motdepassetest123
 *
 * Les données créées par la suite sont marquées @test-run.procope.test
 * (candidatures) et test-run-* (offres) puis NETTOYÉES à la fin, même en
 * cas d'échec (register_shutdown_function, best effort).
 *
 * Code de sortie : 0 si tous les tests passent, 1 sinon.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    exit("Ce script s'exécute uniquement en CLI.\n");
}

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

use App\Core\Database;
use App\Core\Env;
use App\Services\HtmlSanitizer;
use App\Services\Mailer;

Env::load($root . '/.env');

const BASE_URL = 'http://127.0.0.1:8088';
const TEST_EMAIL_DOMAIN = 'test-run.procope.test';
const TEST_SLUG_PREFIX = 'test-run-';

const ADMIN_EMAIL = 'admin@procope.test';
const ADMIN_PASSWORD = 'motdepassetest123';
const OPERATOR_EMAIL = 'operator@demo.procope.test';
const OPERATOR_PASSWORD = 'operateur-demo-2026';
const ROOT_PASSWORD = 'Dondah1er@';

const DEMO_OPEN_OFFER_SLUG = 'demo-charge-de-programmes-incubation';
const DEMO_ARCHIVED_OFFER_SLUG = 'demo-assistant-comptable-2025';

// =====================================================================
// Mini framework de test
// =====================================================================

final class T
{
    public static int $pass = 0;
    public static int $fail = 0;
    public static array $failures = [];
    private static bool $color = false;

    public static function init(): void
    {
        self::$color = (function_exists('sapi_windows_vt100_support')
            && @sapi_windows_vt100_support(STDOUT, true))
            || getenv('TERM') !== false;
    }

    private static function c(string $code, string $text): string
    {
        return self::$color ? "\033[{$code}m{$text}\033[0m" : $text;
    }

    public static function section(string $title): void
    {
        echo "\n" . self::c('1;36', "== $title ==") . "\n";
    }

    public static function ok(string $label): void
    {
        self::$pass++;
        echo '  ' . self::c('32', 'PASS') . "  $label\n";
    }

    public static function ko(string $label, string $detail): void
    {
        self::$fail++;
        self::$failures[] = "$label — $detail";
        echo '  ' . self::c('1;31', 'FAIL') . "  $label\n";
        echo '        ' . self::c('31', $detail) . "\n";
    }

    public static function assertTrue(bool $cond, string $label, string $detail = ''): void
    {
        $cond ? self::ok($label) : self::ko($label, $detail ?: 'condition fausse');
    }

    public static function assertEquals(mixed $expected, mixed $actual, string $label): void
    {
        $expected === $actual
            ? self::ok($label)
            : self::ko($label, 'attendu ' . var_export($expected, true) . ', obtenu ' . var_export($actual, true));
    }

    public static function assertStatus(int $expected, array $response, string $label): void
    {
        $expected === $response['status']
            ? self::ok($label)
            : self::ko($label, "statut HTTP attendu $expected, obtenu {$response['status']}"
                . ' — ' . mb_substr(trim(strip_tags($response['body'])), 0, 120));
    }

    public static function assertContains(string $needle, string $haystack, string $label): void
    {
        str_contains($haystack, $needle)
            ? self::ok($label)
            : self::ko($label, "« $needle » introuvable dans la réponse (" . mb_substr(trim($haystack), 0, 120) . '…)');
    }

    public static function assertNotContains(string $needle, string $haystack, string $label): void
    {
        !str_contains($haystack, $needle)
            ? self::ok($label)
            : self::ko($label, "« $needle » présent alors qu'il devrait être supprimé");
    }

    public static function summary(): int
    {
        echo "\n" . str_repeat('=', 52) . "\n";
        $total = self::$pass + self::$fail;
        if (self::$fail === 0) {
            echo self::c('1;32', "TOUS LES TESTS PASSENT : {$total}/{$total}") . "\n";
            return 0;
        }
        echo self::c('1;31', 'ÉCHECS : ' . self::$fail . " / $total tests") . "\n";
        foreach (self::$failures as $f) {
            echo self::c('31', "  - $f") . "\n";
        }
        return 1;
    }
}

// =====================================================================
// Client HTTP (cURL + cookie jar fichier temporaire par session)
// =====================================================================

final class Http
{
    private string $jar;

    public function __construct()
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'procope-test-cookies-');
    }

    public function jarPath(): string
    {
        return $this->jar;
    }

    /**
     * @param array{form?: array, multipart?: array, follow?: bool} $options
     * @return array{status:int, body:string, content_type:string, location:string}
     */
    public function request(string $method, string $path, array $options = []): array
    {
        $ch = curl_init(BASE_URL . $path);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => $options['follow'] ?? false,
            CURLOPT_COOKIEJAR      => $this->jar,
            CURLOPT_COOKIEFILE     => $this->jar,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CUSTOMREQUEST  => $method,
        ]);
        if (isset($options['multipart'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $options['multipart']);
        } elseif (isset($options['form'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($options['form']));
        }
        $body = (string) curl_exec($ch);
        $result = [
            'status'       => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE),
            'body'         => $body,
            'content_type' => (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
            'location'     => (string) curl_getinfo($ch, CURLINFO_REDIRECT_URL),
        ];
        curl_close($ch);
        return $result;
    }

    public function get(string $path, array $options = []): array
    {
        return $this->request('GET', $path, $options);
    }

    public function post(string $path, array $options = []): array
    {
        return $this->request('POST', $path, $options);
    }

    /** Jeton CSRF du formulaire de connexion (le GET initialise la session). */
    public function loginCsrf(): string
    {
        $page = $this->get('/admin/login');
        if (!preg_match('/name="_csrf" value="([a-f0-9]+)"/', $page['body'], $m)) {
            throw new RuntimeException('Jeton CSRF introuvable sur /admin/login');
        }
        return $m[1];
    }

    /** Connexion admin ; retourne la réponse du POST (302 attendu). */
    public function login(string $email, string $password): array
    {
        return $this->post('/admin/login', ['form' => [
            '_csrf'    => $this->loginCsrf(),
            'email'    => $email,
            'password' => $password,
        ]]);
    }
}

// =====================================================================
// Nettoyage best effort (même en cas d'échec ou d'exception)
// =====================================================================

$tempFiles = [];
register_shutdown_function(static function () use (&$tempFiles): void {
    try {
        $pdo = Database::pdo();
        // CV uploadés par les candidatures de test
        $cvs = $pdo->query(
            "SELECT cv_path FROM job_applications WHERE cv_path IS NOT NULL AND email LIKE '%@" . TEST_EMAIL_DOMAIN . "'"
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($cvs as $cv) {
            @unlink(dirname(__DIR__) . '/storage/cv/' . basename((string) $cv));
        }
        $pdo->exec("DELETE FROM job_applications WHERE email LIKE '%@" . TEST_EMAIL_DOMAIN . "'");
        $pdo->exec("DELETE FROM job_offers WHERE slug LIKE '" . TEST_SLUG_PREFIX . "%'");
        // Compteurs anti-abus alimentés par la suite (IP locale uniquement)
        $pdo->exec("DELETE FROM rate_limits WHERE ip = '127.0.0.1'");
        $pdo->exec("DELETE FROM login_attempts WHERE ip = '127.0.0.1' AND success = 0");
    } catch (Throwable $e) {
        fwrite(STDERR, 'Nettoyage incomplet : ' . $e->getMessage() . "\n");
    }
    foreach ($tempFiles as $file) {
        @unlink($file);
    }
});

/** PDF factice minimal (CV de test uploadé). */
function makeTestPdf(string $path, string $title): void
{
    $text = "BT /F1 14 Tf 50 780 Td ($title) Tj ET";
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
        '<< /Length ' . strlen($text) . " >>\nstream\n$text\nendstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $i => $bodyPart) {
        $offsets[] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n$bodyPart\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    file_put_contents($path, $pdf);
}

// =====================================================================
// Exécution
// =====================================================================

T::init();
echo "Suite de tests PROCOPE API — " . BASE_URL . "\n";

// ---------------------------------------------------------------------
T::section('1. Santé de l\'API publique');
// ---------------------------------------------------------------------

$anon = new Http();
$tempFiles[] = $anon->jarPath();

$r = $anon->get('/api/formations/active');
T::assertStatus(200, $r, 'GET /api/formations/active -> 200');
$json = json_decode($r['body'], true);
T::assertTrue(is_array($json) && array_key_exists('open', $json),
    'GET /api/formations/active -> JSON avec clé "open"', 'corps : ' . mb_substr($r['body'], 0, 120));

$r = $anon->get('/api/offres');
T::assertStatus(200, $r, 'GET /api/offres -> 200');
$json = json_decode($r['body'], true);
T::assertTrue(is_array($json['offers'] ?? null), 'GET /api/offres -> JSON avec liste "offers"');
$slugs = array_column($json['offers'] ?? [], 'slug');
T::assertTrue(in_array(DEMO_OPEN_OFFER_SLUG, $slugs, true),
    'GET /api/offres -> contient l\'offre de démo ouverte', 'slugs : ' . implode(', ', $slugs));

$r = $anon->get('/api/offres/' . DEMO_OPEN_OFFER_SLUG);
T::assertStatus(200, $r, 'GET /api/offres/{slug démo} -> 200');
T::assertContains('Chargé(e) de programmes', $r['body'], 'détail de l\'offre -> contient le titre');

$r = $anon->get('/api/offres/slug-totalement-inconnu-xyz');
T::assertStatus(404, $r, 'GET /api/offres/{slug inconnu} -> 404');

// ---------------------------------------------------------------------
T::section('2. Authentification admin');
// ---------------------------------------------------------------------

$bad = new Http();
$tempFiles[] = $bad->jarPath();
$r = $bad->login(ADMIN_EMAIL, 'mauvais-mot-de-passe');
T::assertStatus(200, $r, 'login mauvais mot de passe -> pas de redirection');
T::assertContains('Identifiants invalides', $r['body'], 'login mauvais mot de passe -> refus générique');

$admin = new Http();
$tempFiles[] = $admin->jarPath();
$r = $admin->login(ADMIN_EMAIL, ADMIN_PASSWORD);
T::assertStatus(302, $r, 'login admin valide -> 302');
T::assertContains('/admin', $r['location'], 'login admin valide -> redirection vers /admin');

$fresh = new Http();
$tempFiles[] = $fresh->jarPath();
$r = $fresh->get('/admin');
T::assertStatus(302, $r, 'GET /admin sans session -> 302');
T::assertContains('/admin/login', $r['location'], 'GET /admin sans session -> redirection vers le login');

// ---------------------------------------------------------------------
T::section('3. Rôles et permissions');
// ---------------------------------------------------------------------

$operator = new Http();
$tempFiles[] = $operator->jarPath();
$r = $operator->login(OPERATOR_EMAIL, OPERATOR_PASSWORD);
T::assertStatus(302, $r, 'login operator (seeder) -> 302');
T::assertStatus(200, $operator->get('/admin'), 'operator -> dashboard 200');
T::assertStatus(403, $operator->get('/admin/users'), 'operator -> /admin/users 403');
T::assertStatus(403, $operator->get('/admin/surveillance'), 'operator -> /admin/surveillance 403');

T::assertStatus(200, $admin->get('/admin/users'), 'super admin -> /admin/users 200');
T::assertStatus(200, $admin->get('/admin/surveillance'), 'super admin -> /admin/surveillance 200');

$rootUsername = (string) Env::get('ROOT_ADMIN_USERNAME', '');
if ($rootUsername === '') {
    T::ko('login compte racine .env', 'ROOT_ADMIN_USERNAME absent du .env');
} else {
    $rootHttp = new Http();
    $tempFiles[] = $rootHttp->jarPath();
    $r = $rootHttp->login($rootUsername, ROOT_PASSWORD);
    T::assertStatus(302, $r, 'login compte racine .env -> 302');
    $r = $rootHttp->get('/admin');
    T::assertStatus(200, $r, 'compte racine -> dashboard 200');
    T::assertContains('Super admin (racine)', $r['body'], 'compte racine -> identifié comme racine');
}

// ---------------------------------------------------------------------
T::section('4. Protection CSRF');
// ---------------------------------------------------------------------

$r = $admin->post('/admin/settings', ['form' => ['site_phone' => '+228 00 00 00 00']]);
T::assertStatus(419, $r, 'POST /admin/settings sans jeton CSRF -> 419');

// ---------------------------------------------------------------------
T::section('5. Pages admin clés (session super admin)');
// ---------------------------------------------------------------------

$pages = [
    '/admin'                       => 'dashboard',
    '/admin/formations'            => 'formations',
    '/admin/inscriptions'          => 'inscriptions',
    '/admin/emplois'               => 'offres d\'emploi',
    '/admin/emplois/candidatures'  => 'candidatures globales',
    '/admin/automations'           => 'automatisations',
    '/admin/archives'              => 'archives',
    '/admin/messages'              => 'messages',
    '/admin/settings'              => 'réglages',
    '/admin/surveillance'          => 'surveillance + journal',
    '/admin/users'                 => 'utilisateurs',
];
foreach ($pages as $path => $label) {
    T::assertStatus(200, $admin->get($path), "GET $path ($label) -> 200");
}

// ---------------------------------------------------------------------
T::section('6. API candidature (POST multipart)');
// ---------------------------------------------------------------------

$cvFile = tempnam(sys_get_temp_dir(), 'procope-test-cv-') . '.pdf';
$tempFiles[] = $cvFile;
makeTestPdf($cvFile, 'CV de test automatique');

$uniqueEmail = 'candidat-' . time() . '@' . TEST_EMAIL_DOMAIN;
$fields = [
    'full_name' => 'Candidat Test Automatique',
    'email'     => $uniqueEmail,
    'phone'     => '+228 90 00 11 22',
    'message'   => 'Candidature envoyée automatiquement par la suite de tests. Merci de l\'ignorer.',
    'cv'        => new CURLFile($cvFile, 'application/pdf', 'cv-test.pdf'),
];

$r = $anon->post('/api/offres/' . DEMO_OPEN_OFFER_SLUG . '/postuler', ['multipart' => $fields]);
T::assertStatus(201, $r, 'candidature valide -> 201');
$json = json_decode($r['body'], true);
T::assertTrue(($json['ok'] ?? false) === true, 'candidature valide -> ok=true');
$inDb = (int) Database::run(
    'SELECT COUNT(*) FROM job_applications WHERE email = ?', [$uniqueEmail]
)->fetchColumn();
T::assertEquals(1, $inDb, 'candidature valide -> 1 ligne en base');

$fields['cv'] = new CURLFile($cvFile, 'application/pdf', 'cv-test.pdf');
$r = $anon->post('/api/offres/' . DEMO_OPEN_OFFER_SLUG . '/postuler', ['multipart' => $fields]);
T::assertStatus(409, $r, 'même e-mail sur la même offre -> 409');

// Offre archivée (dépubliée) -> introuvable côté public
$r = $anon->post('/api/offres/' . DEMO_ARCHIVED_OFFER_SLUG . '/postuler', ['multipart' => [
    'full_name' => 'Candidat Test Automatique',
    'email'     => 'candidat-archive-' . time() . '@' . TEST_EMAIL_DOMAIN,
]]);
T::assertStatus(404, $r, 'candidature sur offre archivée/dépubliée -> 404');

// Offre publiée mais clôturée -> 409 (créée temporairement, marquée test-run-*)
Database::run(
    'INSERT INTO job_offers (title, slug, description, location, contract_type, salary, closes_at, is_published)
     VALUES (?, ?, ?, ?, ?, ?, ?, 1)',
    ['Offre clôturée (test)', TEST_SLUG_PREFIX . 'offre-cloturee', 'Offre de test.', 'Lomé', 'Autre', null,
     date('Y-m-d H:i:s', strtotime('-1 day'))]
);
$r = $anon->post('/api/offres/' . TEST_SLUG_PREFIX . 'offre-cloturee/postuler', ['multipart' => [
    'full_name' => 'Candidat Test Automatique',
    'email'     => 'candidat-cloture-' . time() . '@' . TEST_EMAIL_DOMAIN,
]]);
T::assertStatus(409, $r, 'candidature sur offre publiée clôturée -> 409');

// Honeypot rempli -> acceptation silencieuse SANS enregistrement
$honeypotEmail = 'robot-' . time() . '@' . TEST_EMAIL_DOMAIN;
$r = $anon->post('/api/offres/' . DEMO_OPEN_OFFER_SLUG . '/postuler', ['multipart' => [
    'full_name' => 'Robot Spammeur',
    'email'     => $honeypotEmail,
    'website'   => 'http://spam.example.com',
]]);
T::assertStatus(200, $r, 'honeypot rempli -> 200 (leurre, pas de 201)');
$inDb = (int) Database::run(
    'SELECT COUNT(*) FROM job_applications WHERE email = ?', [$honeypotEmail]
)->fetchColumn();
T::assertEquals(0, $inDb, 'honeypot rempli -> aucune ligne en base');

// ---------------------------------------------------------------------
T::section('7. HtmlSanitizer (tests unitaires)');
// ---------------------------------------------------------------------

$out = HtmlSanitizer::clean('<p>Bonjour <script>alert(1)</script>tout le monde</p>');
T::assertNotContains('<script', $out, 'sanitizer : <script> supprimé avec son contenu');
T::assertNotContains('alert(1)', $out, 'sanitizer : contenu du <script> supprimé');
T::assertContains('Bonjour', $out, 'sanitizer : texte autour du script conservé');

$out = HtmlSanitizer::clean('<p onclick="pwn()" style="color:red" class="x">Texte</p>');
T::assertNotContains('onclick', $out, 'sanitizer : attribut onclick supprimé');
T::assertNotContains('style=', $out, 'sanitizer : attribut style supprimé');
T::assertContains('<p>Texte</p>', $out, 'sanitizer : balise <p> et texte conservés');

$out = HtmlSanitizer::clean('avant <iframe src="http://evil.example"></iframe> après');
T::assertNotContains('<iframe', $out, 'sanitizer : <iframe> supprimé');

$out = HtmlSanitizer::clean('<strong>gras</strong> et <ul><li>liste</li></ul>');
T::assertContains('<strong>gras</strong>', $out, 'sanitizer : <strong> conservé');
T::assertContains('<ul><li>liste</li></ul>', $out, 'sanitizer : <ul>/<li> conservés');

$out = HtmlSanitizer::clean('<a href="https://procope.example/page">lien</a>');
T::assertContains('href="https://procope.example/page"', $out, 'sanitizer : href https conservé');

$out = HtmlSanitizer::clean('<a href="javascript:alert(1)">lien</a>');
T::assertNotContains('javascript:', $out, 'sanitizer : href javascript: supprimé');

$plain = "Bonjour,\nvoici un texte brut avec des {{variables}} et des accents : éàç.";
T::assertEquals($plain, HtmlSanitizer::clean($plain), 'sanitizer : texte brut inchangé');

// ---------------------------------------------------------------------
T::section('8. Exports (session super admin)');
// ---------------------------------------------------------------------

$r = $admin->get('/admin/inscriptions/export');
T::assertStatus(200, $r, 'export Excel inscriptions -> 200');
T::assertContains('spreadsheetml', $r['content_type'], 'export inscriptions -> content-type xlsx');

$r = $admin->get('/admin/emplois/candidatures/export');
T::assertStatus(200, $r, 'export Excel candidatures -> 200');
T::assertContains('spreadsheetml', $r['content_type'], 'export candidatures -> content-type xlsx');

$r = $admin->get('/admin/emplois/candidatures/pdf');
T::assertStatus(200, $r, 'export PDF candidatures -> 200');
T::assertTrue(str_starts_with($r['body'], '%PDF'), 'export PDF candidatures -> magic bytes %PDF',
    'début du corps : ' . bin2hex(substr($r['body'], 0, 8)));

// ---------------------------------------------------------------------
T::section('9. Rendu des templates e-mail (unitaire, aucun envoi)');
// ---------------------------------------------------------------------

$formation = [
    'titre' => 'Formation test unitaire', 'prix' => 25000, 'devise' => 'XOF',
    'lieu' => 'Lomé', 'contact_phone' => '+228 96 45 76 95',
];
$html = Mailer::template('confirmation', [
    'full_name'       => 'Test <script>alert(1)</script>',
    'formation'       => $formation,
    'slots'           => [['label' => 'Samedi test', 'starts_at' => '2026-09-05 08:30:00', 'ends_at' => '2026-09-05 17:00:00']],
    'has_proof'       => true,
    'payment_type'    => 'partiel',
    'amount_declared' => 10000.0,
]);
T::assertContains('/assets/logo.png', $html, 'template confirmation -> logo PROCOPE présent');
$delivered = Mailer::htmlForDelivery($html);
T::assertContains('cid:logo-procope', $delivered, 'htmlForDelivery -> logo en CID pour SMTP');
T::assertNotContains('127.0.0.1', $delivered, 'htmlForDelivery -> plus d\'URL localhost pour le logo');
T::assertContains('Formation test unitaire', $html, 'template confirmation -> titre de la formation');
T::assertNotContains('<script>alert(1)</script>', $html, 'template confirmation -> nom échappé (pas de XSS)');
T::assertContains('&lt;script&gt;', $html, 'template confirmation -> échappement HTML visible');
T::assertContains('Reste à payer', $html, 'template confirmation -> bloc paiement partiel rendu');

$offer = [
    'title' => 'Offre test unitaire', 'contract_type' => 'CDD', 'location' => 'Lomé, Togo',
    'salary' => null, 'closes_at' => date('Y-m-d 18:00:00', strtotime('+7 days')),
];
$html = Mailer::template('candidature_emploi', ['full_name' => 'Ama & Co', 'offer' => $offer]);
T::assertContains('Offre test unitaire', $html, 'template candidature_emploi -> titre de l\'offre');
T::assertContains('Ama &amp; Co', $html, 'template candidature_emploi -> nom échappé (& -> &amp;)');
T::assertContains('/assets/logo.png', $html, 'template candidature_emploi -> logo présent');

$html = Mailer::template('contact', [
    'contact_id' => 42, 'name' => 'Visiteur Test', 'email' => 'v@example.org',
    'phone' => '+228 90 00 00 00', 'subject' => 'Question', 'message_text' => 'Message de test unitaire.',
]);
T::assertContains('Message de test unitaire.', $html, 'template contact -> message rendu');
T::assertContains('Visiteur Test', $html, 'template contact -> nom du contact rendu');

$parsed = Mailer::parseNotifyList(
    ' procopeafrique@gmail.com , CHAMINADE.DONDAH.ADJOLOU@gmail.com;procopeafrique@gmail.com;pas-un-email '
);
T::assertTrue(count($parsed) === 2, 'parseNotifyList -> 2 adresses valides uniques', implode(',', $parsed));
T::assertTrue($parsed[0] === 'procopeafrique@gmail.com', 'parseNotifyList -> 1re adresse normalisée');
T::assertTrue($parsed[1] === 'chaminade.dondah.adjolou@gmail.com', 'parseNotifyList -> 2e adresse en minuscules');
T::assertTrue(Mailer::parseNotifyList('') === [], 'parseNotifyList -> liste vide');

// ---------------------------------------------------------------------
exit(T::summary());
