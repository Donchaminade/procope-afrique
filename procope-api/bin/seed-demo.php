<?php

/**
 * Seeder de données de démonstration (DEV UNIQUEMENT).
 *
 * Usage :
 *   php bin/seed-demo.php            # crée les données manquantes (idempotent)
 *   php bin/seed-demo.php --fresh    # supprime d'abord SES données de démo, puis re-crée
 *
 * Marqueurs d'identification (jamais touchés par --fresh en dehors d'eux) :
 *   - e-mails  : *@demo.procope.test
 *   - slugs    : demo-*  (formations et offres d'emploi)
 *   - fichiers : demo-*  (storage/proofs, storage/cv, public/uploads/offres)
 *
 * Garde-fou : refuse de tourner si APP_ENV vaut « production » / « prod »,
 * ou si APP_ENV est absent du .env (on ne seed jamais un environnement
 * inconnu). Seules les valeurs local / dev / development / test / testing
 * / staging sont acceptées.
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

use App\Core\Database;
use App\Core\Env;

Env::load($root . '/.env');

// --- Garde-fou environnement ---
$appEnv = strtolower((string) Env::get('APP_ENV', ''));
$allowedEnvs = ['local', 'dev', 'development', 'test', 'testing', 'staging'];
if (!in_array($appEnv, $allowedEnvs, true)) {
    fwrite(STDERR, "REFUS : APP_ENV=\"$appEnv\" — ce seeder ne tourne qu'en environnement de développement\n");
    fwrite(STDERR, '(valeurs acceptées : ' . implode(', ', $allowedEnvs) . ").\n");
    exit(1);
}

$fresh = in_array('--fresh', $argv, true);

const DEMO_EMAIL_DOMAIN = 'demo.procope.test';
const DEMO_SLUG_PREFIX  = 'demo-';
const DEMO_FILE_PREFIX  = 'demo-';

$created = [];
$skipped = [];

// =====================================================================
// Génération de fichiers factices (preuves PNG/PDF, CV PDF, affiches PNG)
// =====================================================================

/** PNG factice : image GD avec texte, sinon repli PNG 1x1 encodé en dur. */
function makePng(string $fullPath, string $label, int $width = 640, int $height = 400): void
{
    if (function_exists('imagecreatetruecolor')) {
        $im = imagecreatetruecolor($width, $height);
        $navy = imagecolorallocate($im, 6, 42, 77);
        $orange = imagecolorallocate($im, 245, 166, 35);
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, 0, 0, $width, $height, $navy);
        imagefilledrectangle($im, 0, $height - 40, $width, $height, $orange);
        imagestring($im, 5, 20, 20, 'PROCOPE AFRIQUE - DEMO', $white);
        foreach (explode("\n", wordwrap($label, 45, "\n", true)) as $i => $line) {
            imagestring($im, 4, 20, 60 + $i * 22, $line, $white);
        }
        imagepng($im, $fullPath);
        imagedestroy($im);
        return;
    }
    // Repli : PNG orange 1x1 valide
    file_put_contents($fullPath, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ));
}

/** PDF factice minimal mais valide (une page, texte Helvetica). */
function makePdf(string $fullPath, array $lines): void
{
    $text = "BT /F1 14 Tf 50 780 Td 18 TL\n";
    foreach ($lines as $line) {
        $clean = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line);
        // Texte ASCII : les PDF simples ne gèrent pas l'UTF-8 sans police embarquée
        $clean = @iconv('UTF-8', 'ASCII//TRANSLIT', $clean) ?: $clean;
        $text .= "($clean) Tj T*\n";
    }
    $text .= 'ET';

    $objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
        '<< /Length ' . strlen($text) . " >>\nstream\n$text\nendstream",
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
    ];

    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $i => $body) {
        $offsets[] = strlen($pdf);
        $pdf .= ($i + 1) . " 0 obj\n$body\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n$xref\n%%EOF";
    file_put_contents($fullPath, $pdf);
}

