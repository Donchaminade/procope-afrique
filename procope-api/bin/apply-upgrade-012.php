<?php
// Applique database/upgrade-012.sql + 1 album démo janvier 2027 (idempotent)
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=procope_test;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$sql = file_get_contents(__DIR__ . '/../database/upgrade-012.sql');
$statements = array_filter(array_map('trim', explode(';', $sql)), static function (string $s): bool {
    $s = preg_replace('/^--.*$/m', '', $s);
    return trim((string) $s) !== '';
});

foreach ($statements as $statement) {
    try {
        $pdo->exec($statement);
        echo 'OK   : ' . substr(preg_replace('/\s+/', ' ', $statement), 0, 80) . "\n";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, 'already exists') || str_contains($msg, 'Duplicate column')
            || str_contains($msg, 'Duplicate key') || str_contains($msg, 'Duplicate entry')) {
            echo "SKIP : déjà présent\n";
            continue;
        }
        throw $e;
    }
}

$dir = dirname(__DIR__) . '/public/uploads/galeries';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

function makeGaleriePng(string $fullPath, string $label, string $footer = 'Galerie PROCOPE', int $width = 960, int $height = 640): void
{
    if (function_exists('imagecreatetruecolor')) {
        $im = imagecreatetruecolor($width, $height);
        $navy = imagecolorallocate($im, 6, 42, 77);
        $navy2 = imagecolorallocate($im, 10, 58, 104);
        $orange = imagecolorallocate($im, 245, 166, 35);
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, 0, 0, $width, $height, $navy);
        imagefilledrectangle($im, 0, 0, (int) ($width * 0.38), $height, $navy2);
        imagefilledrectangle($im, 0, $height - 48, $width, $height, $orange);
        imagestring($im, 5, 32, 36, 'PROCOPE AFRIQUE', $white);
        imagestring($im, 5, 32, 68, 'Galerie des formations', $orange);
        foreach (explode("\n", wordwrap($label, 42, "\n", true)) as $i => $line) {
            imagestring($im, 5, 32, 160 + $i * 28, $line, $white);
        }
        imagestring($im, 3, 32, $height - 32, $footer, $navy);
        imagepng($im, $fullPath);
        imagedestroy($im);
        return;
    }
    file_put_contents($fullPath, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ));
}

$formationId = $pdo->query(
    "SELECT id FROM formations WHERE slug = 'formation-procope-2027-vague-1' LIMIT 1"
)->fetchColumn();
if (!$formationId) {
    $formationId = $pdo->query(
        "SELECT id FROM formations WHERE titre LIKE '%2027%' ORDER BY id ASC LIMIT 1"
    )->fetchColumn();
}
$formationId = $formationId ? (int) $formationId : null;

$title = 'Formation PROCOPE 2027 — 1ère vague';
$st = $pdo->prepare('SELECT id FROM formation_galleries WHERE title = ? AND year = 2027 LIMIT 1');
$st->execute([$title]);
$galleryId = $st->fetchColumn();

if ($galleryId) {
    echo "SKIP : album démo déjà présent (id $galleryId)\n";
} else {
    $pdo->prepare(
        'INSERT INTO formation_galleries
            (formation_id, title, year, month, description, is_published)
         VALUES (?, ?, 2027, 1, ?, 1)'
    )->execute([
        $formationId,
        $title,
        'Photos prises pendant la première vague de formation PROCOPE, janvier 2027 à Lomé.',
    ]);
    $galleryId = (int) $pdo->lastInsertId();
    echo "OK   : album démo créé (id $galleryId)\n";
}

$demoImages = [
    ['demo-galerie-2027-01.png', 'Atelier en salle — structurer son projet'],
    ['demo-galerie-2027-02.png', 'Travaux de groupe — business model'],
    ['demo-galerie-2027-03.png', 'Pitch des participants devant le jury'],
    ['demo-galerie-2027-04.png', 'Remise des attestations — clôture'],
];

$sort = 1;
foreach ($demoImages as [$file, $caption]) {
    $exists = $pdo->prepare(
        'SELECT id FROM formation_gallery_images WHERE gallery_id = ? AND path = ? LIMIT 1'
    );
    $exists->execute([$galleryId, $file]);
    if ($exists->fetchColumn()) {
        echo "SKIP : photo $file\n";
        $sort++;
        continue;
    }
    makeGaleriePng($dir . '/' . $file, $caption, 'Janvier 2027  -  1ere vague');
    $pdo->prepare(
        'INSERT INTO formation_gallery_images (gallery_id, path, mime, caption, sort_order)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$galleryId, $file, 'image/png', $caption, $sort]);
    echo "OK   : photo $file\n";
    $sort++;
}

$title2026 = 'Bootcamp pitch — novembre 2026';
$st2026 = $pdo->prepare('SELECT id FROM formation_galleries WHERE title = ? AND year = 2026 LIMIT 1');
$st2026->execute([$title2026]);
$gallery2026 = $st2026->fetchColumn();
if ($gallery2026) {
    echo "SKIP : album 2026 déjà présent (id $gallery2026)\n";
} else {
    $pdo->prepare(
        'INSERT INTO formation_galleries
            (formation_id, title, year, month, description, is_published)
         VALUES (NULL, ?, 2026, 11, ?, 1)'
    )->execute([
        $title2026,
        'Photos du bootcamp pitch de novembre 2026 à Lomé.',
    ]);
    $gallery2026 = (int) $pdo->lastInsertId();
    echo "OK   : album 2026 créé (id $gallery2026)\n";
}

$demo2026 = [
    ['demo-galerie-2026-01.png', 'Préparation des pitchs'],
    ['demo-galerie-2026-02.png', 'Présentation devant les mentors'],
];
$sort = 1;
foreach ($demo2026 as [$file, $caption]) {
    $exists = $pdo->prepare(
        'SELECT id FROM formation_gallery_images WHERE gallery_id = ? AND path = ? LIMIT 1'
    );
    $exists->execute([$gallery2026, $file]);
    if ($exists->fetchColumn()) {
        echo "SKIP : photo $file\n";
        $sort++;
        continue;
    }
    makeGaleriePng($dir . '/' . $file, $caption, 'Novembre 2026  -  Bootcamp pitch');
    $pdo->prepare(
        'INSERT INTO formation_gallery_images (gallery_id, path, mime, caption, sort_order)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([$gallery2026, $file, 'image/png', $caption, $sort]);
    echo "OK   : photo $file\n";
    $sort++;
}

echo "\nDESCRIBE formation_galleries :\n";
foreach ($pdo->query('DESCRIBE formation_galleries')->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo '  ' . $col['Field'] . ' — ' . $col['Type'] . "\n";
}
echo "\nDESCRIBE formation_gallery_images :\n";
foreach ($pdo->query('DESCRIBE formation_gallery_images')->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo '  ' . $col['Field'] . ' — ' . $col['Type'] . "\n";
}

$nAlbums = (int) $pdo->query('SELECT COUNT(*) FROM formation_galleries WHERE is_published = 1')->fetchColumn();
$nImgs = (int) $pdo->query('SELECT COUNT(*) FROM formation_gallery_images')->fetchColumn();
echo "\nPubliés : $nAlbums album(s), $nImgs photo(s)\n";
