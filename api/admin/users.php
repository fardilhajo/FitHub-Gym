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

function userRow(PDO $db, int $id): ?array {
    $stmt = $db->prepare('SELECT UserID, Name, Username, Role, IsActive, CreatedAt FROM USER WHERE UserID = ?');
    $stmt->execute([$id]);
    $u = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$u) return null;
    return [
        'UserID'    => (int)$u['UserID'],
        'Name'      => $u['Name'],
        'Username'  => $u['Username'],
        'Role'      => $u['Role'],
        'IsActive'  => (int)$u['IsActive'],
        'Joined'    => substr($u['CreatedAt'], 0, 10),
        'LastLogin' => 'N/A',
    ];
}

if ($method === 'POST') {
    if (empty($body['Name']) || empty($body['Username']) || empty($body['Password'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Name, Username and Password are required']);
        exit;
    }
    $check = $db->prepare('SELECT COUNT(*) FROM USER WHERE Username = ?');
    $check->execute([$body['Username']]);
    if ($check->fetchColumn() > 0) {
        http_response_code(409);
        echo json_encode(['error' => 'Username already taken']);
        exit;
    }
    $hash = password_hash($body['Password'], PASSWORD_BCRYPT);
    $stmt = $db->prepare('INSERT INTO USER (Name, Username, PasswordHash, Role, IsActive) VALUES (?, ?, ?, ?, 1)');
    $stmt->execute([
        $body['Name'],
        $body['Username'],
        $hash,
        $body['Role'] ?? 'Member',
    ]);
    $newId = (int)$db->lastInsertId();
    // Create empty trainer profile if role is Trainer
    if (($body['Role'] ?? '') === 'Trainer') {
        $db->prepare('INSERT OR IGNORE INTO TRAINER_PROFILE (TrainerID, Bio, Specializations, Certifications) VALUES (?, ?, ?, ?)')
           ->execute([$newId, '', '', '']);
    }
    echo json_encode(['ok' => true, 'user' => userRow($db, $newId)]);
    exit;
}

if ($method === 'PATCH') {
    if (empty($body['UserID'])) {
        http_response_code(400);
        echo json_encode(['error' => 'UserID required']);
        exit;
    }
    $id = (int)$body['UserID'];
    $stmt = $db->prepare('UPDATE USER SET Name=?, Username=?, Role=?, IsActive=? WHERE UserID=?');
    $stmt->execute([
        $body['Name'],
        $body['Username'],
        $body['Role'],
        (int)$body['IsActive'],
        $id,
    ]);
    // Ensure trainer profile row exists when promoting to Trainer
    if (($body['Role'] ?? '') === 'Trainer') {
        $db->prepare('INSERT OR IGNORE INTO TRAINER_PROFILE (TrainerID, Bio, Specializations, Certifications) VALUES (?, ?, ?, ?)')
           ->execute([$id, '', '', '']);
    }
    echo json_encode(['ok' => true, 'user' => userRow($db, $id)]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