function ensureDir(string $dir): void
{
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// =====================================================================
// --fresh : suppression des données de démo existantes (et d'elles seules)
// =====================================================================

if ($fresh) {
    echo "-- Nettoyage des données de démo existantes (--fresh) --\n";
    $pdo = Database::pdo();

    // Fichiers de preuves / CV référencés par les lignes de démo
    $proofPaths = $pdo->query(
        "SELECT payment_proof_path FROM inscriptions
          WHERE payment_proof_path IS NOT NULL
            AND (email LIKE '%@" . DEMO_EMAIL_DOMAIN . "' OR formation_id IN
                 (SELECT id FROM formations WHERE slug LIKE '" . DEMO_SLUG_PREFIX . "%'))"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($proofPaths as $p) {
        @unlink($root . '/storage/proofs/' . basename((string) $p));
    }

    $cvPaths = $pdo->query(
        "SELECT cv_path FROM job_applications
          WHERE cv_path IS NOT NULL
            AND (email LIKE '%@" . DEMO_EMAIL_DOMAIN . "' OR offer_id IN
                 (SELECT id FROM job_offers WHERE slug LIKE '" . DEMO_SLUG_PREFIX . "%'))"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($cvPaths as $p) {
        @unlink($root . '/storage/cv/' . basename((string) $p));
    }

    $imgPaths = $pdo->query(
        "SELECT path FROM job_offer_images
          WHERE offer_id IN (SELECT id FROM job_offers WHERE slug LIKE '" . DEMO_SLUG_PREFIX . "%')"
    )->fetchAll(PDO::FETCH_COLUMN);
    foreach ($imgPaths as $p) {
        @unlink($root . '/public/uploads/offres/' . basename((string) $p));
    }

    // Lignes en base (ordre : enfants avant parents, FK RESTRICT sur inscriptions)
    $n = $pdo->exec(
        "DELETE FROM inscriptions
          WHERE email LIKE '%@" . DEMO_EMAIL_DOMAIN . "'
             OR formation_id IN (SELECT id FROM (SELECT id FROM formations WHERE slug LIKE '" . DEMO_SLUG_PREFIX . "%') t)"
    );
    echo "  inscriptions supprimées : $n\n";
    $n = $pdo->exec(
        "DELETE FROM job_applications
          WHERE email LIKE '%@" . DEMO_EMAIL_DOMAIN . "'
             OR offer_id IN (SELECT id FROM (SELECT id FROM job_offers WHERE slug LIKE '" . DEMO_SLUG_PREFIX . "%') t)"
    );
    echo "  candidatures supprimées : $n\n";
    $n = $pdo->exec("DELETE FROM job_offers WHERE slug LIKE '" . DEMO_SLUG_PREFIX . "%'");
    echo "  offres supprimées : $n\n";
    $n = $pdo->exec("DELETE FROM formations WHERE slug LIKE '" . DEMO_SLUG_PREFIX . "%'");
    echo "  formations supprimées : $n\n";
    $n = $pdo->exec("DELETE FROM contact_messages WHERE email LIKE '%@" . DEMO_EMAIL_DOMAIN . "'");
    echo "  messages de contact supprimés : $n\n";
    $n = $pdo->exec("DELETE FROM users WHERE email LIKE '%@" . DEMO_EMAIL_DOMAIN . "'");
    echo "  comptes supprimés : $n\n";

    // Fichiers orphelins marqués demo-*
    foreach (['/storage/proofs', '/storage/cv', '/public/uploads/offres'] as $dir) {
        foreach (glob($root . $dir . '/' . DEMO_FILE_PREFIX . '*') ?: [] as $file) {
            @unlink($file);
        }
    }
    echo "\n";
}

// =====================================================================
// Helpers d'insertion idempotente
// =====================================================================

function uuid4(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
}

function demoFileName(string $ext): string
{
    return DEMO_FILE_PREFIX . bin2hex(random_bytes(6)) . '.' . $ext;
}

/** prenom.nom@demo.procope.test à partir d'un nom accentué. */
function demoEmail(string $name): string
{
    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $name) ?: $name;
    $local = strtolower(str_replace(' ', '.', $ascii));
    $local = preg_replace('/[^a-z0-9.]/', '', $local);
    return $local . '@' . DEMO_EMAIL_DOMAIN;
}

// =====================================================================
// 1. Formations de démo
// =====================================================================

echo "-- Formations --\n";

