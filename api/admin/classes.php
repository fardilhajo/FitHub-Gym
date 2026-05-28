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

function classWithEnrolled(PDO $db, int $id): ?array {
    $stmt = $db->prepare("
        SELECT fc.ClassID, fc.Name, fc.Type, fc.Day, fc.StartTime, fc.EndTime,
               fc.Room, fc.Capacity, fc.Description, fc.TrainerID, fc.IsActive,
               COUNT(CASE WHEN ce.Status = 'Enrolled' THEN 1 END) AS Enrolled
        FROM FITNESS_CLASS fc
        LEFT JOIN CLASS_ENROLLMENT ce ON ce.ClassID = fc.ClassID
        WHERE fc.ClassID = ?
        GROUP BY fc.ClassID
    ");
    $stmt->execute([$id]);
    $c = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$c) return null;
    return [
        'ClassID'     => (int)$c['ClassID'],
        'Name'        => $c['Name'],
        'Type'        => $c['Type'],
        'Day'         => $c['Day'],
        'StartTime'   => $c['StartTime'],
        'EndTime'     => $c['EndTime'],
        'Room'        => $c['Room'],
        'Capacity'    => (int)$c['Capacity'],
        'Enrolled'    => (int)$c['Enrolled'],
        'Description' => $c['Description'],
        'TrainerID'   => (int)$c['TrainerID'],
        'IsActive'    => (int)$c['IsActive'],
    ];
}

if ($method === 'POST') {
    foreach (['Name', 'Type', 'Day', 'StartTime', 'EndTime', 'Room', 'Capacity', 'TrainerID'] as $k) {
        if (empty($body[$k]) && $body[$k] !== 0) {
            http_response_code(400);
            echo json_encode(['error' => "$k is required"]);
            exit;
        }
    }
    $stmt = $db->prepare('INSERT INTO FITNESS_CLASS (Name, Type, Day, StartTime, EndTime, Room, Capacity, Description, TrainerID, IsActive) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->execute([
        $body['Name'],
        $body['Type'],
        $body['Day'],
        $body['StartTime'],
        $body['EndTime'],
        $body['Room'],
        (int)$body['Capacity'],
        $body['Description'] ?? '',
        (int)$body['TrainerID'],
        isset($body['IsActive']) ? (int)$body['IsActive'] : 1,
    ]);
    $newId = (int)$db->lastInsertId();
    echo json_encode(['ok' => true, 'class' => classWithEnrolled($db, $newId)]);
    exit;
}

if ($method === 'PATCH') {
    if (empty($body['ClassID'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ClassID required']);
        exit;
    }
    $id = (int)$body['ClassID'];
    $stmt = $db->prepare('UPDATE FITNESS_CLASS SET Name=?, Type=?, Day=?, StartTime=?, EndTime=?, Room=?, Capacity=?, Description=?, TrainerID=?, IsActive=? WHERE ClassID=?');
    $stmt->execute([
        $body['Name'],
        $body['Type'],
        $body['Day'],
        $body['StartTime'],
        $body['EndTime'],
        $body['Room'],
        (int)$body['Capacity'],
        $body['Description'] ?? '',
        (int)$body['TrainerID'],
        (int)$body['IsActive'],
        $id,
    ]);
    echo json_encode(['ok' => true, 'class' => classWithEnrolled($db, $id)]);
    exit;
}

if ($method === 'DELETE') {
    if (empty($body['ClassID'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ClassID required']);
        exit;
    }
    $stmt = $db->prepare('DELETE FROM FITNESS_CLASS WHERE ClassID = ?');
    $stmt->execute([(int)$body['ClassID']]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
