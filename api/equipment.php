<?php
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

require_once __DIR__ . '/../includes/db.php';

$stats = [
    'total' => 0,
    'available' => 0,
    'in_use' => 0,
    'reserved' => 0,
    'maintenance' => 0,
];

$rows = $db->query('SELECT Status, COUNT(*) AS Total FROM EQUIPMENT GROUP BY Status')->fetchAll();
foreach ($rows as $row) {
    $stats['total'] += (int)$row['Total'];
    $key = strtolower(str_replace(' ', '_', $row['Status']));
    if (array_key_exists($key, $stats)) {
        $stats[$key] = (int)$row['Total'];
    }
}

$items = $db->query('SELECT EquipmentID, Name, Category, Status, Location, UpdatedAt FROM EQUIPMENT ORDER BY Category, Name')->fetchAll();

echo json_encode(['stats' => $stats, 'items' => $items], JSON_THROW_ON_ERROR);