$formations = [
    [
        'slug'   => 'demo-gestion-financiere-pme-2026',
        'titre'  => 'Gestion financière et fiscalité des PME — Session de démonstration',
        'intro'  => "Deux journées intensives pour apprendre à tenir une comptabilité simple, anticiper ses obligations fiscales (OTR) et sociales (CNSS), et construire un plan de trésorerie réaliste pour votre entreprise. Animée par des experts-comptables partenaires de PROCOPE, cette session s'adresse aux entrepreneurs et porteurs de projet de la région de Lomé.",
        'programme' => "Tenir une comptabilité simplifiée au quotidien\nComprendre et anticiper ses obligations OTR et CNSS\nConstruire un plan de trésorerie sur 12 mois\nPréparer un dossier bancable pour les institutions de microfinance",
        'prix'   => 25000.00,
        'lieu'   => "Salle Melli FERA, derrière l'Hôtel Concorde, Adidogomé",
        'places_max' => 40,
        'ouverte'    => 1,
        'archived'   => false,
        'message_fermeture' => 'Les inscriptions sont actuellement fermées. Contactez-nous au +228 96 45 76 95 pour la prochaine session.',
        'slots'  => [
            ['Samedi ' . date('d/m/Y', strtotime('+21 days')), date('Y-m-d 08:30:00', strtotime('+21 days')), date('Y-m-d 17:00:00', strtotime('+21 days'))],
            ['Dimanche ' . date('d/m/Y', strtotime('+22 days')), date('Y-m-d 14:00:00', strtotime('+22 days')), date('Y-m-d 19:30:00', strtotime('+22 days'))],
        ],
    ],
    [
        'slug'   => 'demo-marketing-digital-2025',
        'titre'  => 'Marketing digital pour entrepreneurs — Édition 2025 (démonstration)',
        'intro'  => "Formation pratique consacrée à la visibilité en ligne des petites entreprises togolaises : créer une page professionnelle efficace, produire du contenu qui convertit, et lancer ses premières campagnes sponsorisées avec un petit budget.",
        'programme' => "Créer et animer une page entreprise (Facebook, TikTok, WhatsApp Business)\nRédiger des publications qui attirent des clients\nLancer une campagne sponsorisée à petit budget\nMesurer ses résultats et ajuster sa stratégie",
        'prix'   => 15000.00,
        'lieu'   => 'Centre communautaire de Bè-Kpota, Lomé',
        'places_max' => 30,
        'ouverte'    => 0,
        'archived'   => true,
        'message_fermeture' => 'Cette session est terminée. Merci à tous les participants !',
        'slots'  => [
            ['Samedi 15 novembre 2025', '2025-11-15 09:00:00', '2025-11-15 17:00:00'],
        ],
    ],
];

$formationIds = [];
foreach ($formations as $f) {
    $existing = Database::run('SELECT id FROM formations WHERE slug = ?', [$f['slug']])->fetchColumn();
    if ($existing) {
        $formationIds[$f['slug']] = (int) $existing;
        $skipped[] = 'formation ' . $f['slug'];
        continue;
    }
    Database::run(
        'INSERT INTO formations (titre, slug, intro, programme, prix, devise, lieu, places_max,
                                 inscriptions_ouvertes, message_fermeture, contact_phone, archived_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $f['titre'], $f['slug'], $f['intro'], $f['programme'], $f['prix'], 'XOF',
            $f['lieu'], $f['places_max'], $f['ouverte'], $f['message_fermeture'],
            '+228 96 45 76 95', $f['archived'] ? date('Y-m-d H:i:s', strtotime('-45 days')) : null,
        ]
    );
    $id = (int) Database::pdo()->lastInsertId();
    $formationIds[$f['slug']] = $id;
    $order = 1;
    foreach ($f['slots'] as [$label, $start, $end]) {
        Database::run(
            'INSERT INTO formation_slots (formation_id, label, starts_at, ends_at, sort_order) VALUES (?, ?, ?, ?, ?)',
            [$id, $label, $start, $end, $order++]
        );
    }
    $created[] = 'formation ' . $f['slug'] . ' (' . count($f['slots']) . ' créneau(x))';
}

$fOpenId = $formationIds['demo-gestion-financiere-pme-2026'];
$fPastId = $formationIds['demo-marketing-digital-2025'];

// =====================================================================
// 2. Inscriptions de démo (12, tous les statuts, preuves factices)
// =====================================================================

echo "-- Inscriptions --\n";

