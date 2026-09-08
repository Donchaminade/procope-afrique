<?php
// Applique database/upgrade-010.sql + retire tout UNIQUE sur email seul (idempotent)
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=procope_test;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$sqlFile = __DIR__ . '/../database/upgrade-010.sql';
$sql = is_file($sqlFile) ? (string) file_get_contents($sqlFile) : '';
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

$indexes = $pdo->query('SHOW INDEX FROM project_applications')->fetchAll(PDO::FETCH_ASSOC);
$byName = [];
foreach ($indexes as $row) {
    $byName[$row['Key_name']][] = $row;
}

$dropped = 0;
foreach ($byName as $name => $cols) {
    if (count($cols) !== 1) {
        continue;
    }
    $col = $cols[0];
    if (strcasecmp((string) $col['Column_name'], 'email') !== 0) {
        continue;
    }
    if ((int) $col['Non_unique'] !== 0) {
        continue;
    }
    $safe = str_replace('`', '', (string) $name);
    $pdo->exec('ALTER TABLE project_applications DROP INDEX `' . $safe . '`');
    echo "OK   : UNIQUE email seul retiré ($safe)\n";
    $dropped++;
}
if ($dropped === 0) {
    echo "SKIP : aucun UNIQUE sur email seul\n";
}

$hasEmailCall = false;
foreach ($byName as $name => $cols) {
    if ($name === 'idx_projapp_email_call') {
        $hasEmailCall = true;
        break;
    }
}
if (!$hasEmailCall) {
    $pdo->exec('ALTER TABLE project_applications ADD KEY idx_projapp_email_call (email, call_id)');
    echo "OK   : idx_projapp_email_call\n";
} else {
    echo "SKIP : idx_projapp_email_call\n";
}

echo "\nINDEX project_applications :\n";
foreach ($pdo->query('SHOW INDEX FROM project_applications')->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $uniq = ((int) $row['Non_unique'] === 0) ? 'UNIQUE' : 'KEY';
    echo '  ' . $row['Key_name'] . ' [' . $uniq . '] ' . $row['Column_name'] . "\n";
}
