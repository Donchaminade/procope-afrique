<?php
// Applique database/upgrade-011.sql + 2 témoignages démo publiés (idempotent)
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=procope_test;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$sql = file_get_contents(__DIR__ . '/../database/upgrade-011.sql');
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

$demos = [
    [
        'author_name' => 'Afi Mensah',
        'role_title'  => 'Participante à la formation entrepreneuriat',
        'quote'       => "L'accompagnement de PROCOPE a été un véritable tremplin pour notre projet. "
            . "Le mentorat et l'accès au réseau nous ont ouvert des portes inestimables.",
    ],
    [
        'author_name' => 'Koffi Mensah',
        'role_title'  => 'Porteur de projet incubé',
        'quote'       => "Grâce aux formations de PROCOPE, nous avons pu structurer notre business model "
            . "et convaincre nos premiers partenaires. Une expérience transformatrice.",
    ],
];

foreach ($demos as $demo) {
    $st = $pdo->prepare(
        "SELECT id FROM testimonials WHERE author_name = ? AND source = 'admin' LIMIT 1"
    );
    $st->execute([$demo['author_name']]);
    if ($st->fetchColumn()) {
        echo 'SKIP : démo ' . $demo['author_name'] . "\n";
        continue;
    }
    $pdo->prepare(
        "INSERT INTO testimonials
            (author_name, role_title, quote, source, statut, published_at)
         VALUES (?, ?, ?, 'admin', 'publie', NOW())"
    )->execute([$demo['author_name'], $demo['role_title'], $demo['quote']]);
    echo 'OK   : démo publiée — ' . $demo['author_name'] . "\n";
}

echo "\nDESCRIBE testimonials :\n";
foreach ($pdo->query('DESCRIBE testimonials')->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo '  ' . $col['Field'] . ' — ' . $col['Type'] . "\n";
}

echo "\nToggles :\n";
$rows = $pdo->query("SELECT skey, svalue FROM settings
    WHERE skey IN ('auto_temoignage_recu', 'auto_temoignage_alerte')
    ORDER BY skey")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo '  ' . $row['skey'] . ' = ' . $row['svalue'] . "\n";
}

$n = (int) $pdo->query("SELECT COUNT(*) FROM testimonials WHERE statut = 'publie'")->fetchColumn();
echo "\nPubliés : $n\n";