ensureDir($root . '/storage/proofs');

/**
 * [formation, nom, sexe, téléphone, ville, situation pro, entrepreneur, entreprise,
 *  secteur, motivation, type paiement, montant déclaré, montant reçu, preuve(png|pdf|null), statut, source]
 */
$inscriptions = [
    [$fOpenId, 'Kodjo Amenyo', 'M', '+228 90 12 34 56', 'Lomé — Agoè', 'Commerçant', 1, 'Amenyo & Fils', 'Commerce général',
     "Je gère une boutique de pièces détachées depuis 4 ans et je veux enfin mettre de l'ordre dans ma comptabilité pour ouvrir un second point de vente.",
     'total', 25000.0, null, null, 'preinscrit', 'Facebook'],
    [$fOpenId, 'Afi Mensah', 'F', '+228 91 23 45 67', 'Lomé — Bè', 'Couturière', 1, 'Atelier Afi Couture', 'Artisanat / mode',
     "Mon atelier grandit et je commence à embaucher. J'ai besoin de comprendre la CNSS et les déclarations pour être en règle.",
     'total', 25000.0, null, null, 'preinscrit', 'WhatsApp'],
    [$fOpenId, 'Kossi Agbeko', 'M', '+228 92 34 56 78', 'Tsévié', 'Agriculteur-transformateur', 1, 'Agbeko Agro', 'Agro-alimentaire',
     "Je transforme le manioc en gari et attiéké. Je veux structurer mon activité pour candidater aux financements de la FAIEJ.",
     'total', 25000.0, null, 'png', 'preuve_recue', 'Ami / proche'],
    [$fOpenId, 'Akossiwa Dogbé', 'F', '+228 93 45 67 89', 'Lomé — Adidogomé', 'Restauratrice', 1, 'Chez Akossiwa', 'Restauration',
     "Mon maquis marche bien mais je ne sais jamais combien je gagne réellement chaque mois. Cette formation tombe à pic.",
     'total', 25000.0, null, 'pdf', 'preuve_recue', 'TikTok'],
    [$fOpenId, 'Yawo Kpodzro', 'M', '+228 96 56 78 90', 'Kpalimé', 'Salarié en reconversion', 0, null, 'Tourisme',
     "Je prépare mon départ de l'administration pour lancer une agence d'écotourisme autour de Kpalimé. Je veux partir sur de bonnes bases fiscales.",
     'total', 25000.0, 25000.0, 'png', 'valide', 'Facebook'],
    [$fOpenId, 'Dela Amevor', 'F', '+228 97 67 89 01', 'Lomé — Tokoin', 'Consultante indépendante', 1, 'DA Conseil', 'Services',
     "Consultante en communication depuis 2 ans, je veux régulariser ma situation à l'OTR et facturer proprement mes clients.",
     'total', 25000.0, 25000.0, 'pdf', 'valide', 'Instagram'],
    [$fOpenId, 'Komlan Attiogbé', 'M', '+228 98 78 90 12', 'Aného', 'Pêcheur-mareyeur', 1, null, 'Pêche / distribution',
     "Je fournis du poisson fumé aux marchés de Lomé. Je veux apprendre à tenir mes comptes pour obtenir un prêt d'équipement.",
     'partiel', 10000.0, 10000.0, 'png', 'paiement_partiel', 'Ami / proche'],
    [$fOpenId, 'Abra Segbefia', 'F', '+228 99 89 01 23', 'Lomé — Nyékonakpoè', 'Étudiante entrepreneure', 1, 'Abra Cosmetics', 'Cosmétique naturelle',
     "Je fabrique des savons au karité que je vends en ligne. Je souhaite formaliser ma micro-entreprise avant de viser les boutiques.",
     'partiel', 15000.0, null, null, 'liste_attente', 'TikTok'],
    [$fOpenId, 'Edem Tsikplonou', 'M', '+228 90 90 12 34', 'Sokodé', 'Sans emploi', 0, null, null,
     "Bonjour, je voulais juste des informations sur vos activités.",
     'total', 25000.0, null, null, 'refuse', 'Autre'],
    [$fPastId, 'Ama Klutse', 'F', '+228 91 01 23 45', 'Lomé — Hédzranawoé', 'Revendeuse', 1, null, 'Commerce en ligne',
     "Je vends des tissus wax sur WhatsApp et Facebook. Je veux professionnaliser ma présence en ligne.",
     'total', 15000.0, 15000.0, 'png', 'valide', 'Facebook'],
    [$fPastId, 'Selorm Gadzekpo', 'M', '+228 92 12 30 45', 'Atakpamé', 'Enseignant', 0, null, 'Éducation',
     "Je développe une plateforme de soutien scolaire et j'ai besoin de visibilité pour trouver mes premiers abonnés.",
     'total', 15000.0, 15000.0, 'pdf', 'valide', 'WhatsApp'],
    [$fPastId, 'Essohana Tchalla', 'F', '+228 93 23 41 56', 'Kara', 'Transformatrice de soja', 1, 'Soja Délices Kara', 'Agro-alimentaire',
     "Inscription envoyée après la date limite de la session.",
     'total', 15000.0, null, null, 'refuse', 'Instagram'],
];

