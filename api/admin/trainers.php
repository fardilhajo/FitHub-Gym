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
$body   = json_decode(file_get_contents('php://input'), true) ?? [];

if ($method === 'PATCH') {
    if (empty($body['TrainerID'])) {
        http_response_code(400);
        echo json_encode(['error' => 'TrainerID required']);
        exit;
    }
    $id = (int)$body['TrainerID'];

    if (!empty($body['Name'])) {
        $db->prepare('UPDATE USER SET Name=? WHERE UserID=?')
           ->execute([$body['Name'], $id]);
    }

    $db->prepare('INSERT OR REPLACE INTO TRAINER_PROFILE (TrainerID, Bio, Specializations, Certifications) VALUES (?, ?, ?, ?)')
       ->execute([
           $id,
           $body['Bio']             ?? '',
           $body['Specializations'] ?? '',
           $body['Certifications']  ?? '',
       ]);

    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
