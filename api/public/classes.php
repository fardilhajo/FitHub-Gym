<?php
require_once __DIR__ . '/_helpers.php';

public_api_cors(['GET']);
public_api_json_headers();
public_api_require_get();

require_once __DIR__ . '/../../includes/db.php';

$classId = public_api_int_param('id', null, 1);
$trainerId = public_api_int_param('trainer', null, 1);
$limit = public_api_int_param('limit', 50, 1, 100);
$offset = public_api_int_param('offset', 0, 0);
$q = public_api_string_param('q');
$type = public_api_string_param('type');
$day = public_api_string_param('day');

$where = ['fc.IsActive = 1'];
$params = [];

if ($classId !== null) {
    $where[] = 'fc.ClassID = :class_id';
    $params[':class_id'] = $classId;
}
if ($trainerId !== null) {
    $where[] = 'fc.TrainerID = :trainer_id';
    $params[':trainer_id'] = $trainerId;
}
if ($q !== '') {
    $where[] = '(fc.Name LIKE :q OR fc.Type LIKE :q OR fc.Description LIKE :q OR fc.Room LIKE :q OR u.Name LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if ($type !== '') {
    $where[] = 'fc.Type = :type';
    $params[':type'] = $type;
}
if ($day !== '') {
    $where[] = 'fc.Day = :day';
    $params[':day'] = $day;
}

$whereSql = implode(' AND ', $where);

$sql = '
    SELECT
        fc.ClassID,
        fc.Name,
        fc.Type,
        fc.Day,
        fc.StartTime,
        fc.EndTime,
        fc.Room,
        fc.Capacity,
        fc.Description,
        fc.ImageURL,
        fc.TrainerID,
        u.Name AS TrainerName,
        COALESCE(enrolled.EnrolledCount, 0) AS EnrolledCount,
        COALESCE(waiting.WaitlistCount, 0) AS WaitlistCount,
        reviews.AvgRating,
        COALESCE(reviews.ReviewCount, 0) AS ReviewCount
    FROM FITNESS_CLASS fc
    JOIN USER u ON fc.TrainerID = u.UserID
    LEFT JOIN (
        SELECT ClassID, COUNT(*) AS EnrolledCount
        FROM CLASS_ENROLLMENT
        WHERE Status = "Enrolled"
        GROUP BY ClassID
    ) enrolled ON enrolled.ClassID = fc.ClassID
    LEFT JOIN (
        SELECT ClassID, COUNT(*) AS WaitlistCount
        FROM CLASS_WAITLIST
        WHERE Status = "Waiting"
        GROUP BY ClassID
    ) waiting ON waiting.ClassID = fc.ClassID
    LEFT JOIN (
        SELECT ClassID, ROUND(AVG(Rating), 1) AS AvgRating, COUNT(*) AS ReviewCount
        FROM CLASS_REVIEW
        GROUP BY ClassID
    ) reviews ON reviews.ClassID = fc.ClassID
    WHERE ' . $whereSql . '
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
';

if ($classId === null) {
    $sql .= ' LIMIT :limit OFFSET :offset';
}

$stmt = $db->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
if ($classId === null) {
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
}
$stmt->execute();
$rows = $stmt->fetchAll();

$classes = array_map(static function (array $row): array {
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
        'waitlist_count' => (int)$row['WaitlistCount'],
        'description' => $row['Description'],
        'image_url' => $row['ImageURL'],
        'trainer' => [
            'id' => (int)$row['TrainerID'],
            'name' => $row['TrainerName'],
        ],
        'rating' => [
            'average' => public_api_float_or_null($row['AvgRating']),
            'count' => (int)$row['ReviewCount'],
        ],
    ];
}, $rows);

if ($classId !== null) {
    if ($classes === []) {
        public_api_error('not_found', 'Class not found.', 404);
    }
    public_api_success($classes[0]);
}

$countStmt = $db->prepare('
    SELECT COUNT(*)
    FROM FITNESS_CLASS fc
    JOIN USER u ON fc.TrainerID = u.UserID
    WHERE ' . $whereSql . '
');
foreach ($params as $key => $value) {
    $countStmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$countStmt->execute();
$total = (int)$countStmt->fetchColumn();

public_api_success($classes, [
    'count' => count($classes),
    'total' => $total,
    'limit' => $limit,
    'offset' => $offset,
    'filters' => [
        'q' => $q,
        'type' => $type,
        'day' => $day,
        'trainer' => $trainerId,
    ],
]);