$modulesPool = [
    ['Créer son entreprise en toute légalité'],
    ['Maîtriser la gestion des obligations fiscales et sociales (OTR & CNSS)'],
    ['Créer son entreprise en toute légalité', 'Accès au financement'],
    ['Tous les modules'],
];

$nbCreated = 0;
foreach ($inscriptions as $i => $row) {
    [$fid, $name, $gender, $phone, $city, $job, $isEnt, $company, $sector,
     $motivation, $payType, $declared, $received, $proofType, $statut, $source] = $row;

    $email = demoEmail($name);
    $exists = Database::run(
        'SELECT COUNT(*) FROM inscriptions WHERE formation_id = ? AND email = ?',
        [$fid, $email]
    )->fetchColumn();
    if ($exists) {
        $skipped[] = "inscription $email";
        continue;
    }

    $proofPath = null;
    $proofMime = null;
    $proofName = null;
    if ($proofType === 'png') {
        $proofPath = demoFileName('png');
        makePng($root . '/storage/proofs/' . $proofPath, "Preuve de paiement Mobile Money\n$name\nMontant : " . number_format((float) ($received ?? $declared), 0, ',', ' ') . ' F CFA');
        $proofMime = 'image/png';
        $proofName = 'recu-tmoney.png';
    } elseif ($proofType === 'pdf') {
        $proofPath = demoFileName('pdf');
        makePdf($root . '/storage/proofs/' . $proofPath, [
            'RECU DE DEPOT - DEMONSTRATION',
            'Client : ' . $name,
            'Montant : ' . number_format((float) ($received ?? $declared), 0, ',', ' ') . ' F CFA',
            'Reference : PRC-' . strtoupper(bin2hex(random_bytes(4))),
        ]);
        $proofMime = 'application/pdf';
        $proofName = 'recu-banque.pdf';
    }

    Database::run(
        'INSERT INTO inscriptions
            (uuid, formation_id, full_name, gender, phone, email, city, professional_status,
             is_entrepreneur, company_name, sector, motivation, modules, payment_method,
             payment_type, amount_declared, amount_received,
             payment_proof_path, payment_proof_mime, payment_proof_name,
             acquisition_source, consent_image, statut, ip, user_agent, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, ?)',
        [
            uuid4(), $fid, $name, $gender, $phone, $email, $city, $job, $isEnt, $company,
            $sector, $motivation, json_encode($modulesPool[$i % count($modulesPool)], JSON_UNESCAPED_UNICODE),
            $i % 2 === 0 ? 'mobile_money' : 'ecobank',
            $payType, $declared, $received, $proofPath, $proofMime, $proofName, $source,
            $statut, '127.0.0.1', 'seed-demo',
            date('Y-m-d H:i:s', strtotime('-' . (2 + $i) . ' days')),
        ]
    );
    $nbCreated++;
}
if ($nbCreated) {
    $created[] = "$nbCreated inscription(s) (statuts variés + preuves factices)";
}

// =====================================================================
// 3. Messages de contact (4, statuts variés)
// =====================================================================

echo "-- Messages de contact --\n";

