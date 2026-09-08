<?php
// Applique database/upgrade-009.sql + colonnes manquantes (idempotent)
$pdo = new PDO('mysql:host=127.0.0.1;port=3306;dbname=procope_test;charset=utf8mb4', 'root', '', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$sql = file_get_contents(__DIR__ . '/../database/upgrade-009.sql');
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
            || str_contains($msg, 'Duplicate key') || str_contains($msg, 'Duplicate entry')) {
            echo "SKIP : déjà présent\n";
            continue;
        }
        throw $e;
    }
}

$hasColumn = static function (PDO $pdo, string $table, string $column): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
    );
    $st->execute([$table, $column]);
    return (int) $st->fetchColumn() > 0;
};

$hasFk = static function (PDO $pdo, string $name): bool {
    $st = $pdo->prepare(
        'SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
          WHERE TABLE_SCHEMA = DATABASE() AND CONSTRAINT_NAME = ?'
    );
    $st->execute([$name]);
    return (int) $st->fetchColumn() > 0;
};

if (!$hasColumn($pdo, 'project_applications', 'call_id')) {
    $pdo->exec(
        'ALTER TABLE project_applications
            ADD COLUMN call_id INT UNSIGNED NULL COMMENT \'NULL = candidature spontanée\' AFTER project_id,
            ADD KEY idx_projapp_call (call_id, created_at)'
    );
    echo "OK   : project_applications.call_id\n";
} else {
    echo "SKIP : project_applications.call_id\n";
}
if (!$hasFk($pdo, 'fk_projapp_call')) {
    try {
        $pdo->exec(
            'ALTER TABLE project_applications
                ADD CONSTRAINT fk_projapp_call FOREIGN KEY (call_id)
                REFERENCES incubation_calls (id) ON DELETE SET NULL'
        );
        echo "OK   : fk_projapp_call\n";
    } catch (PDOException $e) {
        echo "SKIP : fk_projapp_call (" . $e->getMessage() . ")\n";
    }
} else {
    echo "SKIP : fk_projapp_call\n";
}

if (!$hasColumn($pdo, 'incubated_projects', 'application_id')) {
    $pdo->exec(
        'ALTER TABLE incubated_projects
            ADD COLUMN application_id INT UNSIGNED NULL
                COMMENT \'Dépôt source (NULL = vitrine manuelle)\' AFTER id'
    );
    echo "OK   : incubated_projects.application_id\n";
} else {
    echo "SKIP : incubated_projects.application_id\n";
}
try {
    $pdo->exec('ALTER TABLE incubated_projects ADD UNIQUE KEY uq_incproj_application (application_id)');
    echo "OK   : uq_incproj_application\n";
} catch (PDOException $e) {
    echo "SKIP : uq_incproj_application\n";
}
if (!$hasFk($pdo, 'fk_incproj_application')) {
    try {
        $pdo->exec(
            'ALTER TABLE incubated_projects
                ADD CONSTRAINT fk_incproj_application FOREIGN KEY (application_id)
                REFERENCES project_applications (id) ON DELETE SET NULL'
        );
        echo "OK   : fk_incproj_application\n";
    } catch (PDOException $e) {
        echo "SKIP : fk_incproj_application (" . $e->getMessage() . ")\n";
    }
} else {
    echo "SKIP : fk_incproj_application\n";
}

echo "\nDESCRIBE incubation_calls :\n";
foreach ($pdo->query('DESCRIBE incubation_calls')->fetchAll(PDO::FETCH_ASSOC) as $col) {
    echo '  ' . $col['Field'] . ' — ' . $col['Type'] . "\n";
}

echo "\nToggles :\n";
$rows = $pdo->query("SELECT skey, svalue FROM settings
    WHERE skey IN ('auto_projet_publie', 'auto_depot_projet', 'auto_depot_retenu', 'auto_depot_refuse')
    ORDER BY skey")->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    echo '  ' . $row['skey'] . ' = ' . $row['svalue'] . "\n";
}
