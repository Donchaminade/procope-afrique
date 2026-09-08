<?php
// Applique database/upgrade-013.sql + 3 affiches (événements passés), idempotent
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=procope_test;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$hasColumn = static function (PDO $pdo, string $table, string $column): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$table, $column]);
    return (int) $st->fetchColumn() > 0;
};

$hasIndex = static function (PDO $pdo, string $table, string $index): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?'
    );
    $st->execute([$table, $index]);
    return (int) $st->fetchColumn() > 0;
};

if (!$hasColumn($pdo, 'formation_galleries', 'kind')) {
    $pdo->exec(
        "ALTER TABLE formation_galleries
            ADD COLUMN kind ENUM('photos', 'affiche') NOT NULL DEFAULT 'photos'
                COMMENT 'photos = galerie de vague ; affiche = événement passé'
                AFTER description"
    );
    echo "OK   : formation_galleries.kind\n";
} else {
    echo "SKIP : formation_galleries.kind\n";
}

if (!$hasIndex($pdo, 'formation_galleries', 'idx_fg_kind')) {
    $pdo->exec('ALTER TABLE formation_galleries ADD KEY idx_fg_kind (is_published, kind, year)');
    echo "OK   : idx_fg_kind\n";
} else {
    echo "SKIP : idx_fg_kind\n";
}

$dir = dirname(__DIR__) . '/public/uploads/galeries';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}

$imgRoot = dirname(__DIR__, 2) . '/img';

function makeAffichePng(string $fullPath, string $title, string $period): void
{
    $width = 720;
    $height = 960;
    if (function_exists('imagecreatetruecolor')) {
        $im = imagecreatetruecolor($width, $height);
        $navy = imagecolorallocate($im, 6, 42, 77);
        $navy2 = imagecolorallocate($im, 10, 58, 104);
        $orange = imagecolorallocate($im, 245, 166, 35);
        $white = imagecolorallocate($im, 255, 255, 255);
        imagefilledrectangle($im, 0, 0, $width, $height, $navy);
        imagefilledrectangle($im, 0, 0, $width, 140, $navy2);
        imagefilledrectangle($im, 0, $height - 72, $width, $height, $orange);
        imagestring($im, 5, 36, 48, 'PROCOPE AFRIQUE', $white);
        imagestring($im, 5, 36, 88, 'Evenement passe', $orange);
        $y = 280;
        foreach (explode("\n", wordwrap($title, 28, "\n", true)) as $line) {
            imagestring($im, 5, 36, $y, $line, $white);
            $y += 32;
        }
        imagestring($im, 4, 36, $y + 24, $period, $orange);
        imagestring($im, 3, 36, $height - 44, 'Lome  -  Togo', $navy);
        imagepng($im, $fullPath);
        imagedestroy($im);
        return;
    }
    file_put_contents($fullPath, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
    ));
}

function storeAfficheFile(string $dir, string $imgRoot, string $basename, string $title, string $period): array
{
    $srcJpg = $imgRoot . '/' . $basename . '.jpg';
    $destJpg = $dir . '/' . $basename . '.jpg';
    if (is_file($srcJpg)) {
        if (!is_file($destJpg) || filesize($destJpg) !== filesize($srcJpg)) {
            copy($srcJpg, $destJpg);
        }
        return [$basename . '.jpg', 'image/jpeg'];
    }
    $destPng = $dir . '/' . $basename . '.png';
    if (!is_file($destPng)) {
        makeAffichePng($destPng, $title, $period);
    }
    return [$basename . '.png', 'image/png'];
}

$affiches = [
    [
        'title'       => 'Entrepreneuriat & création d\'entreprise',
        'year'        => 2025,
        'month'       => 3,
        'description' => 'Mars 2025 · Lomé',
        'file'        => 'affiche-1',
        'period'      => 'Mars 2025',
    ],
    [
        'title'       => 'Bootcamp pitch',
        'year'        => 2024,
        'month'       => 11,
        'description' => 'Novembre 2024 · Lomé',
        'file'        => 'affiche-2',
        'period'      => 'Novembre 2024',
    ],
    [
        'title'       => 'Femmes & innovation',
        'year'        => 2024,
        'month'       => 6,
        'description' => 'Juin 2024 · Lomé',
        'file'        => 'affiche-3',
        'period'      => 'Juin 2024',
    ],
];

foreach ($affiches as $spec) {
    $st = $pdo->prepare(
        "SELECT id FROM formation_galleries WHERE title = ? AND year = ? AND kind = 'affiche' LIMIT 1"
    );
    $st->execute([$spec['title'], $spec['year']]);
    $galleryId = $st->fetchColumn();
    if ($galleryId) {
        echo 'SKIP : affiche « ' . $spec['title'] . " »\n";
    } else {
        $pdo->prepare(
            "INSERT INTO formation_galleries
                (formation_id, title, year, month, description, kind, is_published)
             VALUES (NULL, ?, ?, ?, ?, 'affiche', 1)"
        )->execute([$spec['title'], $spec['year'], $spec['month'], $spec['description']]);
        $galleryId = (int) $pdo->lastInsertId();
        echo 'OK   : affiche « ' . $spec['title'] . " » (id $galleryId)\n";
    }

    [$stored, $mime] = storeAfficheFile($dir, $imgRoot, $spec['file'], $spec['title'], $spec['period']);
    $exists = $pdo->prepare(
        'SELECT id FROM formation_gallery_images WHERE gallery_id = ? AND path = ? LIMIT 1'
    );
    $exists->execute([$galleryId, $stored]);
    if ($exists->fetchColumn()) {
        echo "SKIP : visuel $stored\n";
        continue;
    }
    $pdo->prepare(
        'INSERT INTO formation_gallery_images (gallery_id, path, mime, caption, sort_order)
         VALUES (?, ?, ?, ?, 1)'
    )->execute([$galleryId, $stored, $mime, $spec['title']]);
    echo "OK   : visuel $stored\n";
}

echo "\nDESCRIBE formation_galleries :\n";
foreach ($pdo->query('DESCRIBE formation_galleries')->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo '  ' . $col['Field'] . ' — ' . $col['Type'] . "\n";
}

$nPhotos = (int) $pdo->query("SELECT COUNT(*) FROM formation_galleries WHERE kind = 'photos' AND is_published = 1")->fetchColumn();
$nAffiches = (int) $pdo->query("SELECT COUNT(*) FROM formation_galleries WHERE kind = 'affiche' AND is_published = 1")->fetchColumn();
echo "\nPubliés : $nPhotos album(s) photos, $nAffiches affiche(s)\n";
