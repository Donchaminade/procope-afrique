<?php
// Applique database/upgrade-003.sql à la base locale (idempotent : ignore les colonnes déjà présentes)
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=procope_test;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$sql = file_get_contents(__DIR__ . '/../database/upgrade-003.sql');
$statements = array_filter(array_map('trim', explode(';', $sql)), static function (string $s): bool {
    $s = preg_replace('/^--.*$/m', '', $s);
    return trim((string) $s) !== '';
});

foreach ($statements as $statement) {
    try {
        $pdo->exec($statement);
        echo "OK   : " . substr(preg_replace('/\s+/', ' ', $statement), 0, 80) . "\n";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate column')) {
            echo "SKIP : colonne déjà présente\n";
            continue;
        }
        throw $e;
    }
}

echo "\nDESCRIBE formations :\n";
foreach ($pdo->query('DESCRIBE formations')->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo '  ' . $col['Field'] . ' — ' . $col['Type'] . "\n";
}