$contacts = [
    ['Mawuli Adjonou', 'mawuli.adjonou@' . DEMO_EMAIL_DOMAIN, '+228 96 11 22 33', 'Incubation de projet',
     "Bonjour, je porte un projet de recyclage de déchets plastiques à Lomé et je souhaiterais savoir comment intégrer votre programme d'incubation. Quels sont les critères et le calendrier des prochaines cohortes ?", 'nouveau'],
    ['Chantal Lawson', 'chantal.lawson@' . DEMO_EMAIL_DOMAIN, '+228 97 22 33 44', 'Partenariat',
     "Bonjour, je représente une ONG basée à Cotonou qui accompagne des femmes entrepreneures. Nous aimerions explorer un partenariat avec PROCOPE pour des formations croisées Togo-Bénin.", 'nouveau'],
    ['Folly Anani', 'folly.anani@' . DEMO_EMAIL_DOMAIN, null, 'Formations',
     "Bonsoir, j'ai raté la dernière session de formation. Est-ce qu'une nouvelle vague est prévue avant la fin de l'année ? Merci de me tenir informé.", 'lu'],
    ['Rachida Boukari', 'rachida.boukari@' . DEMO_EMAIL_DOMAIN, '+228 99 44 55 66', 'Autre demande',
     "Bonjour, votre équipe intervient-elle aussi à l'intérieur du pays ? Je suis à Dapaong et plusieurs jeunes de ma coopérative aimeraient être accompagnés.", 'traite'],
];

$nbCreated = 0;
foreach ($contacts as $i => [$name, $email, $phone, $subject, $message, $statut]) {
    $exists = Database::run('SELECT COUNT(*) FROM contact_messages WHERE email = ?', [$email])->fetchColumn();
    if ($exists) {
        $skipped[] = "message de $email";
        continue;
    }
    Database::run(
        'INSERT INTO contact_messages (name, email, phone, subject, message, statut, ip, user_agent, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$name, $email, $phone, $subject, $message, $statut, '127.0.0.1', 'seed-demo',
         date('Y-m-d H:i:s', strtotime('-' . (1 + $i * 2) . ' days'))]
    );
    $nbCreated++;
}
if ($nbCreated) {
    $created[] = "$nbCreated message(s) de contact";
}

// =====================================================================
// 4. Offres d'emploi (3) + affiches PNG
// =====================================================================

echo "-- Offres d'emploi --\n";

ensureDir($root . '/public/uploads/offres');

$offers = [
    [
        'slug' => 'demo-charge-de-programmes-incubation',
        'title' => "Chargé(e) de programmes d'incubation",
        'description' => "PROCOPE Afrique recrute un(e) chargé(e) de programmes pour animer le parcours d'incubation de sa prochaine cohorte d'entrepreneurs.\n\nVos missions :\n- organiser et animer les ateliers de formation (gestion, fiscalité, financement) ;\n- assurer le suivi individuel des porteurs de projet incubés ;\n- coordonner les intervenants externes (experts-comptables, banquiers, mentors) ;\n- produire les rapports d'activité à destination des partenaires.\n\nProfil recherché : bac+3 minimum (gestion, économie, entrepreneuriat), 2 ans d'expérience en accompagnement d'entrepreneurs ou en gestion de projet, excellent relationnel, maîtrise du français (l'éwé est un plus).\n\nPoste basé à Lomé, déplacements ponctuels à l'intérieur du pays.",
        'location' => 'Lomé, Togo',
        'contract_type' => 'CDD',
        'salary' => 'Selon profil (grille PROCOPE)',
        'closes_at' => date('Y-m-d 18:00:00', strtotime('+10 days')),
        'is_published' => 1,
        'archived' => false,
        'images' => 2,
    ],
    [
        'slug' => 'demo-community-manager-junior',
        'title' => 'Community manager junior',
        'description' => "Pour renforcer sa visibilité en ligne, PROCOPE Afrique recherche un(e) community manager junior.\n\nVos missions :\n- animer les pages Facebook, TikTok et LinkedIn de l'incubateur ;\n- couvrir les événements (formations, remises de prix, journées portes ouvertes) ;\n- concevoir les visuels simples (Canva) et les courtes vidéos ;\n- répondre aux messages de la communauté avec l'équipe.\n\nProfil : première expérience en gestion de réseaux sociaux (stage accepté), créativité, autonomie, disponibilité certains week-ends d'événement.",
        'location' => 'Lomé, Togo (hybride possible)',
        'contract_type' => 'Stage',
        'salary' => 'Indemnité mensuelle + prime de résultats',
        'closes_at' => date('Y-m-d 18:00:00', strtotime('+4 days')),
        'is_published' => 1,
        'archived' => false,
        'images' => 0,
    ],
    [
        'slug' => 'demo-assistant-comptable-2025',
        'title' => 'Assistant(e) comptable (campagne 2025 clôturée)',
        'description' => "Campagne de recrutement 2025, aujourd'hui terminée. L'assistant(e) comptable appuyait la coordination dans la tenue des comptes de l'incubateur : saisie des pièces, rapprochements bancaires, préparation des déclarations OTR et CNSS, appui aux audits des partenaires.",
        'location' => 'Lomé, Togo',
        'contract_type' => 'CDI',
        'salary' => null,
        'closes_at' => date('Y-m-d 18:00:00', strtotime('-60 days')),
        'is_published' => 0,
        'archived' => true,
        'images' => 0,
    ],
];

