<?php
/**
 * Ajax search endpoint.
 * GET /api/search.php?q=<query>
 * Returns JSON array of matching classes from FITNESS_CLASS.
 */

header('Content-Type: application/json; charset=utf-8');

// Only accept GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (strlen($query) < 2) {
    echo json_encode([]);
    exit;
}

$searchTerm = '%' . $query . '%';

$stmt = $db->prepare('
    SELECT
        fc.ClassID,
        fc.Name,
        fc.Type,
        fc.Day,
        fc.Time,
        u.Name AS TrainerName
    FROM FITNESS_CLASS fc
    LEFT JOIN USER u ON fc.TrainerID = u.UserID
    WHERE fc.Name LIKE :q1
       OR fc.Type LIKE :q2
       OR u.Name  LIKE :q3
    LIMIT 8
');

$stmt->execute([
    ':q1' => $searchTerm,
    ':q2' => $searchTerm,
    ':q3' => $searchTerm,
]);

$results = $stmt->fetchAll();

echo json_encode($results);
