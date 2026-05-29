<?php
require_once __DIR__ . '/_helpers.php';

public_api_cors(['GET']);
public_api_json_headers();
public_api_require_get();

require_once __DIR__ . '/../../includes/db.php';

$equipmentId = public_api_int_param('id', null, 1);
$limit = public_api_int_param('limit', 50, 1, 100);
$offset = public_api_int_param('offset', 0, 0);
$category = public_api_string_param('category');
$status = public_api_enum_param('status', ['Available', 'In use', 'Reserved', 'Maintenance']);
$slotStart = public_api_datetime_param('start');
$slotEnd = public_api_datetime_param('end');

if (($slotStart === null) !== ($slotEnd === null)) {
    public_api_error('invalid_parameter', 'Parameters start and end must be provided together. Tragic, but time slots need two ends.');
}
if ($slotStart !== null && $slotEnd !== null && $slotEnd <= $slotStart) {
    public_api_error('invalid_parameter', 'Parameter end must be later than start. Time still insists on moving forwards.');
}

$checkingSlot = $slotStart !== null && $slotEnd !== null;
$slotStartSql = $checkingSlot ? public_api_sql_datetime($slotStart) : null;
$slotEndSql = $checkingSlot ? public_api_sql_datetime($slotEnd) : null;

$where = ['1 = 1'];
$params = [];

if ($equipmentId !== null) {
    $where[] = 'e.EquipmentID = :equipment_id';
    $params[':equipment_id'] = $equipmentId;
}
if ($category !== '') {
    $where[] = 'e.Category = :category';
    $params[':category'] = $category;
}
if ($status !== '') {
    $where[] = 'e.Status = :status';
    $params[':status'] = $status;
}

$whereSql = implode(' AND ', $where);
$conflictSelect = $checkingSlot ? ',
        (
            SELECT COUNT(*)
            FROM EQUIPMENT_RESERVATION er
            WHERE er.EquipmentID = e.EquipmentID
              AND er.Status = "Reserved"
              AND er.StartAt < :slot_end
              AND er.EndAt > :slot_start
        ) AS ConflictCount' : ', 0 AS ConflictCount';

$sql = '
    SELECT
        e.EquipmentID,
        e.Name,
        e.Category,
        e.Status,
        e.Location,
        e.UpdatedAt,
        (
            SELECT er.StartAt
            FROM EQUIPMENT_RESERVATION er
            WHERE er.EquipmentID = e.EquipmentID
              AND er.Status = "Reserved"
              AND er.EndAt > datetime("now")
            ORDER BY er.StartAt ASC
            LIMIT 1
        ) AS NextReservationStart,
        (
            SELECT er.EndAt
            FROM EQUIPMENT_RESERVATION er
            WHERE er.EquipmentID = e.EquipmentID
              AND er.Status = "Reserved"
              AND er.EndAt > datetime("now")
            ORDER BY er.StartAt ASC
            LIMIT 1
        ) AS NextReservationEnd
        ' . $conflictSelect . '
    FROM EQUIPMENT e
    WHERE ' . $whereSql . '
    ORDER BY e.Category, e.Name
';

if ($equipmentId === null) {
    $sql .= ' LIMIT :limit OFFSET :offset';
}

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
if ($checkingSlot) {
    $stmt->bindValue(':slot_start', $slotStartSql, PDO::PARAM_STR);
    $stmt->bindValue(':slot_end', $slotEndSql, PDO::PARAM_STR);
}
if ($equipmentId === null) {
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
}
$stmt->execute();
$rows = $stmt->fetchAll();

$items = array_map(static function (array $row) use ($checkingSlot, $slotStartSql, $slotEndSql): array {
    $conflicts = (int)$row['ConflictCount'];
    $item = [
        'id' => (int)$row['EquipmentID'],
        'name' => $row['Name'],
        'category' => $row['Category'],
        'status' => $row['Status'],
        'location' => $row['Location'],
        'updated_at' => $row['UpdatedAt'],
        'next_reservation' => $row['NextReservationStart'] ? [
            'start' => $row['NextReservationStart'],
            'end' => $row['NextReservationEnd'],
        ] : null,
    ];

    if ($checkingSlot) {
        $item['requested_slot'] = [
            'start' => $slotStartSql,
            'end' => $slotEndSql,
            'conflict_count' => $conflicts,
            'is_available' => $row['Status'] !== 'Maintenance' && $conflicts === 0,
        ];
    }

    return $item;
}, $rows);

if ($equipmentId !== null) {
    if ($items === []) {
        public_api_error('not_found', 'Equipment item not found.', 404);
    }
    public_api_success($items[0]);
}

$countStmt = $db->prepare('SELECT COUNT(*) FROM EQUIPMENT e WHERE ' . $whereSql);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();

$stats = [
    'total' => 0,
    'available' => 0,
    'in_use' => 0,
    'reserved' => 0,
    'maintenance' => 0,
];
$statRows = $db->query('SELECT Status, COUNT(*) AS Total FROM EQUIPMENT GROUP BY Status')->fetchAll();
foreach ($statRows as $row) {
    $stats['total'] += (int)$row['Total'];
    $key = strtolower(str_replace(' ', '_', $row['Status']));
    if (array_key_exists($key, $stats)) {
        $stats[$key] = (int)$row['Total'];
    }
}

public_api_success($items, [
    'count' => count($items),
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset,
    'stats' => $stats,
    'filters' => [
        'category' => $category,
        'status' => $status,
        'requested_slot' => $checkingSlot ? [
            'start' => $slotStartSql,
            'end' => $slotEndSql,
        ] : null,
    ],
]);