$offerIds = [];
foreach ($offers as $o) {
    $existing = Database::run('SELECT id FROM job_offers WHERE slug = ?', [$o['slug']])->fetchColumn();
    if ($existing) {
        $offerIds[$o['slug']] = (int) $existing;
        $skipped[] = 'offre ' . $o['slug'];
        continue;
    }
    Database::run(
        'INSERT INTO job_offers (title, slug, description, location, contract_type, salary, closes_at, is_published, archived_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $o['title'], $o['slug'], $o['description'], $o['location'], $o['contract_type'],
            $o['salary'], $o['closes_at'], $o['is_published'],
            $o['archived'] ? date('Y-m-d H:i:s', strtotime('-50 days')) : null,
        ]
    );
    $id = (int) Database::pdo()->lastInsertId();
    $offerIds[$o['slug']] = $id;

    for ($img = 1; $img <= $o['images']; $img++) {
        $path = demoFileName('png');
        makePng(
            $root . '/public/uploads/offres/' . $path,
            "Affiche $img — " . $o['title'],
            800,
            1000
        );
        Database::run(
            'INSERT INTO job_offer_images (offer_id, path, mime, is_main, sort_order) VALUES (?, ?, ?, ?, ?)',
            [$id, $path, 'image/png', $img === 1 ? 1 : 0, $img]
        );
    }
    $created[] = 'offre ' . $o['slug'] . ($o['images'] ? " ({$o['images']} affiche(s))" : '');
}

$offerOpenId = $offerIds['demo-charge-de-programmes-incubation'];
$offerSoonId = $offerIds['demo-community-manager-junior'];
$offerOldId  = $offerIds['demo-assistant-comptable-2025'];

// =====================================================================
// 5. Candidatures (8, tous les statuts, CV PDF factices)
// =====================================================================

echo "-- Candidatures --\n";

ensureDir($root . '/storage/cv');

/** [offre, nom, téléphone, message, cv?, statut] */
$applications = [
    [$offerOpenId, 'Espoir Kougbenya', '+228 90 55 66 77',
     "Titulaire d'une licence en gestion des entreprises (Université de Lomé) et fort de deux ans au sein d'une coopérative agricole comme chargé de suivi, je souhaite mettre mon expérience de terrain au service des entrepreneurs incubés par PROCOPE.", true, 'nouvelle'],
    [$offerOpenId, 'Victoire Amegbleame', '+228 91 66 77 88',
     "Diplômée en économie du développement, j'ai coordonné pendant trois ans des programmes de microfinance rurale dans la région des Plateaux. Votre approche de l'incubation sociale correspond exactement à ma vision de l'accompagnement.", true, 'en_examen'],
    [$offerOpenId, 'Sitou Adjallé', null,
     "Bonjour, je suis très motivé pour ce poste. Je n'ai pas encore d'expérience mais j'apprends vite.", false, 'nouvelle'],
    [$offerSoonId, 'Grace Dossou', '+228 92 77 88 99',
     "Créatrice de contenu depuis 2 ans (12 000 abonnés TikTok autour de l'entrepreneuriat local), je maîtrise Canva, CapCut et la programmation de publications. Je serais ravie de raconter les success stories de vos incubés.", true, 'en_examen'],
    [$offerSoonId, 'Josué Agbodjan', '+228 93 88 99 00',
     "Stagiaire en communication digitale dans une radio de Lomé, je couvre déjà des événements en direct et je gère la page Facebook de la rédaction. Disponible immédiatement.", true, 'nouvelle'],
    [$offerOldId, 'Reine Houngbedji', '+228 96 99 00 11',
     "Assistante comptable avec 3 ans d'expérience en cabinet, je maîtrise la saisie, les rapprochements bancaires et les déclarations fiscales togolaises.", true, 'retenue'],
    [$offerOldId, 'Prosper Napo', '+228 97 00 11 22',
     "Récemment diplômé du BTS comptabilité-gestion, je cherche un premier poste pour mettre en pratique mes acquis.", true, 'refusee'],
    [$offerOldId, 'Larba Kombate', null,
     "Bonjour, je suis disponible pour ce poste, merci de me rappeler.", false, 'refusee'],
];

