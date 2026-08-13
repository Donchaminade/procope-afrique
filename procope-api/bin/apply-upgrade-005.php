<?php
// Applique database/upgrade-005.sql à la base locale (idempotent : IF NOT EXISTS + INSERT IGNORE)
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=procope_test;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$sql = file_get_contents(__DIR__ . '/../database/upgrade-005.sql');
$statements = array_filter(array_map('trim', explode(';', $sql)), static function (string $s): bool {
    $s = preg_replace('/^--.*$/m', '', $s);
    return trim((string) $s) !== '';
});

foreach ($statements as $statement) {
    try {
        $pdo->exec($statement);
        echo "OK   : " . substr(preg_replace('/\s+/', ' ', $statement), 0, 80) . "\n";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'already exists')) {
            echo "SKIP : table déjà présente\n";
            continue;
        }
        throw $e;
    }
}

foreach (['job_offers', 'job_applications'] as $table) {
    echo "\nDESCRIBE $table :\n";
    foreach ($pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo '  ' . $col['Field'] . ' — ' . $col['Type'] . "\n";
    }
}

echo "\nToggles emploi :\n";
$rows = $pdo->query("SELECT skey, svalue FROM settings
    WHERE skey IN ('auto_offre_publiee', 'auto_offre_rappel', 'auto_offre_prolongee', 'auto_candidature_emploi')
    ORDER BY skey")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo '  ' . $row['skey'] . ' = ' . $row['svalue'] . "\n";
}
