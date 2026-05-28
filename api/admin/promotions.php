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
$body   = json_decode($GLOBALS['MOCK_INPUT'] ?? file_get_contents('php://input'), true) ?? [];

function getPromotionWithDetails(PDO $db, int $id): ?array {
    $stmt = $db->prepare('
        SELECT p.*, 
               c.Name AS ClassName, 
               u.Name AS TrainerName
        FROM FEATURED_PROMOTION p
        LEFT JOIN FITNESS_CLASS c ON p.ClassID = c.ClassID
        LEFT JOIN USER u ON p.TrainerID = u.UserID
        WHERE p.PromotionID = ?
    ');
    $stmt->execute([$id]);
    $p = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$p) return null;
    return [
        'PromotionID'      => (int)$p['PromotionID'],
        'Type'             => $p['Type'],
        'ClassID'          => $p['ClassID'] !== null ? (int)$p['ClassID'] : null,
        'TrainerID'        => $p['TrainerID'] !== null ? (int)$p['TrainerID'] : null,
        'PromoTitle'       => $p['PromoTitle'],
        'PromoDescription' => $p['PromoDescription'] ?? '',
        'IsActive'         => (int)$p['IsActive'],
        'ClassName'        => $p['ClassName'] ?? '',
        'TrainerName'      => $p['TrainerName'] ?? '',
    ];
}

if ($method === 'POST') {
    if (empty($body['PromoTitle']) || !trim($body['PromoTitle'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Promo Title is required']);
        exit;
    }
    if (empty($body['Type']) || !in_array($body['Type'], ['Class', 'Trainer'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Type must be Class or Trainer']);
        exit;
    }
    if ($body['Type'] === 'Class' && empty($body['ClassID'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ClassID is required for Class promotion']);
        exit;
    }
    if ($body['Type'] === 'Trainer' && empty($body['TrainerID'])) {
        http_response_code(400);
        echo json_encode(['error' => 'TrainerID is required for Trainer promotion']);
        exit;
    }

    $classId = $body['Type'] === 'Class' ? (int)$body['ClassID'] : null;
    $trainerId = $body['Type'] === 'Trainer' ? (int)$body['TrainerID'] : null;

    $stmt = $db->prepare('
        INSERT INTO FEATURED_PROMOTION (Type, ClassID, TrainerID, PromoTitle, PromoDescription, IsActive) 
        VALUES (?, ?, ?, ?, ?, ?)
    ');
    $stmt->execute([
        $body['Type'],
        $classId,
        $trainerId,
        $body['PromoTitle'],
        $body['PromoDescription'] ?? '',
        isset($body['IsActive']) ? (int)$body['IsActive'] : 1,
    ]);
    
    $newId = (int)$db->lastInsertId();
    echo json_encode(['ok' => true, 'promotion' => getPromotionWithDetails($db, $newId)]);
    exit;
}

if ($method === 'PATCH') {
    if (empty($body['PromotionID'])) {
        http_response_code(400);
        echo json_encode(['error' => 'PromotionID required']);
        exit;
    }
    $id = (int)$body['PromotionID'];

    $classId = $body['Type'] === 'Class' ? (int)$body['ClassID'] : null;
    $trainerId = $body['Type'] === 'Trainer' ? (int)$body['TrainerID'] : null;

    $stmt = $db->prepare('
        UPDATE FEATURED_PROMOTION 
        SET Type=?, ClassID=?, TrainerID=?, PromoTitle=?, PromoDescription=?, IsActive=? 
        WHERE PromotionID=?
    ');
    $stmt->execute([
        $body['Type'],
        $classId,
        $trainerId,
        $body['PromoTitle'],
        $body['PromoDescription'] ?? '',
        (int)$body['IsActive'],
        $id
    ]);

    echo json_encode(['ok' => true, 'promotion' => getPromotionWithDetails($db, $id)]);
    exit;
}

if ($method === 'DELETE') {
    if (empty($body['PromotionID'])) {
        http_response_code(400);
        echo json_encode(['error' => 'PromotionID required']);
        exit;
    }
    $stmt = $db->prepare('DELETE FROM FEATURED_PROMOTION WHERE PromotionID = ?');
    $stmt->execute([(int)$body['PromotionID']]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(405);
echo json_encode(['error' => 'Method not allowed']);