$nbCreated = 0;
foreach ($applications as $i => [$oid, $name, $phone, $message, $withCv, $statut]) {
    $email = demoEmail($name);
    $exists = Database::run(
        'SELECT COUNT(*) FROM job_applications WHERE offer_id = ? AND email = ?',
        [$oid, $email]
    )->fetchColumn();
    if ($exists) {
        $skipped[] = "candidature $email";
        continue;
    }

    $cvPath = null;
    if ($withCv) {
        $cvPath = demoFileName('pdf');
        makePdf($root . '/storage/cv/' . $cvPath, [
            'CURRICULUM VITAE - DEMONSTRATION',
            $name,
            'Lome, Togo - ' . $phone,
            '',
            'EXPERIENCE PROFESSIONNELLE',
            '- Voir details dans la candidature en ligne',
            '',
            'Document factice genere par bin/seed-demo.php',
        ]);
    }

    Database::run(
        'INSERT INTO job_applications
            (offer_id, full_name, email, phone, message, cv_path, cv_mime, cv_name, statut, ip, user_agent, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            $oid, $name, $email, $phone, $message, $cvPath,
            $cvPath ? 'application/pdf' : null,
            $cvPath ? 'cv-' . strtolower(strtok($name, ' ')) . '.pdf' : null,
            $statut, '127.0.0.1', 'seed-demo',
            date('Y-m-d H:i:s', strtotime('-' . (1 + $i) . ' days')),
        ]
    );
    $nbCreated++;
}
if ($nbCreated) {
    $created[] = "$nbCreated candidature(s) (CV PDF factices)";
}

// =====================================================================
// 6. Compte operator de démo
// =====================================================================

echo "-- Comptes --\n";

$opEmail = 'operator@' . DEMO_EMAIL_DOMAIN;
$opPassword = 'operateur-demo-2026';
if (Database::run('SELECT COUNT(*) FROM users WHERE email = ?', [$opEmail])->fetchColumn()) {
    $skipped[] = "compte $opEmail";
} else {
    Database::run(
        'INSERT INTO users (email, password_hash, name, role, is_active) VALUES (?, ?, ?, ?, 1)',
        [$opEmail, password_hash($opPassword, PASSWORD_ARGON2ID), 'Opérateur démo', 'operator']
    );
    $created[] = "compte operator $opEmail";
}

// =====================================================================
// Résumé
// =====================================================================

echo "\n================= RÉSUMÉ DU SEEDER =================\n";
echo 'Environnement : APP_ENV=' . $appEnv . ($fresh ? ' (mode --fresh)' : '') . "\n\n";
if ($created) {
    echo "Créé (" . count($created) . ") :\n";
    foreach ($created as $c) {
        echo "  + $c\n";
    }
} else {
    echo "Rien à créer.\n";
}
if ($skipped) {
    echo "\nIgnoré, déjà présent (" . count($skipped) . ") :\n";
    foreach ($skipped as $s) {
        echo "  = $s\n";
    }
}

echo "\n----------- Identifiants de connexion -----------\n";
echo "Super admin (base)  : admin@procope.test / motdepassetest123\n";
echo "Operator (démo)     : $opEmail / $opPassword\n";
echo "Super admin racine  : " . Env::get('ROOT_ADMIN_USERNAME', '(non défini)') . " (mot de passe dans le .env)\n";
echo "Admin local         : http://127.0.0.1:8088/admin\n";
echo "==================================================\n";
