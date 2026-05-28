<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/security.php';

require_login();
$user = current_user($db);
if (!$user || $user['Role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'GET') {
    verify_csrf();
}
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

function eqRow(PDO $db, int $id): ?array {
    $stmt = $db->prepare('SELECT EquipmentID, Name, Category, Status, Location, UpdatedAt FROM EQUIPMENT WHERE EquipmentID = ?');
    $stmt->execute([$id]);
    $e = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$e) return null;
    return [
        'EquipmentID' => (int)$e['EquipmentID'],
        'Name'        => $e['Name'],
        'Category'    => $e['Category'],
        'Status'      => $e['Status'],
        'Location'    => $e['Location'],
        'UpdatedAt'   => $e['UpdatedAt'],
    ];
}

if ($method === 'POST') {
    if (empty($body['Name'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Name is required']);
        exit;
    }
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare('INSERT INTO EQUIPMENT (Name, Category, Status, Location, UpdatedAt) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([
        $body['Name'],
        $body['Category'] ?? 'Other',
        $body['Status']   ?? 'Available',
        $body['Location'] ?? '',
        $now,
    ]);
    $newId = (int)$db->lastInsertId();
    echo json_encode(['ok' => true, 'equipment' => eqRow($db, $newId)]);
    exit;
}

if ($method === 'PATCH') {
    if (empty($body['EquipmentID'])) {
        http_response_code(400);
        echo json_encode(['error' => 'EquipmentID required']);
        exit;
    }
    $id  = (int)$body['EquipmentID'];
    $now = date('Y-m-d H:i:s');
    $stmt = $db->prepare('UPDATE EQUIPMENT SET Name=?, Category=?, Status=?, Location=?, UpdatedAt=? WHERE EquipmentID=?');
    $stmt->execute([
        $body['Name'],
        $body['Category'],
        $body['Status'],
        $body['Location'],
        $now,
        $id,
    ]);
    echo json_encode(['ok' => true, 'equipment' => eqRow($db, $id)]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
