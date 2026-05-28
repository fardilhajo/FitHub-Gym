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

// Users
$userRows = $db->query(
    'SELECT UserID, Name, Username, Role, IsActive, CreatedAt FROM USER ORDER BY UserID'
)->fetchAll(PDO::FETCH_ASSOC);

$users = array_map(function ($u) {
    return [
        'UserID'    => (int)$u['UserID'],
        'Name'      => $u['Name'],
        'Username'  => $u['Username'],
        'Role'      => $u['Role'],
        'IsActive'  => (int)$u['IsActive'],
        'Joined'    => substr($u['CreatedAt'], 0, 10),
        'LastLogin' => 'N/A',
    ];
}, $userRows);

// Trainer profiles keyed by TrainerID
$tpRows = $db->query(
    'SELECT TrainerID, Bio, Specializations, Certifications FROM TRAINER_PROFILE'
)->fetchAll(PDO::FETCH_ASSOC);

$trainerProfiles = [];
foreach ($tpRows as $tp) {
    $trainerProfiles[(int)$tp['TrainerID']] = [
        'Bio'             => $tp['Bio'] ?? '',
        'Specializations' => $tp['Specializations'] ?? '',
        'Certifications'  => $tp['Certifications'] ?? '',
    ];
}

// Classes with live enrollment counts
$classRows = $db->query("
    SELECT fc.ClassID, fc.Name, fc.Type, fc.Day, fc.StartTime, fc.EndTime,
           fc.Room, fc.Capacity, fc.Description, fc.TrainerID, fc.IsActive,
           COUNT(CASE WHEN ce.Status = 'Enrolled' THEN 1 END) AS Enrolled
    FROM FITNESS_CLASS fc
    LEFT JOIN CLASS_ENROLLMENT ce ON ce.ClassID = fc.ClassID
    GROUP BY fc.ClassID
    ORDER BY fc.ClassID
")->fetchAll(PDO::FETCH_ASSOC);

$classes = array_map(function ($c) {
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
}, $classRows);

// Equipment
$eqRows = $db->query(
    'SELECT EquipmentID, Name, Category, Status, Location, UpdatedAt FROM EQUIPMENT ORDER BY Category, Name'
)->fetchAll(PDO::FETCH_ASSOC);

$equipment = array_map(function ($e) {
    return [
        'EquipmentID' => (int)$e['EquipmentID'],
        'Name'        => $e['Name'],
        'Category'    => $e['Category'],
        'Status'      => $e['Status'],
        'Location'    => $e['Location'],
        'UpdatedAt'   => $e['UpdatedAt'],
    ];
}, $eqRows);

// Reports summary counts
$reportsCount = [
    'total'      => (int)$db->query('SELECT COUNT(*) FROM REPORT')->fetchColumn(),
    'open'       => (int)$db->query("SELECT COUNT(*) FROM REPORT WHERE Status = 'Open'")->fetchColumn(),
    'inProgress' => (int)$db->query("SELECT COUNT(*) FROM REPORT WHERE Status = 'In Progress'")->fetchColumn(),
    'resolved'   => (int)$db->query("SELECT COUNT(*) FROM REPORT WHERE Status = 'Resolved'")->fetchColumn(),
    'closed'     => (int)$db->query("SELECT COUNT(*) FROM REPORT WHERE Status = 'Closed'")->fetchColumn(),
];

echo json_encode([
    'users'           => $users,
    'trainerProfiles' => $trainerProfiles,
    'classes'         => $classes,
    'equipment'       => $equipment,
    'reports'         => $reportsCount,
], JSON_THROW_ON_ERROR);
