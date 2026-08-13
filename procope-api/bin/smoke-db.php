<?php
// Smoke test local : crée la base de test et importe migrations.sql
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3,
    ]);
    echo "MYSQL OK\n";
    $pdo->exec('CREATE DATABASE IF NOT EXISTS procope_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $pdo->exec('USE procope_test');
    $sql = file_get_contents(__DIR__ . '/../database/migrations.sql');
    $pdo->exec($sql);
    echo "MIGRATIONS OK\n";
    $count = $pdo->query('SELECT COUNT(*) FROM formations')->fetchColumn();
    $slots = $pdo->query('SELECT COUNT(*) FROM formation_slots')->fetchColumn();
    echo "formations=$count slots=$slots\n";
} catch (Throwable $e) {
    echo 'FAIL: ' . $e->getMessage() . "\n";
    exit(1);
}
