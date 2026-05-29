<?php
require_once __DIR__ . '/_helpers.php';

public_api_cors(['GET']);
public_api_json_headers();
public_api_require_get();

require_once __DIR__ . '/../../includes/db.php';

$trainerId = public_api_int_param('id', null, 1);
$limit = public_api_int_param('limit', 50, 1, 100);
$offset = public_api_int_param('offset', 0, 0);
$q = public_api_string_param('q');

$where = ['u.Role = "Trainer"', 'u.IsActive = 1'];
$params = [];

if ($trainerId !== null) {
    $where[] = 'u.UserID = :trainer_id';
    $params[':trainer_id'] = $trainerId;
}
if ($q !== '') {
    $where[] = '(u.Name LIKE :q OR tp.Bio LIKE :q OR tp.Specializations LIKE :q OR tp.Certifications LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}

$whereSql = implode(' AND ', $where);

$sql = '
    SELECT
        u.UserID,
        u.Name,
        u.ProfilePhoto,
        tp.Bio,
        tp.Specializations,
        tp.Certifications,
        COUNT(DISTINCT fc.ClassID) AS ClassCount,
        COUNT(DISTINCT ta.AvailabilityID) AS AvailabilityCount
    FROM USER u
    LEFT JOIN TRAINER_PROFILE tp ON u.UserID = tp.TrainerID
    LEFT JOIN FITNESS_CLASS fc ON fc.TrainerID = u.UserID AND fc.IsActive = 1
    LEFT JOIN TRAINER_AVAILABILITY ta ON ta.TrainerID = u.UserID AND ta.IsActive = 1
    WHERE ' . $whereSql . '
    GROUP BY u.UserID
    ORDER BY u.Name
';

if ($trainerId === null) {
    $sql .= ' LIMIT :limit OFFSET :offset';
}

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
if ($trainerId === null) {
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
}
$stmt->execute();
$rows = $stmt->fetchAll();

$trainers = array_map(static function (array $row): array {
    return [
        'id' => (int)$row['UserID'],
        'name' => $row['Name'],
        'profile_photo' => $row['ProfilePhoto'],
        'bio' => $row['Bio'],
        'specializations' => public_api_csv_list($row['Specializations'] ?? ''),
        'certifications' => public_api_csv_list($row['Certifications'] ?? ''),
        'class_count' => (int)$row['ClassCount'],
        'availability_count' => (int)$row['AvailabilityCount'],
    ];
}, $rows);

if ($trainerId !== null) {
    if ($trainers === []) {
        public_api_error('not_found', 'Trainer not found.', 404);
    }

    $classStmt = $db->prepare('
        SELECT
            fc.ClassID,
            fc.Name,
            fc.Type,
            fc.Day,
            fc.StartTime,
            fc.EndTime,
            fc.Room,
            fc.Capacity,
            COUNT(CASE WHEN ce.Status = "Enrolled" THEN 1 END) AS EnrolledCount
        FROM FITNESS_CLASS fc
        LEFT JOIN CLASS_ENROLLMENT ce ON ce.ClassID = fc.ClassID
        WHERE fc.TrainerID = :trainer_id AND fc.IsActive = 1
        GROUP BY fc.ClassID
        ORDER BY
            CASE fc.Day
                WHEN "Monday" THEN 1
                WHEN "Tuesday" THEN 2
                WHEN "Wednesday" THEN 3
                WHEN "Thursday" THEN 4
                WHEN "Friday" THEN 5
                WHEN "Saturday" THEN 6
                WHEN "Sunday" THEN 7
                ELSE 8
            END,
            fc.StartTime,
            fc.Name
    ');
    $classStmt->execute([':trainer_id' => $trainerId]);
    $classRows = $classStmt->fetchAll();

    $availabilityStmt = $db->prepare('
        SELECT AvailabilityID, Day, StartTime, EndTime, Location
        FROM TRAINER_AVAILABILITY
        WHERE TrainerID = :trainer_id AND IsActive = 1
        ORDER BY
            CASE Day
                WHEN "Monday" THEN 1
                WHEN "Tuesday" THEN 2
                WHEN "Wednesday" THEN 3
                WHEN "Thursday" THEN 4
                WHEN "Friday" THEN 5
                WHEN "Saturday" THEN 6
                WHEN "Sunday" THEN 7
                ELSE 8
            END,
            StartTime
    ');
    $availabilityStmt->execute([':trainer_id' => $trainerId]);
    $availabilityRows = $availabilityStmt->fetchAll();

    $trainer = $trainers[0];
    $trainer['classes'] = array_map(static function (array $row): array {
        $capacity = (int)$row['Capacity'];
        $enrolled = (int)$row['EnrolledCount'];

        return [
            'id' => (int)$row['ClassID'],
            'name' => $row['Name'],
            'type' => $row['Type'],
            'day' => $row['Day'],
            'start_time' => $row['StartTime'],
            'end_time' => $row['EndTime'],
            'room' => $row['Room'],
            'capacity' => $capacity,
            'enrolled_count' => $enrolled,
            'spots_left' => max(0, $capacity - $enrolled),
        ];
    }, $classRows);
    $trainer['availability'] = array_map(static function (array $row): array {
        return [
            'id' => (int)$row['AvailabilityID'],
            'day' => $row['Day'],
            'start_time' => $row['StartTime'],
            'end_time' => $row['EndTime'],
            'location' => $row['Location'],
        ];
    }, $availabilityRows);

    public_api_success($trainer);
}

$countStmt = $db->prepare('
    SELECT COUNT(*)
    FROM USER u
    LEFT JOIN TRAINER_PROFILE tp ON u.UserID = tp.TrainerID
    WHERE ' . $whereSql . '
');
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();

public_api_success($trainers, [
    'count' => count($trainers),
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset,
    'filters' => [
        'q' => $q,
    ],
]);
