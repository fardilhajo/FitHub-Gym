<?php
/**
 * PDO connection to SQLite database.
 * Include this file to get access to the $db variable.
 */

$dbPath = __DIR__ . '/../Database/LTW-DataBAse-1.db';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $db->exec('PRAGMA foreign_keys = ON');
} catch (PDOException $e) {
    http_response_code(500);
    echo 'Database connection failed: ' . $e->getMessage();
    exit;
}
