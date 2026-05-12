<?php
function getDB(): PDO {
    $db = new PDO('sqlite:' . __DIR__ . '/LTW-DataBAse-1.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    try {
        $db->exec('ALTER TABLE USER ADD COLUMN Email TEXT UNIQUE');
    } catch (PDOException $e) {
        // Column already exists
    }
    return $db;
}
