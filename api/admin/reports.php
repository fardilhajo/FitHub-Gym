<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../includes/db.php';
require_once __DIR__ . '/../../includes/security.php';
require_once __DIR__ . '/../../includes/notifications.php';

require_login();
$user = current_user($db);
if (!$user || $user['Role'] !== 'Admin') {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Forbidden']);
    exit;
}

$method = $_SERVER['REQUEST_METHOD'];

/* ── GET: fetch all reports ────────────────────────────────────────────── */
if ($method === 'GET') {
    $rows = $db->query("
        SELECT r.ReportID, r.MemberID, r.Category, r.Subject, r.Description,
               r.Status, r.Priority, r.EquipmentID, r.ClassID,
               r.CreatedAt, r.UpdatedAt,
               u.Name AS MemberName, u.Username AS MemberUsername,
               e.Name AS EquipmentName,
               fc.Name AS ClassName
        FROM REPORT r
        JOIN USER u ON u.UserID = r.MemberID
        LEFT JOIN EQUIPMENT e ON e.EquipmentID = r.EquipmentID
        LEFT JOIN FITNESS_CLASS fc ON fc.ClassID = r.ClassID
        ORDER BY r.CreatedAt DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    // Fetch all responses keyed by ReportID
    $respRows = $db->query("
        SELECT rr.ResponseID, rr.ReportID, rr.UserID, rr.Message, rr.CreatedAt,
               u.Name AS AuthorName, u.Role AS AuthorRole
        FROM REPORT_RESPONSE rr
        JOIN USER u ON u.UserID = rr.UserID
        ORDER BY rr.CreatedAt ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $responsesByReport = [];
    foreach ($respRows as $resp) {
        $rid = (int)$resp['ReportID'];
        if (!isset($responsesByReport[$rid])) $responsesByReport[$rid] = [];
        $responsesByReport[$rid][] = [
            'ResponseID' => (int)$resp['ResponseID'],
            'UserID'     => (int)$resp['UserID'],
            'AuthorName' => $resp['AuthorName'],
            'AuthorRole' => $resp['AuthorRole'],
            'Message'    => $resp['Message'],
            'CreatedAt'  => $resp['CreatedAt'],
        ];
    }

    $reports = array_map(function ($r) use ($responsesByReport) {
        $rid = (int)$r['ReportID'];
        return [
            'ReportID'       => $rid,
            'MemberID'       => (int)$r['MemberID'],
            'MemberName'     => $r['MemberName'],
            'MemberUsername'  => $r['MemberUsername'],
            'Category'       => $r['Category'],
            'Subject'        => $r['Subject'],
            'Description'    => $r['Description'],
            'Status'         => $r['Status'],
            'Priority'       => $r['Priority'],
            'EquipmentID'    => $r['EquipmentID'] ? (int)$r['EquipmentID'] : null,
            'EquipmentName'  => $r['EquipmentName'],
            'ClassID'        => $r['ClassID'] ? (int)$r['ClassID'] : null,
            'ClassName'      => $r['ClassName'],
            'CreatedAt'      => $r['CreatedAt'],
            'UpdatedAt'      => $r['UpdatedAt'],
            'Responses'      => $responsesByReport[$rid] ?? [],
        ];
    }, $rows);

    echo json_encode(['ok' => true, 'reports' => $reports], JSON_THROW_ON_ERROR);
    exit;
}

/* ── POST: respond or update status ───────────────────────────────────── */
if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
        exit;
    }

    $action = $input['action'] ?? '';

    // ── Respond to a report ──
    if ($action === 'respond') {
        $reportId = (int)($input['ReportID'] ?? 0);
        $message  = trim($input['Message'] ?? '');

        if (!$reportId || !$message) {
            echo json_encode(['ok' => false, 'error' => 'ReportID and Message are required']);
            exit;
        }

        $report = $db->prepare('SELECT ReportID, MemberID, Subject FROM REPORT WHERE ReportID = :id');
        $report->execute([':id' => $reportId]);
        $report = $report->fetch(PDO::FETCH_ASSOC);
        if (!$report) {
            echo json_encode(['ok' => false, 'error' => 'Report not found']);
            exit;
        }

        $stmt = $db->prepare('
            INSERT INTO REPORT_RESPONSE (ReportID, UserID, Message)
            VALUES (:rid, :uid, :msg)
        ');
        $stmt->execute([':rid' => $reportId, ':uid' => $user['UserID'], ':msg' => $message]);

        $db->prepare('UPDATE REPORT SET UpdatedAt = CURRENT_TIMESTAMP WHERE ReportID = :id')
           ->execute([':id' => $reportId]);

        // Notify the member
        notify($db, (int)$report['MemberID'], 'report_update',
            'New response on your report',
            'An admin responded to your report "' . $report['Subject'] . '": ' . substr($message, 0, 120)
        );

        $newResp = [
            'ResponseID' => (int)$db->lastInsertId(),
            'UserID'     => (int)$user['UserID'],
            'AuthorName' => $user['Name'],
            'AuthorRole' => $user['Role'],
            'Message'    => $message,
            'CreatedAt'  => date('Y-m-d H:i:s'),
        ];

        echo json_encode(['ok' => true, 'response' => $newResp]);
        exit;
    }

    // ── Update report status ──
    if ($action === 'update_status') {
        $reportId  = (int)($input['ReportID'] ?? 0);
        $newStatus = $input['Status'] ?? '';
        $allowed   = ['Open', 'In Progress', 'Resolved', 'Closed'];

        if (!$reportId || !in_array($newStatus, $allowed, true)) {
            echo json_encode(['ok' => false, 'error' => 'Valid ReportID and Status required']);
            exit;
        }

        $report = $db->prepare('SELECT ReportID, MemberID, Subject, Status FROM REPORT WHERE ReportID = :id');
        $report->execute([':id' => $reportId]);
        $report = $report->fetch(PDO::FETCH_ASSOC);
        if (!$report) {
            echo json_encode(['ok' => false, 'error' => 'Report not found']);
            exit;
        }

        $db->prepare('UPDATE REPORT SET Status = :status, UpdatedAt = CURRENT_TIMESTAMP WHERE ReportID = :id')
           ->execute([':status' => $newStatus, ':id' => $reportId]);

        // Notify the member
        notify($db, (int)$report['MemberID'], 'report_update',
            'Report status updated',
            'Your report "' . $report['Subject'] . '" has been updated from ' . $report['Status'] . ' to ' . $newStatus . '.'
        );

        echo json_encode(['ok' => true, 'Status' => $newStatus]);
        exit;
    }

    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Unknown action']);
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
