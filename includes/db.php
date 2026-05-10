<?php
/**
 * PDO connection to the project SQLite database.
 * The database is recreated from database/database.sql when database.db is missing.
 */

$databaseDir = __DIR__ . '/../database';
$dbPath = $databaseDir . '/database.db';
$sqlPath = $databaseDir . '/database.sql';

if (!is_dir($databaseDir)) {
    mkdir($databaseDir, 0755, true);
}

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA foreign_keys = ON');

    $hasUserTable = (bool)$db->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = 'USER'")->fetchColumn();
    if (!$hasUserTable && is_file($sqlPath)) {
        $db->exec(file_get_contents($sqlPath));
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Database connection failed: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    exit;
}
