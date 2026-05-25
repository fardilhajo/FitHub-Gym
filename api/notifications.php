<?php
header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/security.php';

$user = current_user($db);
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthenticated']);
    exit;
}

$userId = (int)$user['UserID'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? (json_decode(file_get_contents('php://input'), true)['action'] ?? '');

    if ($action === 'read_all') {
        $db->prepare('UPDATE NOTIFICATION SET IsRead = 1 WHERE UserID = :uid')
           ->execute([':uid' => $userId]);
        echo json_encode(['ok' => true]);
        exit;
    }

    if ($action === 'read' && isset($_POST['id'])) {
        $db->prepare('UPDATE NOTIFICATION SET IsRead = 1 WHERE NotificationID = :nid AND UserID = :uid')
           ->execute([':nid' => (int)$_POST['id'], ':uid' => $userId]);
        echo json_encode(['ok' => true]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['error' => 'Unknown action']);
    exit;
}

// GET — return notifications
$limit = min((int)($_GET['limit'] ?? 20), 50);

$stmt = $db->prepare('
    SELECT NotificationID, Type, Title, Message, IsRead, CreatedAt
    FROM NOTIFICATION
    WHERE UserID = :uid
    ORDER BY CreatedAt DESC
    LIMIT :lim
');
$stmt->bindValue(':uid', $userId, PDO::PARAM_INT);
$stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$unreadStmt = $db->prepare('SELECT COUNT(*) FROM NOTIFICATION WHERE UserID = :uid AND IsRead = 0');
$unreadStmt->execute([':uid' => $userId]);
$unread = (int)$unreadStmt->fetchColumn();

echo json_encode(['unread' => $unread, 'items' => $rows]);
