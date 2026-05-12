<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$query = trim($_GET['q'] ?? '');
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
        fc.StartTime,
        fc.EndTime,
        u.Name AS TrainerName
    FROM FITNESS_CLASS fc
    JOIN USER u ON fc.TrainerID = u.UserID
    WHERE fc.IsActive = 1
      AND (fc.Name LIKE :q OR fc.Type LIKE :q OR fc.Day LIKE :q OR u.Name LIKE :q)
    ORDER BY fc.Day, fc.StartTime
    LIMIT 8
');
$stmt->execute([':q' => $searchTerm]);

$results = array_map(static function (array $row): array {
    $row['Url'] = 'book-class.php?q=' . rawurlencode($row['Name']);
    return $row;
}, $stmt->fetchAll());

echo json_encode($results, JSON_THROW_ON_ERROR);
