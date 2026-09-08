<?php
// Applique database/upgrade-008.sql à la base locale (idempotent)
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=procope_test;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$sql = file_get_contents(__DIR__ . '/../database/upgrade-008.sql');
$statements = array_filter(array_map('trim', explode(';', $sql)), static function (string $s): bool {
    $s = preg_replace('/^--.*$/m', '', $s);
    return trim((string) $s) !== '';
});

foreach ($statements as $statement) {
    try {
        $pdo->exec($statement);
        echo "OK   : " . substr(preg_replace('/\s+/', ' ', $statement), 0, 80) . "\n";
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (str_contains($msg, 'already exists') || str_contains($msg, 'Duplicate column')
            || str_contains($msg, 'Duplicate key')) {
            echo "SKIP : déjà présent\n";
            continue;
        }
        throw $e;
    }
}

foreach (['incubated_projects', 'project_images', 'project_applications', 'contact_replies'] as $table) {
    echo "\nDESCRIBE $table :\n";
    foreach ($pdo->query("DESCRIBE $table")->fetchAll(PDO::FETCH_ASSOC) as $col) {
        echo '  ' . $col['Field'] . ' — ' . $col['Type'] . "\n";
    }
}

echo "\nToggles projets :\n";
$rows = $pdo->query("SELECT skey, svalue FROM settings
    WHERE skey IN ('auto_projet_publie', 'auto_depot_projet', 'auto_depot_retenu', 'auto_depot_refuse')
    ORDER BY skey")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo '  ' . $row['skey'] . ' = ' . $row['svalue'] . "\n";
}
